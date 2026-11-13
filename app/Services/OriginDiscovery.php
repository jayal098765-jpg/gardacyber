<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

/**
 * Finds the real origin server behind a CDN/WAF by inspecting the public DNS
 * footprint of the domain: SPF mechanisms, MX hosts, subdomains whose A records
 * still point straight at the origin instead of the edge, and hostnames the
 * domain has published certificates for.
 *
 * This is passive analysis of the domain being assessed. Nothing is sent to the
 * provider's control plane and no account is touched.
 */
class OriginDiscovery
{
    /**
     * Subdomain labels that commonly expose the origin because they were
     * never proxied through the CDN (mail, file transfer, admin, legacy).
     *
     * @var array<int, string>
     */
    public const ORIGIN_PREFIXES = [
        'direct', 'origin', 'backend', 'web', 'www2', 'old', 'legacy',
        'mail', 'smtp', 'imap', 'pop', 'webmail', 'autodiscover', 'exchange',
        'ftp', 'sftp', 'files', 'cpanel', 'whm', 'plesk',
        'vpn', 'remote', 'rdp', 'ssh', 'git', 'jenkins', 'ci',
        'dev', 'staging', 'test', 'beta', 'uat', 'preprod', 'qa',
        'api-origin', 'gw', 'gateway', 'lb', 'internal', 'ns1', 'ns2',
    ];

    public function __construct(private CdnFingerprint $fingerprint = new CdnFingerprint) {}

    /**
     * Collect origin candidates for a root domain.
     *
     * Work is bounded twice: $limit caps how many usable candidates are
     * returned, and $deadline (a microtime value) stops further DNS lookups so
     * a slow resolver cannot outlive the caller's scan budget.
     *
     * @return array<int, array{ip: string, host: string, source: string}>
     */
    public function candidates(string $rootDomain, int $limit = 8, ?float $deadline = null): array
    {
        $limit = max(1, $limit);

        $found = [
            ...$this->fromSpf($rootDomain),
            ...$this->fromMx($rootDomain, $limit, $deadline),
            ...$this->fromSubdomains($rootDomain, $limit, $deadline),
            ...$this->fromCertificateTransparency($rootDomain, $limit, $deadline),
        ];

        $unique = [];
        $seen = [];

        foreach ($found as $candidate) {
            $key = $candidate['ip'];

            if (isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;
            $unique[] = $candidate;

            if (count($unique) >= $limit) {
                break;
            }
        }

        return $unique;
    }

    /**
     * IPs published inside the domain's SPF policy. Operators frequently
     * allowlist the origin's public address there.
     *
     * @return array<int, array{ip: string, host: string, source: string}>
     */
    public function fromSpf(string $rootDomain): array
    {
        $candidates = [];

        foreach ($this->txtRecords($rootDomain) as $txt) {
            if (stripos($txt, 'v=spf1') === false) {
                continue;
            }

            if (preg_match_all('/ip4:([0-9.]+)/i', $txt, $v4)) {
                foreach ($v4[1] as $ip) {
                    if ($this->isUsableOrigin($ip)) {
                        $candidates[] = ['ip' => $ip, 'host' => $rootDomain, 'source' => 'SPF ip4 mechanism'];
                    }
                }
            }

            if (preg_match_all('/ip6:([0-9a-f:]+)/i', $txt, $v6)) {
                foreach ($v6[1] as $ip) {
                    if ($this->isUsableOrigin($ip)) {
                        $candidates[] = ['ip' => $ip, 'host' => $rootDomain, 'source' => 'SPF ip6 mechanism'];
                    }
                }
            }
        }

        return $candidates;
    }

    /**
     * Mail exchangers usually sit on the same network as the origin and are
     * rarely behind the CDN.
     *
     * @return array<int, array{ip: string, host: string, source: string}>
     */
    public function fromMx(string $rootDomain, int $limit = 8, ?float $deadline = null): array
    {
        $candidates = [];

        foreach ($this->mxHosts($rootDomain) as $host) {
            if ($this->exhausted($candidates, $limit, $deadline)) {
                break;
            }

            foreach ($this->resolveA($host) as $ip) {
                if ($this->isUsableOrigin($ip)) {
                    $candidates[] = ['ip' => $ip, 'host' => $host, 'source' => 'MX record host'];
                }
            }
        }

        return $candidates;
    }

    /**
     * Unproxied subdomains whose A record bypasses the CDN edge.
     *
     * @return array<int, array{ip: string, host: string, source: string}>
     */
    public function fromSubdomains(string $rootDomain, int $limit = 8, ?float $deadline = null): array
    {
        $candidates = [];

        foreach (self::ORIGIN_PREFIXES as $prefix) {
            if ($this->exhausted($candidates, $limit, $deadline)) {
                break;
            }

            $host = $prefix.'.'.$rootDomain;

            foreach ($this->resolveA($host) as $ip) {
                if ($this->isUsableOrigin($ip)) {
                    $candidates[] = ['ip' => $ip, 'host' => $host, 'source' => 'unproxied subdomain '.$prefix];
                }
            }
        }

        return $candidates;
    }

    /**
     * Hostnames the domain has obtained certificates for, taken from the public
     * certificate transparency log. Certs are often issued for internal or
     * legacy names (`direct.`, `legacy.`, `dev.`) that were never proxied by
     * the CDN, and those still resolve straight at the origin.
     *
     * @return array<int, array{ip: string, host: string, source: string}>
     */
    public function fromCertificateTransparency(string $rootDomain, int $limit = 8, ?float $deadline = null): array
    {
        if (! (bool) config('scanner.crt_sh', true)) {
            return [];
        }

        $names = $this->crtShNames($rootDomain, max(1, (int) config('scanner.crt_sh_budget', 30)));

        $candidates = [];

        foreach ($names as $host) {
            if ($this->exhausted($candidates, $limit, $deadline)) {
                break;
            }

            foreach ($this->resolveA($host) as $ip) {
                if ($this->isUsableOrigin($ip)) {
                    $candidates[] = ['ip' => $ip, 'host' => $host, 'source' => 'certificate transparency host'];
                }
            }
        }

        return $candidates;
    }

    /**
     * Ask crt.sh which names sit under the domain. Origin-looking labels are
     * inspected first because the edge almost never fronts them.
     *
     * @return array<int, string>
     */
    protected function crtShNames(string $rootDomain, int $limit = 30): array
    {
        try {
            $response = Http::connectTimeout(3)
                ->timeout(6)
                ->withUserAgent('GardaCyber/1.0 (+passive certificate transparency lookup)')
                ->get('https://crt.sh/', ['q' => '%.'.$rootDomain, 'output' => 'json']);
        } catch (\Throwable) {
            return [];
        }

        if (! $response->successful()) {
            return [];
        }

        $entries = $response->json();

        if (! is_array($entries)) {
            return [];
        }

        $suffix = '.'.strtolower(trim($rootDomain, '.'));
        $names = [];

        foreach ($entries as $entry) {
            $published = is_array($entry) ? ($entry['name_value'] ?? $entry['common_name'] ?? null) : null;

            if (! is_string($published)) {
                continue;
            }

            foreach (preg_split('/\r\n|\n|\r/', $published) ?: [] as $name) {
                $name = strtolower(trim($name));
                $name = preg_replace('/^\*\./', '', $name) ?? $name;
                $name = trim($name, '.');

                if ($name === '' || ! str_ends_with($name, $suffix) || str_contains($name, '*')) {
                    continue;
                }

                $names[$name] = true;
            }
        }

        $ordered = array_keys($names);

        usort($ordered, fn (string $a, string $b): int => $this->originRank($b) <=> $this->originRank($a));

        return array_slice($ordered, 0, max(1, $limit));
    }

    /**
     * Rank a hostname by whether its leftmost label is a known origin prefix.
     */
    private function originRank(string $host): int
    {
        $label = strstr($host, '.', true) ?: $host;

        return in_array($label, self::ORIGIN_PREFIXES, true) ? 1 : 0;
    }

    /**
     * @param  array<int, array{ip: string, host: string, source: string}>  $candidates
     */
    private function exhausted(array $candidates, int $limit, ?float $deadline): bool
    {
        if (count($candidates) >= max(1, $limit)) {
            return true;
        }

        return $deadline !== null && microtime(true) >= $deadline;
    }

    /**
     * An origin candidate must be public and outside the CDN ranges, otherwise
     * it is just another edge node.
     */
    public function isUsableOrigin(string $ip): bool
    {
        if (! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            return false;
        }

        $lowered = strtolower($ip);

        if (str_starts_with($lowered, '::ffff:') || str_starts_with($ip, '127.') || str_starts_with($ip, '169.254.')) {
            return false;
        }

        return ! $this->fingerprint->isCloudflareIp($ip);
    }

    /**
     * @return array<int, string>
     */
    protected function txtRecords(string $hostname): array
    {
        $records = @dns_get_record($hostname, DNS_TXT);
        $values = [];

        foreach ($records ?: [] as $record) {
            foreach (['txt', 'entries'] as $key) {
                if (! empty($record[$key])) {
                    $values[] = is_array($record[$key]) ? implode('', $record[$key]) : (string) $record[$key];
                }
            }
        }

        return array_values(array_unique($values));
    }

    /**
     * @return array<int, string>
     */
    protected function mxHosts(string $hostname): array
    {
        $records = @dns_get_record($hostname, DNS_MX);

        return array_values(array_filter(array_map(
            fn (array $record): string => rtrim((string) ($record['target'] ?? ''), '.'),
            $records ?: []
        )));
    }

    /**
     * @return array<int, string>
     */
    protected function resolveA(string $hostname): array
    {
        $records = @dns_get_record($hostname, DNS_A | DNS_AAAA);

        return array_values(array_filter(array_map(
            fn (array $record): ?string => $record['ip'] ?? $record['ipv6'] ?? null,
            $records ?: []
        )));
    }
}
