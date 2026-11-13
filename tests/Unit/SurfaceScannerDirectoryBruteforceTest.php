<?php

namespace Tests\Unit;

use App\Services\SurfaceScanner;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SurfaceScannerDirectoryBruteforceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Config::set('scanner.dir_bruteforce', true);
        Config::set('scanner.dir_bruteforce_budget', 10);
        Config::set('scanner.dir_bruteforce_timeout', 2);
    }

    /**
     * @return array{ip: string, status: int, headers: array, body: string, truncated: bool, blocked: bool, waf: array|null, via_origin: bool, via_browser: bool}
     */
    private function capture(): array
    {
        return [
            'ip' => '93.184.216.34',
            'status' => 200,
            'headers' => ['content-type' => ['text/html']],
            'body' => '<html><head><title>Welcome</title></head><body><h1>Hello there</h1></body></html>',
            'truncated' => false,
            'blocked' => false,
            'waf' => null,
            'via_origin' => false,
            'via_browser' => false,
        ];
    }

    /**
     * @param  array<int, string>  $paths
     * @param  array<string, Response|string>  $stubs
     * @return array<int, array{kind: string, severity: string, category: string, title: string, description: string, evidence: string}>
     */
    private function probe(array $paths, array $stubs, ?array $capture = null): array
    {
        Config::set('scanner.dir_bruteforce_paths', $paths);

        // Specific stubs must come first (first match wins) while the control
        // path still needs a deterministic miss.
        Http::fake($stubs + ['https://site.example.com/*' => Http::response('', 404)]);

        return (new SurfaceScanner)->probeDirectoryExposure('site.example.com', '93.184.216.34', $capture ?? $this->capture());
    }

    public function test_reports_environment_file_served_with_credentials(): void
    {
        $findings = $this->probe(['/.env'], [
            'https://site.example.com/.env' => Http::response(
                "APP_NAME=Garda\nAPP_KEY=base64:abc123\nDB_HOST=127.0.0.1\nDB_PASSWORD=hunter2\n",
                200,
                ['content-type' => ['text/plain']],
            ),
        ]);

        $this->assertCount(1, $findings);
        $this->assertSame('exposure', $findings[0]['category']);
        $this->assertSame('critical', $findings[0]['severity']);
        $this->assertStringContainsString('file signature', $findings[0]['evidence']);
        $this->assertStringContainsString('GET /.env returned HTTP 200', $findings[0]['evidence']);
    }

    public function test_proves_a_backup_archive_by_its_magic_bytes(): void
    {
        $findings = $this->probe(['/backup.zip'], [
            'https://site.example.com/backup.zip' => Http::response(
                "PK\x03\x04\x14\x00\x00\x00binary-archive-body",
                200,
                ['content-type' => ['application/zip']],
            ),
        ]);

        $this->assertCount(1, $findings);
        $this->assertSame('exposure', $findings[0]['category']);
        $this->assertSame('critical', $findings[0]['severity']);
        $this->assertStringContainsString('ZIP magic bytes', $findings[0]['evidence']);
    }

    public function test_reports_an_open_directory_listing(): void
    {
        $findings = $this->probe(['/logs/'], [
            'https://site.example.com/logs/' => Http::response(
                '<html><head><title>Index of /logs</title></head><body><a href="app.log">app.log</a></body></html>',
                200,
                ['content-type' => ['text/html']],
            ),
        ]);

        $this->assertCount(1, $findings);
        $this->assertSame('misconfiguration', $findings[0]['category']);
        $this->assertSame('medium', $findings[0]['severity']);
        $this->assertStringContainsString('directory index', $findings[0]['evidence']);
    }

    public function test_treats_an_authorization_challenge_as_low_severity_disclosure(): void
    {
        $findings = $this->probe(['/admin/'], [
            'https://site.example.com/admin/' => Http::response(
                '<html><head><title>401 Unauthorized</title></head><body>Authentication required</body></html>',
                401,
                ['content-type' => ['text/html']],
            ),
        ]);

        $this->assertCount(1, $findings);
        $this->assertSame('misconfiguration', $findings[0]['category']);
        $this->assertSame('low', $findings[0]['severity']);
        $this->assertStringContainsString('No content was read', $findings[0]['description']);
    }

    public function test_discards_a_single_page_app_catch_all_answer(): void
    {
        $findings = $this->probe(['/admin/', '/graphql'], [
            'https://site.example.com/admin/' => Http::response(
                '<html><head><title>Welcome</title></head><body><h1>Hello there</h1></body></html>',
                200,
                ['content-type' => ['text/html']],
            ),
            'https://site.example.com/graphql' => Http::response(
                '<html><head><title>Welcome</title></head><body><h1>Hello there</h1></body></html>',
                200,
                ['content-type' => ['text/html']],
            ),
        ]);

        $this->assertSame([], $findings);
    }

    public function test_discards_a_soft_404_that_matches_the_control_path(): void
    {
        $soft404 = '<html><head><title>Not found</title></head><body><p>Sorry, no such page.</p></body></html>';

        $findings = $this->probe(['/metrics'], [
            'https://site.example.com/*' => Http::response($soft404, 200, ['content-type' => ['text/html']]),
        ]);

        $this->assertSame([], $findings);
    }

    public function test_ignores_a_200_without_any_content_proof(): void
    {
        $findings = $this->probe(['/metrics'], [
            'https://site.example.com/metrics' => Http::response(
                'uptime_seconds 1234',
                200,
                ['content-type' => ['text/plain']],
            ),
        ]);

        $this->assertSame([], $findings);
    }

    public function test_stops_probing_when_the_edge_blocked_the_capture(): void
    {
        Http::fake();

        $findings = (new SurfaceScanner)->probeDirectoryExposure(
            'site.example.com',
            '93.184.216.34',
            ['blocked' => true] + $this->capture(),
        );

        $this->assertSame([], $findings);
        Http::assertNothingSent();
    }

    public function test_returns_nothing_when_the_probe_is_disabled(): void
    {
        Config::set('scanner.dir_bruteforce', false);
        Http::fake();

        $findings = (new SurfaceScanner)->probeDirectoryExposure('site.example.com', '93.184.216.34', $this->capture());

        $this->assertSame([], $findings);
        Http::assertNothingSent();
    }

    public function test_caps_the_number_of_reported_findings(): void
    {
        $paths = array_map(fn (int $index): string => '/.env.'.$index, range(1, 9));

        $stubs = [];

        foreach ($paths as $path) {
            $stubs['https://site.example.com'.$path] = Http::response(
                "APP_KEY=base64:key\nDB_PASSWORD=secret\n",
                200,
                ['content-type' => ['text/plain']],
            );
        }

        $findings = $this->probe($paths, $stubs);

        $this->assertCount(6, $findings);
    }
}
