<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\AssetGroup;
use App\Models\Finding;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AiIntelligenceTest extends TestCase
{
    use RefreshDatabase;

    private function signedInUser(array $attributes = []): User
    {
        return User::factory()->create(array_merge([
            'terms_accepted_at' => now(),
        ], $attributes));
    }

    public function test_ai_explain_requires_authentication(): void
    {
        $finding = Finding::factory()->open()->create();

        $this->postJson(route('findings.ai-explain', $finding))
            ->assertUnauthorized();
    }

    public function test_ai_explain_strictly_returns_404_on_cross_tenant_access(): void
    {
        $owner = $this->signedInUser();
        $intruder = $this->signedInUser();
        $group = AssetGroup::factory()->for($owner)->create();
        $finding = Finding::factory()->open()->create(['asset_group_id' => $group->id]);

        $this->actingAs($intruder)
            ->postJson(route('findings.ai-explain', $finding))
            ->assertNotFound();
    }

    public function test_ai_explain_falls_back_to_remediation_advisor_when_unconfigured(): void
    {
        $user = $this->signedInUser();
        $group = AssetGroup::factory()->for($user)->create();
        $finding = Finding::factory()->open()->create([
            'asset_group_id' => $group->id,
            'category' => 'security-headers',
            'title' => 'Missing HSTS',
        ]);

        $response = $this->actingAs($user)
            ->postJson(route('findings.ai-explain', $finding))
            ->assertOk()
            ->assertJsonStructure([
                'analysis',
                'root_cause',
                'remediation',
                'commands',
                'source',
                'verification',
            ]);

        $this->assertSame('fallback', $response->json('source'));
        $this->assertNotEmpty($response->json('analysis'));
        $this->assertNotEmpty($response->json('remediation'));
    }

    public function test_ai_explain_calls_openai_provider_when_configured(): void
    {
        $user = $this->signedInUser([
            'ai_provider' => 'openai',
            'ai_api_key' => 'sk-test-key-12345',
            'ai_model' => 'gpt-4o-mini',
        ]);
        $group = AssetGroup::factory()->for($user)->create();
        $finding = Finding::factory()->open()->create([
            'asset_group_id' => $group->id,
            'category' => 'defacement',
            'title' => 'Defacement detected',
        ]);

        Http::fake([
            'https://api.openai.com/v1/chat/completions' => Http::response([
                'choices' => [
                    [
                        'message' => [
                            'content' => json_encode([
                                'analysis' => 'AI detected unauthorized webroot tamper via file upload vulnerability.',
                                'root_cause' => 'Unrestricted file upload in legacy admin portal.',
                                'remediation' => [
                                    'Quarantine affected host immediately.',
                                    'Restore webroot from verified clean backup.',
                                ],
                                'commands' => [
                                    'systemctl stop nginx',
                                    'chown -R root:www-data /var/www/html',
                                ],
                                'config_snippet' => 'deny all;',
                                'verification' => 'curl -I https://example.test',
                            ]),
                        ],
                    ],
                ],
            ], 200),
        ]);

        $response = $this->actingAs($user)
            ->postJson(route('findings.ai-explain', $finding))
            ->assertOk()
            ->assertJsonPath('source', 'ai')
            ->assertJsonPath('root_cause', 'Unrestricted file upload in legacy admin portal.')
            ->assertJsonPath('commands.0', 'systemctl stop nginx');

        Http::assertSent(function ($request) {
            return str_contains($request->url(), '/chat/completions')
                && $request->hasHeader('Authorization', 'Bearer sk-test-key-12345');
        });
    }

    public function test_ai_explain_falls_back_when_ai_provider_fails_or_times_out(): void
    {
        $user = $this->signedInUser([
            'ai_provider' => 'openai',
            'ai_api_key' => 'sk-bad-key',
        ]);
        $group = AssetGroup::factory()->for($user)->create();
        $finding = Finding::factory()->open()->create(['asset_group_id' => $group->id]);

        Http::fake([
            'https://api.openai.com/v1/chat/completions' => Http::response('Server error', 500),
        ]);

        $response = $this->actingAs($user)
            ->postJson(route('findings.ai-explain', $finding))
            ->assertOk()
            ->assertJsonPath('source', 'fallback');

        $this->assertNotEmpty($response->json('analysis'));
    }

    public function test_ai_summary_requires_authentication(): void
    {
        $group = AssetGroup::factory()->create();

        $this->postJson(route('groups.ai-summary', $group))
            ->assertUnauthorized();
    }

    public function test_ai_summary_strictly_returns_404_on_cross_tenant_access(): void
    {
        $owner = $this->signedInUser();
        $intruder = $this->signedInUser();
        $group = AssetGroup::factory()->for($owner)->create();

        $this->actingAs($intruder)
            ->postJson(route('groups.ai-summary', $group))
            ->assertNotFound();
    }

    public function test_ai_summary_generates_density_weighted_fallback_when_unconfigured(): void
    {
        $user = $this->signedInUser();
        $group = AssetGroup::factory()->for($user)->create(['root_domain' => 'testcorp.example']);
        $asset = Asset::factory()->create(['asset_group_id' => $group->id, 'hostname' => 'api.testcorp.example']);

        Finding::factory()->open()->vulnerability()->severity('critical')->create([
            'asset_group_id' => $group->id,
            'asset_id' => $asset->id,
            'title' => 'Exposed Debug Endpoint',
        ]);
        Finding::factory()->open()->incident()->severity('high')->create([
            'asset_group_id' => $group->id,
            'asset_id' => $asset->id,
            'title' => 'Gambling Injection Detected',
        ]);

        $response = $this->actingAs($user)
            ->postJson(route('groups.ai-summary', $group))
            ->assertOk()
            ->assertJsonStructure([
                'summary',
                'risk_assessment',
                'action_items',
                'density',
                'ratings',
                'source',
            ]);

        $this->assertSame('fallback', $response->json('source'));
        $this->assertCount(3, $response->json('action_items'));
        $this->assertStringContainsString('Density-weighted risk', $response->json('risk_assessment'));
        $this->assertGreaterThan(0, $response->json('density'));
    }

    public function test_ai_summary_calls_provider_when_configured(): void
    {
        $user = $this->signedInUser([
            'ai_provider' => '9router',
            'ai_api_key' => '9router-key-xyz',
            'ai_base_url' => 'https://9router.net/v1',
            'ai_model' => 'gpt-4o-mini',
        ]);
        $group = AssetGroup::factory()->for($user)->create(['root_domain' => 'portal.example']);

        Http::fake([
            'https://9router.net/v1/chat/completions' => Http::response([
                'choices' => [
                    [
                        'message' => [
                            'content' => json_encode([
                                'summary' => 'Perimeter is in heightened risk due to critical administrative exposures.',
                                'risk_assessment' => 'Density calculation indicates critical density (3.0 issues/host). Immediate action required.',
                                'action_items' => [
                                    'Patch administrative portal bypass on portal.example.',
                                    'Revoke exposed staging secrets.',
                                    'Enforce HSTS across perimeter.',
                                ],
                            ]),
                        ],
                    ],
                ],
            ], 200),
        ]);

        $response = $this->actingAs($user)
            ->postJson(route('groups.ai-summary', $group));

        $response->assertOk()
            ->assertJsonPath('source', 'ai')
            ->assertJsonPath('action_items.0', 'Patch administrative portal bypass on portal.example.');

        $this->assertCount(3, $response->json('action_items'));

        Http::assertSent(fn ($req) => str_contains($req->url(), '9router.net'));
    }

    public function test_ai_settings_can_be_saved_via_profile_and_ai_settings_routes(): void
    {
        $user = $this->signedInUser();

        // Save via profile PATCH
        $this->actingAs($user)
            ->patch(route('profile.update'), [
                'ai_provider' => 'openai',
                'ai_base_url' => 'https://api.openai.com/v1',
                'ai_api_key' => 'sk-my-key',
                'ai_model' => 'gpt-4o',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'ai_provider' => 'openai',
            'ai_model' => 'gpt-4o',
        ]);

        // Save via ai-settings POST/PATCH
        $this->actingAs($user)
            ->postJson(route('ai-settings.store'), [
                'ai_provider' => 'anthropic',
                'ai_base_url' => 'https://api.anthropic.com',
                'ai_api_key' => 'ant-key-123',
                'ai_model' => 'claude-3-5-sonnet-20241022',
            ])
            ->assertOk()
            ->assertJsonPath('status', 'success');

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'ai_provider' => 'anthropic',
            'ai_model' => 'claude-3-5-sonnet-20241022',
        ]);
    }

    public function test_ai_settings_test_connection_endpoint_fails_when_unconfigured(): void
    {
        $user = $this->signedInUser();

        $this->actingAs($user)
            ->postJson(route('ai-settings.test'), [])
            ->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    public function test_ai_settings_test_connection_endpoint_succeeds_with_valid_provider(): void
    {
        $user = $this->signedInUser();

        Http::fake([
            'https://api.openai.com/v1/chat/completions' => Http::response([
                'choices' => [
                    ['message' => ['content' => '{"pong": true}']],
                ],
            ], 200),
        ]);

        $this->actingAs($user)
            ->postJson(route('ai-settings.test'), [
                'ai_provider' => 'openai',
                'ai_api_key' => 'sk-valid-key',
                'ai_model' => 'gpt-4o-mini',
            ])
            ->assertOk()
            ->assertJsonPath('success', true);
    }

    public function test_ai_settings_test_connection_endpoint_fails_when_remote_provider_rejects(): void
    {
        $user = $this->signedInUser();

        Http::fake([
            'https://api.openai.com/v1/chat/completions' => Http::response('Unauthorized', 401),
        ]);

        $this->actingAs($user)
            ->postJson(route('ai-settings.test'), [
                'ai_provider' => 'openai',
                'ai_api_key' => 'sk-invalid-key',
            ])
            ->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    public function test_ai_explain_calls_anthropic_provider_when_configured(): void
    {
        $user = $this->signedInUser([
            'ai_provider' => 'anthropic',
            'ai_api_key' => 'ant-test-key-999',
            'ai_model' => 'claude-3-5-haiku-20241022',
        ]);
        $group = AssetGroup::factory()->for($user)->create();
        $finding = Finding::factory()->open()->create([
            'asset_group_id' => $group->id,
            'category' => 'exposure',
            'title' => 'Exposed .git directory',
        ]);

        Http::fake([
            'https://api.anthropic.com/v1/messages' => Http::response([
                'content' => [
                    [
                        'text' => json_encode([
                            'analysis' => 'Publicly accessible .git folder exposes entire commit history.',
                            'root_cause' => 'Web server configuration missing rule to deny dotfiles.',
                            'remediation' => [
                                'Remove .git from webroot.',
                                'Add Nginx deny rule for hidden files.',
                            ],
                            'commands' => [
                                'rm -rf /var/www/html/.git',
                            ],
                            'config_snippet' => 'location ~ /\\.(?!well-known) { deny all; }',
                            'verification' => 'curl -I https://example.test/.git/HEAD',
                        ]),
                    ],
                ],
            ], 200),
        ]);

        $response = $this->actingAs($user)
            ->postJson(route('findings.ai-explain', $finding))
            ->assertOk()
            ->assertJsonPath('source', 'ai')
            ->assertJsonPath('root_cause', 'Web server configuration missing rule to deny dotfiles.')
            ->assertJsonPath('commands.0', 'rm -rf /var/www/html/.git');

        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'api.anthropic.com/v1/messages')
                && $request->hasHeader('x-api-key', 'ant-test-key-999')
                && $request->hasHeader('anthropic-version', '2023-06-01');
        });
    }
}
