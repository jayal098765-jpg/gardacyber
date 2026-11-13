<?php

namespace Tests\Unit;

use App\Services\CdnFingerprint;
use Tests\TestCase;

class CdnFingerprintTest extends TestCase
{
    public function test_detects_cloudflare_from_edge_headers(): void
    {
        $detection = (new CdnFingerprint)->detect(
            ['cf-ray' => ['8a1b2c3d4e5f-SIN'], 'content-type' => ['text/html']],
            '<html><body>Application</body></html>',
            200,
        );

        $this->assertNotNull($detection);
        $this->assertSame('cloudflare', $detection['provider']);
        $this->assertFalse($detection['challenged']);
        $this->assertContains('response header: cf-ray', $detection['signals']);
    }

    public function test_detects_cloudflare_from_server_header(): void
    {
        $detection = (new CdnFingerprint)->detect(['server' => ['cloudflare']], 'ok', 200);

        $this->assertNotNull($detection);
        $this->assertSame('cloudflare', $detection['provider']);
        $this->assertFalse($detection['challenged']);
    }

    public function test_flags_interactive_challenge_body_as_challenged(): void
    {
        $detection = (new CdnFingerprint)->detect(
            ['cf-ray' => ['8a1b2c3d4e5f-SIN']],
            '<html><head><title>Just a moment...</title></head><body>Checking your browser before accessing</body></html>',
            403,
        );

        $this->assertNotNull($detection);
        $this->assertTrue($detection['challenged']);
    }

    public function test_flags_cf_mitigated_header_as_challenged(): void
    {
        $detection = (new CdnFingerprint)->detect(
            ['cf-mitigated' => ['challenge'], 'content-type' => ['text/html']],
            '<html><body>Verifying you are human</body></html>',
            200,
        );

        $this->assertNotNull($detection);
        $this->assertTrue($detection['challenged']);
    }

    public function test_treats_small_provider_error_page_as_challenge(): void
    {
        $detection = (new CdnFingerprint)->detect(['cf-ray' => ['8a1b2c3d4e5f-SIN']], 'Forbidden', 403);

        $this->assertNotNull($detection);
        $this->assertTrue($detection['challenged']);
    }

    public function test_ignores_responses_without_edge_signatures(): void
    {
        $this->assertNull((new CdnFingerprint)->detect(
            ['content-type' => ['text/html'], 'server' => ['nginx/1.24.0']],
            '<html><body>Plain origin</body></html>',
            200,
        ));
    }

    public function test_recognises_cloudflare_edge_addresses(): void
    {
        $fingerprint = new CdnFingerprint;

        $this->assertTrue($fingerprint->isCloudflareIp('104.16.0.5'));
        $this->assertTrue($fingerprint->isCloudflareIp('173.245.48.1'));
        $this->assertTrue($fingerprint->isCloudflareIp('2606:4700::6810:105'));
        $this->assertFalse($fingerprint->isCloudflareIp('8.8.8.8'));
        $this->assertFalse($fingerprint->isCloudflareIp('192.0.2.10'));
    }

    public function test_rejects_malformed_cidr_and_address_family_mismatch(): void
    {
        $fingerprint = new CdnFingerprint;

        $this->assertFalse($fingerprint->ipInCidr('104.16.0.5', '104.16.0.0'));
        $this->assertFalse($fingerprint->ipInCidr('104.16.0.5', '2606:4700::/32'));
        $this->assertFalse($fingerprint->ipInCidr('not-an-ip', '104.16.0.0/13'));
        $this->assertTrue($fingerprint->ipInCidr('104.16.0.5', '104.16.0.0/13'));
    }
}
