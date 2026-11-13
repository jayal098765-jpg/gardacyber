<?php

namespace Tests\Feature;

use App\Models\AssetGroup;
use App\Models\Finding;
use App\Models\User;
use App\Services\SecurityRatingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class BackendRegressionTest extends TestCase
{
    use RefreshDatabase;

    private function fakeRecaptchaOk(): void
    {
        Http::fake([
            'https://www.google.com/recaptcha/api/siteverify' => Http::response(['success' => true]),
        ]);
    }

    public function test_dashboard_selects_worst_severity_and_excludes_other_workspaces(): void
    {
        $user = User::factory()->create(['terms_accepted_at' => now()]);
        $group = AssetGroup::factory()->for($user)->create();
        Finding::factory()->open()->vulnerability()->severity('low')->create(['asset_group_id' => $group->id]);
        $critical = Finding::factory()->open()->vulnerability()->severity('critical')->create(['asset_group_id' => $group->id]);
        Finding::factory()->open()->incident()->severity('low')->create(['asset_group_id' => $group->id]);
        $incident = Finding::factory()->open()->incident()->severity('high')->create(['asset_group_id' => $group->id]);
        Finding::factory()->open()->create(['title' => 'Another workspace secret']);

        $this->actingAs($user)->get(route('dashboard'))->assertOk()
            ->assertViewHas('worstVuln', fn (Finding $finding): bool => $finding->is($critical))
            ->assertViewHas('worstIncident', fn (Finding $finding): bool => $finding->is($incident))
            ->assertViewHas('openVulns', fn ($findings): bool => $findings->count() === 2)
            ->assertDontSee('Another workspace secret');
    }

    public function test_group_ratings_load_findings_in_one_query(): void
    {
        $groups = AssetGroup::factory()->count(3)->create();
        Finding::factory()->open()->vulnerability()->severity('high')->create(['asset_group_id' => $groups->first()->id]);
        DB::enableQueryLog();
        DB::flushQueryLog();

        $ratings = app(SecurityRatingService::class)->rateGroups($groups);
        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        $this->assertCount(1, $queries);
        $this->assertSame(78, $ratings[0]['vuln']['score']);
        $this->assertSame(100, $ratings[1]['vuln']['score']);
    }

    public function test_profile_update_without_optional_fields_preserves_names(): void
    {
        $user = User::factory()->create(['terms_accepted_at' => now(), 'display_name' => 'Name', 'workspace_name' => 'Workspace']);

        $this->actingAs($user)->patch(route('profile.update'), ['email' => 'new@gardacyber.test'])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertDatabaseHas('users', ['id' => $user->id, 'email' => 'new@gardacyber.test', 'display_name' => 'Name', 'workspace_name' => 'Workspace']);
    }

    public function test_login_attempts_are_throttled(): void
    {
        $this->fakeRecaptchaOk();

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->post('/login', ['email' => 'missing@gardacyber.test', 'password' => 'wrong', 'g-recaptcha-response' => 'dummy-token'])->assertSessionHasErrors('email');
        }

        $this->post('/login', ['email' => 'missing@gardacyber.test', 'password' => 'wrong', 'g-recaptcha-response' => 'dummy-token'])->assertTooManyRequests();
        $this->assertGuest();
    }

    public function test_signup_attempts_are_throttled(): void
    {
        $this->fakeRecaptchaOk();

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->post('/signup', ['g-recaptcha-response' => 'dummy-token'])->assertSessionHasErrors(['email', 'password']);
        }

        $this->post('/signup', ['g-recaptcha-response' => 'dummy-token'])->assertTooManyRequests();
        $this->assertDatabaseCount('users', 0);
    }

    public function test_storyboard_renders_attack_phases_for_group(): void
    {
        $user = User::factory()->create(['terms_accepted_at' => now()]);
        $group = AssetGroup::factory()->for($user)->create(['root_domain' => 'story.test']);

        Finding::factory()->open()->vulnerability()->severity('high')->create([
            'asset_group_id' => $group->id,
            'category' => 'port-fingerprint',
            'title' => 'Open database port exposed',
        ]);

        Finding::factory()->open()->incident()->severity('critical')->create([
            'asset_group_id' => $group->id,
            'title' => 'Gambling injection detected',
        ]);

        $this->actingAs($user)
            ->get(route('groups.storyboard', $group))
            ->assertOk()
            ->assertSee('Papan Cerita Serangan')
            ->assertSee('Reconnaissance')
            ->assertSee('Compromise')
            ->assertSee('Open database port exposed')
            ->assertSee('Gambling injection detected');
    }

    public function test_storyboard_is_scoped_to_owner(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create(['terms_accepted_at' => now()]);
        $group = AssetGroup::factory()->for($owner)->create();

        $this->actingAs($intruder)
            ->get(route('groups.storyboard', $group))
            ->assertNotFound();
    }

    public function test_signup_regenerates_session_token_and_ignores_terms_override(): void
    {
        $this->fakeRecaptchaOk();

        $this->get('/signup')->assertOk();
        $token = session()->token();

        $this->post('/signup', [
            'email' => 'new@gardacyber.test',
            'password' => 'strong-password',
            'password_confirmation' => 'strong-password',
            'terms_accepted_at' => now()->toDateTimeString(),
            'g-recaptcha-response' => 'dummy-token',
        ])->assertRedirect(route('terms.accept'));

        $this->assertAuthenticated();
        $this->assertNotSame($token, session()->token());
        $this->assertDatabaseHas('users', ['email' => 'new@gardacyber.test', 'terms_accepted_at' => null]);
    }
}
