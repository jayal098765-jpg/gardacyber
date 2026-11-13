<?php

namespace App\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

class SurfaceScanner
{
    /**
     * Browser-grade identity for the primary capture. Edge bot rules that key
     * off obviously-automated clients are far less likely to challenge it, so
     * the scanner sees the application instead of an interstitial.
     */
    private const USER_AGENT = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36';

    /**
     * Clearance the edge handed the browser handoff. Every later probe sends
     * these cookies so `curl` arrives with an identity the edge has already
     * vetted.
     *
     * @var array<int, array{name: string, value: string, domain: string}>
     */
    private array $edgeCookies = [];

    private ?string $edgeUserAgent = null;

    public function __construct(
        private CdnFingerprint $fingerprint = new CdnFingerprint,
        private OriginDiscovery $origins = new OriginDiscovery,
        private BrowserClearance $browser = new BrowserClearance,
    ) {}

    public function assertAllowed(string $hostname): void
    {
        if (config('scanner.allow_any_public', true)) {
            if (! preg_match('/^([a-z0-9]([a-z0-9-]*[a-z0-9])?\.)+[a-z]{2,}$/i', $hostname)) {
                throw ValidationException::withMessages(['domain' => 'Enter a valid domain name (e.g. example.com).']);
            }

            $lowered = strtolower($hostname);
            if (in_array($lowered, ['localhost', '127.0.0.1', '::1'], true) ||
                str_ends_with($lowered, '.local') ||
                str_ends_with($lowered, '.internal') ||
                str_ends_with($lowered, '.localhost')) {
                throw ValidationException::withMessages(['domain' => 'Local, internal, and private hostnames cannot be scanned.']);
            }

            return;
        }

        if (! in_array($hostname, config('scanner.allowed_domains', []), true)) {
            throw ValidationException::withMessages(['domain' => 'Target is not in the allowed domains list.']);
        }
    }

    public function resolve(string $hostname): string
    {
        $records = @dns_get_record($hostname, DNS_A | DNS_AAAA);
        $addresses = array_values(array_filter(array_map(fn (array $record): ?string => $record['ip'] ?? $record['ipv6'] ?? null, $records ?: [])));

        if ($addresses === []) {
            throw new \RuntimeException('No public DNS address found for hostname: '.$hostname);
        }

        foreach ($addresses as $address) {
            if (! filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)
                || str_starts_with(strtolower($address), '::ffff:')
                || str_starts_with($address, '127.')
                || str_starts_with($address, '169.254.')
                || $address === '0.0.0.0') {
                throw new \RuntimeException('Private, loopback, or reserved IP addresses ('.$address.') cannot be scanned.');
            }
        }

        return $addresses[0];
    }

    /**
     * Discover live subdomains via DNS.
     *
     * @return array<int, array{hostname: string, ip: string}>
     */
    public function discoverSubdomains(string $rootDomain): array
    {
        $prefixes = ['www', 'api', 'admin', 'mail', 'dev', 'staging', 'portal', 'app', 'docs', 'cdn'];
        $discovered = [];

        foreach ($prefixes as $prefix) {
            $sub = $prefix.'.'.$rootDomain;
            try {
                $ip = $this->resolve($sub);
                $discovered[] = ['hostname' => $sub, 'ip' => $ip];
            } catch (\Throwable) {
                // Not resolved or private — skip
            }
        }

        return $discovered;
    }

    /**
     * Check active TCP ports.
     *
     * @return array<int, int>
     */
    public function probePorts(string $ip): array
    {
        $ports = [80, 443, 8080, 8443];
        $open = [];

        foreach ($ports as $port) {
            $fp = @fsockopen($ip, $port, $errno, $errstr, 0.5);
            if (is_resource($fp)) {
                $open[] = $port;
                fclose($fp);
            }
        }

        return $open ?: [443];
    }

    /**
     * @return array{ip: string, status: int, headers: array, body: string, truncated: bool, blocked: bool, waf: array|null, via_origin: bool}
     */
    public function capture(string $hostname): array
    {
        $this->assertAllowed($hostname);
        $ip = $this->resolve($hostname);

        return $this->withWafAnalysis($hostname, $this->fetchPrimary($hostname, $ip));
    }

    /**
     * @return array{ip: string, status: int, headers: array, body: string, truncated: bool}
     */
    private function fetchPrimary(string $hostname, string $ip): array
    {
        $pinnedIp = $this->pin($ip);

        try {
            return $this->fetchUrl('https://'.$hostname.'/', $hostname, $pinnedIp, 443, $ip);
        } catch (\Throwable $httpsException) {
            // Fallback to HTTP on port 80 if HTTPS fails
            try {
                return $this->fetchUrl('http://'.$hostname.'/', $hostname, $pinnedIp, 80, $ip);
            } catch (\Throwable) {
                throw new \RuntimeException('Could not establish HTTP or HTTPS connection to '.$hostname.': '.$httpsException->getMessage());
            }
        }
    }

    private function pin(string $ip): string
    {
        return str_contains($ip, ':') ? '['.$ip.']' : $ip;
    }

    /**
     * A real browser's navigation headers. Edge bot rules score these heavily,
     * so sending them keeps the assessment out of the challenge path far more
     * often than a bare user-agent swap does.
     *
     * The client hints are derived from the active identity: a hint set that
     * disagrees with the user agent is itself a bot signal, and after a browser
     * handoff the version is the real installed browser, not a constant.
     *
     * @return array<string, string>
     */
    private function browserHeaders(): array
    {
        $agent = $this->edgeUserAgent ?? self::USER_AGENT;
        $major = preg_match('#Chrome/(\d+)#', $agent, $matches) === 1 ? (int) $matches[1] : 124;

        if (str_contains($agent, 'Windows NT')) {
            $platform = '"Windows"';
        } elseif (str_contains($agent, 'Macintosh')) {
            $platform = '"macOS"';
        } else {
            $platform = '"Linux"';
        }

        return [
            'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,*/*;q=0.8',
            'Accept-Language' => 'en-US,en;q=0.9',
            'Sec-Ch-Ua' => '"Chromium";v="'.$major.'", "Google Chrome";v="'.$major.'", "Not-A.Brand";v="99"',
            'Sec-Ch-Ua-Mobile' => '?0',
            'Sec-Ch-Ua-Platform' => $platform,
            'Sec-Fetch-Dest' => 'document',
            'Sec-Fetch-Mode' => 'navigate',
            'Sec-Fetch-Site' => 'none',
            'Sec-Fetch-User' => '?1',
            'Upgrade-Insecure-Requests' => '1',
            'Cache-Control' => 'no-cache',
            'Pragma' => 'no-cache',
        ];
    }

    /**
     * A probe request carrying the same identity the edge has already vetted:
     * the browser's clearance cookies and user agent when a handoff happened,
     * otherwise the plain browser headers.
     *
     * @return PendingRequest
     */
    private function probeRequest(string $hostname, string $ip, float $connectTimeout = 2, float $timeout = 4)
    {
        return $this->applyProbeIdentity(
            Http::connectTimeout($connectTimeout)->timeout($timeout),
            $hostname,
            $ip,
        );
    }

    /**
     * Attach the shared probe identity plus the pinned-origin options.
     *
     * @param  PendingRequest  $request
     * @return PendingRequest
     */
    private function applyProbeIdentity($request, string $hostname, string $ip, int $port = 443)
    {
        $request = $request
            ->withHeaders($this->browserHeaders())
            ->withUserAgent($this->edgeUserAgent ?? self::USER_AGENT)
            ->withOptions($this->resolveOptions($hostname, $ip, $port));

        if ($this->edgeCookies !== []) {
            $request = $request->withHeaders(['Cookie' => $this->browser->cookieHeader($this->edgeCookies)]);
        }

        return $request;
    }

    /**
     * @return array<string, mixed>
     */
    private function resolveOptions(string $hostname, string $ip, int $port = 443): array
    {
        return [
            'allow_redirects' => false,
            'proxy' => '',
            'verify' => false,
            'curl' => [CURLOPT_RESOLVE => [$hostname.':'.$port.':'.$this->pin($ip)]],
        ];
    }

    /**
     * @return array{ip: string, status: int, headers: array, body: string, truncated: bool}
     */
    private function fetchUrl(string $url, string $hostname, string $pinnedIp, int $port, string $ip): array
    {
        $response = Http::connectTimeout(5)->timeout(12)
            ->withHeaders($this->browserHeaders())
            ->withUserAgent(self::USER_AGENT)
            ->withOptions([
                'allow_redirects' => false,
                'proxy' => '',
                'verify' => false, // Allow self-signed in audit
                'curl' => [CURLOPT_RESOLVE => [$hostname.':'.$port.':'.$pinnedIp]],
            ])->get($url);

        $rawBody = $response->body();
        $truncated = strlen($rawBody) > 1048576;
        $body = substr($rawBody, 0, 1048576);

        return [
            'ip' => $ip,
            'status' => $response->status(),
            'headers' => array_change_key_case($response->headers(), CASE_LOWER),
            'body' => $body,
            'truncated' => $truncated,
        ];
    }

    /**
     * Classify the edge response and, when a WAF challenge blocked the scan,
     * re-capture the site from either a discovered origin address or a real
     * browser that has passed the challenge. No challenge is bypassed by
     * forging anything: the origin is fetched as the application serves it,
     * and the browser path simply lets the challenge's own JavaScript run.
     * If neither works the capture is returned flagged as blocked.
     *
     * @param  array{ip: string, status: int, headers: array, body: string, truncated: bool}  $capture
     * @return array{ip: string, status: int, headers: array, body: string, truncated: bool, blocked: bool, waf: array|null, via_origin: bool, via_browser: bool}
     */
    private function withWafAnalysis(string $hostname, array $capture): array
    {
        $detection = $this->fingerprint->detect($capture['headers'], $capture['body'], $capture['status']);
        $blocked = (bool) ($detection['challenged'] ?? false);

        $capture['waf'] = $detection;
        $capture['blocked'] = $blocked;
        $capture['via_origin'] = false;
        $capture['via_browser'] = false;

        if (! $blocked || ! (bool) config('scanner.waf_bypass', true)) {
            return $capture;
        }

        $originCapture = $this->bypassWaf($hostname);

        if ($originCapture !== null) {
            return [
                ...$originCapture,
                'waf' => $detection,
                'blocked' => false,
                'via_origin' => true,
                'via_browser' => false,
                'edge_status' => $capture['status'],
            ];
        }

        // Origin discovery came up empty. Fall back to executing the
        // challenge exactly as a visitor's browser would; when it clears, the
        // clearance identity is reused by every probe that follows.
        $browserCapture = $this->browserCapture($hostname, $capture['ip']);

        if ($browserCapture === null) {
            return $capture;
        }

        return [
            ...$browserCapture,
            'waf' => $detection,
            'blocked' => false,
            'via_origin' => false,
            'via_browser' => true,
            'edge_status' => $capture['status'],
        ];
    }

    /**
     * Run the edge's interstitial in a headless browser and keep the
     * clearance cookies it earns. Public so tests can stub the handoff.
     *
     * @return array{ip: string, status: int, headers: array, body: string, truncated: bool}|null
     */
    public function browserCapture(string $hostname, string $ip): ?array
    {
        if (! (bool) config('scanner.browser_bypass', true)) {
            return null;
        }

        $result = $this->browser->solve($hostname, $ip);

        if ($result === null) {
            return null;
        }

        $this->edgeCookies = $result['cookies'];
        $this->edgeUserAgent = $result['user_agent'];

        return [
            'ip' => $ip,
            'status' => $result['status'],
            'headers' => ['content-type' => ['text/html']],
            'body' => $result['body'],
            'truncated' => false,
            'title' => $result['title'],
        ];
    }

    /**
     * Walk the domain's passive DNS footprint looking for an address that
     * serves the application without the edge in front of it.
     *
     * @return array{ip: string, status: int, headers: array, body: string, truncated: bool, origin: array{ip: string, host: string, source: string}}|null
     */
    public function bypassWaf(string $hostname): ?array
    {
        $limit = max(1, (int) config('scanner.waf_bypass_candidates', 6));
        $deadline = microtime(true) + max(1.0, (float) config('scanner.waf_bypass_budget', 12.0));

        foreach ($this->origins->candidates($hostname, $limit, $deadline) as $candidate) {
            if (microtime(true) > $deadline) {
                break;
            }

            $capture = $this->captureViaOrigin($hostname, $candidate['ip']);

            if ($capture === null || ! $this->isVerifiedOrigin($capture)) {
                continue;
            }

            return [...$capture, 'origin' => $candidate];
        }

        return null;
    }

    /**
     * Fetch the hostname pinned to a candidate origin address while keeping
     * Host/SNI on the real hostname, exactly as a browser would when it has
     * been pointed at the origin directly.
     *
     * @return array{ip: string, status: int, headers: array, body: string, truncated: bool}|null
     */
    public function captureViaOrigin(string $hostname, string $originIp): ?array
    {
        $pinnedIp = $this->pin($originIp);

        foreach ([['https', 443], ['http', 80]] as [$scheme, $port]) {
            try {
                return $this->fetchUrl($scheme.'://'.$hostname.'/', $hostname, $pinnedIp, $port, $originIp);
            } catch (\Throwable) {
                // Try the next scheme, then give up on this candidate.
            }
        }

        return null;
    }

    /**
     * A candidate only counts as the origin when it serves a real application
     * response instead of another challenge page.
     *
     * @param  array{status: int, headers: array, body: string}  $capture
     */
    public function isVerifiedOrigin(array $capture): bool
    {
        if ($capture['status'] < 200 || $capture['status'] >= 400) {
            return false;
        }

        if (trim($capture['body']) === '') {
            return false;
        }

        $detection = $this->fingerprint->detect($capture['headers'], $capture['body'], $capture['status']);

        return $detection === null || ! $detection['challenged'];
    }

    /**
     * Findings describing how the edge affected (or failed to affect) the scan.
     *
     * @param  array<string, mixed>  $capture
     * @return array<int, array{kind: string, severity: string, category: string, title: string, description: string, evidence: string}>
     */
    public function wafFindings(array $capture, string $hostname): array
    {
        // A browser-cleared capture is the application as served to a visitor
        // who passed the edge's own check. Nothing was bypassed and the
        // challenge is gone, so there is no edge finding to report.
        if (! empty($capture['via_browser'])) {
            return [];
        }

        $waf = $capture['waf'] ?? null;
        $provider = is_array($waf) && isset($waf['provider']) ? (string) $waf['provider'] : 'edge WAF';

        if (! empty($capture['via_origin'])) {
            $origin = is_array($capture['origin'] ?? null) ? $capture['origin'] : [];

            return [[
                'kind' => 'vulnerability',
                'severity' => 'medium',
                'category' => 'waf-bypass',
                'title' => 'Origin server directly reachable behind '.$provider,
                'description' => 'The origin address answers on the public hostname with no CDN/WAF in front of it. Anyone who learns this address can skip the edge protections entirely, so the checks in this scan were collected against the origin.',
                'evidence' => 'Origin '.($origin['ip'] ?? $capture['ip']).' confirmed via '.($origin['source'] ?? 'DNS footprint').'; edge returned HTTP '.($capture['edge_status'] ?? $capture['status']).' while the origin returned HTTP '.$capture['status'].'.',
            ]];
        }

        if (! empty($capture['blocked'])) {
            return [[
                'kind' => 'vulnerability',
                'severity' => 'low',
                'category' => 'waf',
                'title' => 'Edge WAF challenged the automated assessment',
                'description' => 'The '.$provider.' edge answered with a challenge or block page instead of the application, and no direct origin could be confirmed. Header, exposure, and injection coverage for this scan is partial.',
                'evidence' => 'Edge response HTTP '.$capture['status'].' matched a '.$provider.' challenge signature; passive DNS origin discovery produced no verified application response for '.$hostname.'.',
            ]];
        }

        return [];
    }

    /**
     * Non-destructive probes for web vulnerabilities & sensitive exposures.
     *
     * @return array<int, array{kind: string, severity: string, category: string, title: string, description: string, evidence: string}>
     */
    public function probeActiveVulnerabilities(string $hostname, string $ip): array
    {
        $findings = [];
        $baseUrl = 'https://'.$hostname;

        // 1. Exposure Probes
        $exposurePaths = [
            '/.git/HEAD' => [
                'match' => 'ref: refs/',
                'severity' => 'critical',
                'category' => 'exposure',
                'title' => 'Exposed .git repository directory',
                'desc' => 'Source code repository directory is publicly accessible, allowing recovery of full application code and embedded credentials.',
            ],
            '/.env' => [
                'match' => 'APP_KEY=',
                'severity' => 'critical',
                'category' => 'exposure',
                'title' => 'Exposed environment configuration (.env)',
                'desc' => 'Environment file containing application keys, database credentials, or secret tokens is publicly readable.',
            ],
            '/phpinfo.php' => [
                'match' => 'phpinfo()',
                'severity' => 'high',
                'category' => 'exposure',
                'title' => 'PHPinfo configuration diagnostic page exposed',
                'desc' => 'Internal server configuration, environment variables, and module versions are exposed.',
            ],
            '/_ignition/health-check' => [
                'match' => 'can_execute_commands',
                'severity' => 'high',
                'category' => 'misconfiguration',
                'title' => 'Laravel Ignition debug mode exposed',
                'desc' => 'Framework debug mode is active on a public endpoint.',
            ],
            '/swagger-ui.html' => [
                'match' => 'swagger-ui',
                'severity' => 'low',
                'category' => 'exposure',
                'title' => 'Public Swagger API documentation exposed',
                'desc' => 'API documentation is exposed without authentication.',
            ],
        ];

        foreach ($exposurePaths as $path => $probe) {
            try {
                $resp = $this->probeRequest($hostname, $ip)->get($baseUrl.$path);

                if ($resp->status() === 200 && str_contains($resp->body(), $probe['match'])) {
                    $findings[] = [
                        'kind' => 'vulnerability',
                        'severity' => $probe['severity'],
                        'category' => $probe['category'],
                        'title' => $probe['title'],
                        'description' => $probe['desc'],
                        'evidence' => 'GET '.$path.' returned HTTP 200 containing signature pattern "'.$probe['match'].'".',
                    ];
                }
            } catch (\Throwable) {
                // Request timeout or connection reset — safe ignore
            }
        }

        // 2. Advanced reflected XSS probe (multi-vector, non-executing markers)
        $findings = [...$findings, ...$this->probeAdvancedXss($hostname, $ip)];

        // 3. SQL Error Disclosure Probe
        try {
            $resp = $this->probeRequest($hostname, $ip)->get($baseUrl.'/?id=1%27%22');

            $sqlErrors = [
                'You have an error in your SQL syntax',
                'mysql_fetch_array()',
                'PostgreSQL query failed: ERROR:',
                'pg_query(): Query failed: ERROR:',
                'ORA-01756: quoted string not properly terminated',
                'SQLite3::query(): unrecognized token',
                'Microsoft OLE DB Provider for SQL Server',
            ];

            foreach ($sqlErrors as $err) {
                if (str_contains($resp->body(), $err)) {
                    $findings[] = [
                        'kind' => 'vulnerability',
                        'severity' => 'critical',
                        'category' => 'cve',
                        'title' => 'Database error disclosure (SQL Injection indicator)',
                        'description' => 'Unescaped quote parameter provoked a raw database error message disclosure in the response.',
                        'evidence' => 'Matched database error string in response: "'.$err.'".',
                    ];
                    break;
                }
            }
        } catch (\Throwable) {
        }

        return $findings;
    }

    /**
     * Advanced reflected XSS probe: tests several parameters with non-executing
     * markers in raw HTML, attribute, and script contexts. Only reflection is
     * reported — no payload is executed and no exploit is delivered.
     *
     * @return array<int, array{kind: string, severity: string, category: string, title: string, description: string, evidence: string}>
     */
    public function probeAdvancedXss(string $hostname, string $ip): array
    {
        $findings = [];
        $marker = 'garda_'.substr(md5($hostname), 0, 8);
        $baseUrl = 'https://'.$hostname;

        $parameters = ['q', 'search', 'term', 's', 'query', 'id', 'page', 'callback', 'next', 'url', 'redirect', 'lang'];

        // Script context is checked first so the worst-case context wins when
        // a parameter reflects in multiple contexts.
        $vectors = [
            'script' => [
                'needle' => null,
                'severity' => 'critical',
                'title' => 'Reflected parameter injection (Potential XSS — script context)',
                'description' => 'A query parameter is reflected inside a <script> block, allowing direct JavaScript injection.',
            ],
            'attribute' => [
                'needle' => 'autofocus onfocus="'.$marker,
                'severity' => 'high',
                'title' => 'Reflected parameter injection (Potential XSS — attribute context)',
                'description' => 'A query parameter is reflected inside an HTML attribute without escaping, allowing an attacker to break out and inject event-handler attributes.',
            ],
            'html' => [
                'needle' => '<'.$marker.'>',
                'severity' => 'high',
                'title' => 'Reflected parameter injection (Potential XSS — raw HTML context)',
                'description' => 'User-supplied query parameters are reflected unencoded into HTML responses without context-aware sanitization.',
            ],
        ];

        $reported = 0;
        $maxReported = 3;

        foreach ($parameters as $parameter) {
            foreach ($vectors as $context => $vector) {
                $payload = match ($context) {
                    'html' => '<'.$marker.'>',
                    'attribute' => '" autofocus onfocus="'.$marker,
                    'script' => '\';'.$marker.';\'',
                };

                try {
                    $resp = $this->probeRequest($hostname, $ip)
                        ->get($baseUrl.'/?'.http_build_query([$parameter => $payload]));

                    if ($resp->status() !== 200) {
                        continue;
                    }

                    $matched = $context === 'script'
                        ? preg_match('/<script[^>]*>[^<]*'.preg_quote($marker, '/').'/is', $resp->body()) === 1
                        : str_contains($resp->body(), $vector['needle']);
                } catch (\Throwable) {
                    continue;
                }

                if ($matched) {
                    $findings[] = [
                        'kind' => 'vulnerability',
                        'severity' => $vector['severity'],
                        'category' => 'xss',
                        'title' => $vector['title'],
                        'description' => $vector['description'],
                        'evidence' => 'Probe marker "'.$marker.'" reflected via parameter "'.$parameter.'" in '.$context.' context. GET '.$baseUrl.'/?'.http_build_query([$parameter => '...']).' returned HTTP 200.',
                    ];
                    $reported++;

                    // One finding per parameter is enough; cap report volume.
                    break;
                }
            }

            if ($reported >= $maxReported) {
                break;
            }
        }

        return $findings;
    }

    /**
     * Brute-force posture probe: sends a small number of clearly-invalid login
     * attempts to a discovered authentication endpoint and checks whether the
     * server enforces rate limiting, throttling, or lockout signals. This is
     * an indicator probe (default 5 attempts) — not a credential-cracking tool.
     *
     * @return array<int, array{kind: string, severity: string, category: string, title: string, description: string, evidence: string}>
     */
    public function probeAuthBruteForceResistance(string $hostname, string $ip, array $capture): array
    {
        // The edge is answering, not the application: attempt counts and
        // throttle headers would describe the WAF, not the login endpoint.
        if (! empty($capture['blocked'])) {
            return [];
        }

        $attempts = max(1, min(10, (int) config('scanner.brute_force_attempts', 5)));
        $loginPaths = $this->discoverLoginPaths($capture);

        if ($loginPaths === []) {
            return [];
        }

        $findings = [];
        $username = 'garda_probe_'.substr(md5($hostname), 0, 8).'@example.invalid';
        $password = 'invalid-password-garda-probe';
        $baseUrl = 'https://'.$hostname;

        foreach ($loginPaths as $path) {
            $statuses = [];
            $rateLimited = false;
            $alive = false;
            $edgeBlocked = false;

            for ($i = 0; $i < $attempts; $i++) {
                try {
                    $resp = $this->probeRequest($hostname, $ip)
                        ->asForm()->post($baseUrl.$path, [
                            'email' => $username,
                            'username' => $username,
                            'password' => $password,
                        ]);

                    $statuses[] = $resp->status();

                    $detection = $this->fingerprint->detect(
                        array_change_key_case($resp->headers(), CASE_LOWER),
                        $resp->body(),
                        $resp->status(),
                    );

                    if ($detection['challenged'] ?? false) {
                        $edgeBlocked = true;
                        break;
                    }

                    if (in_array($statuses[array_key_last($statuses)], [200, 201, 204, 301, 302, 303, 307, 308, 401, 403, 422], true)) {
                        $alive = true;
                    }

                    if (in_array($resp->status(), [429, 423], true)) {
                        $rateLimited = true;
                    }

                    if (trim((string) $resp->header('Retry-After')) !== ''
                        || preg_match('/^x-ratelimit/i', implode(' ', array_keys($resp->headers())))) {
                        $rateLimited = true;
                    }
                } catch (\Throwable) {
                    $statuses[] = 0;
                }

                if ($rateLimited) {
                    break;
                }

                usleep(150000); // Gentle 150ms spacing between attempts.
            }

            if (! $edgeBlocked && ! $rateLimited && $alive && count($statuses) >= 2) {
                $findings[] = [
                    'kind' => 'vulnerability',
                    'severity' => 'medium',
                    'category' => 'auth-bruteforce',
                    'title' => 'Authentication endpoint lacks brute-force protection',
                    'description' => 'A login endpoint accepted rapid, repeated invalid credential submissions without returning rate-limit, throttling, or lockout signals (HTTP 429/423 or Retry-After / X-RateLimit headers).',
                    'evidence' => 'POST '.$path.' returned HTTP '.implode(', ', $statuses).' across '.$attempts.' sequential attempts with no throttle signal.',
                ];
            }
        }

        return $findings;
    }

    /**
     * Hijacking probe: checks each discovered subdomain for a dangling CNAME
     * pointing at an unclaimed third-party cloud/SaaS resource (subdomain
     * takeover). A takeover is only reported when the target provider's own
     * "resource not found" signature is confirmed over HTTPS.
     *
     * @param  array<int, array{hostname: string, ip: string}>  $subdomains
     * @return array<int, array{kind: string, severity: string, category: string, title: string, description: string, evidence: string}>
     */
    public function probeSubdomainTakeover(array $subdomains): array
    {
        $findings = [];

        foreach ($subdomains as $subdomain) {
            $cnameTargets = $this->cnameFor($subdomain['hostname']);

            foreach ($cnameTargets as $target) {
                $provider = $this->takeoverProviderFor($target);

                if ($provider === null) {
                    continue;
                }

                $signature = $this->confirmTakeoverSignature($subdomain['hostname'], $subdomain['ip'], $provider['signatures']);

                if ($signature !== null) {
                    $findings[] = [
                        'kind' => 'vulnerability',
                        'severity' => 'critical',
                        'category' => 'subdomain-takeover',
                        'title' => 'Subdomain takeover confirmed on dangling CNAME',
                        'description' => 'The subdomain points to an unclaimed '.$provider['name'].' resource. An attacker could claim the resource and serve arbitrary content or harvest session cookies under the root domain.',
                        'evidence' => 'DNS CNAME '.$subdomain['hostname'].' → '.$target.'; HTTPS fetch matched provider signature "'.$signature.'".',
                    ];
                    break;
                }
            }
        }

        return $findings;
    }

    /**
     * Resolve CNAME records for a hostname (wrapped for testability).
     *
     * @return array<int, string>
     */
    protected function cnameFor(string $hostname): array
    {
        $records = @dns_get_record($hostname, DNS_CNAME);

        return array_values(array_filter(array_map(
            fn (array $record): string => rtrim((string) ($record['target'] ?? ''), '.'),
            $records ?: []
        )));
    }

    /**
     * Discover candidate login endpoints from the captured page and from
     * well-known default paths.
     *
     * @return array<int, string>
     */
    private function discoverLoginPaths(array $capture): array
    {
        $paths = [];

        if (preg_match_all('/<form[^>]*action\s*=\s*["\']([^"\']+)["\']/i', $capture['body'] ?? '', $matches)) {
            foreach ($matches[1] as $action) {
                $normalized = ltrim(parse_url($action, PHP_URL_PATH) ?: $action, '/');

                if ($normalized !== '' && str_contains(strtolower((string) $capture['body']), 'password')) {
                    $paths[] = '/'.$normalized;
                }
            }
        }

        foreach (['/login', '/signin', '/auth/login', '/admin/login', '/user/login', '/api/login'] as $path) {
            $paths[] = $path;
        }

        return array_values(array_unique($paths));
    }

    /**
     * Match a CNAME target against known takeover-prone providers.
     *
     * @return array{name: string, signatures: array<int, string>}|null
     */
    private function takeoverProviderFor(string $target): ?array
    {
        $providers = [
            'GitHub Pages' => [
                '/\.github\.io$/i',
                ['There isn\'t a GitHub Pages site here.'],
            ],
            'AWS S3' => [
                '/\.s3(?:[.-][a-z0-9-]+)?\.amazonaws\.com$/i',
                ['NoSuchBucket'],
            ],
            'Azure App Service' => [
                '/\.(?:azurewebsites\.net|cloudapp\.net|trafficmanager\.net|azureedge\.net)$/i',
                ['404 Web Site not found', 'There is no app hosted at this web address'],
            ],
            'Heroku' => [
                '/\.(?:herokudns\.com|herokuapp\.com)$/i',
                ['no such app'],
            ],
            'Netlify' => [
                '/\.netlify\.(?:app|com)$/i',
                ['Not Found - Request ID', 'Page Not Found'],
            ],
            'Surge.sh' => [
                '/\.surge\.sh$/i',
                ['404 Not Found', 'Not Found — Surge'],
            ],
            'Bitbucket' => [
                '/\.bitbucket\.io$/i',
                ['Repository not found'],
            ],
            'Pantheon' => [
                '/\.pantheonsite\.io$/i',
                ['The site you are looking for could not be found'],
            ],
        ];

        foreach ($providers as $name => [$pattern, $signatures]) {
            if (preg_match($pattern, $target)) {
                return ['name' => $name, 'signatures' => $signatures];
            }
        }

        return null;
    }

    /**
     * Fetch a subdomain over HTTPS and look for the provider's "unclaimed
     * resource" signature.
     *
     * @param  array<int, string>  $signatures
     */
    private function confirmTakeoverSignature(string $hostname, string $ip, array $signatures): ?string
    {
        try {
            $resp = $this->probeRequest($hostname, $ip, 3, 6)->get('https://'.$hostname.'/');
        } catch (\Throwable) {
            return null;
        }

        foreach ($signatures as $signature) {
            if (str_contains($resp->body(), $signature)) {
                return $signature;
            }
        }

        return null;
    }

    /**
     * Directory and backup-file brute force over a curated, read-only list of
     * paths. Every request is a plain GET: nothing is uploaded, submitted, or
     * deleted, and no traversal payload is sent.
     *
     * A candidate is only reported when the response is provably different
     * from the application's own catch-all behaviour: the soft-404 baseline
     * (a random path) and the homepage (single-page-app routers) must both
     * differ, and the content must carry real proof — a filename needle, an
     * index listing, a panel marker, or a download content type.
     *
     * @param  array<string, mixed>  $capture
     * @return array<int, array{kind: string, severity: string, category: string, title: string, description: string, evidence: string}>
     */
    public function probeDirectoryExposure(string $hostname, string $ip, array $capture): array
    {
        if (! (bool) config('scanner.dir_bruteforce', true)) {
            return [];
        }

        // The edge is answering, so every path would describe the WAF.
        if (! empty($capture['blocked'])) {
            return [];
        }

        $paths = config('scanner.dir_bruteforce_paths', []);

        if (! is_array($paths) || $paths === []) {
            return [];
        }

        $baseUrl = 'https://'.$hostname;
        $deadline = microtime(true) + max(1.0, (float) config('scanner.dir_bruteforce_budget', 20));
        $timeout = min(8.0, max(2.0, (float) config('scanner.dir_bruteforce_timeout', 4)));

        $baseline = $this->dirBaseline($hostname, $ip);
        $homepage = $this->responseSignature((string) ($capture['body'] ?? ''));

        $findings = [];
        $maxFindings = 6;
        $requested = array_values(array_unique(array_map(
            fn (mixed $path): string => '/'.ltrim((string) $path, '/'),
            $paths
        )));

        foreach (array_chunk($requested, 8) as $chunk) {
            if (microtime(true) >= $deadline || count($findings) >= $maxFindings) {
                break;
            }

            $responses = Http::pool(function ($pool) use ($chunk, $hostname, $ip, $baseUrl, $timeout): void {
                foreach ($chunk as $index => $path) {
                    $this->applyProbeIdentity(
                        $pool->as((string) $index)->connectTimeout(2)->timeout($timeout),
                        $hostname,
                        $ip,
                    )->get($baseUrl.$path);
                }
            });

            foreach ($chunk as $index => $path) {
                $response = $responses[(string) $index] ?? null;

                if (! $response instanceof Response) {
                    continue;
                }

                $finding = $this->directoryFinding(
                    $path,
                    $response->status(),
                    $response->headers(),
                    $response->body(),
                    $baseline,
                    $homepage,
                );

                if ($finding === null) {
                    continue;
                }

                $findings[] = $finding;

                if (count($findings) >= $maxFindings) {
                    break;
                }
            }
        }

        return $findings;
    }

    /**
     * Fetch a path that cannot exist so the application's "not found"
     * behaviour can be compared against every candidate.
     *
     * @return array{status: int, signature: string}|null
     */
    private function dirBaseline(string $hostname, string $ip): ?array
    {
        $path = '/garda-missing-'.bin2hex(random_bytes(6));

        try {
            $response = $this->probeRequest($hostname, $ip)->get('https://'.$hostname.$path);
        } catch (\Throwable) {
            return null;
        }

        return [
            'status' => $response->status(),
            'signature' => $this->responseSignature($response->body()),
        ];
    }

    /**
     * Compact, binary-safe fingerprint of a response body used to decide
     * whether two responses are the same page.
     */
    private function responseSignature(string $body): string
    {
        if ($body === '') {
            return '';
        }

        $normalized = preg_replace('/\s+/', ' ', strip_tags($body));

        if (! is_string($normalized)) {
            return substr($body, 0, 256);
        }

        return trim(substr($normalized, 0, 1024));
    }

    /**
     * Decide whether one candidate response proves the path exists.
     *
     * @param  array<string, array<int, string>>  $headers
     * @param  array{status: int, signature: string}|null  $baseline
     * @return array{kind: string, severity: string, category: string, title: string, description: string, evidence: string}|null
     */
    private function directoryFinding(string $path, int $status, array $headers, string $body, ?array $baseline, string $homepage): ?array
    {
        $restricted = in_array($status, [401, 403], true);

        // 404/410 are misses, 3xx are redirect noise, 5xx is the server's own
        // problem. Only an authorization challenge is interesting.
        if (! $restricted && ($status < 200 || $status >= 400)) {
            return null;
        }

        $lowerHeaders = array_change_key_case($headers, CASE_LOWER);

        // A mid-scan challenge is the edge talking, not the application.
        if ($this->fingerprint->isChallenged(null, $lowerHeaders, $body, $status)) {
            return null;
        }

        $signature = $this->responseSignature($body);

        // The application's catch-all answered: a single-page-app router
        // serves the homepage for every path, and a soft 404 serves the same
        // page as the random control path. Neither is a hit.
        if ($signature !== '' && $signature === $homepage) {
            return null;
        }

        if ($signature !== '' && $baseline !== null && $signature === $baseline['signature']) {
            return null;
        }

        if ($restricted) {
            return [
                'kind' => 'vulnerability',
                'severity' => 'low',
                'category' => 'misconfiguration',
                'title' => 'Restricted administrative or backup path is reachable',
                'description' => 'The path answers with an authorization challenge rather than a not-found response, which discloses that a protected interface or file exists at this location. No content was read.',
                'evidence' => 'GET '.$path.' returned HTTP '.$status.' while a random control path returned HTTP '.($baseline['status'] ?? 0).'; response bodies differ.',
            ];
        }

        $contentType = strtolower(trim(implode(' ', (array) ($lowerHeaders['content-type'] ?? []))));
        $needle = $this->contentNeedle($path, $body);
        $marker = $this->panelMarker($path, $body);

        // A download is only proof when the path names a file and the server
        // actually streamed something other than an HTML page.
        $servedAsFile = preg_match('/\.[a-z0-9]{2,6}$/i', $path) === 1
            && $contentType !== ''
            && ! str_contains($contentType, 'text/html')
            && stripos($body, '<html') === false;

        if ($needle === null && $marker === null && ! $servedAsFile) {
            return null;
        }

        $proof = $needle ?? $marker ?? ('served as '.$contentType);
        $severity = $this->fileSeverity($path, $needle !== null || $servedAsFile);
        $category = $needle !== null || $servedAsFile ? 'exposure' : 'misconfiguration';

        return [
            'kind' => 'vulnerability',
            'severity' => $severity,
            'category' => $category,
            'title' => $category === 'exposure'
                ? 'Sensitive file or backup reachable without authentication'
                : 'Administrative or diagnostic interface accessible without authentication',
            'description' => $category === 'exposure'
                ? 'A file that should not be public is served directly: configuration, source, database, or backup content that commonly carries credentials and full application data.'
                : 'An administrative, diagnostic, or monitoring interface answers without an authentication challenge, exposing operational detail and sometimes state-changing actions.',
            'evidence' => 'GET '.$path.' returned HTTP '.$status.' ('.$contentType.') and '.$proof.'; a random control path returned HTTP '.($baseline['status'] ?? 0).'.',
        ];
    }

    /**
     * Body or magic-byte evidence that a path returned the file it names
     * instead of an HTML error page.
     */
    private function contentNeedle(string $path, string $body): ?string
    {
        $rules = [
            ['#^/\.env#', ['APP_KEY=', 'DB_PASSWORD=', 'DB_HOST=']],
            ['#^/\.git/#', ['ref: refs/', 'repositoryformatversion', '[core]']],
            ['#^/\.svn/#', ['svn://', 'dir']],
            ['#^/composer\.(json|lock)$#', ['"require"', '"packages"', '"content-hash"']],
            ['#^/package\.json$#', ['"dependencies"', '"devDependencies"']],
            ['#^/phpinfo\.php$#', ['phpinfo()', 'PHP Version']],
            ['#^/server-status$#', ['Apache Server Status', 'Server uptime']],
            ['#^/actuator#', ['"activeProfiles"', '"propertySources"', '"status":"UP"', '"status": "UP"']],
            ['#^/\.DS_Store$#', ["\x00\x00\x00\x01Bud1"]],
        ];

        foreach ($rules as [$pattern, $signatures]) {
            if (preg_match($pattern, $path) !== 1) {
                continue;
            }

            foreach ($signatures as $signature) {
                if (str_contains($body, $signature)) {
                    return 'the body contains the file signature "'.trim($signature).'"';
                }
            }
        }

        if (str_ends_with($path, '.zip') && str_starts_with($body, "PK\x03\x04")) {
            return 'the body starts with the ZIP magic bytes';
        }

        if ((str_ends_with($path, '.tar.gz') || str_ends_with($path, '.tgz') || str_ends_with($path, '.sql.gz'))
            && str_starts_with($body, "\x1f\x8b")) {
            return 'the body starts with the gzip magic bytes';
        }

        if (str_ends_with($path, '.sql') && preg_match('/^(?:--|CREATE TABLE|INSERT INTO|DROP TABLE)/mi', $body) === 1) {
            return 'the body contains SQL dump statements';
        }

        if (str_ends_with($path, '.bak') && str_contains($body, '<?php')) {
            return 'the body contains PHP source code';
        }

        return null;
    }

    /**
     * Directory listings and known administrative/diagnostic interfaces,
     * which are HTML and therefore cannot be proven by content type alone.
     */
    private function panelMarker(string $path, string $body): ?string
    {
        if (stripos($body, '<title>Index of /') !== false || stripos($body, 'Directory listing for /') !== false) {
            return 'the body renders a directory index listing';
        }

        $panels = [
            '/phpmyadmin' => ['phpMyAdmin', 'pma_username'],
            '/pma' => ['phpMyAdmin'],
            '/adminer' => ['Adminer'],
            '/wp-admin' => ['wp-admin', 'wp-login'],
            '/wp-login.php' => ['wp-submit', 'user_login'],
            '/administrator' => ['Joomla!', 'com_login'],
            '/jenkins' => ['Jenkins'],
            '/grafana' => ['Grafana'],
            '/kibana' => ['kibana'],
            '/swagger-ui' => ['swagger-ui'],
            '/api-docs' => ['openapi', 'swagger'],
            '/console' => ['Web Console', 'console'],
            '/.git/config' => ['[core]'],
        ];

        foreach ($panels as $candidate => $signatures) {
            if (! str_starts_with($path, $candidate)) {
                continue;
            }

            foreach ($signatures as $signature) {
                if (stripos($body, $signature) !== false) {
                    return 'the body contains the interface marker "'.$signature.'"';
                }
            }
        }

        return null;
    }

    /**
     * Severity by what the path itself implies. Configuration, source,
     * database, and archive files hand over credentials or the full dataset.
     */
    private function fileSeverity(string $path, bool $isFile): string
    {
        $critical = [
            '#^/\.env#i',
            '#^/\.git/#i',
            '#^/\.svn/#i',
            '#^/\.DS_Store$#i',
            '#\.(?:zip|tar|tgz|tar\.gz|sql|sql\.gz|bak|old|backup|7z|rar)$#i',
            '#(?:^|/)(?:backup|dump|db)#i',
            '#composer\.(?:json|lock)$#i',
            '#\.(?:pem|key|pfx|p12)$#i',
        ];

        foreach ($critical as $pattern) {
            if (preg_match($pattern, $path) === 1) {
                return 'critical';
            }
        }

        return $isFile ? 'high' : 'medium';
    }

    /** @return array{ip: string, status: int, headers: array, body: string, truncated: bool, blocked: bool, waf: array|null, via_origin: bool} */
    public function demoCapture(): array
    {
        return [
            'ip' => '192.0.2.10',
            'status' => 200,
            'headers' => ['content-type' => ['text/html'], 'server' => ['nginx/1.24.0']],
            'body' => '<html><head><title>GardaCyber training fixture</title></head><body><h1>Hacked by training-team</h1><p>slot gacor jackpot maxwin</p></body></html>',
            'truncated' => false,
            'blocked' => false,
            'waf' => null,
            'via_origin' => false,
        ];
    }

    /** @return array<int, array{kind: string, severity: string, category: string, title: string, description: string, evidence: string}> */
    public function analyze(array $capture, string $type): array
    {
        $findings = [];
        $headers = $capture['headers'];
        $viaBrowser = ! empty($capture['via_browser']);

        // A browser-cleared capture carries the rendered DOM, not the edge's
        // origin response, so its header bag is synthetic. Judging security
        // headers from it would report every header as missing.
        $isHtml = $viaBrowser
            || str_contains(strtolower(implode(' ', $headers['content-type'] ?? [])), 'text/html');

        if ($type !== 'incident' && ! $viaBrowser && empty($capture['blocked']) && $capture['status'] >= 200 && $capture['status'] < 300 && $isHtml) {
            foreach ([
                'strict-transport-security' => ['medium', 'HSTS header missing', 'Enable Strict-Transport-Security after verifying HTTPS across your site.'],
                'content-security-policy' => ['low', 'Content Security Policy missing', 'Deploy a tested Content-Security-Policy to reduce browser injection impact.'],
                'x-content-type-options' => ['low', 'MIME sniffing protection missing', 'Set X-Content-Type-Options: nosniff.'],
                'x-frame-options' => ['medium', 'Clickjacking protection missing (X-Frame-Options)', 'Set X-Frame-Options: SAMEORIGIN (or a frame-ancestors CSP) to prevent UI redress / clickjacking attacks.'],
                'referrer-policy' => ['low', 'Referrer policy missing', 'Set a Referrer-Policy appropriate for the application.'],
            ] as $header => [$severity, $title, $description]) {
                if (empty($headers[$header])) {
                    $findings[] = compact('severity', 'title', 'description') + ['kind' => 'vulnerability', 'category' => 'security-headers', 'evidence' => 'HTTPS / returned '.$capture['status'].'; response header '.$header.' absent. Configuration observation, not proof of exploitation.'];
                }
            }
        }

        // A challenge page is the edge's copy, not the application's, so its
        // text says nothing about whether the site was defaced or injected.
        if ($type !== 'vulnerability' && empty($capture['blocked']) && $isHtml) {
            $spaced = preg_replace('/>\s*</', '> <', $capture['body']);
            $text = html_entity_decode(strip_tags($spaced ?? $capture['body']), ENT_QUOTES | ENT_HTML5, 'UTF-8');

            foreach ([
                'defacement' => ['/\b(?:hacked|defaced)\s+by\s+[\w-]+/i', 'critical', 'Confirmed website defacement signature'],
                'gambling-injection' => ['/\b(?:slot\s+gacor|maxwin|judi\s+online|zeus\s+slot|slot88|pragmatic\s+play)\b/i', 'high', 'Online gambling content injection detected'],
                'seo-spam' => ['/\b(?:buy\s+viagra|cheap\s+cialis|replica\s+watches)\b/i', 'medium', 'SEO spam keyword injection detected'],
            ] as $category => [$pattern, $severity, $title]) {
                if (preg_match($pattern, $text, $matches)) {
                    $findings[] = ['kind' => 'incident', 'severity' => $severity, 'category' => $category, 'title' => $title, 'description' => 'Heuristic content match on live page text. Indicates likely active site compromise.', 'evidence' => 'Matched visible text: '.mb_substr($matches[0], 0, 160).'. Body SHA-256: '.hash('sha256', $capture['body'])];
                }
            }
        }

        return $findings;
    }
}
