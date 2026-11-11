<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TelegramAlertTest extends TestCase
{
    use RefreshDatabase;

    private function signedInUser(array $attributes = []): User
    {
        return User::factory()->create(array_merge([
            'terms_accepted_at' => now(),
        ], $attributes));
    }

    public function test_telegram_test_requires_authentication(): void
    {
        $this->postJson(route('profile.telegram-test'))
            ->assertUnauthorized();
    }

    public function test_telegram_test_returns_422_with_formatted_markdown_when_unconfigured(): void
    {
        $user = $this->signedInUser();

        $response = $this->actingAs($user)
            ->postJson(route('profile.telegram-test'))
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonStructure([
                'success',
                'message',
                'formatted_text',
                'payload' => [
                    'event',
                    'signal',
                    'asset',
                    'severity',
                    'detected_at',
                    'markdown',
                ],
            ]);

        $this->assertStringContainsString('*🚨 GardaCyber Security Alert*', $response->json('formatted_text'));
        $this->assertStringContainsString('*Signal:*', $response->json('formatted_text'));
    }

    public function test_telegram_test_dispatches_markdown_alert_to_bot_api(): void
    {
        $user = $this->signedInUser([
            'telegram_bot_token' => '123456:ABC-DEF1234ghIkl-zyx57W2v1u123ew11',
            'telegram_chat_id' => '-100987654321',
        ]);

        Http::fake([
            'https://api.telegram.org/bot123456:ABC-DEF1234ghIkl-zyx57W2v1u123ew11/sendMessage' => Http::response([
                'ok' => true,
                'result' => ['message_id' => 999],
            ], 200),
        ]);

        $response = $this->actingAs($user)
            ->postJson(route('profile.telegram-test'), [
                'signal' => 'Gambling Injection Detected',
                'asset' => 'shop.gardacyber.test',
            ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('delivered', true);

        Http::assertSent(function ($request) {
            return $request->url() === 'https://api.telegram.org/bot123456:ABC-DEF1234ghIkl-zyx57W2v1u123ew11/sendMessage'
                && $request['chat_id'] === '-100987654321'
                && $request['parse_mode'] === 'Markdown'
                && str_contains($request['text'], 'Gambling Injection Detected')
                && str_contains($request['text'], 'shop.gardacyber.test');
        });
    }

    public function test_telegram_test_dispatches_to_webhook_url_when_provided(): void
    {
        $user = $this->signedInUser();

        Http::fake([
            'https://custom-webhook.example.com/alerts' => Http::response(['received' => true], 200),
        ]);

        $response = $this->actingAs($user)
            ->postJson(route('profile.telegram-test'), [
                'webhook_url' => 'https://custom-webhook.example.com/alerts',
                'signal' => 'Defacement Signal Detected',
            ])
            ->assertOk()
            ->assertJsonPath('success', true);

        Http::assertSent(function ($request) {
            return $request->url() === 'https://custom-webhook.example.com/alerts'
                && $request['parse_mode'] === 'Markdown'
                && str_contains($request['text'], 'Defacement Signal Detected');
        });
    }

    public function test_telegram_test_handles_telegram_api_rejection_gracefully(): void
    {
        $user = $this->signedInUser();

        Http::fake([
            'https://api.telegram.org/botinvalid-token/sendMessage' => Http::response([
                'ok' => false,
                'error_code' => 401,
                'description' => 'Unauthorized',
            ], 401),
        ]);

        $response = $this->actingAs($user)
            ->postJson(route('profile.telegram-test'), [
                'bot_token' => 'invalid-token',
                'chat_id' => '12345',
            ])
            ->assertStatus(422)
            ->assertJsonPath('success', false);

        $this->assertStringContainsString('Telegram API error', $response->json('message'));
    }
}
