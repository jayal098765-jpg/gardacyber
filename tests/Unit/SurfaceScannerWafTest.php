<?php

namespace Tests\Unit;

use App\Services\CdnFingerprint;
use App\Services\OriginDiscovery;
use App\Services\SurfaceScanner;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SurfaceScannerWafTest extends TestCase
{
    /**
     * @param  array<int, array{ip: string, host: string, source: string}>  $origins
     */
    private function scanner(array $origins = [], ?array $originCapture = null): SurfaceScanner
    {
        $discovery = new class($origins) extends OriginDiscovery
        {
            /**
             * @param  array<int, array{ip: string, host: string, source: string}>  $origins
             */
            public function __construct(private array $origins)
            {
                parent::__construct();
            }

            public function candidates(string $rootDomain, int $limit = 8, ?float $deadline = null): array
            {
                return $this->origins;
            }
        };

        return new class($discovery, $originCapture) extends SurfaceScanner
        {
            public function __construct(OriginDiscovery $origins, private ?array $originCapture)
            {
                parent::__construct(new CdnFingerprint, $origins);
            }

            public function resolve(string $hostname): string
            {
                return '104.16.0.5';
            }

            public function captureViaOrigin(string $hostname, string $originIp): ?array
            {
                return $this->originCapture;
            }

            public function browserCapture(string $hostname, string $ip): ?array
            {
                return null;
            }
        };
    }

    private function challengeResponse(): mixed
    {
        return Http::response(
            '<html><head><title>Just a moment...</title></head><body>Checking your browser before accessing</body></html>',
            403,
            ['cf-ray' => ['8a1b2c3d4e5f-SIN'], 'content-type' => ['text/html']],
        );
    }

    /** @return array<string, mixed> */
    private function originCapture(): array
    {
        return [
            'ip' => '192.0.2.77',
            'status' => 200,
            'headers' => ['content-type' => ['text/html; charset=utf-8']],
            'body' => '<html><head><title>Origin app</title></head><body><h1>Origin</h1></body></html>',
            'truncated' => false,
        ];
    }

    public function test_capture_is_flagged_blocked_when_the_edge_challenges_and_no_origin_is_found(): void
    {
        Config::set('scanner.waf_bypass', true);
        Http::fake(['https://blocked.example.com/' => $this->challengeResponse()]);

        $capture = $this->scanner()->capture('blocked.example.com');

        $this->assertTrue($capture['blocked']);
        $this->assertFalse($capture['via_origin']);
        $this->assertSame('cloudflare', $capture['waf']['provider']);
        $this->assertSame(403, $capture['status']);
    }

    public function test_capture_continues_against_the_origin_when_the_edge_is_challenged(): void
    {
        Config::set('scanner.waf_bypass', true);
        Http::fake(['https://blocked.example.com/' => $this->challengeResponse()]);

        $capture = $this->scanner(
            origins: [['ip' => '192.0.2.77', 'host' => 'origin.example.com', 'source' => 'SPF ip4 mechanism']],
            originCapture: $this->originCapture(),
        )->capture('blocked.example.com');

        $this->assertFalse($capture['blocked']);
        $this->assertTrue($capture['via_origin']);
        $this->assertSame(200, $capture['status']);
        $this->assertSame(403, $capture['edge_status']);
        $this->assertSame('192.0.2.77', $capture['ip']);
        $this->assertSame('cloudflare', $capture['waf']['provider']);
    }

    public function test_capture_stays_blocked_when_the_origin_is_behind_another_challenge(): void
    {
        Config::set('scanner.waf_bypass', true);
        Http::fake(['https://blocked.example.com/' => $this->challengeResponse()]);

        $capture = $this->scanner(
            origins: [['ip' => '192.0.2.77', 'host' => 'origin.example.com', 'source' => 'MX record host']],
            originCapture: [
                'ip' => '192.0.2.77',
                'status' => 403,
                'headers' => ['server' => ['cloudflare']],
                'body' => 'Attention Required! | Cloudflare',
                'truncated' => false,
            ],
        )->capture('blocked.example.com');

        $this->assertTrue($capture['blocked']);
        $this->assertFalse($capture['via_origin']);
    }

    public function test_capture_stays_blocked_when_origin_bypass_is_disabled(): void
    {
        Config::set('scanner.waf_bypass', false);
        Http::fake(['https://blocked.example.com/' => $this->challengeResponse()]);

        $capture = $this->scanner(
            origins: [['ip' => '192.0.2.77', 'host' => 'origin.example.com', 'source' => 'SPF ip4 mechanism']],
            originCapture: $this->originCapture(),
        )->capture('blocked.example.com');

        $this->assertTrue($capture['blocked']);
        $this->assertFalse($capture['via_origin']);
    }

    public function test_bypass_waf_returns_null_when_no_usable_origin_exists(): void
    {
        $this->assertNull($this->scanner()->bypassWaf('blocked.example.com'));
    }

    public function test_browser_capture_is_not_flagged_as_blocked_on_a_healthy_edge_response(): void
    {
        Http::fake([
            'https://healthy.example.com/' => Http::response(
                '<html><body>Application</body></html>',
                200,
                ['cf-ray' => ['8a1b2c3d4e5f-SIN'], 'content-type' => ['text/html']],
            ),
        ]);

        $capture = $this->scanner()->capture('healthy.example.com');

        $this->assertFalse($capture['blocked']);
        $this->assertFalse($capture['via_origin']);
        $this->assertSame('cloudflare', $capture['waf']['provider']);
    }

    public function test_analyze_skips_header_findings_for_a_blocked_capture(): void
    {
        $findings = (new SurfaceScanner)->analyze([
            'ip' => '104.16.0.5',
            'status' => 200,
            'headers' => ['content-type' => ['text/html']],
            'body' => '<html><body>Just a moment...</body></html>',
            'truncated' => false,
            'blocked' => true,
            'waf' => ['provider' => 'cloudflare', 'signals' => [], 'challenged' => true],
            'via_origin' => false,
        ], 'full');

        $this->assertSame([], $findings);
    }

    public function test_brute_force_probe_skips_a_blocked_capture(): void
    {
        Config::set('scanner.brute_force_attempts', 2);
        Http::fake(fn () => Http::response('ok', 200));

        $findings = (new SurfaceScanner)->probeAuthBruteForceResistance(
            'blocked.example.com',
            '104.16.0.5',
            ['blocked' => true, 'body' => '<form action="/login"><input type="password"></form>'],
        );

        $this->assertSame([], $findings);
    }

    public function test_waf_findings_report_origin_exposure_as_medium(): void
    {
        $findings = (new SurfaceScanner)->wafFindings([
            'ip' => '192.0.2.77',
            'status' => 200,
            'headers' => [],
            'body' => 'app',
            'truncated' => false,
            'blocked' => false,
            'waf' => ['provider' => 'cloudflare', 'signals' => [], 'challenged' => true],
            'via_origin' => true,
            'edge_status' => 403,
            'origin' => ['ip' => '192.0.2.77', 'host' => 'origin.example.com', 'source' => 'SPF ip4 mechanism'],
        ], 'blocked.example.com');

        $this->assertCount(1, $findings);
        $this->assertSame('waf-bypass', $findings[0]['category']);
        $this->assertSame('medium', $findings[0]['severity']);
        $this->assertStringContainsString('192.0.2.77', $findings[0]['evidence']);
        $this->assertStringContainsString('SPF ip4 mechanism', $findings[0]['evidence']);
    }

    public function test_waf_findings_report_an_unresolved_challenge_as_low(): void
    {
        $findings = (new SurfaceScanner)->wafFindings([
            'ip' => '104.16.0.5',
            'status' => 403,
            'headers' => [],
            'body' => 'Just a moment...',
            'truncated' => false,
            'blocked' => true,
            'waf' => ['provider' => 'cloudflare', 'signals' => [], 'challenged' => true],
            'via_origin' => false,
        ], 'blocked.example.com');

        $this->assertCount(1, $findings);
        $this->assertSame('waf', $findings[0]['category']);
        $this->assertSame('low', $findings[0]['severity']);
    }

    public function test_waf_findings_are_silent_for_an_unaffected_scan(): void
    {
        $findings = (new SurfaceScanner)->wafFindings([
            'ip' => '104.16.0.5',
            'status' => 200,
            'headers' => [],
            'body' => 'app',
            'truncated' => false,
            'blocked' => false,
            'waf' => ['provider' => 'cloudflare', 'signals' => [], 'challenged' => false],
            'via_origin' => false,
        ], 'healthy.example.com');

        $this->assertSame([], $findings);
    }
}
