<?php

namespace Tests\Feature;

use App\Models\AssetGroup;
use App\Models\Scan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class SingleHostScanTest extends TestCase
{
    use RefreshDatabase;

    private function signedInUser(): User
    {
        return User::factory()->create(['terms_accepted_at' => now()]);
    }

    public function test_single_host_flag_is_recorded_on_new_asset_group_and_scan(): void
    {
        Queue::fake();
        $user = $this->signedInUser();

        $response = $this->actingAs($user)
            ->post(route('scans.store'), [
                'domain' => 'training.gardacyber.test',
                'mode' => 'demo',
                'type' => 'full',
                'authorized' => '1',
                'single_host' => '1',
            ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect();

        $group = AssetGroup::where('user_id', $user->id)
            ->where('root_domain', 'training.gardacyber.test')
            ->first();

        $this->assertNotNull($group);
        $this->assertTrue($group->single_host);

        $scan = Scan::where('asset_group_id', $group->id)->first();
        $this->assertNotNull($scan);
        $this->assertTrue($scan->options['single_host'] ?? false);
    }

    public function test_single_host_flag_defaults_to_false_when_omitted(): void
    {
        Queue::fake();
        $user = $this->signedInUser();

        $this->actingAs($user)
            ->post(route('scans.store'), [
                'domain' => 'training.gardacyber.test',
                'mode' => 'demo',
                'type' => 'full',
                'authorized' => '1',
            ])
            ->assertRedirect();

        $group = AssetGroup::where('user_id', $user->id)->first();
        $this->assertFalse($group->single_host);
    }

    public function test_single_host_option_updates_existing_asset_group(): void
    {
        Queue::fake();
        $user = $this->signedInUser();
        $group = AssetGroup::factory()->for($user)->create([
            'root_domain' => 'training.gardacyber.test',
            'single_host' => false,
        ]);

        $this->actingAs($user)
            ->post(route('scans.store'), [
                'group_id' => $group->id,
                'mode' => 'demo',
                'type' => 'full',
                'authorized' => '1',
                'single_host' => '1',
            ])
            ->assertRedirect();

        $this->assertTrue($group->fresh()->single_host);
    }
}
