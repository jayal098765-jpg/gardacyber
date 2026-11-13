<?php

return [
    'allow_any_public' => env('SCANNER_ALLOW_ANY_PUBLIC', true),
    'allowed_domains' => array_values(array_filter(array_map('trim', explode(',', env('SCANNER_ALLOWED_DOMAINS', ''))))),

    // Advanced, non-destructive probes (XSS reflection, brute-force posture, takeover checks).
    'active_probes' => env('SCANNER_ACTIVE_PROBES', true),

    // Maximum rapid credential attempts sent to a discovered login endpoint to
    // observe whether rate limiting / lockout is enforced. Keep low: this is
    // an indicator probe, not a cracking tool.
    'brute_force_attempts' => (int) env('SCANNER_BRUTEFORCE_ATTEMPTS', 5),

    // When a CDN/WAF (Cloudflare et al.) answers with a challenge instead of the
    // application, look for an origin address in the domain's own DNS footprint
    // and continue the assessment against it. Challenges are never solved.
    'waf_bypass' => env('SCANNER_WAF_BYPASS', true),

    // Maximum origin candidates to fetch, and the seconds allowed for the whole
    // attempt (DNS lookups included) before the scan carries on without it.
    'waf_bypass_candidates' => (int) env('SCANNER_WAF_BYPASS_CANDIDATES', 6),
    'waf_bypass_budget' => (float) env('SCANNER_WAF_BYPASS_BUDGET', 12),

    // When no origin can be confirmed, hand the challenge to a headless browser
    // and let its own JavaScript run. The clearance it earns is reused by the
    // probes that follow. Never used against interactive challenges (captcha,
    // Turnstile) — those simply stay blocked.
    'browser_bypass' => env('SCANNER_BROWSER_BYPASS', true),
    'browser_path' => env('SCANNER_BROWSER_PATH'),
    'browser_timeout' => (float) env('SCANNER_BROWSER_TIMEOUT', 25),

    // Read-only directory / backup-file probing. Requests are plain GETs
    // against a curated path list; nothing is written, uploaded, or deleted.
    'dir_bruteforce' => env('SCANNER_DIR_BRUTEFORCE', true),
    'dir_bruteforce_budget' => (float) env('SCANNER_DIR_BRUTEFORCE_BUDGET', 20),
    'dir_bruteforce_timeout' => (float) env('SCANNER_DIR_BRUTEFORCE_TIMEOUT', 4),
    'dir_bruteforce_paths' => [
        '/.env',
        '/.env.backup',
        '/.env.local',
        '/.env.production',
        '/.git/HEAD',
        '/.git/config',
        '/.svn/entries',
        '/.DS_Store',
        '/.htaccess',
        '/.htpasswd',
        '/.ssh/id_rsa',
        '/.aws/credentials',
        '/composer.json',
        '/composer.lock',
        '/package.json',
        '/yarn.lock',
        '/docker-compose.yml',
        '/Dockerfile',
        '/crossdomain.xml',
        '/phpinfo.php',
        '/info.php',
        '/server-status',
        '/server-info',
        '/metrics',
        '/graphql',
        '/openapi.json',
        '/swagger-ui.html',
        '/api-docs',
        '/debug/',
        '/debug.log',
        '/errors.log',
        '/laravel.log',
        '/storage/logs/laravel.log',
        '/backup.zip',
        '/backup.sql',
        '/backup.tar.gz',
        '/www.zip',
        '/site.zip',
        '/site.tar.gz',
        '/db.sql',
        '/dump.sql',
        '/database.sql',
        '/config.php.bak',
        '/wp-config.php.bak',
        '/config/database.php',
        '/backup/',
        '/backups/',
        '/db/',
        '/logs/',
        '/files/',
        '/uploads/',
        '/tmp/',
        '/private/',
        '/internal/',
        '/admin/',
        '/administrator/',
        '/wp-admin/',
        '/wp-login.php',
        '/phpmyadmin/',
        '/pma/',
        '/adminer.php',
        '/jenkins/',
        '/grafana/',
        '/kibana/',
        '/console/',
        '/actuator',
        '/actuator/env',
        '/actuator/health',
        '/actuator/mappings',
    ],

    // Certificate transparency (crt.sh) is used as one more passive source of
    // subdomain names that may still resolve to the origin: seconds allowed for
    // the lookup, and how many names to inspect.
    'crt_sh' => env('SCANNER_CRT_SH', true),
    'crt_sh_budget' => (int) env('SCANNER_CRT_SH_BUDGET', 30),
];
