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
];
