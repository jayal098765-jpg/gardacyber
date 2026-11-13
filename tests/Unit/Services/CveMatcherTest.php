<?php

namespace Tests\Unit\Services;

use App\Services\CveMatcher;
use PHPUnit\Framework\TestCase;

class CveMatcherTest extends TestCase
{
    private CveMatcher $matcher;

    protected function setUp(): void
    {
        parent::setUp();

        $this->matcher = new CveMatcher;
    }

    public function test_it_parses_nginx_banner(): void
    {
        $fingerprint = $this->matcher->fingerprintFromBanner('nginx/1.18.0');

        $this->assertSame('nginx', $fingerprint['vendor']);
        $this->assertSame('nginx', $fingerprint['product']);
        $this->assertSame('1.18.0', $fingerprint['version']);
    }

    public function test_it_parses_apache_banner_with_comment(): void
    {
        $fingerprint = $this->matcher->fingerprintFromBanner('Apache/2.4.49 (Ubuntu)');

        $this->assertSame('apache', $fingerprint['vendor']);
        $this->assertSame('http_server', $fingerprint['product']);
        $this->assertSame('2.4.49', $fingerprint['version']);
    }

    public function test_it_parses_iis_banner(): void
    {
        $fingerprint = $this->matcher->fingerprintFromBanner('Microsoft-IIS/10.0');

        $this->assertSame('microsoft', $fingerprint['vendor']);
        $this->assertSame('iis', $fingerprint['product']);
        $this->assertSame('10.0', $fingerprint['version']);
    }

    public function test_it_rejects_fingerprint_without_version(): void
    {
        $fingerprint = $this->matcher->fingerprintFromBanner('some-random-server');

        $this->assertNull($fingerprint);
    }

    public function test_version_in_range_checks_all_bounds(): void
    {
        $range = [
            'versionStartIncluding' => '2.4.0',
            'versionEndExcluding' => '2.4.50',
        ];

        $this->assertTrue($this->matcher->versionInRange('2.4.0', $range));
        $this->assertTrue($this->matcher->versionInRange('2.4.49', $range));
        $this->assertFalse($this->matcher->versionInRange('2.3.9', $range));
        $this->assertFalse($this->matcher->versionInRange('2.4.50', $range));
    }

    public function test_version_in_range_with_exclusive_start(): void
    {
        $range = [
            'versionStartExcluding' => '1.0.0',
            'versionEndIncluding' => '1.2.0',
        ];

        $this->assertTrue($this->matcher->versionInRange('1.0.1', $range));
        $this->assertFalse($this->matcher->versionInRange('1.0.0', $range));
        $this->assertTrue($this->matcher->versionInRange('1.2.0', $range));
    }

    public function test_version_in_any_range_matches_any_entry(): void
    {
        $ranges = [
            ['versionEndIncluding' => '1.0.0'],
            ['versionStartIncluding' => '2.0.0'],
        ];

        $this->assertTrue($this->matcher->versionInAnyRange('1.0.0', $ranges));
        $this->assertTrue($this->matcher->versionInAnyRange('2.0.0', $ranges));
        $this->assertFalse($this->matcher->versionInAnyRange('1.5.0', $ranges));
    }

    public function test_empty_ranges_never_match(): void
    {
        $this->assertFalse($this->matcher->versionInAnyRange('1.0.0', []));
    }
}
