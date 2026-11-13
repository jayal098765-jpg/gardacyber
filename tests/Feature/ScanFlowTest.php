<?php

namespace Tests\Feature;

use App\Jobs\RunSurfaceScan;
use App\Models\Asset;
use App\Models\AssetGroup;
use App\Models\Finding;
use App\Models\Scan;
use App\Models\User;
use App\Services\SecurityRatingService;
use App\Services\SurfaceScanner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ScanFlowTest extends TestCase
{
    use RefreshDatabase;

    private function signedInUser(): User
    {
        return User::factory()->create(['terms_accepted_at' => now()]);
    }

    public function test_new_scan_creates_group_and_queues_job(): void
    {
        Queue::fake();
        $user = $this->signedInUser();

        $response = $this->actingAs($user)
            ->post(route('scans.store'), [
                'domain' => 'training.gardacyber.test',
                'type' => 'full',
                'authorized' => '1',
            ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect();

        $this->assertDatabaseHas('asset_groups', ['root_domain' => 'training.gardacyber.test']);
        $this->assertDatabaseHas('scans', [
            'type' => 'full',
            'status' => 'queued',
        ]);

        Queue::assertPushed(RunSurfaceScan::class);
    }

    public function test_live_scan_fails_validation_when_not_in_allowed_domains(): void
    {
        $user = $this->signedInUser();
        Config::set('scanner.allow_any_public', false);
        Config::set('scanner.allowed_domains', ['corp.gardacyber.test']);

        $response = $this->actingAs($user)
            ->post(route('scans.store'), [
                'domain' => 'unauthorized.test',
                'type' => 'full',
                'authorized' => '1',
            ]);

        $response->assertSessionHasErrors('domain');
        $this->assertDatabaseCount('scans', 0);
    }

    public function test_live_scan_accepts_any_public_domain_when_open_mode_enabled(): void
    {
        Queue::fake();
        $user = $this->signedInUser();
        Config::set('scanner.allow_any_public', true);

        $response = $this->actingAs($user)
            ->post(route('scans.store'), [
                'domain' => 'any-target.example.org',
                'type' => 'full',
                'authorized' => '1',
            ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect();
        Queue::assertPushed(RunSurfaceScan::class);
    }

    public function test_running_surface_scan_job_populates_assets_and_findings(): void
    {
        $user = $this->signedInUser();
        $group = AssetGroup::factory()->create([
            'user_id' => $user->id,
            'root_domain' => 'example.com',
        ]);

        $scan = Scan::create([
            'asset_group_id' => $group->id,
            'type' => 'full',
            'status' => Scan::STATUS_QUEUED,
            'options' => ['stage' => 0],
            'stage_count' => 4,
        ]);

        $fakeScanner = new class extends SurfaceScanner
        {
            public function capture(string $hostname): array
            {
                return [
                    'ip' => '93.184.216.34',
                    'status' => 200,
                    'headers' => ['content-type' => ['text/html; charset=utf-8'], 'server' => ['nginx/1.24.0']],
                    'body' => '<html><head><title>Example Domain</title></head><body><h1>Example</h1></body></html>',
                    'truncated' => false,
                ];
            }

            public function discoverSubdomains(string $rootDomain): array
            {
                return [];
            }

            public function probePorts(string $ip): array
            {
                return [80, 443];
            }

            public function probeActiveVulnerabilities(string $hostname, string $ip): array
            {
                return [];
            }

            public function probeAdvancedXss(string $hostname, string $ip): array
            {
                return [];
            }

            public function probeAuthBruteForceResistance(string $hostname, string $ip, array $capture): array
            {
                return [];
            }

            public function probeSubdomainTakeover(array $subdomains): array
            {
                return [];
            }
        };

        $job = new RunSurfaceScan($scan->id);
        $job->handle($fakeScanner, app(SecurityRatingService::class));

        $this->assertSame(Scan::STATUS_COMPLETED, $scan->fresh()->status);
        $this->assertGreaterThan(0, Asset::where('asset_group_id', $group->id)->count());
        $this->assertGreaterThan(0, Finding::where('asset_group_id', $group->id)->count());

        // Check progress response
        $this->actingAs($user)
            ->getJson(route('scans.progress', $scan))
            ->assertOk()
            ->assertJsonPath('finished', true)
            ->assertJsonPath('percent', 100)
            ->assertJsonPath('status', 'completed');
    }

    public function test_scan_can_be_stopped(): void
    {
        $user = $this->signedInUser();
        $group = AssetGroup::factory()->create(['user_id' => $user->id]);

        $scan = Scan::create([
            'asset_group_id' => $group->id,
            'type' => 'full',
            'status' => Scan::STATUS_RUNNING,
            'started_at' => now(),
            'options' => ['stage' => 1],
        ]);

        $this->actingAs($user)->post(route('scans.stop', $scan))->assertRedirect();
        $this->assertEquals(Scan::STATUS_STOPPED, $scan->fresh()->status);
    }

    public function test_user_cannot_touch_other_users_scan(): void
    {
        $owner = $this->signedInUser();
        $intruder = $this->signedInUser();
        $group = AssetGroup::factory()->create(['user_id' => $owner->id]);
        $scan = Scan::factory()->create(['asset_group_id' => $group->id]);

        $this->actingAs($intruder)
            ->getJson(route('scans.progress', $scan))
            ->assertNotFound();

        $this->actingAs($intruder)->get(route('scans.show', $scan))->assertNotFound();
        $this->actingAs($intruder)->post(route('scans.stop', $scan))->assertNotFound();
    }

    public function test_user_cannot_start_scan_for_another_users_group(): void
    {
        $group = AssetGroup::factory()->create();
        $intruder = $this->signedInUser();

        $this->actingAs($intruder)->post(route('scans.store'), [
            'group_id' => $group->id,
            'type' => 'full',
            'authorized' => '1',
        ])->assertNotFound();

        $this->assertDatabaseCount('scans', 0);
    }

    public function test_unauthorized_checkbox_is_required(): void
    {
        $this->actingAs($this->signedInUser())->post(route('scans.store'), [
            'domain' => 'training.gardacyber.test',
            'type' => 'full',
        ])->assertSessionHasErrors('authorized');

        $this->assertDatabaseCount('scans', 0);
    }
}
