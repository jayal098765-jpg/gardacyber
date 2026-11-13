<?php

namespace App\Services;

/**
 * Finds the real origin server behind a CDN/WAF by inspecting the public DNS
 * footprint of the domain: SPF mechanisms, MX hosts, and subdomains whose A
 * records still point straight at the origin instead of the edge.
 *
 * This is passive DNS analysis of the domain being assessed. Nothing is sent
 * to the provider's control plane and no account is touched.
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
