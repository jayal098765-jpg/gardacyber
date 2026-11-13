<?php

namespace Tests\Feature;

use App\Models\AssetGroup;
use App\Models\Finding;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    private function signedInUser(): User
    {
        $user = User::factory()->create(['terms_accepted_at' => now()]);
        $group = AssetGroup::factory()->create(['user_id' => $user->id]);

        Finding::factory()->count(3)->open()->vulnerability()->severity('critical')->create(['asset_group_id' => $group->id]);
        Finding::factory()->count(2)->open()->incident()->severity('high')->create(['asset_group_id' => $group->id]);

        return $user;
    }

    public function test_dashboard_renders_aggregates(): void
    {
        $user = $this->signedInUser();

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertViewIs('app.dashboard')
            ->assertSee('Vulnerabilitas Terbuka')
            ->assertSee('Insiden Terbuka');
    }

    public function test_dashboard_requires_terms(): void
    {
        $user = User::factory()->create(['terms_accepted_at' => null]);

        $this->actingAs($user)->get(route('dashboard'))->assertRedirect(route('terms.accept'));
    }

    public function test_dashboard_shows_severity_banner_when_critical_open(): void
    {
        $user = $this->signedInUser();

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk()->assertSee('eksposur kritis butuh perhatian');
    }
}
