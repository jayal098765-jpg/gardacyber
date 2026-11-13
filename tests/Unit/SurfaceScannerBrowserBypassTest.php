<?php

namespace Tests\Unit;

use App\Services\BrowserClearance;
use App\Services\CdnFingerprint;
use App\Services\OriginDiscovery;
use App\Services\SurfaceScanner;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SurfaceScannerBrowserBypassTest extends TestCase
{
    /**
     * @param  array{cookies: array<int, array{name: string, value: string, domain: string}>, user_agent: string, status: int, body: string, title: string}|null  $solution
     */
    private function scanner(?array $solution, ?OriginDiscovery $origins = null): SurfaceScanner
    {
        $browser = new class($solution) extends BrowserClearance
        {
            public bool $called = false;

            /**
             * @param  array{cookies: array<int, array{name: string, value: string, domain: string}>, user_agent: string, status: int, body: string, title: string}|null  $solution
             */
            public function __construct(private ?array $solution) {}

            public function solve(string $hostname, string $ip): ?array
            {
                $this->called = true;

                return $this->solution;
            }
        };

        $origins ??= new class extends OriginDiscovery
        {
            public function __construct()
            {
                parent::__construct();
            }

            public function candidates(string $rootDomain, int $limit = 8, ?float $deadline = null): array
            {
                return [];
            }
        };

        return new class($browser, $origins) extends SurfaceScanner
        {
            public function __construct(BrowserClearance $browser, OriginDiscovery $origins)
            {
                parent::__construct(new CdnFingerprint, $origins, $browser);
            }

            public function resolve(string $hostname): string
            {
                return '104.16.0.5';
            }
        };
    }

    /** @return array{cookies: array<int, array{name: string, value: string, domain: string}>, user_agent: string, status: int, body: string, title: string} */
    private function solution(): array
    {
        return [
            'cookies' => [
                ['name' => 'cf_clearance', 'value' => 'clearance-token', 'domain' => 'blocked.example.com'],
            ],
            'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) Firefox/128.0',
            'status' => 200,
            'body' => '<html><head><title>Browser app</title></head><body><h1>Application</h1></body></html>',
            'title' => 'Browser app',
        ];
    }

    private function fakeChallenge(): void
    {
        Http::fake([
            'https://blocked.example.com/' => Http::response(
                '<html><head><title>Just a moment...</title></head><body>Checking your browser before accessing</body></html>',
                403,
                ['cf-ray' => ['8a1b2c3d4e5f-SIN'], 'content-type' => ['text/html']],
            ),
        ]);
    }

    public function test_capture_falls_back_to_a_browser_cleared_session_when_no_origin_exists(): void
    {
        Config::set('scanner.waf_bypass', true);
        Config::set('scanner.browser_bypass', true);
        $this->fakeChallenge();

        $capture = $this->scanner($this->solution())->capture('blocked.example.com');

        $this->assertFalse($capture['blocked']);
        $this->assertTrue($capture['via_browser']);
        $this->assertFalse($capture['via_origin']);
        $this->assertSame(200, $capture['status']);
        $this->assertSame(403, $capture['edge_status']);
        $this->assertSame('cloudflare', $capture['waf']['provider']);
        $this->assertStringContainsString('Browser app', $capture['body']);
    }

    public function test_capture_stays_blocked_when_the_browser_cannot_clear_the_challenge(): void
    {
        Config::set('scanner.waf_bypass', true);
        Config::set('scanner.browser_bypass', true);
        $this->fakeChallenge();

        $capture = $this->scanner(null)->capture('blocked.example.com');

        $this->assertTrue($capture['blocked']);
        $this->assertFalse($capture['via_browser']);
        $this->assertSame(403, $capture['status']);
    }

    public function test_browser_handoff_is_skipped_when_disabled(): void
    {
        Config::set('scanner.waf_bypass', true);
        Config::set('scanner.browser_bypass', false);
        $this->fakeChallenge();

        $capture = $this->scanner($this->solution())->capture('blocked.example.com');

        $this->assertTrue($capture['blocked']);
        $this->assertFalse($capture['via_browser']);
    }

    public function test_probes_reuse_the_browser_clearance_identity(): void
    {
        Config::set('scanner.waf_bypass', true);
        Config::set('scanner.browser_bypass', true);
        Http::fake([
            'https://blocked.example.com/' => Http::response(
                '<html><head><title>Just a moment...</title></head><body>Checking your browser before accessing</body></html>',
                403,
                ['cf-ray' => ['8a1b2c3d4e5f-SIN'], 'content-type' => ['text/html']],
            ),
            'https://blocked.example.com/*' => Http::response('', 404),
        ]);

        $scanner = $this->scanner($this->solution());
        $capture = $scanner->capture('blocked.example.com');

        $scanner->probeActiveVulnerabilities('blocked.example.com', $capture['ip']);

        $cleared = Http::recorded(fn (Request $request): bool => $request->hasHeader('Cookie', 'cf_clearance=clearance-token'));

        $this->assertNotEmpty($cleared);
        $this->assertSame(
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64) Firefox/128.0',
            $cleared->first()[0]->header('User-Agent')[0] ?? '',
        );
    }

    public function test_analyze_skips_header_findings_but_still_reads_a_browser_cleared_page(): void
    {
        $findings = (new SurfaceScanner)->analyze([
            'ip' => '104.16.0.5',
            'status' => 200,
            'headers' => ['content-type' => ['text/html']],
            'body' => '<html><body><h1>Hacked by training-team</h1></body></html>',
            'truncated' => false,
            'blocked' => false,
            'waf' => ['provider' => 'cloudflare', 'signals' => [], 'challenged' => true],
            'via_origin' => false,
            'via_browser' => true,
        ], 'full');

        $this->assertSame(['defacement'], array_column($findings, 'category'));
    }

    public function test_waf_findings_are_silent_for_a_browser_cleared_capture(): void
    {
        $findings = (new SurfaceScanner)->wafFindings([
            'ip' => '104.16.0.5',
            'status' => 200,
            'headers' => [],
            'body' => 'app',
            'truncated' => false,
            'blocked' => false,
            'waf' => ['provider' => 'cloudflare', 'signals' => [], 'challenged' => true],
            'via_origin' => false,
            'via_browser' => true,
            'edge_status' => 403,
        ], 'blocked.example.com');

        $this->assertSame([], $findings);
    }
}
