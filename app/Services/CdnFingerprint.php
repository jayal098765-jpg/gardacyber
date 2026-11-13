<?php

namespace App\Services;

/**
 * Detects CDN / WAF providers from response headers and page markers, and
 * distinguishes a normal edge response from an interactive challenge.
 *
 * Detection only: nothing here attempts to solve a challenge or CAPTCHA.
 * When a target is challenged, the caller should pivot to origin discovery
 * (see OriginDiscovery) instead of retrying the edge.
 */
class CdnFingerprint
{
    /**
     * Official Cloudflare edge ranges (https://www.cloudflare.com/ips/).
     *
     * @var array<int, string>
     */
    public const CLOUDFLARE_RANGES = [
        '173.245.48.0/20',
        '103.21.244.0/22',
        '103.22.200.0/22',
        '103.31.4.0/22',
        '141.101.64.0/18',
        '108.162.192.0/18',
        '190.93.240.0/20',
        '188.114.96.0/20',
        '197.234.240.0/22',
        '198.41.128.0/17',
        '162.158.0.0/15',
        '104.16.0.0/13',
        '104.24.0.0/14',
        '172.64.0.0/13',
        '131.0.72.0/22',
        '2400:cb00::/32',
        '2606:4700::/32',
        '2803:f800::/32',
        '2405:b500::/32',
        '2405:8100::/32',
        '2a06:98c0::/29',
        '2c0f:f248::/32',
    ];

    /**
     * Header signatures per provider. First entry is the display name, the
     * rest are header names whose presence indicates the provider.
     *
     * @var array<string, array<int, string>>
     */
    private const HEADER_SIGNATURES = [
        'cloudflare' => ['cf-ray', 'cf-cache-status', 'cf-mitigated', 'cf-apo-via'],
        'akamai' => ['akamai-grn', 'akamai-request-id', 'x-akamai-transformed', 'x-akamai-request-id'],
        'imperva' => ['x-iinfo', 'x-cdn'],
        'sucuri' => ['x-sucuri-id', 'x-sucuri-cache'],
        'cloudfront' => ['x-amz-cf-id', 'x-amz-cf-pop'],
        'fastly' => ['x-fastly-request-id', 'x-served-by', 'fastly-debug-digest'],
        'azure-frontdoor' => ['x-azure-ref', 'x-fd-int'],
        'stackpath' => ['x-sp-cache', 'x-sp-request-id'],
    ];

    /**
     * Body markers identifying an active WAF challenge / block page.
     *
     * @var array<string, array<int, string>>
     */
    private const CHALLENGE_MARKERS = [
        'cloudflare' => [
            'Just a moment...',
            'Enable JavaScript and cookies to continue',
            'Attention Required! | Cloudflare',
            'cf_chl_opt',
            'challenge-platform',
            'cf-challenge',
            'Checking your browser before accessing',
        ],
        'imperva' => [
            '_Incapsula_Resource',
            'Incapsula incident ID',
            'Request unsuccessful. Incapsula incident ID',
        ],
        'akamai' => [
            'Reference&#32;&#35;',
            'Access Denied',
        ],
        'sucuri' => [
            'Sucuri WebSite Firewall - Access Denied',
            'Sucuri Website Firewall',
        ],
    ];

    /**
     * @param  array<string, array<int, string>>  $headers  Lowercased header map.
     * @return array{provider: string, signals: array<int, string>, challenged: bool}|null
     */
    public function detect(array $headers, string $body = '', int $status = 0): ?array
    {
        $signals = [];
        $provider = $this->providerFromHeaders($headers, $signals);

        $challenged = $this->isChallenged($provider, $headers, $body, $status);

        if ($provider === null && ! $challenged) {
            return null;
        }

        return [
            'provider' => $provider ?? 'unknown',
            'signals' => $signals,
            'challenged' => $challenged,
        ];
    }

    /**
     * True when the response is a WAF block / interstitial rather than the
     * application itself.
     */
    public function isChallenged(?string $provider, array $headers, string $body = '', int $status = 0): bool
    {
        if (trim((string) ($headers['cf-mitigated'][0] ?? '')) !== '') {
            return true;
        }

        $blockStatus = in_array($status, [403, 429, 503], true);

        foreach (self::CHALLENGE_MARKERS as $markers) {
            foreach ($markers as $marker) {
                if ($body !== '' && stripos($body, $marker) !== false) {
                    return true;
                }
            }
        }

        // A 403/503 from a known CDN with a tiny body is almost always the
        // edge blocking us rather than the origin's own error page.
        return $provider !== null && $blockStatus && strlen($body) < 20000;
    }

    /**
     * True when the IP belongs to the Cloudflare edge.
     */
    public function isCloudflareIp(string $ip): bool
    {
        return $this->ipInAnyCidr($ip, self::CLOUDFLARE_RANGES);
    }

    /**
     * @param  array<int, string>  $cidrs
     */
    public function ipInAnyCidr(string $ip, array $cidrs): bool
    {
        foreach ($cidrs as $cidr) {
            if ($this->ipInCidr($ip, $cidr)) {
                return true;
            }
        }

        return false;
    }

    public function ipInCidr(string $ip, string $cidr): bool
    {
        [$subnet] = explode('/', $cidr, 2);
        $bits = (int) (explode('/', $cidr, 2)[1] ?? -1);

        if ($bits < 0) {
            return false;
        }

        $ipBin = @inet_pton($ip);
        $netBin = @inet_pton($subnet);

        if ($ipBin === false || $netBin === false || strlen($ipBin) !== strlen($netBin)) {
            return false;
        }

        $fullBytes = intdiv($bits, 8);
        $remainder = $bits % 8;

        if ($fullBytes > 0 && strncmp($ipBin, $netBin, $fullBytes) !== 0) {
            return false;
        }

        if ($remainder === 0) {
            return true;
        }

        $mask = (0xFF << (8 - $remainder)) & 0xFF;

        return (ord($ipBin[$fullBytes]) & $mask) === (ord($netBin[$fullBytes]) & $mask);
    }

    /**
     * @param  array<int, string>  $signals
     */
    private function providerFromHeaders(array $headers, array &$signals): ?string
    {
        foreach (self::HEADER_SIGNATURES as $provider => $names) {
            foreach ($names as $name) {
                if (array_key_exists($name, $headers)) {
                    $signals[] = 'response header: '.$name;

                    return $provider;
                }
            }
        }

        $server = strtolower(implode(' ', $headers['server'] ?? []));
        $via = strtolower(implode(' ', $headers['via'] ?? []));

        foreach ([
            'cloudflare' => 'cloudflare',
            'akamaighost' => 'akamai',
            'cloudfront' => 'cloudfront',
            'sucuri' => 'sucuri',
            'incapsula' => 'imperva',
            'bigip' => 'f5',
        ] as $needle => $provider) {
            if (str_contains($server, $needle)) {
                $signals[] = 'server header: '.$needle;

                return $provider;
            }
        }

        foreach (['cloudfront', 'fastly', 'cloudflare'] as $needle) {
            if (str_contains($via, $needle)) {
                $signals[] = 'via header: '.$needle;

                return $needle;
            }
        }

        return null;
    }
}
