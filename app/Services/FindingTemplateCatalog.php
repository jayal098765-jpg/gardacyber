<?php

namespace App\Services;

class FindingTemplateCatalog
{
    /**
     * Vulnerability templates: title, category, severity.
     *
     * @return array<int, array{0: string, 1: string, 2: string}>
     */
    public static function vulnerabilities(): array
    {
        return [
            ['Apache Tomcat Manager Default Login', 'weak-credentials', 'critical'],
            ['Apache Struts 2 Remote Code Execution (CVE-2017-5638)', 'cve', 'critical'],
            ['Exposed .git directory leaks source and credentials', 'exposure', 'critical'],
            ['FTP Anonymous Login', 'exposure', 'critical'],
            ['Grafana admin panel with default credentials (admin/admin)', 'weak-credentials', 'critical'],
            ['Unauthenticated Redis instance exposed to the internet', 'exposure', 'high'],
            ['Subdomain Takeover via GitHub', 'subdomain-takeover', 'high'],
            ['Laravel Debug Enabled', 'misconfiguration', 'high'],
            ['PhpMyAdmin Setup File — Detect', 'exposure', 'high'],
            ['Apache RCE (CVE-2021-41773)', 'cve', 'high'],
            ['Card data entered outside a PCI-compliant iframe', 'misconfiguration', 'high'],
            ['Node.js Express NODE_ENV Development Mode', 'misconfiguration', 'medium'],
            ['PHPinfo Page — Detect', 'exposure', 'medium'],
            ['Server version disclosed in banner', 'tech-detect', 'low'],
            ['Weak Cipher Suites Detection', 'ssl-tls', 'low'],
            ['TLS certificate expiring in under 14 days', 'ssl-tls', 'medium'],
            ['Expired SSL Certificate', 'ssl-tls', 'low'],
            ['Default login credentials on admin panel', 'weak-credentials', 'high'],
            ['CORS misconfiguration allows arbitrary origins', 'misconfiguration', 'medium'],
            ['Open directory listing enabled', 'exposure', 'medium'],
            ['Subdomain takeover on dangling CNAME', 'subdomain-takeover', 'critical'],
            ['Unencrypted HTTP form submission', 'ssl-tls', 'low'],
        ];
    }

    /**
     * Incident templates: title, category, severity, screenshot palette.
     *
     * @return array<int, array{0: string, 1: string, 2: string, 3: string}>
     */
    public static function incidents(): array
    {
        return [
            ['Suspected website defacement (hacked-by)', 'defacement', 'critical', 'hacked'],
            ['Online-gambling SEO injection (slot-gacor)', 'gambling-injection', 'high', 'slot'],
            ['Online-gambling SEO injection (maxwin)', 'gambling-injection', 'high', 'slot'],
            ['Suspected website defacement (pwned)', 'defacement', 'high', 'hacked'],
            ['SEO-spam content injection (viagra)', 'seo-spam', 'medium', 'spam'],
            ['Hidden SEO spam backlinks', 'seo-spam', 'high', 'spam'],
        ];
    }

    /**
     * Host prefixes used to build deterministic demo hostnames.
     *
     * @return array<int, string>
     */
    public static function hostPrefixes(): array
    {
        return ['api', 'www', 'auth', 'admin', 'cdn', 'legacy', 'staging', 'dev', 'mail', 'docs', 'db-test', 'files', 'campus', 'news'];
    }

    /**
     * Generates a deterministic pseudo-random register from a seed.
     */
    public static function seededPicker(string $seed, int $max): int
    {
        return crc32($seed) % $max;
    }
}
