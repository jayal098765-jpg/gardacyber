<?php

namespace Tests\Feature;

use App\Models\Cve;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CveSyncCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('cve.throttle_seconds', 0);

        Http::fake([
            'services.nvd.nist.gov/*' => Http::response($this->nvdPage(), 200),
            'https://www.cisa.gov/*' => Http::response($this->kevFeed(), 200),
        ]);
    }

    public function test_it_syncs_cves_and_flags_exploited_kev_entries(): void
    {
        $this->artisan('cve:sync', ['--year' => [2002], '--kev' => true])
            ->expectsOutputToContain('Syncing NVD CVE data')
            ->assertSuccessful();

        $this->assertDatabaseCount('cves', 2);

        $first = Cve::where('cve_id', 'CVE-2002-0001')->first();
        $this->assertNotNull($first);
        $this->assertSame('critical', $first->severity);
        $this->assertSame(9.8, $first->cvss_score);
        $this->assertTrue($first->exploited);
        $this->assertSame('apache', $first->vendor);
        $this->assertSame('http_server', $first->product);
        $this->assertContains('cpe:2.3:a:apache:http_server:2.4.49:*:*:*:*:*:*:*', $first->cpes);

        $second = Cve::where('cve_id', 'CVE-2002-0002')->first();
        $this->assertNotNull($second);
        $this->assertSame('high', $second->severity);
        $this->assertSame(7.5, $second->cvss_score);
        $this->assertFalse($second->exploited);
        $this->assertSame('2.4.0', $second->version_ranges[0]['versionStartIncluding']);
        $this->assertSame('2.4.50', $second->version_ranges[0]['versionEndExcluding']);
    }

    public function test_it_is_idempotent_on_second_run(): void
    {
        $this->artisan('cve:sync', ['--year' => [2002]])
            ->assertSuccessful();

        $this->artisan('cve:sync', ['--year' => [2002]])
            ->assertSuccessful();

        $this->assertDatabaseCount('cves', 2);
    }

    public function test_kev_only_mode_updates_flags(): void
    {
        Cve::create([
            'cve_id' => 'CVE-2002-0001',
            'description' => 'x',
            'exploited' => false,
        ]);

        $this->artisan('cve:sync', ['--kev-only' => true])
            ->assertSuccessful();

        $this->assertTrue(Cve::where('cve_id', 'CVE-2002-0001')->value('exploited'));
    }

    public function test_it_falls_back_to_full_pagination_when_date_queries_are_blocked(): void
    {
        Http::fake(function ($request) {
            if (str_contains($request->url(), 'pubStartDate')) {
                return Http::response('', 404);
            }

            return Http::response($this->nvdPage(), 200);
        });

        $this->artisan('cve:sync', ['--year' => [2002]])
            ->assertSuccessful();

        $this->assertDatabaseCount('cves', 2);
        $this->assertNotNull(Cve::where('cve_id', 'CVE-2002-0001')->first());
    }

    /**
     * @return array<string, mixed>
     */
    private function nvdPage(): array
    {
        return [
            'resultsPerPage' => 2000,
            'startIndex' => 0,
            'totalResults' => 2,
            'vulnerabilities' => [
                [
                    'cve' => [
                        'id' => 'CVE-2002-0001',
                        'published' => '2002-01-01T00:00:00.000Z',
                        'lastModified' => '2002-02-01T00:00:00.000Z',
                        'descriptions' => [
                            ['lang' => 'en', 'value' => 'Remote code execution in Apache.'],
                        ],
                        'metrics' => [
                            'cvssMetricV31' => [
                                ['cvssData' => ['baseScore' => 9.8, 'baseSeverity' => 'CRITICAL']],
                            ],
                        ],
                        'configurations' => [
                            'nodes' => [
                                [
                                    'nodes' => [],
                                    'cpe_match' => [
                                        [
                                            'vulnerable' => true,
                                            'criteria' => 'cpe:2.3:a:apache:http_server:2.4.49:*:*:*:*:*:*:*',
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
                [
                    'cve' => [
                        'id' => 'CVE-2002-0002',
                        'published' => '2002-03-01T00:00:00.000Z',
                        'lastModified' => '2002-04-01T00:00:00.000Z',
                        'descriptions' => [
                            ['lang' => 'en', 'value' => 'Path traversal in Apache 2.4.x.'],
                        ],
                        'metrics' => [
                            'cvssMetricV2' => [
                                ['cvssData' => ['baseScore' => 7.5]],
                            ],
                        ],
                        'configurations' => [
                            'nodes' => [
                                [
                                    'nodes' => [],
                                    'cpe_match' => [
                                        [
                                            'vulnerable' => true,
                                            'criteria' => 'cpe:2.3:a:apache:http_server:*:*:*:*:*:*:*:*',
                                            'versionStartIncluding' => '2.4.0',
                                            'versionEndExcluding' => '2.4.50',
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function kevFeed(): array
    {
        return [
            'title' => 'CISA KEV',
            'vulnerabilities' => [
                [
                    'cveID' => 'CVE-2002-0001',
                    'vendorProject' => 'Apache',
                    'product' => 'HTTP Server',
                    'vulnerabilityName' => 'Apache RCE',
                    'dateAdded' => '2021-11-03',
                    'shortDescription' => 'x',
                    'requiredAction' => 'x',
                    'dueDate' => '2022-05-03',
                    'knownRansomwareCampaignUse' => 'Unknown',
                    'notes' => '',
                ],
            ],
        ];
    }
}
