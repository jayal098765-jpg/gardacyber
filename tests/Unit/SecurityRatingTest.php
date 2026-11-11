<?php

namespace Tests\Unit;

use App\Models\AssetGroup;
use App\Models\Finding;
use App\Services\SecurityRatingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SecurityRatingTest extends TestCase
{
    use RefreshDatabase;

    private function rate(array $findings): array
    {
        $service = new SecurityRatingService;

        return $service->rate(collect($findings));
    }

    private function finding(string $severity, string $kind = Finding::KIND_VULNERABILITY): Finding
    {
        $group = AssetGroup::factory()->create();

        return Finding::factory()->create([
            'asset_group_id' => $group->id,
            'severity' => $severity,
            'kind' => $kind,
            'status' => Finding::STATUS_OPEN,
        ]);
    }

    public function test_empty_posture_scores_100_grade_a(): void
    {
        $rating = $this->rate([]);
        $this->assertSame(100, $rating['score']);
        $this->assertSame('A', $rating['grade']);
    }

    public function test_single_critical_caps_at_f(): void
    {
        $rating = $this->rate([$this->finding('critical')]);
        $this->assertSame('F', $rating['grade']);
        $this->assertLessThan(60, $rating['score']);
    }

    public function test_only_lows_can_still_reach_a(): void
    {
        $rating = $this->rate([$this->finding('low')]);
        $this->assertSame('A', $rating['grade']);
    }

    public function test_high_caps_at_c(): void
    {
        $rating = $this->rate([$this->finding('high')]);
        $this->assertSame('C', $rating['grade']);
        $this->assertLessThan(80, $rating['score']);
    }

    public function test_incident_weighs_more_than_vulnerability(): void
    {
        $vuln = $this->rate([$this->finding('high')]);
        $incident = $this->rate([$this->finding('high', Finding::KIND_INCIDENT)]);

        $this->assertLessThan($vuln['score'], $incident['score']);
    }

    public function test_more_findings_push_score_lower(): void
    {
        $one = $this->rate([$this->finding('medium')]);
        $three = $this->rate([
            $this->finding('medium'),
            $this->finding('medium'),
            $this->finding('medium'),
        ]);

        $this->assertLessThan($one['score'], $three['score']);
    }
}
