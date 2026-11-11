<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\Finding;
use App\Models\Report;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DemoSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_default_seeding_does_not_create_production_demo_account(): void
    {
        $this->app->instance('env', 'production');

        $this->artisan('db:seed', ['--class' => DatabaseSeeder::class, '--force' => true])->assertSuccessful();

        $this->assertDatabaseCount('users', 0);
    }

    public function test_local_demo_is_idempotent_safe_and_preserves_existing_data(): void
    {
        $this->freezeTime();
        $existing = User::factory()->create();
        $this->app->instance('env', 'local');

        $this->seed(DatabaseSeeder::class);
        $this->seed(DatabaseSeeder::class);

        $this->assertModelExists($existing);
        $this->assertDatabaseCount('users', 2);
        $this->assertDatabaseCount('asset_groups', 3);
        $this->assertDatabaseCount('assets', 3);
        $this->assertDatabaseCount('findings', 5);
        $this->assertDatabaseCount('scans', 3);
        $this->assertDatabaseCount('reports', 3);
        foreach (Asset::all() as $asset) {
            $this->assertStringEndsWith('.gardacyber.test', $asset->hostname);
            $this->assertStringStartsWith('192.0.2.', $asset->ip_address);
        }
        foreach (Finding::all() as $finding) {
            $this->assertTrue($finding->first_seen_at->lessThan($finding->last_seen_at));
        }
        foreach (Report::all() as $report) {
            $this->assertCount($report->item_count, $report->payload);
        }
    }
}
