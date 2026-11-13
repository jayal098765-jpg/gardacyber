<?php

namespace App\Services;

use App\Models\Cve;
use Illuminate\Support\Collection;

class CveMatcher
{
    /**
     * Banner product tokens mapped to (vendor, product, display label).
     *
     * @var array<string, array{0: string, 1: string, 2: string}>
     */
    private const PRODUCT_ALIASES = [
        'nginx' => ['nginx', 'nginx', 'Nginx'],
        'openresty' => ['openresty', 'openresty', 'OpenResty'],
        'apache' => ['apache', 'http_server', 'Apache HTTP Server'],
        'microsoft-iis' => ['microsoft', 'iis', 'Microsoft IIS'],
        'iis' => ['microsoft', 'iis', 'Microsoft IIS'],
        'tomcat' => ['apache', 'tomcat', 'Apache Tomcat'],
        'caddy' => ['caddy', 'caddy', 'Caddy'],
        'php' => ['php', 'php', 'PHP'],
        'gunicorn' => ['gunicorn', 'gunicorn', 'Gunicorn'],
        'lighttpd' => ['lighttpd', 'lighttpd', 'LiteSpeed'],
        'cloudflare' => ['cloudflare', 'cloudflare', 'Cloudflare'],
    ];

    /**
     * Parse a raw server banner into a tech fingerprint.
     *
     * @return array{vendor: string, product: string, name: string, version: string|null}|null
     */
    public function fingerprintFromBanner(string $banner): ?array
    {
        $banner = trim($banner);

        if ($banner === '') {
            return null;
        }

        $parsed = null;

        foreach (self::PRODUCT_ALIASES as $token => [$vendor, $product, $name]) {
            if (str_starts_with(strtolower($banner), $token)) {
                $parsed = compact('vendor', 'product', 'name');
                break;
            }
        }

        if ($parsed === null) {
            return null;
        }

        preg_match('/(\d+\.\d+(?:\.\d+)*)/', $banner, $version);

        $parsed['version'] = $version[1] ?? null;

        return $parsed;
    }

    /**
     * Best matching CVE rows for a fingerprint, limited by severity and
     * optionally restricted to exploited CVEs when no version is known.
     *
     * @param  array{vendor: string, product: string, version: string|null}  $fingerprint
     * @return Collection<int, Cve>
     */
    public function matchesForFingerprint(array $fingerprint): Collection
    {
        $query = Cve::query()->ofProduct($fingerprint['vendor'], $fingerprint['product']);

        if ($fingerprint['version'] === null) {
            $query->exploited();
        }

        $candidates = $query->orderByDesc('cvss_score')->limit(200)->get();

        return $candidates->filter(
            fn (Cve $cve): bool => $fingerprint['version'] === null
                || $cve->version_ranges === []
                || $this->versionInAnyRange($fingerprint['version'], $cve->version_ranges)
        )->values();
    }

    /**
     * Check a version against a list of NVD version ranges.
     *
     * @param  array<int, array<string, mixed>>  $ranges
     */
    public function versionInAnyRange(string $version, array $ranges): bool
    {
        if ($version === '') {
            return false;
        }

        foreach ($ranges as $range) {
            if ($this->versionInRange($version, $range)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check a version against a single NVD bound set.
     *
     * @param  array<string, mixed>  $range
     */
    public function versionInRange(string $version, array $range): bool
    {
        $ok = true;

        if (isset($range['versionStartIncluding']) && $range['versionStartIncluding'] !== '') {
            $ok = $ok && version_compare($version, (string) $range['versionStartIncluding'], '>=');
        }

        if ($ok && isset($range['versionStartExcluding']) && $range['versionStartExcluding'] !== '') {
            $ok = version_compare($version, (string) $range['versionStartExcluding'], '>');
        }

        if ($ok && isset($range['versionEndIncluding']) && $range['versionEndIncluding'] !== '') {
            $ok = version_compare($version, (string) $range['versionEndIncluding'], '<=');
        }

        if ($ok && isset($range['versionEndExcluding']) && $range['versionEndExcluding'] !== '') {
            $ok = version_compare($version, (string) $range['versionEndExcluding'], '<');
        }

        return $ok;
    }

    /**
     * Generate CVE-based finding arrays for a capture's server banners.
     *
     * @param  array<string, mixed>  $capture
     * @return array<int, array<string, mixed>>
     */
    public function buildFindingsForCapture(array $capture): array
    {
        $banners = [];

        foreach (['server', 'x-powered-by'] as $header) {
            foreach ($capture['headers'][$header] ?? [] as $value) {
                if (is_string($value)) {
                    $banners[] = $value;
                }
            }
        }

        $findings = [];
        $seen = [];

        foreach (array_unique($banners) as $banner) {
            $fingerprint = $this->fingerprintFromBanner($banner);

            if ($fingerprint === null) {
                continue;
            }

            $key = $fingerprint['vendor'].'/'.$fingerprint['product'].'/'.($fingerprint['version'] ?? '*');

            if (isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;

            $matches = $this->matchesForFingerprint($fingerprint);

            if ($matches->isEmpty()) {
                continue;
            }

            $findings[] = $this->buildFinding($fingerprint, $matches);
        }

        return $findings;
    }

    /**
     * @param  array{vendor: string, product: string, name: string, version: string|null}  $fingerprint
     * @param  Collection<int, Cve>  $matches
     * @return array<string, mixed>
     */
    private function buildFinding(array $fingerprint, Collection $matches): array
    {
        $sorted = $matches->sortByDesc(fn (Cve $cve): float => $cve->cvss_score ?? 0);

        $top = $sorted->take((int) config('cve.max_cves_per_finding', 25));
        $severity = $top->first()?->severity ?? 'medium';
        $count = $sorted->count();

        $titles = $top->map(fn (Cve $cve): string => sprintf(
            '%s (%s%s)',
            $cve->cve_id,
            $cve->severity,
            $cve->exploited ? ', exploited' : ''
        ))->values()->all();

        $evidence = 'CVE matches for '.$fingerprint['name']
            .($fingerprint['version'] !== null ? ' '.$fingerprint['version'] : '')
            .': '.implode(', ', $titles);

        $title = $fingerprint['name']
            .($fingerprint['version'] !== null ? ' '.$fingerprint['version'] : '')
            .' matches '.$count.' known CVE'.($count === 1 ? '' : 's');

        return [
            'kind' => 'vulnerability',
            'category' => 'cve',
            'severity' => $severity,
            'title' => $title,
            'description' => 'The detected product version falls inside the affected range of one or more known vulnerabilities (NVD). Exploited CVEs are those confirmed in the CISA KEV catalog.',
            'evidence' => $evidence,
            'cve_id' => $top->first()?->cve_id,
            'owasp' => 'A06:2025',
        ];
    }
}
