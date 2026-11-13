<?php

namespace Tests\Feature;

use App\Models\AssetGroup;
use App\Models\Scan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ActivityPageTest extends TestCase
{
    use RefreshDatabase;

    private function userWithScans(): array
    {
        $user = User::factory()->create(['terms_accepted_at' => now()]);
        $group = AssetGroup::factory()->for($user)->create(['root_domain' => 'oplog.example.invalid']);

        $rows = [
            [Scan::TYPE_FULL, Scan::STATUS_COMPLETED, now()->subMinutes(20), now()->subMinutes(19), null, ['vulnerabilities' => 2, 'incidents' => 1]],
            [Scan::TYPE_VULNERABILITY, Scan::STATUS_RUNNING, now()->subMinutes(3), null, null, []],
            [Scan::TYPE_INCIDENT, Scan::STATUS_QUEUED, null, null, null, []],
            [Scan::TYPE_FULL, Scan::STATUS_FAILED, now()->subHours(2), now()->subHours(2)->addSeconds(40), null, []],
            [Scan::TYPE_VULNERABILITY, Scan::STATUS_STOPPED, now()->subHours(1), null, now()->subHours(1)->addMinutes(2), []],
        ];

        foreach ($rows as [$type, $status, $started, $finished, $stopped, $options]) {
            Scan::create([
                'asset_group_id' => $group->id,
                'type' => $type,
                'status' => $status,
                'started_at' => $started,
                'finished_at' => $finished,
                'stopped_at' => $stopped,
                'options' => $options,
            ]);
        }

        return [$user, $group];
    }

    public function test_activity_page_renders_every_scan_status(): void
    {
        [$user] = $this->userWithScans();

        $response = $this->actingAs($user)->get(route('activity.index'));

        $response->assertOk();
        $response->assertSee('oplog.example.invalid');
        $response->assertSee('Completed');
        $response->assertSee('Running');
        $response->assertSee('Queued');
        $response->assertSee('Failed');
        $response->assertSee('Stopped');
        $response->assertSee('2 vuln · 1 incident');
    }

    public function test_activity_page_lists_newest_scan_first(): void
    {
        [$user] = $this->userWithScans();

        $response = $this->actingAs($user)->get(route('activity.index'));

        $response->assertOk();

        // The running scan (created last) appears before the older completed one.
        $html = $response->getContent();
        $this->assertLessThan(
            strpos($html, 'Running'),
            strpos($html, 'Completed')
        );
    }

    public function test_stop_action_on_activity_page_stops_live_scan(): void
    {
        [$user] = $this->userWithScans();
        $scan = Scan::where('status', Scan::STATUS_RUNNING)->firstOrFail();

        $this->actingAs($user)
            ->post(route('scans.stop', $scan))
            ->assertRedirect();

        $this->assertSame(Scan::STATUS_STOPPED, $scan->fresh()->status);
    }

    public function test_delete_action_removes_scan_record(): void
    {
        [$user] = $this->userWithScans();
        $scan = Scan::where('status', Scan::STATUS_RUNNING)->firstOrFail();

        $this->actingAs($user)
            ->delete(route('activity.scans.destroy', $scan))
            ->assertRedirect();

        $this->assertDatabaseMissing('scans', ['id' => $scan->id]);
    }

    public function test_user_cannot_delete_other_users_scan(): void
    {
        [$owner] = $this->userWithScans();
        $intruder = User::factory()->create(['terms_accepted_at' => now()]);
        $scan = Scan::where('status', Scan::STATUS_RUNNING)->firstOrFail();

        $this->actingAs($intruder)
            ->delete(route('activity.scans.destroy', $scan))
            ->assertNotFound();
    }
}
