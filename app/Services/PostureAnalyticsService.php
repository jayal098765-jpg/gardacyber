<?php

namespace App\Services;

use App\Models\AssetGroup;
use App\Models\Finding;
use App\Models\PostureSnapshot;
use Illuminate\Support\Collection;

class PostureAnalyticsService
{
    public const SEVERITY_WEIGHTS = [
        Finding::SEVERITY_CRITICAL => 4,
        Finding::SEVERITY_HIGH => 3,
        Finding::SEVERITY_MEDIUM => 2,
        Finding::SEVERITY_LOW => 1,
    ];

    /**
     * Daily-averaged posture history across the user's groups, newest first.
     *
     * @return Collection<int, array{date: string, vuln_score: float, incident_score: float}>
     */
    public function dailyHistory(Collection $groups, int $days = 30): Collection
    {
        if ($groups->isEmpty()) {
            return collect();
        }

        $snapshots = PostureSnapshot::query()
            ->whereIn('asset_group_id', $groups->pluck('id'))
            ->where('created_at', '>=', now()->subDays($days))
            ->orderBy('created_at')
            ->get();

        return $snapshots
            ->groupBy(fn (PostureSnapshot $s): string => $s->created_at->toDateString())
            ->map(fn (Collection $day): array => [
                'date' => $day->first()->created_at->toDateString(),
                'vuln_score' => round($day->avg('vuln_score'), 1),
                'incident_score' => round($day->avg('incident_score'), 1),
            ])
            ->values()
            ->sortByDesc('date')
            ->values();
    }

    /**
     * A single 0-100 "Exposure Index" for the whole workspace.
     * Higher means more open exposure relative to assets.
     *
     * @return array{score: int, level: string, open_findings: int, assets: int, detail: string}
     */
    public function exposureIndex(Collection $groups, Collection $openVulns, Collection $openIncidents): array
    {
        $assets = max(1, (int) $groups->sum('assets_count'));

        $weighted = $openVulns->sum(fn (Finding $f): int => self::SEVERITY_WEIGHTS[$f->severity] ?? 1)
            + $openIncidents->sum(fn (Finding $f): int => self::SEVERITY_WEIGHTS[$f->severity] ?? 1) * 2;

        $score = (int) min(100, round($weighted / $assets * 18));

        $level = match (true) {
            $score >= 70 => 'Critical',
            $score >= 45 => 'High',
            $score >= 20 => 'Moderate',
            default => 'Low',
        };

        $detail = "{$weighted} severity-weighted open findings across {$assets} asset(s).";

        return [
            'score' => $score,
            'level' => $level,
            'open_findings' => $openVulns->count() + $openIncidents->count(),
            'assets' => $assets,
            'detail' => $detail,
        ];
    }

    /**
     * Build SVG polyline points from a collection of [value] series.
     *
     * @param  Collection<int, float>  $values
     * @return array{points: string, max: float, min: float}
     */
    public function chartSeries(Collection $values, int $width, int $height): array
    {
        if ($values->isEmpty()) {
            return ['points' => '', 'max' => 0, 'min' => 0];
        }

        $max = max($values->max(), 1);
        $min = min($values->min(), 0);
        $range = max($max - $min, 1);

        $step = $width / max(count($values) - 1, 1);
        $points = $values->map(function (float $value, int $i) use ($step, $height, $min, $range): string {
            $x = round($i * $step, 1);
            $y = round($height - (($value - $min) / $range) * ($height - 8) - 4, 1);
            return "{$x},{$y}";
        })->implode(' ');

        return ['points' => $points, 'max' => $max, 'min' => $min];
    }
}
