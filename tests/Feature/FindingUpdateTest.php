<?php

namespace Tests\Feature;

use App\Models\AssetGroup;
use App\Models\Finding;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class FindingUpdateTest extends TestCase
{
    use RefreshDatabase;

    #[TestWith(['resolved'])]
    #[TestWith(['false_positive'])]
    #[TestWith(['open'])]
    public function test_owner_can_change_only_status(string $status): void
    {
        $user = User::factory()->create(['terms_accepted_at' => now()]);
        $group = AssetGroup::factory()->for($user)->create();
        $finding = Finding::factory()->open()->create(['asset_group_id' => $group->id, 'title' => 'Original']);

        $this->actingAs($user)->patch(route('findings.update', $finding), ['status' => $status, 'title' => 'Tampered', 'asset_group_id' => 999])->assertRedirect();

        $this->assertDatabaseHas('findings', ['id' => $finding->id, 'status' => $status, 'title' => 'Original', 'asset_group_id' => $group->id]);
    }

    public function test_other_workspace_cannot_change_finding(): void
    {
        $finding = Finding::factory()->open()->create();
        $user = User::factory()->create(['terms_accepted_at' => now()]);

        $this->actingAs($user)->patch(route('findings.update', $finding), ['status' => 'resolved'])->assertNotFound();

        $this->assertDatabaseHas('findings', ['id' => $finding->id, 'status' => 'open']);
    }

    public function test_invalid_status_leaves_finding_unchanged(): void
    {
        $user = User::factory()->create(['terms_accepted_at' => now()]);
        $group = AssetGroup::factory()->for($user)->create();
        $finding = Finding::factory()->open()->create(['asset_group_id' => $group->id]);

        $this->actingAs($user)->patch(route('findings.update', $finding), ['status' => 'deleted'])
            ->assertInvalid(['status' => 'The selected status is invalid.']);

        $this->assertDatabaseHas('findings', ['id' => $finding->id, 'status' => 'open']);
    }

    public function test_guest_cannot_change_finding(): void
    {
        $finding = Finding::factory()->open()->create();

        $this->patch(route('findings.update', $finding), ['status' => 'resolved'])->assertRedirect(route('login'));

        $this->assertDatabaseHas('findings', ['id' => $finding->id, 'status' => 'open']);
    }

    public function test_owner_can_view_finding_detail_and_remediation_playbook(): void
    {
        $user = User::factory()->create(['terms_accepted_at' => now()]);
        $group = AssetGroup::factory()->for($user)->create();
        $finding = Finding::factory()->open()->create([
            'asset_group_id' => $group->id,
            'category' => 'security-headers',
            'title' => 'HSTS header missing',
            'evidence' => 'HTTPS response missing Strict-Transport-Security header',
        ]);

        $response = $this->actingAs($user)->get(route('findings.show', $finding));

        $response->assertOk()
            ->assertSee('HSTS header missing')
            ->assertSee('Remediation &amp; Hardening Playbook', false)
            ->assertSee('Strict-Transport-Security');
    }

    public function test_other_workspace_cannot_view_finding_detail(): void
    {
        $finding = Finding::factory()->open()->create();
        $user = User::factory()->create(['terms_accepted_at' => now()]);

        $this->actingAs($user)->get(route('findings.show', $finding))->assertNotFound();
    }

    public function test_guest_cannot_view_finding_detail(): void
    {
        $finding = Finding::factory()->open()->create();

        $this->get(route('findings.show', $finding))->assertRedirect(route('login'));
    }
}
