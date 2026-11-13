<?php

namespace App\Services;

use App\Models\Finding;

class RemediationAdvisor
{
    /**
     * @return array{
     *     summary: string,
     *     impact: string,
     *     steps: array<int, string>,
     *     config_snippet: ?string,
     *     verification: string
     * }
     */
    public function guideFor(Finding $finding): array
    {
        return match ($finding->category) {
            'defacement' => [
                'summary' => 'Active web page alteration detected with attacker signatures or unauthorized graffiti.',
                'impact' => 'Immediate reputational damage, loss of trust, and potential underlying remote code execution or credential compromise.',
                'steps' => [
                    'Isolate the host or place the application into maintenance mode immediately.',
                    'Compare web root files against git version control or a verified clean backup snapshot.',
                    'Inspect access logs around the modification timestamp for file upload POSTs or suspicious administrative logins.',
                    'Rotate database passwords, SSH keys, and application secrets stored on the compromised host.',
                    'Report the incident to the internal CSIRT and document evidentiary hashes.',
                ],
                'config_snippet' => "# Lock webroot permissions (read-only for web server daemon):\nchown -R root:www-data /var/www/html\nfind /var/www/html -type d -exec chmod 755 {} \\;\nfind /var/www/html -type f -exec chmod 644 {} \\;",
                'verification' => 'Run GardaCyber Incident Check on the affected URL. The classifier must report no defacement signatures.',
            ],
            'gambling-injection' => [
                'summary' => 'Unauthorized online gambling keywords (slot, maxwin, judi online) detected on an indexable page.',
                'impact' => 'Search engine ranking penalty, domain reputation blacklisting, and SEO traffic diversion to illegal gambling networks.',
                'steps' => [
                    'Inspect the database (e.g. wp_posts, cms_content) for recently modified articles containing spam keywords.',
                    'Audit .htaccess and Nginx configuration for user-agent specific redirects (e.g. cloaking that targets Googlebot).',
                    'Scan plugins, themes, and upload directories for obfuscated PHP backdoors (eval, base64_decode, gzinflate).',
                    'Remove malicious injected content and verify source code against repository hash.',
                    'Request URL re-indexing in Google Search Console to clear spam snippets from search results.',
                ],
                'config_snippet' => "# Block script execution in uploads directory (Nginx):\nlocation ~* /(?:uploads|files)/.*\\.php$ {\n    deny all;\n    return 404;\n}",
                'verification' => 'Re-fetch page with standard user-agent and Googlebot user-agent. Confirm clean response.',
            ],
            'security-headers' => [
                'summary' => 'Browser security defense headers are missing from HTTP/HTTPS responses.',
                'impact' => 'Increased vulnerability to clickjacking, MIME-type sniffing, cross-site scripting (XSS), and downgrade attacks.',
                'steps' => [
                    'Deploy Strict-Transport-Security (HSTS) with at least 1 year duration.',
                    'Set X-Content-Type-Options: nosniff to prevent browsers from MIME-interpreting binary content.',
                    'Define a Content-Security-Policy (CSP) tailored to the application scripts and assets.',
                    'Enforce Referrer-Policy: strict-origin-when-cross-origin.',
                ],
                'config_snippet' => "# Recommended Nginx security headers:\nadd_header Strict-Transport-Security \"max-age=31536000; includeSubDomains\" always;\nadd_header X-Content-Type-Options \"nosniff\" always;\nadd_header X-Frame-Options \"SAMEORIGIN\" always;\nadd_header Referrer-Policy \"strict-origin-when-cross-origin\" always;\nadd_header Content-Security-Policy \"default-src 'self'; script-src 'self'; style-src 'self' 'unsafe-inline';\" always;",
                'verification' => 'Run curl -I https://'.($finding->asset?->hostname ?: 'your-domain').' and verify all headers are present in response.',
            ],
            'subdomain-takeover' => [
                'summary' => 'Dangling DNS CNAME pointer identified pointing to an unclaimed cloud or SaaS resource.',
                'impact' => 'Attacker can claim the third-party resource and serve arbitrary content or harvest session cookies under your root domain.',
                'steps' => [
                    'Check DNS zone configuration for the affected CNAME record.',
                    'Determine whether the target service (e.g. GitHub Pages, AWS S3, Azure App Service) is still actively provisioned.',
                    'If the resource is decommissioned, immediately remove the dangling CNAME record from your DNS zone.',
                    'If the service is still required, re-claim and bind the domain within the respective cloud provider console.',
                ],
                'config_snippet' => "# Verify CNAME resolution via dig:\ndig ".($finding->asset?->hostname ?: 'subdomain.example.com').' CNAME +short',
                'verification' => 'DNS query should no longer resolve to the unclaimed third-party hostname.',
            ],
            'xss' => [
                'summary' => 'User-supplied input is reflected into HTML responses without context-aware encoding or sanitization.',
                'impact' => 'An attacker can execute JavaScript in the victim\'s browser session, enabling session hijacking, credential phishing, or malicious action on behalf of the user.',
                'steps' => [
                    'Encode output based on the HTML context (body, attribute, URL, or script) using a template engine with auto-escaping (e.g. Blade {{ }}).',
                    'Never interpolate user input directly inside <script> blocks; keep all application data out of inline JavaScript.',
                    'Deploy a Content-Security-Policy with script-src \'self\' to contain any residual injection.',
                    'Add context-aware sanitization (e.g. HTMLPurifier) for rich-text fields.',
                    'Verify each reflection point with the input reflected unencoded in any context.',
                ],
                'config_snippet' => "# Example CSP to limit script injection impact:\nadd_header Content-Security-Policy \"default-src 'self'; script-src 'self'; object-src 'none'; frame-ancestors 'self';\" always;",
                'verification' => 'Re-run GardaCyber vulnerability scan; the reflected-injection finding must no longer appear on the affected parameter.',
            ],
            'auth-bruteforce' => [
                'summary' => 'An authentication endpoint does not enforce rate limiting, throttling, or account lockout on repeated failed attempts.',
                'impact' => 'The account is vulnerable to credential stuffing / brute-force password attacks, especially when users reuse passwords across services.',
                'steps' => [
                    'Add per-IP and per-account rate limiting (e.g. Laravel throttle middleware, Redis-backed counter) on the login endpoint.',
                    'Introduce progressive lockout after a small number of failures and reset on successful authentication.',
                    'Require strong passwords and implement Multi-Factor Authentication (MFA) for all privileged accounts.',
                    'Add a CAPTCHA or proof-of-work after repeated failures to slow automated bots.',
                    'Monitor failed-login rates in logs and alert on anomalous spikes.',
                ],
                'config_snippet' => "# Laravel route throttling example:\nRoute::post('/login', [LoginController::class, 'store'])->middleware('throttle:5,1');",
                'verification' => 'Re-run GardaCyber vulnerability scan; the brute-force protection finding must disappear once throttling returns HTTP 429 on rapid repeats.',
            ],
            'waf' => [
                'summary' => 'The CDN/WAF edge returned a challenge or block page to an automated, authorized assessment instead of the application.',
                'impact' => 'The assessment only sees part of the perimeter: header, exposure, and injection checks run against an interstitial. Edge protection itself is working as designed for unknown clients.',
                'steps' => [
                    'Review edge security events for the assessment source address and confirm which rule fired.',
                    'Allowlist the authorized assessment source address (and its user-agent) for the scan window only.',
                    'Re-run the assessment once the rule is in place to obtain full header, exposure, and injection coverage.',
                    'Check whether the same rule also blocks legitimate API clients, feed readers, or search crawlers.',
                    'Keep challenge pages enabled for all unauthenticated traffic outside the allowlisted source.',
                ],
                'config_snippet' => "# Cloudflare example, scoped to the assessment window:\n# Security > WAF > Custom rules\n#   (ip.src eq <scanner-ip> and http.user_agent contains \"GardaCyber\")\n#   -> Action: Skip -> all remaining custom rules, managed rules, rate limiting",
                'verification' => 'Re-run the GardaCyber scan; the completion message must report the live perimeter scanned instead of limited coverage.',
            ],
            'waf-bypass' => [
                'summary' => 'A CDN/WAF edge fronts the hostname, but the origin server also answers directly on its own public address.',
                'impact' => 'Anyone who learns the origin address can skip WAF rules, bot management, rate limiting, and DDoS absorption entirely, then attack the application on its own terms.',
                'steps' => [
                    'Restrict the origin firewall to the CDN provider\'s published edge ranges (or mandatory authenticated origin pulls).',
                    'Remove or firewall DNS records that leak the origin address (direct, origin, legacy, and mail hosts).',
                    'Rotate the origin address if it has already been published or indexed by scanning services.',
                    'Enable authenticated origin pulls / mTLS between the CDN and the origin.',
                    'Re-run the assessment and confirm the origin is no longer reachable except through the edge.',
                ],
                'config_snippet' => "# Allow only Cloudflare edge ranges on the origin (nftables sketch):\nnft add rule inet filter input tcp dport { 80, 443 } ip saddr != { 173.245.48.0/20, 103.21.244.0/22, 104.16.0.0/13, 172.64.0.0/13 } drop",
                'verification' => 'From an address outside the CDN ranges, a direct connection to the origin IP on the hostname must be refused.',
            ],
            'exposure' => [
                'summary' => 'Sensitive administrative portal, repository, or internal debugging file accessible from the public internet.',
                'impact' => 'Unauthorized data disclosure, credential harvesting, or bypass of perimeter authentication barriers.',
                'steps' => [
                    'Restrict access to internal administrative endpoints using VPN, IP allowlist, or SSO authentication gateway.',
                    'Delete publicly exposed .git directories, phpinfo files, backup archives, or test scripts from production servers.',
                    'Review web server configuration to deny access to hidden files and version control metadata.',
                ],
                'config_snippet' => "# Deny access to hidden files (.git, .env, etc) in Nginx:\nlocation ~ /\\.(?!well-known) {\n    deny all;\n    return 404;\n}",
                'verification' => 'Verify endpoint returns HTTP 403 or 404 from external networks.',
            ],
            default => [
                'summary' => 'Security observation flagged by GardaCyber rule engine requiring analyst verification.',
                'impact' => 'May expose service surface or configuration anomalies that lower perimeter resilience.',
                'steps' => [
                    'Review the recorded evidence and response headers.',
                    'Verify whether the service or header is intentional for business operations.',
                    'Apply least-privilege configuration and update relevant packages to latest stable releases.',
                ],
                'config_snippet' => null,
                'verification' => 'Re-run GardaCyber assessment to confirm the finding is resolved or acknowledged.',
            ],
        };
    }
}
