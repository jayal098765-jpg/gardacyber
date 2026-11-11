<?php

namespace Tests\Feature;

use App\Models\AssetGroup;
use App\Models\Finding;
use App\Models\Report;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_csv_generation_exports_only_owned_open_findings_and_escapes_formulas(): void
    {
        $user = User::factory()->create(['terms_accepted_at' => now()]);
        $group = AssetGroup::factory()->for($user)->create();
        Finding::factory()->open()->create(['asset_group_id' => $group->id, 'title' => '=HYPERLINK("https://example.test")']);
        Finding::factory()->open()->create(['title' => 'Other workspace secret']);

        $response = $this->actingAs($user)->post(route('reports.store'), ['scope' => 'organization', 'kind' => 'csv']);

        $response->assertOk()->assertDownload();
        $this->assertStringContainsString("'=HYPERLINK", $response->streamedContent());
        $this->assertStringNotContainsString('Other workspace secret', $response->streamedContent());
        $this->assertDatabaseHas('reports', ['user_id' => $user->id, 'item_count' => 1]);
    }

    public function test_group_scope_requires_owned_group(): void
    {
        $user = User::factory()->create(['terms_accepted_at' => now()]);
        $otherGroup = AssetGroup::factory()->create();

        $this->actingAs($user)->post(route('reports.store'), ['scope' => 'group', 'kind' => 'html'])
            ->assertInvalid(['group_id' => 'The group id field is required when scope is group.']);
        $this->post(route('reports.store'), ['scope' => 'group', 'kind' => 'html', 'group_id' => $otherGroup->id])->assertNotFound();

        $this->assertDatabaseCount('reports', 0);
    }

    public function test_html_download_escapes_snapshot_and_blocks_active_content(): void
    {
        $user = User::factory()->create(['terms_accepted_at' => now()]);
        $report = Report::factory()->for($user)->create([
            'scope' => '<script>alert(1)</script>', 'kind' => 'html',
            'payload' => [['title' => '<script>alert(2)</script>', 'host' => '<img src=x onerror=alert(3)>']],
        ]);

        $response = $this->actingAs($user)->get(route('reports.download', $report));

        $response->assertOk()->assertDownload('report-'.$report->id.'.html')
            ->assertHeader('Content-Security-Policy', "sandbox; default-src 'none'; style-src 'unsafe-inline'");
        $this->assertStringContainsString('&lt;script&gt;', $response->streamedContent());
        $this->assertStringNotContainsString('<script>', $response->streamedContent());
        $this->assertStringNotContainsString('<img src=x', $response->streamedContent());
    }

    public function test_other_workspace_cannot_download_or_delete_report(): void
    {
        $report = Report::factory()->create();
        $user = User::factory()->create(['terms_accepted_at' => now()]);

        $this->actingAs($user)->get(route('reports.download', $report))->assertNotFound();
        $this->delete(route('reports.destroy', $report))->assertNotFound();

        $this->assertModelExists($report);
    }

    public function test_guests_cannot_generate_reports(): void
    {
        $this->post(route('reports.store'), ['scope' => 'organization', 'kind' => 'html'])->assertRedirect(route('login'));

        $this->assertDatabaseCount('reports', 0);
    }

    public function test_owned_group_html_generation_stores_matching_snapshot(): void
    {
        $user = User::factory()->create(['terms_accepted_at' => now()]);
        $group = AssetGroup::factory()->for($user)->create();
        $finding = Finding::factory()->open()->create(['asset_group_id' => $group->id]);
        Finding::factory()->create(['asset_group_id' => $group->id, 'status' => 'resolved']);

        $this->actingAs($user)->post(route('reports.store'), ['scope' => 'group', 'kind' => 'html', 'group_id' => $group->id])
            ->assertRedirect(route('reports.index'));

        $report = $user->reports()->sole();
        $this->assertSame(1, $report->item_count);
        $this->assertSame($finding->title, $report->payload[0]['title']);
        $this->assertSame($group->id, $report->asset_group_id);
    }
}
