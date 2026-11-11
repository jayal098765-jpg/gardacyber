<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    private function fakeRecaptchaOk(): void
    {
        Http::fake([
            'https://www.google.com/recaptcha/api/siteverify' => Http::response(['success' => true]),
        ]);
    }

    public function test_guest_sees_landing_page(): void
    {
        $this->get('/')->assertOk()->assertSee('GardaCyber');
    }

    public function test_new_account_lands_on_terms_gate(): void
    {
        $this->fakeRecaptchaOk();

        $this->post('/signup', [
            'email' => 'new@example.com',
            'display_name' => 'New User',
            'password' => 'hackathon',
            'password_confirmation' => 'hackathon',
            'g-recaptcha-response' => 'dummy-token',
        ])->assertRedirect(route('terms.accept'));

        $this->assertAuthenticated();
        $this->assertDatabaseHas('users', ['email' => 'new@example.com']);
    }

    public function test_registration_validates_unique_email_and_min_password(): void
    {
        $this->fakeRecaptchaOk();

        User::factory()->create(['email' => 'taken@example.com']);

        $this->post('/signup', [
            'email' => 'taken@example.com',
            'password' => 'short',
            'password_confirmation' => 'short',
            'g-recaptcha-response' => 'dummy-token',
        ])->assertSessionHasErrors(['email', 'password']);
    }

    public function test_login_logout_cycle(): void
    {
        $this->fakeRecaptchaOk();

        $user = User::factory()->create(['password' => 'password']);

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
            'g-recaptcha-response' => 'dummy-token',
        ])->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($user);

        $this->post('/logout')->assertRedirect(route('landing'));
        $this->assertGuest();
    }

    public function test_wrong_credentials_are_rejected(): void
    {
        $this->fakeRecaptchaOk();

        $this->post('/login', [
            'email' => 'nobody@example.com',
            'password' => 'wrong-password',
            'g-recaptcha-response' => 'dummy-token',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_signup_requires_recaptcha_token(): void
    {
        $this->post('/signup', [
            'email' => 'new@example.com',
            'password' => 'hackathon',
            'password_confirmation' => 'hackathon',
        ])->assertSessionHasErrors('recaptcha');

        $this->assertGuest();
    }

    public function test_login_rejects_low_recaptcha_score(): void
    {
        Http::fake([
            'https://www.google.com/recaptcha/api/siteverify' => Http::response(['success' => true, 'score' => 0.3]),
        ]);

        $user = User::factory()->create(['password' => 'password']);

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
            'g-recaptcha-response' => 'low-score-token',
        ])->assertSessionHasErrors('recaptcha');

        $this->assertGuest();
    }

    public function test_terms_gate_must_be_accepted_before_console(): void
    {
        $user = User::factory()->create(['terms_accepted_at' => null]);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertRedirect(route('terms.accept'));

        $this->actingAs($user)
            ->post(route('terms.accept'), [
                'accept_terms' => '1',
                'accept_authorized' => '1',
            ])
            ->assertRedirect(route('dashboard'));

        $this->assertNotNull($user->fresh()->terms_accepted_at);
    }
}
