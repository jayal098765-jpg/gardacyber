<?php

namespace Tests\Unit;

use App\Services\SurfaceScanner;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SurfaceScannerAdvancedProbesTest extends TestCase
{
    public function test_probe_advanced_xss_reports_reflection_into_script_context_as_critical(): void
    {
        Http::fake(function (Request $request) {
            $value = $request['q'] ?? '';

            return Http::response('<html><head><script>var q=\''.$value.'\';</script></head></html>', 200);
        });

        $scanner = new SurfaceScanner;
        $findings = $scanner->probeAdvancedXss('test.example.com', '192.0.2.55');

        $this->assertNotEmpty($findings);
        $this->assertSame('xss', $findings[0]['category']);
        $this->assertSame('critical', $findings[0]['severity']);
        $this->assertStringContainsString('script context', $findings[0]['title']);
    }

    public function test_probe_advanced_xss_reports_reflection_into_attribute_context(): void
    {
        Http::fake(function (Request $request) {
            $value = $request['q'] ?? '';

            return Http::response('<html><body><input value="'.$value.'"></body></html>', 200);
        });

        $scanner = new SurfaceScanner;
        $findings = $scanner->probeAdvancedXss('test.example.com', '192.0.2.55');

        $this->assertNotEmpty($findings);
        $this->assertSame('xss', $findings[0]['category']);
        $this->assertSame('high', $findings[0]['severity']);
        $this->assertStringContainsString('attribute context', $findings[0]['title']);
    }

    public function test_probe_advanced_xss_reports_unencoded_html_reflection_with_evidence(): void
    {
        Http::fake(function (Request $request) {
            $value = $request['q'] ?? '';

            return Http::response('<html><body><h1>'.$value.'</h1></body></html>', 200);
        });

        $scanner = new SurfaceScanner;
        $findings = $scanner->probeAdvancedXss('test.example.com', '192.0.2.55');

        $this->assertNotEmpty($findings);
        $this->assertSame('xss', $findings[0]['category']);
        $this->assertSame('high', $findings[0]['severity']);
        $this->assertStringContainsString('https://test.example.com/?q=', $findings[0]['evidence']);
    }

    public function test_probe_advanced_xss_does_not_report_when_input_is_escaped(): void
    {
        Http::fake(fn (Request $request) => Http::response('<html><body>Nothing reflected here.</body></html>', 200));

        $scanner = new SurfaceScanner;
        $findings = $scanner->probeAdvancedXss('test.example.com', '192.0.2.55');

        $this->assertSame([], $findings);
    }

    public function test_probe_brute_force_reports_unprotected_login_endpoint(): void
    {
        Config::set('scanner.brute_force_attempts', 2);
        Http::fake(fn (Request $request) => Http::response('ok', 200));

        $scanner = new SurfaceScanner;
        $capture = [
            'body' => '<form action="/login" method="post"><input type="password" name="password"></form>',
        ];
        $findings = $scanner->probeAuthBruteForceResistance('test.example.com', '192.0.2.55', $capture);

        $this->assertNotEmpty($findings);
        $this->assertSame('auth-bruteforce', $findings[0]['category']);
        $this->assertStringContainsString('HTTP 200, 200', $findings[0]['evidence']);
    }

    public function test_probe_brute_force_skips_endpoint_when_throttled(): void
    {
        Config::set('scanner.brute_force_attempts', 2);
        Http::fake(fn (Request $request) => Http::response('Too Many Requests', 429));

        $scanner = new SurfaceScanner;
        $findings = $scanner->probeAuthBruteForceResistance('test.example.com', '192.0.2.55', ['body' => '']);

        $this->assertSame([], $findings);
    }

    public function test_probe_brute_force_skips_missing_endpoints(): void
    {
        Config::set('scanner.brute_force_attempts', 2);
        Http::fake(fn (Request $request) => Http::response('Not found', 404));

        $scanner = new SurfaceScanner;
        $findings = $scanner->probeAuthBruteForceResistance('test.example.com', '192.0.2.55', ['body' => '']);

        $this->assertSame([], $findings);
    }

    public function test_probe_subdomain_takeover_confirms_dangling_cname(): void
    {
        $scanner = new class extends SurfaceScanner
        {
            protected function cnameFor(string $hostname): array
            {
                return ['target.github.io'];
            }
        };

        Http::fake([
            'https://sub.example.com/' => Http::response("There isn't a GitHub Pages site here.", 200),
        ]);

        $findings = $scanner->probeSubdomainTakeover([
            ['hostname' => 'sub.example.com', 'ip' => '192.0.2.9'],
        ]);

        $this->assertCount(1, $findings);
        $this->assertSame('subdomain-takeover', $findings[0]['category']);
        $this->assertSame('critical', $findings[0]['severity']);
        $this->assertStringContainsString('target.github.io', $findings[0]['evidence']);
    }

    public function test_probe_subdomain_takeover_ignores_claimed_resources(): void
    {
        $scanner = new class extends SurfaceScanner
        {
            protected function cnameFor(string $hostname): array
            {
                return ['target.github.io'];
            }
        };

        Http::fake([
            'https://sub.example.com/' => Http::response('<html><body>Example subdomain</body></html>', 200),
        ]);

        $findings = $scanner->probeSubdomainTakeover([
            ['hostname' => 'sub.example.com', 'ip' => '192.0.2.9'],
        ]);

        $this->assertSame([], $findings);
    }

    public function test_probe_subdomain_takeover_ignores_unrelated_cnames(): void
    {
        $scanner = new class extends SurfaceScanner
        {
            protected function cnameFor(string $hostname): array
            {
                return ['internal-cdn.example.net'];
            }
        };

        Http::fake([
            'https://sub.example.com/' => Http::response('ok', 200),
        ]);

        $findings = $scanner->probeSubdomainTakeover([
            ['hostname' => 'sub.example.com', 'ip' => '192.0.2.9'],
        ]);

        $this->assertSame([], $findings);
    }

    public function test_analyzer_flags_missing_clickjacking_header(): void
    {
        $scanner = new SurfaceScanner;
        $capture = [
            'ip' => '192.0.2.1',
            'status' => 200,
            'headers' => ['content-type' => ['text/html; charset=utf-8']],
            'body' => '<!doctype html><html><body><h1>Safe</h1></body></html>',
            'truncated' => false,
        ];

        $findings = $scanner->analyze($capture, 'full');
        $titles = array_column($findings, 'title');

        $this->assertContains('Clickjacking protection missing (X-Frame-Options)', $titles);
    }
}
