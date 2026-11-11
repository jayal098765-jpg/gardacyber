<?php

namespace App\Services;

use App\Models\AssetGroup;
use App\Models\Finding;
use App\Models\User;
use Illuminate\Support\Collection;

class SecurityRatingService
{
    /**
     * Severity penalty weights (score deducted per open finding).
     */
    private const SEVERITY_WEIGHTS = [
        Finding::SEVERITY_CRITICAL => 45,
        Finding::SEVERITY_HIGH => 22,
        Finding::SEVERITY_MEDIUM => 10,
        Finding::SEVERITY_LOW => 4,
    ];

    /**
     * Incidents weigh harder than vulnerabilities of the same severity.
     */
    private const INCIDENT_MULTIPLIER = 1.35;

    /**
     * Worst open issue caps the best achievable grade band.
     */
    private const MAX_SCORE_BY_WORST = [
        Finding::SEVERITY_CRITICAL => 59,
        Finding::SEVERITY_HIGH => 79,
        Finding::SEVERITY_MEDIUM => 89,
        Finding::SEVERITY_LOW => 100,
        'none' => 100,
    ];

    /**
     * @return array{score: int, grade: string}
     */
    public function rate(Collection $openFindings): array
    {
        $score = 100;

        foreach ($openFindings as $finding) {
            $penalty = self::SEVERITY_WEIGHTS[$finding->severity] ?? 5;

            if ($finding->kind === Finding::KIND_INCIDENT) {
                $penalty = (int) round($penalty * self::INCIDENT_MULTIPLIER);
            }

            $score -= $penalty;
        }

        $score = max(0, $score);
        $worst = $this->worstSeverity($openFindings);
        $score = min($score, self::MAX_SCORE_BY_WORST[$worst]);

        return [
            'score' => $score,
            'grade' => $this->gradeFor($score),
        ];
    }

    /**
     * @return array{vuln: array{score: int, grade: string}, incident: array{score: int, grade: string}}
     */
    public function rateGroup(AssetGroup $group): array
    {
        $group->loadMissing('findings');
        $findings = $group->findings->where('status', Finding::STATUS_OPEN);

        return [
            'vuln' => $this->rate($findings->where('kind', Finding::KIND_VULNERABILITY)->values()),
            'incident' => $this->rate($findings->where('kind', Finding::KIND_INCIDENT)->values()),
        ];
    }

    /**
     * @return array{vuln: array{score: int, grade: string}, incident: array{score: int, grade: string}}
     */
    public function rateOrganization(User $user): array
    {
        $groupIds = $user->groups()->pluck('id');

        $findings = Finding::query()
            ->whereIn('asset_group_id', $groupIds)
            ->where('status', Finding::STATUS_OPEN)
            ->get();

        return [
            'vuln' => $this->rate($findings->where('kind', Finding::KIND_VULNERABILITY)->values()),
            'incident' => $this->rate($findings->where('kind', Finding::KIND_INCIDENT)->values()),
        ];
    }

    /**
     * @return array<int, array{group: AssetGroup, vuln: array{score: int, grade: string}, incident: array{score: int, grade: string}}>
     */
    public function rateGroups(Collection $groups): array
    {
        (new \Illuminate\Database\Eloquent\Collection($groups->all()))->loadMissing('findings');

        return $groups->map(fn (AssetGroup $group) => [
            'group' => $group,
            ...$this->rateGroup($group),
        ])->values()->all();
    }

    private function worstSeverity(Collection $openFindings): string
    {
        $rank = [
            Finding::SEVERITY_CRITICAL => 4,
            Finding::SEVERITY_HIGH => 3,
            Finding::SEVERITY_MEDIUM => 2,
            Finding::SEVERITY_LOW => 1,
        ];

        $worstRank = $openFindings->reduce(
            fn (?int $carry, $finding) => max($carry ?? 0, $rank[$finding->severity] ?? 0),
            null
        );

        if ($worstRank === null) {
            return 'none';
        }

        return array_search($worstRank, $rank, true) ?: 'none';
    }

    private function gradeFor(int $score): string
    {
        return match (true) {
            $score >= 90 => 'A',
            $score >= 80 => 'B',
            $score >= 70 => 'C',
            $score >= 60 => 'D',
            default => 'F',
        };
    }
}
