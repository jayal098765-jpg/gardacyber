<?php

namespace App\Services;

use App\Models\Cve;

/**
 * Small curated set of well-known CVEs for common web-stack products.
 * Seeded at boot so scans produce CVE matches immediately, even before the
 * first full NVD sync finishes on a fresh deployment.
 */
class CveBaseline
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public static function entries(): array
    {
        return [
            self::entry('CVE-2021-23017', 'nginx', 'nginx', 'critical', 9.8, 'Nginx resolver off-by-one heap overflow allows this execution (0.6.18 to 1.20.0).', [
                ['versionStartIncluding' => '0.6.18', 'versionEndExcluding' => '1.20.1'],
            ]),
            self::entry('CVE-2022-41741', 'nginx', 'nginx', 'critical', 9.8, 'Memory corruption in nginx mp4 module (1.23.2 to 1.23.3).', [
                ['versionStartIncluding' => '1.23.2', 'versionEndIncluding' => '1.23.3'],
            ]),
            self::entry('CVE-2017-7529', 'nginx', 'nginx', 'medium', 5.3, 'Nginx range filter integer overflow leaks sensitive data (0.5.6 to 1.13.2).', [
                ['versionStartIncluding' => '0.5.6', 'versionEndExcluding' => '1.13.3'],
            ]),
            self::entry('CVE-2019-9511', 'nginx', 'nginx', 'high', 7.5, 'HTTP/2 data DDoS (window size exhaustion) in nginx 1.13.0 to 1.17.2.', [
                ['versionStartIncluding' => '1.13.0', 'versionEndExcluding' => '1.17.3'],
            ]),
            self::entry('CVE-2021-41773', 'apache', 'http_server', 'critical', 9.8, 'Apache HTTP Server path traversal and file disclosure in 2.4.49.', [
                ['versionStartIncluding' => '2.4.49', 'versionEndIncluding' => '2.4.49'],
            ]),
            self::entry('CVE-2021-42013', 'apache', 'http_server', 'critical', 9.8, 'Apache HTTP Server path traversal leading to RCE in 2.4.49 and 2.4.50.', [
                ['versionStartIncluding' => '2.4.49', 'versionEndIncluding' => '2.4.50'],
            ]),
            self::entry('CVE-2022-22720', 'apache', 'http_server', 'high', 8.8, 'Apache HTTP Server 2.4.0 to 2.4.51 request smuggling via chunked trailer.', [
                ['versionStartIncluding' => '2.4.0', 'versionEndExcluding' => '2.4.52'],
            ]),
            self::entry('CVE-2023-25690', 'apache', 'http_server', 'high', 9.8, 'Apache HTTP Server 2.4.0 to 2.4.55 HTTP request splitting/smuggling.', [
                ['versionStartIncluding' => '2.4.0', 'versionEndExcluding' => '2.4.56'],
            ]),
            self::entry('CVE-2021-31166', 'microsoft', 'iis', 'critical', 9.8, 'Microsoft HTTP.sys remote code execution affecting Windows 10/Server with IIS.', [
                ['versionStartIncluding' => '10.0', 'versionEndIncluding' => '10.0'],
            ]),
            self::entry('CVE-2017-7269', 'microsoft', 'iis', 'critical', 9.8, 'IIS 6.0 WebDAV buffer overflow allows remote code execution.', [
                ['versionStartIncluding' => '6.0', 'versionEndIncluding' => '6.0'],
            ]),
            self::entry('CVE-2024-4577', 'php', 'php', 'critical', 9.8, 'PHP Windows CGI argument injection remote code execution in 8.1, 8.2 and 8.3 (Windows).', [
                ['versionStartIncluding' => '8.1.0', 'versionEndExcluding' => '8.1.29'],
                ['versionStartIncluding' => '8.2.0', 'versionEndExcluding' => '8.2.20'],
                ['versionStartIncluding' => '8.3.0', 'versionEndExcluding' => '8.3.8'],
            ]),
            self::entry('CVE-2019-11043', 'php', 'php', 'critical', 9.8, 'PHP-FPM underflow enables RCE in PHP 7.1 to 7.3 with certain nginx configs.', [
                ['versionStartIncluding' => '7.1.0', 'versionEndExcluding' => '7.1.33'],
                ['versionStartIncluding' => '7.2.0', 'versionEndExcluding' => '7.2.24'],
                ['versionStartIncluding' => '7.3.0', 'versionEndExcluding' => '7.3.11'],
            ]),
            self::entry('CVE-2012-1823', 'php', 'php', 'critical', 9.8, 'PHP CGI argument injection allows remote code execution before 5.4.3/5.3.13.', [
                ['versionStartIncluding' => '5.0.0', 'versionEndExcluding' => '5.3.13'],
            ]),
            self::entry('CVE-2020-1938', 'apache', 'tomcat', 'critical', 9.8, 'Apache Tomcat Ghostcat: AJP file read/inclusion RCE in 9.0.0.M1 to 9.0.30.', [
                ['versionStartIncluding' => '9.0.0.M1', 'versionEndExcluding' => '9.0.31'],
            ]),
            self::entry('CVE-2017-12615', 'apache', 'tomcat', 'high', 8.8, 'Apache Tomcat PUT JSP upload RCE in 7.0.0 to 7.0.79.', [
                ['versionStartIncluding' => '7.0.0', 'versionEndExcluding' => '7.0.80'],
            ]),
            self::entry('CVE-2024-50379', 'apache', 'tomcat', 'critical', 9.8, 'Apache Tomcat race condition leading to RCE in 9.0.0.M1 to 9.0.98, 10.1.x to 10.1.34, 11.0.0-M1 to 11.0.2.', [
                ['versionStartIncluding' => '9.0.0.M1', 'versionEndExcluding' => '9.0.99'],
                ['versionStartIncluding' => '10.1.0', 'versionEndExcluding' => '10.1.35'],
                ['versionStartIncluding' => '11.0.0.M1', 'versionEndExcluding' => '11.0.3'],
            ]),
            self::entry('CVE-2014-0160', 'openssl', 'openssl', 'high', 7.5, 'OpenSSL Heartbleed information disclosure in 1.0.1 to 1.0.1f.', [
                ['versionStartIncluding' => '1.0.1', 'versionEndExcluding' => '1.0.1g'],
            ]),
        ];
    }

    /**
     * @param  array<int, array<string, string>>  $ranges
     * @return array<string, mixed>
     */
    private static function entry(string $cveId, string $vendor, string $product, string $severity, float $score, string $description, array $ranges): array
    {
        return [
            'cve_id' => $cveId,
            'vendor' => $vendor,
            'product' => $product,
            'severity' => $severity,
            'cvss_score' => $score,
            'description' => $description,
            'exploited' => in_array($cveId, ['CVE-2021-41773', 'CVE-2021-42013', 'CVE-2024-4577', 'CVE-2019-11043', 'CVE-2014-0160'], true),
            'cpes' => json_encode([sprintf('cpe:2.3:a:%s:%s:*:*:*:*:*:*:*', $vendor, $product)]),
            'version_ranges' => json_encode($ranges),
        ];
    }

    public static function seed(): int
    {
        $inserted = 0;
        $updated = 0;

        foreach (array_chunk(self::entries(), 50) as $chunk) {
            $result = Cve::query()->upsert($chunk, ['cve_id'], [
                'description', 'severity', 'cvss_score', 'exploited', 'vendor', 'product', 'cpes', 'version_ranges',
            ]);

            $before = Cve::query()->whereIn('cve_id', array_column($chunk, 'cve_id'))->count();
            $after = Cve::query()->whereIn('cve_id', array_column($chunk, 'cve_id'))->count();
            $inserted += max(0, $after - $before);
            $updated += max(0, count($chunk) - max(0, $after - $before));
        }

        return $inserted + $updated;
    }
}
