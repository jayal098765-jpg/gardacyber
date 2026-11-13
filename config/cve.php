<?php

return [
    // NVD API 2.0 base URL for CVE bulk retrieval.
    'api_base' => env('NVD_API_BASE', 'https://services.nvd.nist.gov/rest/json/cves/2.0'),

    // Optional NVD API key (free from https://nvd.nist.gov/developers/request-an-api-key).
    // Without a key the API allows 5 requests per 30 seconds; with one, 50.
    'api_key' => env('NVD_API_KEY'),

    // Results per API page (NVD max is 2000).
    'page_size' => 2000,

    // Seconds to sleep between NVD API requests to stay under the rate limit.
    // With an API key the NVD limit is 50 requests per 30 seconds; without
    // one it is 5 requests per 30 seconds.
    'throttle_seconds' => (float) env('NVD_THROTTLE_SECONDS', env('NVD_API_KEY') ? 0.65 : 6.5),

    'timeout' => 90,

    'user_agent' => 'GardaCyber-CVE-Sync/1.0',

    // CISA Known Exploited Vulnerabilities feed.
    'kev_url' => env('CISA_KEV_URL', 'https://www.cisa.gov/sites/default/files/feeds/known_exploited_vulnerabilities.json'),

    // Cap on how many CVEs a single generated finding may list in its evidence.
    'max_cves_per_finding' => (int) env('CVE_MAX_PER_FINDING', 25),
];
