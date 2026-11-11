<?php

namespace Tests\Unit;

use App\Services\SurfaceScanner;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class SurfaceScannerTest extends TestCase
{
    public function test_assert_allowed_blocks_unauthorized_hostnames_in_restricted_mode(): void
    {
        Config::set('scanner.allow_any_public', false);
        Config::set('scanner.allowed_domains', ['allowed.gardacyber.test']);
        $scanner = new SurfaceScanner;

        $this->expectException(ValidationException::class);
        $scanner->assertAllowed('evil.com');
    }

    public function test_assert_allowed_blocks_internal_hosts_in_public_mode(): void
    {
        Config::set('scanner.allow_any_public', true);
        $scanner = new SurfaceScanner;

        $this->expectException(ValidationException::class);
        $scanner->assertAllowed('localhost');
    }

    public function test_assert_allowed_passes_public_domains_in_open_mode(): void
    {
        Config::set('scanner.allow_any_public', true);
        $scanner = new SurfaceScanner;

        $scanner->assertAllowed('target.example.com');
        $this->assertTrue(true);
    }

    public function test_analyzer_flags_missing_security_headers_for_html_responses(): void
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

        $this->assertContains('HSTS header missing', $titles);
        $this->assertContains('Content Security Policy missing', $titles);
    }

    public function test_analyzer_flags_defacement_and_gambling_heuristics_with_context(): void
    {
        $scanner = new SurfaceScanner;
        $capture = [
            'ip' => '192.0.2.10',
            'status' => 200,
            'headers' => ['content-type' => ['text/html']],
            'body' => '<html><head><title>GardaCyber training fixture</title></head><body><h1>Hacked by training-team</h1><p>slot gacor jackpot maxwin</p></body></html>',
            'truncated' => false,
        ];

        $findings = $scanner->analyze($capture, 'incident');
        $titles = array_column($findings, 'title');

        $this->assertContains('Confirmed website defacement signature', $titles);
        $this->assertContains('Online gambling content injection detected', $titles);

        foreach ($findings as $finding) {
            $this->assertSame('incident', $finding['kind']);
            $this->assertNotEmpty($finding['evidence']);
        }
    }

    public function test_probe_active_vulnerabilities_detects_git_and_sql_error_reflections(): void
    {
        Http::fake([
            'https://test.example.com/.git/HEAD' => Http::response("ref: refs/heads/main\n", 200),
            'https://test.example.com/.env' => Http::response("APP_KEY=base64:xyz\nDB_PASSWORD=secret", 200),
            'https://test.example.com/?id=1%27%22' => Http::response('You have an error in your SQL syntax near ...', 200),
            'https://test.example.com/?q=*' => Http::response('Search results', 200),
            'https://test.example.com/*' => Http::response('Not found', 404),
        ]);

        $scanner = new SurfaceScanner;
        $findings = $scanner->probeActiveVulnerabilities('test.example.com', '192.0.2.55');

        $titles = array_column($findings, 'title');

        $this->assertContains('Exposed .git repository directory', $titles);
        $this->assertContains('Exposed environment configuration (.env)', $titles);
        $this->assertContains('Database error disclosure (SQL Injection indicator)', $titles);
    }
}
