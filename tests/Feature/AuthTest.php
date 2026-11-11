<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_sees_landing_page(): void
    {
        $this->get('/')->assertOk()->assertSee('GardaCyber');
    }

    public function test_new_account_lands_on_terms_gate(): void
    {
        $this->post('/signup', [
            'email' => 'new@example.com',
            'display_name' => 'New User',
            'password' => 'hackathon',
            'password_confirmation' => 'hackathon',
        ])->assertRedirect(route('terms.accept'));

        $this->assertAuthenticated();
        $this->assertDatabaseHas('users', ['email' => 'new@example.com']);
    }

    public function test_registration_validates_unique_email_and_min_password(): void
    {
        User::factory()->create(['email' => 'taken@example.com']);

        $this->post('/signup', [
            'email' => 'taken@example.com',
            'password' => 'short',
            'password_confirmation' => 'short',
        ])->assertSessionHasErrors(['email', 'password']);
    }

    public function test_login_logout_cycle(): void
    {
        $user = User::factory()->create(['password' => 'password']);

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($user);

        $this->post('/logout')->assertRedirect(route('landing'));
        $this->assertGuest();
    }

    public function test_wrong_credentials_are_rejected(): void
    {
        $this->post('/login', [
            'email' => 'nobody@example.com',
            'password' => 'wrong-password',
        ])->assertSessionHasErrors('email');

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
