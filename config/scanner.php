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

    // When no origin can be confirmed, hand the challenge to a browser and let
    // its own JavaScript run. The clearance it earns is reused by the probes
    // that follow. Nothing is clicked and no captcha is solved.
    //
    // A managed Turnstile widget refuses a headless build but passes the same
    // build when it owns a real window, so a headful attempt follows the
    // headless one whenever a desktop session exists. On a server that means
    // keeping `browser_headless` on and disabling the fallback, or running the
    // scan under Xvfb.
    'browser_bypass' => env('SCANNER_BROWSER_BYPASS', true),
    'browser_path' => env('SCANNER_BROWSER_PATH'),
    'browser_timeout' => (float) env('SCANNER_BROWSER_TIMEOUT', 25),
    'browser_headless' => env('SCANNER_BROWSER_HEADLESS', true),
    'browser_headful_fallback' => env('SCANNER_BROWSER_HEADFUL_FALLBACK', true),
    'browser_probe_timeout' => (float) env('SCANNER_BROWSER_PROBE_TIMEOUT', 12),

    // When the queue connection is `sync`, scan jobs are pushed to the
    // `database` queue and a one-shot worker is booted to process them, so
    // the scan runs outside the HTTP request and the operator sees live
    // progress. Set to false to fall back to synchronous execution.
    'async_worker' => env('SCANNER_ASYNC_WORKER', true),

    // The PHP CLI binary used to boot the background worker. Leave empty to
    // auto-detect (the `php.exe` next to the current SAPI binary on Windows,
    // otherwise PHP_BINARY). Web SAPIs like php-cgi must not be used here.
    'php_binary' => env('SCANNER_PHP_BINARY'),

    // How many one-shot workers to boot per launch. Workers exit when the
    // queue is empty, so a second worker mostly adds resilience and lets
    // simultaneous scans run in parallel.
    'async_worker_count' => max(1, (int) env('SCANNER_ASYNC_WORKER_COUNT', 2)),

    // Seconds a scan may sit queued (in a sync-queue environment) while the
    // background worker is given a chance to pick it up. Once exceeded, the
    // next progress poll runs the scan inline so it can never stay stuck in
    // the queued state.
    'async_worker_grace' => (int) env('SCANNER_ASYNC_WORKER_GRACE', 8),

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
