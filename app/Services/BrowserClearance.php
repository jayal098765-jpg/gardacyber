<?php

namespace App\Services;

/**
 * Drives a real headless Chromium-family browser through a CDN/WAF
 * interstitial so the scanner can obtain the session cookies the edge issues
 * after it has vetted a genuine browser.
 *
 * The browser is only ever pointed at the hostname being assessed and is used
 * exactly as a visitor would use it: the challenge's own JavaScript runs and,
 * if it passes, the resulting cookies are handed back to the caller so the
 * curl-based probes present the same identity. Nothing is clicked, no CAPTCHA
 * is solved, and when the challenge stays interactive the solver gives up and
 * returns null so the caller keeps the blocked result.
 */
class BrowserClearance
{
    /**
     * Fallback identity when the browser cannot report its own user agent.
     */
    public const DEFAULT_USER_AGENT = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36';

    /**
     * Markers that only appear on an *active* interstitial.
     *
     * `challenge-platform` is deliberately absent: Cloudflare leaves its
     * detection script in the cleared document, so that token is present after
     * a successful handoff as well and would make every solved page look
     * blocked forever.
     *
     * @var array<int, string>
     */
    private const INTERSTITIAL_MARKERS = [
        'Just a moment...',
        'Enable JavaScript and cookies to continue',
        'Checking your browser before accessing',
        'Verifying you are human',
        'Attention Required! | Cloudflare',
        'Sorry, you have been blocked',
        'challenge-stage',
        'cf-please-wait',
        'cf_chl_opt',
        'Incapsula incident ID',
        'Sucuri WebSite Firewall - Access Denied',
    ];

    /**
     * Load $hostname in a browser and return the cookies earned once the
     * interstitial clears.
     *
     * A managed Turnstile widget decides a headless build is automated and
     * never hands out clearance, while the same build with a real window
     * passes it unattended. So the headless attempt runs first and, when a
     * display is available, a visible attempt follows with the remaining
     * budget. Both simply let the challenge's own JavaScript run.
     *
     * @return array{cookies: array<int, array{name: string, value: string, domain: string}>, user_agent: string, status: int, body: string, title: string}|null
     */
    public function solve(string $hostname, string $ip): ?array
    {
        if (! (bool) config('scanner.browser_bypass', true)) {
            return null;
        }

        $modes = $this->attemptModes();

        foreach ($modes as $headless) {
            $timeout = max(5.0, (float) config('scanner.browser_timeout', 25));

            if ($headless && count($modes) > 1) {
                // Leave most of the budget for the visible attempt, which is
                // the one that can actually clear an interactive challenge.
                $timeout = min($timeout, max(5.0, (float) config('scanner.browser_probe_timeout', 12)));
            }

            $result = $this->attempt($hostname, $ip, $headless, $timeout);

            if ($result !== null) {
                return $result;
            }
        }

        return null;
    }

    /**
     * The browser identities to try, in order. `true` means headless.
     *
     * @return array<int, bool>
     */
    public function attemptModes(): array
    {
        $modes = (bool) config('scanner.browser_headless', true) ? [true] : [false];

        if ((bool) config('scanner.browser_headful_fallback', true) &&
            ! in_array(false, $modes, true) &&
            $this->canShowWindow()) {
            $modes[] = false;
        }

        return $modes;
    }

    /**
     * A visible browser needs a desktop session; without one the launch fails
     * instantly, so it is not worth the attempt.
     */
    public function canShowWindow(): bool
    {
        if (PHP_OS_FAMILY === 'Linux') {
            return trim((string) getenv('DISPLAY')) !== '';
        }

        return true;
    }

    /**
     * @return array{cookies: array<int, array{name: string, value: string, domain: string}>, user_agent: string, status: int, body: string, title: string}|null
     */
    private function attempt(string $hostname, string $ip, bool $headless, float $timeout): ?array
    {
        $binary = $this->binary();

        if ($binary === null) {
            return null;
        }

        $profile = $this->profileDirectory();

        if ($profile === null) {
            return null;
        }

        $process = null;
        $socket = null;

        try {
            $process = $this->launch($binary, $profile, $hostname, $ip, $headless);

            if ($process === null) {
                return null;
            }

            $deadline = microtime(true) + $timeout;

            $port = $this->awaitDevToolsPort($profile, $deadline);

            if ($port === null) {
                return null;
            }

            $userAgent = $this->browserUserAgent($port);

            $target = $this->openTarget($port);

            if ($target === null) {
                return null;
            }

            $socket = $this->connect($target, $deadline);

            if ($socket === null) {
                return null;
            }

            $this->messageId = 0;

            $page = $this->navigate($socket, $hostname, $userAgent, $deadline);

            if ($page === null) {
                return null;
            }

            $cookies = $this->relevantCookies($this->fetchCookies($socket, $deadline), $hostname);

            return [
                'cookies' => $cookies,
                'user_agent' => $userAgent,
                'status' => $page['status'],
                'body' => $page['body'],
                'title' => $page['title'],
            ];
        } catch (\Throwable) {
            return null;
        } finally {
            if (is_resource($socket)) {
                @fclose($socket);
            }

            $this->terminate($process, $profile);
        }
    }

    /**
     * True when the rendered markup is still an edge interstitial rather than
     * the application. Deliberately content-only: no headers are available
     * inside the browser.
     */
    public function isChallengePage(string $html): bool
    {
        if (trim($html) === '') {
            return false;
        }

        foreach (self::INTERSTITIAL_MARKERS as $marker) {
            if (stripos($html, $marker) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * Keep only cookies that the edge will accept for the scanned host.
     *
     * @param  array<int, array<string, mixed>>  $cookies
     * @return array<int, array{name: string, value: string, domain: string}>
     */
    public function relevantCookies(array $cookies, string $hostname): array
    {
        $hostname = strtolower($hostname);
        $relevant = [];

        foreach ($cookies as $cookie) {
            $domain = strtolower(ltrim((string) ($cookie['domain'] ?? ''), '.'));
            $name = (string) ($cookie['name'] ?? '');

            if ($domain === '' || $name === '') {
                continue;
            }

            if ($domain !== $hostname && ! str_ends_with($hostname, '.'.$domain)) {
                continue;
            }

            $relevant[] = [
                'name' => $name,
                'value' => (string) ($cookie['value'] ?? ''),
                'domain' => $domain,
            ];
        }

        return $relevant;
    }

    /**
     * @param  array<int, array{name: string, value: string}>  $cookies
     */
    public function cookieHeader(array $cookies): string
    {
        $pairs = [];

        foreach ($cookies as $cookie) {
            $name = (string) ($cookie['name'] ?? '');

            if ($name === '') {
                continue;
            }

            $pairs[] = $name.'='.(string) ($cookie['value'] ?? '');
        }

        return implode('; ', $pairs);
    }

    /**
     * Locate a Chromium-family binary: configured path first, then the usual
     * install locations for Windows, macOS, and Linux.
     */
    private function binary(): ?string
    {
        $configured = trim((string) config('scanner.browser_path', ''));

        if ($configured !== '' && is_file($configured)) {
            return $configured;
        }

        $localAppData = getenv('LOCALAPPDATA') ?: '';

        $candidates = array_filter([
            'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe',
            'C:\\Program Files (x86)\\Google\\Chrome\\Application\\chrome.exe',
            $localAppData !== '' ? $localAppData.'\\Google\\Chrome\\Application\\chrome.exe' : '',
            'C:\\Program Files\\Microsoft\\Edge\\Application\\msedge.exe',
            'C:\\Program Files (x86)\\Microsoft\\Edge\\Application\\msedge.exe',
            '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome',
            '/Applications/Microsoft Edge.app/Contents/MacOS/Microsoft Edge',
            '/Applications/Chromium.app/Contents/MacOS/Chromium',
            '/usr/bin/google-chrome',
            '/usr/bin/google-chrome-stable',
            '/usr/bin/chromium',
            '/usr/bin/chromium-browser',
            '/usr/bin/microsoft-edge',
        ]);

        foreach ($candidates as $candidate) {
            if (is_file($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    private function profileDirectory(): ?string
    {
        $directory = rtrim(sys_get_temp_dir(), '\\/').DIRECTORY_SEPARATOR.'garda-browser-'.bin2hex(random_bytes(6));

        if (! @mkdir($directory, 0700, true) && ! is_dir($directory)) {
            return null;
        }

        return $directory;
    }

    /**
     * @return resource|null
     */
    private function launch(string $binary, string $profile, string $hostname, string $ip, bool $headless)
    {
        $arguments = [
            $binary,
            '--remote-debugging-port=0',
            '--user-data-dir='.$profile,
            '--window-size=1280,900',
            '--window-position=40,40',
            '--no-first-run',
            '--no-default-browser-check',
            '--disable-background-networking',
            '--disable-extensions',
            '--disable-sync',
            '--disable-dev-shm-usage',
            '--disable-blink-features=AutomationControlled',
            '--mute-audio',
            '--no-proxy-server',
            '--no-sandbox',
            'about:blank',
        ];

        if ($headless) {
            $arguments[] = '--headless=new';
            $arguments[] = '--disable-gpu';
        }

        // Pin the hostname to the address the scanner already resolved so the
        // browser and the curl probes share the exact same path.
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            $arguments[] = '--host-resolver-rules=MAP '.$hostname.' '.$ip;
        }

        $log = $profile.DIRECTORY_SEPARATOR.'browser.log';
        $nullDevice = PHP_OS_FAMILY === 'Windows' ? 'NUL' : '/dev/null';

        $descriptors = [
            0 => ['file', $nullDevice, 'r'],
            1 => ['file', $log, 'a'],
            2 => ['file', $log, 'a'],
        ];

        $pipes = [];
        $process = @proc_open($arguments, $descriptors, $pipes, null, null, ['bypass_shell' => true]);

        return is_resource($process) ? $process : null;
    }

    private function awaitDevToolsPort(string $profile, float $deadline): ?int
    {
        $file = $profile.DIRECTORY_SEPARATOR.'DevToolsActivePort';

        while (microtime(true) < $deadline) {
            if (is_file($file)) {
                $lines = @file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
                $port = (int) ($lines[0] ?? 0);

                if ($port > 0) {
                    return $port;
                }
            }

            usleep(150000);
        }

        return null;
    }

    private function browserUserAgent(int $port): string
    {
        $version = $this->cdpHttp($port, 'GET', '/json/version');
        $userAgent = trim((string) ($version['User-Agent'] ?? ''));

        if ($userAgent === '') {
            return self::DEFAULT_USER_AGENT;
        }

        // The "HeadlessChrome" token marks the client as a bot to most edges.
        // This is a normal Chromium build, so advertise the equivalent Chrome
        // identity and keep it for every later curl probe.
        return str_replace('HeadlessChrome/', 'Chrome/', $userAgent);
    }

    /**
     * @return string|null The target's WebSocket debugger URL.
     */
    private function openTarget(int $port): ?string
    {
        $target = $this->cdpHttp($port, 'PUT', '/json/new?about:blank')
            ?? $this->cdpHttp($port, 'GET', '/json/new?about:blank');

        $url = is_array($target) ? ($target['webSocketDebuggerUrl'] ?? null) : null;

        return is_string($url) && $url !== '' ? $url : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function cdpHttp(int $port, string $method, string $path): ?array
    {
        $socket = @stream_socket_client('tcp://127.0.0.1:'.$port, $errno, $errstr, 3);

        if (! is_resource($socket)) {
            return null;
        }

        stream_set_timeout($socket, 3);

        @fwrite($socket, $method.' '.$path." HTTP/1.1\r\nHost: 127.0.0.1:".$port."\r\nAccept: application/json\r\nConnection: close\r\n\r\n");

        $raw = stream_get_contents($socket);
        @fclose($socket);

        if (! is_string($raw) || $raw === '') {
            return null;
        }

        $split = strpos($raw, "\r\n\r\n");

        if ($split === false) {
            return null;
        }

        $body = substr($raw, $split + 4);
        $decoded = json_decode($body, true);

        if (is_array($decoded)) {
            return $decoded;
        }

        $start = strpos($body, '{');

        if ($start === false) {
            return null;
        }

        $decoded = json_decode(substr($body, $start), true);

        return is_array($decoded) ? $decoded : null;
    }

    /**
     * @return resource|null
     */
    private function connect(string $url, float $deadline)
    {
        $parts = parse_url($url);

        if (! is_array($parts)) {
            return null;
        }

        $host = $parts['host'] ?? '127.0.0.1';
        $port = (int) ($parts['port'] ?? 80);
        $path = ($parts['path'] ?? '/').(isset($parts['query']) ? '?'.$parts['query'] : '');

        $socket = @stream_socket_client('tcp://'.$host.':'.$port, $errno, $errstr, 5);

        if (! is_resource($socket)) {
            return null;
        }

        stream_set_timeout($socket, 5);

        $request = 'GET '.$path." HTTP/1.1\r\n"
            .'Host: '.$host.':'.$port."\r\n"
            ."Upgrade: websocket\r\n"
            ."Connection: Upgrade\r\n"
            .'Sec-WebSocket-Key: '.base64_encode(random_bytes(16))."\r\n"
            ."Sec-WebSocket-Version: 13\r\n"
            ."\r\n";

        @fwrite($socket, $request);

        $response = '';

        while (! str_contains($response, "\r\n\r\n") && microtime(true) < $deadline) {
            $chunk = @fread($socket, 1024);

            if ($chunk === false || $chunk === '') {
                break;
            }

            $response .= $chunk;
        }

        if (! str_contains($response, ' 101 ')) {
            @fclose($socket);

            return null;
        }

        return $socket;
    }

    private int $messageId = 0;

    /**
     * Navigate to the hostname and wait for the interstitial to clear.
     *
     * @param  resource  $socket
     * @return array{status: int, body: string, title: string}|null
     */
    private function navigate($socket, string $hostname, string $userAgent, float $deadline): ?array
    {
        $this->cdp($socket, 'Page.enable', [], $deadline);
        $this->cdp($socket, 'Runtime.enable', [], $deadline);
        $this->cdp($socket, 'Network.enable', [], $deadline);

        // Present the same identity the later curl probes will send, so any
        // clearance the edge issues is bound to a user agent we can replay.
        $this->cdp($socket, 'Network.setUserAgentOverride', [
            'userAgent' => $userAgent,
            'acceptLanguage' => 'en-US,en;q=0.9',
        ], $deadline);

        // Fire and forget: this reply only arrives once the navigation
        // commits, so an origin that stalls on connect would otherwise block
        // the entire handoff. Progress is observed through Runtime.evaluate.
        $this->send($socket, 'Page.navigate', ['url' => 'https://'.$hostname.'/']);

        $expression = <<<'JS'
        (() => {
          const entry = (performance.getEntriesByType('navigation')[0] || {});
          return JSON.stringify({
            ready: document.readyState,
            title: document.title,
            url: location.href,
            status: entry.responseStatus || 0,
            html: (document.documentElement ? document.documentElement.outerHTML : '')
          });
        })()
        JS;

        while (microtime(true) < $deadline) {
            usleep(600000);

            $state = $this->evaluate($socket, $expression, $deadline);

            if ($state === null) {
                continue;
            }

            $html = substr((string) ($state['html'] ?? ''), 0, 1048576);
            $url = (string) ($state['url'] ?? '');

            // Before the navigation commits the document is still about:blank;
            // capturing that would report an empty page as the application.
            if ($html === '' || ($state['ready'] ?? '') !== 'complete') {
                continue;
            }

            if (! str_starts_with($url, 'https://') && ! str_starts_with($url, 'http://')) {
                continue;
            }

            if ($this->isChallengePage($html)) {
                continue;
            }

            // A failed load renders Chrome's own error document, which is not
            // the application and must never be analysed as one.
            if (str_contains($html, 'main-frame-error')) {
                continue;
            }

            return [
                'status' => (int) ($state['status'] ?? 0) ?: 200,
                'body' => $html,
                'title' => (string) ($state['title'] ?? ''),
            ];
        }

        return null;
    }

    /**
     * @param  resource  $socket
     * @return array<string, mixed>|null
     */
    private function evaluate($socket, string $expression, float $deadline): ?array
    {
        $response = $this->cdp($socket, 'Runtime.evaluate', [
            'expression' => $expression,
            'returnByValue' => true,
            'awaitPromise' => true,
        ], $deadline);

        $value = $response['result']['result']['value'] ?? null;

        if (! is_string($value)) {
            return null;
        }

        $decoded = json_decode($value, true);

        return is_array($decoded) ? $decoded : null;
    }

    /**
     * @param  resource  $socket
     * @return array<int, array<string, mixed>>
     */
    private function fetchCookies($socket, float $deadline): array
    {
        $response = $this->cdp($socket, 'Network.getAllCookies', [], $deadline);
        $cookies = $response['result']['cookies'] ?? [];

        return is_array($cookies) ? $cookies : [];
    }

    /**
     * Issue a CDP command without waiting for its reply.
     *
     * @param  resource  $socket
     * @param  array<string, mixed>  $params
     */
    private function send($socket, string $method, array $params = []): void
    {
        $this->wsSend($socket, (string) json_encode([
            'id' => ++$this->messageId,
            'method' => $method,
            'params' => (object) $params,
        ]));
    }

    /**
     * Issue a CDP command and wait for its matching reply, discarding events.
     *
     * @param  resource  $socket
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>|null
     */
    private function cdp($socket, string $method, array $params, float $deadline): ?array
    {
        $id = ++$this->messageId;

        $this->wsSend($socket, (string) json_encode([
            'id' => $id,
            'method' => $method,
            'params' => (object) $params,
        ]));

        while (microtime(true) < $deadline) {
            $raw = $this->wsReadMessage($socket, $deadline);

            if ($raw === null) {
                return null;
            }

            $message = json_decode($raw, true);

            if (! is_array($message)) {
                continue;
            }

            if (($message['id'] ?? null) === $id) {
                return $message;
            }
        }

        return null;
    }

    /**
     * Send a single, masked client frame.
     *
     * @param  resource  $socket
     */
    private function wsSend($socket, string $payload, int $opcode = 0x1): void
    {
        $length = strlen($payload);
        $mask = random_bytes(4);

        $frame = chr(0x80 | $opcode);

        if ($length < 126) {
            $frame .= chr(0x80 | $length);
        } elseif ($length < 65536) {
            $frame .= chr(0x80 | 126).pack('n', $length);
        } else {
            $frame .= chr(0x80 | 127).pack('J', $length);
        }

        $masked = '';

        for ($i = 0; $i < $length; $i++) {
            $masked .= $payload[$i] ^ $mask[$i % 4];
        }

        @fwrite($socket, $frame.$mask.$masked);
    }

    /**
     * Read one complete message, answering pings and following fragments.
     *
     * @param  resource  $socket
     */
    private function wsReadMessage($socket, float $deadline): ?string
    {
        $buffer = '';

        while (microtime(true) < $deadline) {
            $frame = $this->wsReadFrame($socket);

            if ($frame === null) {
                return null;
            }

            if ($frame['opcode'] === 0x8) {
                return null;
            }

            if ($frame['opcode'] === 0x9) {
                $this->wsSend($socket, $frame['payload'], 0xA);

                continue;
            }

            if ($frame['opcode'] === 0xA) {
                continue;
            }

            $buffer .= $frame['payload'];

            if ($frame['fin']) {
                return $buffer;
            }
        }

        return null;
    }

    /**
     * @param  resource  $socket
     * @return array{opcode: int, fin: bool, payload: string}|null
     */
    private function wsReadFrame($socket): ?array
    {
        $header = $this->readExact($socket, 2);

        if ($header === null) {
            return null;
        }

        $first = ord($header[0]);
        $second = ord($header[1]);

        $fin = ($first & 0x80) === 0x80;
        $opcode = $first & 0x0F;
        $masked = ($second & 0x80) === 0x80;
        $length = $second & 0x7F;

        if ($length === 126) {
            $extended = $this->readExact($socket, 2);

            if ($extended === null) {
                return null;
            }

            $length = unpack('n', $extended)[1];
        } elseif ($length === 127) {
            $extended = $this->readExact($socket, 8);

            if ($extended === null) {
                return null;
            }

            $length = unpack('J', $extended)[1];
        }

        if ($length > 8 * 1024 * 1024) {
            return null;
        }

        $mask = $masked ? $this->readExact($socket, 4) : null;

        if ($masked && $mask === null) {
            return null;
        }

        $payload = $length > 0 ? $this->readExact($socket, $length) : '';

        if ($payload === null) {
            return null;
        }

        if ($mask !== null) {
            $unmasked = '';

            for ($i = 0; $i < $length; $i++) {
                $unmasked .= $payload[$i] ^ $mask[$i % 4];
            }

            $payload = $unmasked;
        }

        return ['opcode' => $opcode, 'fin' => $fin, 'payload' => $payload];
    }

    /**
     * @param  resource  $socket
     */
    private function readExact($socket, int $length): ?string
    {
        $buffer = '';

        while (strlen($buffer) < $length) {
            $chunk = @fread($socket, $length - strlen($buffer));

            if ($chunk === false || $chunk === '') {
                return null;
            }

            $buffer .= $chunk;
        }

        return $buffer;
    }

    private function terminate(mixed $process, string $profile): void
    {
        if (is_resource($process)) {
            $pid = (int) (proc_get_status($process)['pid'] ?? 0);

            if ($pid > 0 && PHP_OS_FAMILY === 'Windows') {
                @exec('taskkill /F /T /PID '.$pid.' 2>NUL');
            }

            @proc_terminate($process);
            @proc_close($process);
        }

        $this->removeDirectory($profile);
    }

    private function removeDirectory(string $directory): void
    {
        if (! is_dir($directory)) {
            return;
        }

        // Chromium can hold profile files briefly after exit; retry a few times.
        for ($attempt = 0; $attempt < 3; $attempt++) {
            $items = @scandir($directory);

            if ($items === false) {
                return;
            }

            foreach ($items as $item) {
                if ($item === '.' || $item === '..') {
                    continue;
                }

                $path = $directory.DIRECTORY_SEPARATOR.$item;

                if (is_dir($path) && ! is_link($path)) {
                    $this->removeDirectory($path);
                } else {
                    @unlink($path);
                }
            }

            if (@rmdir($directory)) {
                return;
            }

            usleep(150000);
        }
    }
}
