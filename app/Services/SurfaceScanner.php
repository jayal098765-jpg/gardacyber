<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

class SurfaceScanner
{
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

    /** @return array{ip: string, status: int, headers: array, body: string, truncated: bool} */
    public function capture(string $hostname): array
    {
        $this->assertAllowed($hostname);
        $ip = $this->resolve($hostname);
        $pinnedIp = str_contains($ip, ':') ? '['.$ip.']' : $ip;

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

    private function fetchUrl(string $url, string $hostname, string $pinnedIp, int $port, string $ip): array
    {
        $response = Http::connectTimeout(5)->timeout(12)
            ->withUserAgent('GardaCyber/1.0 (authorized perimeter assessment; +https://gardacyber.test)')
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
     * Non-destructive probes for web vulnerabilities & sensitive exposures.
     *
     * @return array<int, array{kind: string, severity: string, category: string, title: string, description: string, evidence: string}>
     */
    public function probeActiveVulnerabilities(string $hostname, string $ip): array
    {
        $findings = [];
        $pinnedIp = str_contains($ip, ':') ? '['.$ip.']' : $ip;
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
                $resp = Http::connectTimeout(2)->timeout(4)
                    ->withUserAgent('GardaCyber/1.0')
                    ->withOptions([
                        'allow_redirects' => false,
                        'verify' => false,
                        'curl' => [CURLOPT_RESOLVE => [$hostname.':443:'.$pinnedIp]],
                    ])->get($baseUrl.$path);

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

        // 2. Reflected XSS Parameter Probe (Non-executing tag reflection)
        try {
            $xssMarker = 'garda_probe_'.substr(md5($hostname), 0, 6);
            $resp = Http::connectTimeout(2)->timeout(4)
                ->withUserAgent('GardaCyber/1.0')
                ->withOptions(['allow_redirects' => false, 'verify' => false, 'curl' => [CURLOPT_RESOLVE => [$hostname.':443:'.$pinnedIp]]])
                ->get($baseUrl.'/?q=%22%3E%3C'.$xssMarker.'%3E');

            if ($resp->status() === 200 && str_contains($resp->body(), '<'.$xssMarker.'>')) {
                $findings[] = [
                    'kind' => 'vulnerability',
                    'severity' => 'high',
                    'category' => 'cve',
                    'title' => 'Reflected parameter injection (Potential XSS)',
                    'description' => 'User-supplied query parameters are reflected unencoded into HTML responses without context-aware sanitization.',
                    'evidence' => 'Probe tag <'.$xssMarker.'> reflected verbatim in response body without HTML entity encoding.',
                ];
            }
        } catch (\Throwable) {
        }

        // 3. SQL Error Disclosure Probe
        try {
            $resp = Http::connectTimeout(2)->timeout(4)
                ->withUserAgent('GardaCyber/1.0')
                ->withOptions(['allow_redirects' => false, 'verify' => false, 'curl' => [CURLOPT_RESOLVE => [$hostname.':443:'.$pinnedIp]]])
                ->get($baseUrl.'/?id=1%27%22');

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

    /** @return array{ip: string, status: int, headers: array, body: string, truncated: bool} */
    public function demoCapture(): array
    {
        return [
            'ip' => '192.0.2.10',
            'status' => 200,
            'headers' => ['content-type' => ['text/html'], 'server' => ['nginx/1.24.0']],
            'body' => '<html><head><title>GardaCyber training fixture</title></head><body><h1>Hacked by training-team</h1><p>slot gacor jackpot maxwin</p></body></html>',
            'truncated' => false,
        ];
    }

    /** @return array<int, array{kind: string, severity: string, category: string, title: string, description: string, evidence: string}> */
    public function analyze(array $capture, string $type): array
    {
        $findings = [];
        $headers = $capture['headers'];
        $isHtml = str_contains(strtolower(implode(' ', $headers['content-type'] ?? [])), 'text/html');

        if ($type !== 'incident' && $capture['status'] >= 200 && $capture['status'] < 300 && $isHtml) {
            foreach ([
                'strict-transport-security' => ['medium', 'HSTS header missing', 'Enable Strict-Transport-Security after verifying HTTPS across your site.'],
                'content-security-policy' => ['low', 'Content Security Policy missing', 'Deploy a tested Content-Security-Policy to reduce browser injection impact.'],
                'x-content-type-options' => ['low', 'MIME sniffing protection missing', 'Set X-Content-Type-Options: nosniff.'],
                'referrer-policy' => ['low', 'Referrer policy missing', 'Set a Referrer-Policy appropriate for the application.'],
            ] as $header => [$severity, $title, $description]) {
                if (empty($headers[$header])) {
                    $findings[] = compact('severity', 'title', 'description') + ['kind' => 'vulnerability', 'category' => 'security-headers', 'evidence' => 'HTTPS / returned '.$capture['status'].'; response header '.$header.' absent. Configuration observation, not proof of exploitation.'];
                }
            }
        }

        if ($type !== 'vulnerability' && $isHtml) {
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
