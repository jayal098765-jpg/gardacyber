<?php

namespace Tests\Unit;

use App\Services\OriginDiscovery;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class OriginDiscoveryTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Certificate transparency is a network call; only the tests that
        // exercise it turn it back on with a fake.
        Config::set('scanner.crt_sh', false);
    }

    /**
     * @param  array<int, string>  $txt
     * @param  array<int, string>  $mx
     * @param  array<string, array<int, string>>  $a
     */
    private function discovery(array $txt = [], array $mx = [], array $a = []): OriginDiscovery
    {
        return new class($txt, $mx, $a) extends OriginDiscovery
        {
            /**
             * @param  array<int, string>  $txt
             * @param  array<int, string>  $mx
             * @param  array<string, array<int, string>>  $a
             */
            public function __construct(private array $txt, private array $mx, private array $a)
            {
                parent::__construct();
            }

            protected function txtRecords(string $hostname): array
            {
                return $this->txt;
            }

            protected function mxHosts(string $hostname): array
            {
                return $this->mx;
            }

            protected function resolveA(string $hostname): array
            {
                return $this->a[$hostname] ?? [];
            }
        };
    }

    public function test_collects_spf_addresses_and_skips_edge_and_private_ranges(): void
    {
        $candidates = $this->discovery(
            txt: ['v=spf1 ip4:192.0.2.10 ip4:104.16.0.5 ip4:10.0.0.5 ip6:2001:db8::1 -all'],
        )->fromSpf('example.com');

        $ips = array_column($candidates, 'ip');

        $this->assertContains('192.0.2.10', $ips);
        $this->assertContains('2001:db8::1', $ips);
        $this->assertNotContains('104.16.0.5', $ips); // Cloudflare edge
        $this->assertNotContains('10.0.0.5', $ips); // private
        $this->assertSame('SPF ip4 mechanism', $candidates[0]['source']);
    }

    public function test_ignores_txt_records_that_are_not_spf(): void
    {
        $candidates = $this->discovery(
            txt: ['google-site-verification=abc', 'v=DMARC1; p=none'],
        )->fromSpf('example.com');

        $this->assertSame([], $candidates);
    }

    public function test_collects_mx_hosts_that_are_not_behind_the_cdn(): void
    {
        $candidates = $this->discovery(
            mx: ['mail.example.com'],
            a: ['mail.example.com' => ['192.0.2.20', '104.16.0.9']],
        )->fromMx('example.com');

        $this->assertCount(1, $candidates);
        $this->assertSame('192.0.2.20', $candidates[0]['ip']);
        $this->assertSame('mail.example.com', $candidates[0]['host']);
        $this->assertSame('MX record host', $candidates[0]['source']);
    }

    public function test_collects_unproxied_subdomains(): void
    {
        $candidates = $this->discovery(
            a: [
                'origin.example.com' => ['192.0.2.30'],
                'dev.example.com' => ['192.0.2.31'],
                'www.example.com' => ['104.16.0.7'],
            ],
        )->fromSubdomains('example.com');

        $this->assertSame(['192.0.2.30', '192.0.2.31'], array_column($candidates, 'ip'));
        $this->assertSame('unproxied subdomain origin', $candidates[0]['source']);
    }

    public function test_dedupes_candidates_across_sources(): void
    {
        $candidates = $this->discovery(
            txt: ['v=spf1 ip4:192.0.2.30 -all'],
            mx: ['mail.example.com'],
            a: ['mail.example.com' => ['192.0.2.30']],
        )->candidates('example.com');

        $this->assertCount(1, $candidates);
        $this->assertSame('192.0.2.30', $candidates[0]['ip']);
    }

    public function test_respects_candidate_limit(): void
    {
        $candidates = $this->discovery(
            txt: ['v=spf1 ip4:192.0.2.10 ip4:192.0.2.11 ip4:192.0.2.12 -all'],
        )->candidates('example.com', 2);

        $this->assertCount(2, $candidates);
    }

    public function test_stops_dns_work_once_the_deadline_passed(): void
    {
        $discovery = $this->discovery(
            a: ['origin.example.com' => ['192.0.2.30']],
        );

        $this->assertSame([], $discovery->fromSubdomains('example.com', 8, microtime(true) - 1));
    }

    public function test_collects_origin_hosts_published_in_certificate_transparency(): void
    {
        Config::set('scanner.crt_sh', true);

        Http::fake([
            'https://crt.sh/*' => Http::response([
                ['name_value' => "*.example.com\nwww.example.com"],
                ['common_name' => 'origin.example.com'],
                ['name_value' => 'unrelated.example.net'],
            ], 200),
        ]);

        $candidates = $this->discovery(
            a: [
                'origin.example.com' => ['192.0.2.40'],
                'www.example.com' => ['104.16.0.7'],
            ],
        )->fromCertificateTransparency('example.com');

        $this->assertCount(1, $candidates);
        $this->assertSame('192.0.2.40', $candidates[0]['ip']);
        $this->assertSame('origin.example.com', $candidates[0]['host']);
        $this->assertSame('certificate transparency host', $candidates[0]['source']);
    }

    public function test_inspects_origin_looking_certificate_names_first(): void
    {
        Config::set('scanner.crt_sh', true);
        Config::set('scanner.crt_sh_budget', 1);

        Http::fake([
            'https://crt.sh/*' => Http::response([
                ['name_value' => "shop.example.com\nlegacy.example.com"],
            ], 200),
        ]);

        $candidates = $this->discovery(
            a: [
                'shop.example.com' => ['192.0.2.50'],
                'legacy.example.com' => ['192.0.2.51'],
            ],
        )->fromCertificateTransparency('example.com');

        $this->assertCount(1, $candidates);
        $this->assertSame('legacy.example.com', $candidates[0]['host']);
    }

    public function test_skips_certificate_transparency_when_disabled(): void
    {
        Http::fake();

        $candidates = $this->discovery(
            a: ['origin.example.com' => ['192.0.2.40']],
        )->fromCertificateTransparency('example.com');

        $this->assertSame([], $candidates);
        Http::assertNothingSent();
    }

    public function test_certificate_transparency_lookup_failure_does_not_break_discovery(): void
    {
        Config::set('scanner.crt_sh', true);

        Http::fake(['https://crt.sh/*' => Http::response('busy', 503)]);

        $this->assertSame([], $this->discovery()->fromCertificateTransparency('example.com'));
    }
}
