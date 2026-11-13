<?php

namespace App\Services;

use App\Models\Scan;

class ScanProgressService
{
    /**
     * Per-type phases. Each entry carries a display name and a weight (out of
     * 100) so the progress bar reflects how long a phase actually takes —
     * e.g. the edge capture can dominate a full scan when a browser handoff
     * is needed.
     *
     * The final entry is the terminal "Finalize" step; its weight only shows
     * once the scan has completed.
     *
     * @var array<string, array<int, array{name: string, weight: int}>>
     */
    private const STAGES = [
        Scan::TYPE_FULL => [
            ['name' => 'Validate scope & resolve DNS', 'weight' => 8],
            ['name' => 'Probe CDN/WAF edge & capture', 'weight' => 30],
            ['name' => 'Audit security headers & exposures', 'weight' => 14],
            ['name' => 'Probe directories & backup paths', 'weight' => 18],
            ['name' => 'Run injection & brute-force probes', 'weight' => 14],
            ['name' => 'Check subdomain takeover', 'weight' => 6],
            ['name' => 'Persist evidence', 'weight' => 6],
            ['name' => 'Finalize', 'weight' => 4],
        ],
        Scan::TYPE_VULNERABILITY => [
            ['name' => 'Validate scope & resolve DNS', 'weight' => 8],
            ['name' => 'Probe CDN/WAF edge & capture', 'weight' => 30],
            ['name' => 'Audit security headers & exposures', 'weight' => 14],
            ['name' => 'Probe directories & backup paths', 'weight' => 18],
            ['name' => 'Run injection & brute-force probes', 'weight' => 14],
            ['name' => 'Check subdomain takeover', 'weight' => 6],
            ['name' => 'Persist evidence', 'weight' => 6],
            ['name' => 'Finalize', 'weight' => 4],
        ],
        Scan::TYPE_INCIDENT => [
            ['name' => 'Probe edge & capture', 'weight' => 45],
            ['name' => 'Audit compromise heuristics', 'weight' => 40],
            ['name' => 'Persist evidence', 'weight' => 10],
            ['name' => 'Finalize', 'weight' => 5],
        ],
    ];

    /**
     * Planning estimate for a full run of each type, in seconds. Used for the
     * ETA until actual progress provides a measured rate.
     *
     * @var array<string, int>
     */
    private const EXPECTED_SECONDS = [
        Scan::TYPE_FULL => 180,
        Scan::TYPE_VULNERABILITY => 150,
        Scan::TYPE_INCIDENT => 40,
    ];

    /** @return array<int, string> */
    public function stagesFor(Scan $scan): array
    {
        return array_column(self::STAGES[$scan->type] ?? self::STAGES[Scan::TYPE_FULL], 'name');
    }

    public function progress(Scan $scan): array
    {
        $stages = $this->stagesFor($scan);
        $stage = min((int) ($scan->options['stage'] ?? 0), count($stages));
        $terminal = ! $scan->isLive();
        $elapsed = $scan->started_at ? max(0, ($scan->finished_at ?? $scan->stopped_at ?? now())->getTimestamp() - $scan->started_at->getTimestamp()) : 0;
        $percent = $terminal ? 100 : min(95, $this->cumulativePercent($scan, $stage));
        $etaSeconds = $percent > 0
            ? (int) ceil($elapsed * (100 - $percent) / $percent)
            : $this->expectedSeconds($scan);

        return [
            'status' => $scan->status,
            'mode' => 'live',
            'percent' => $percent,
            'stage_index' => $stage,
            'stages' => array_map(function (string $name, int $index) use ($scan, $stage, $terminal): array {
                return [
                    'name' => $name,
                    'state' => $scan->status === Scan::STATUS_COMPLETED || $index < $stage
                        ? 'complete'
                        : ($terminal ? $scan->status : ($index === $stage ? $scan->status : 'pending')),
                ];
            }, $stages, array_keys($stages)),
            'elapsed_seconds' => $elapsed,
            'eta_seconds' => $etaSeconds,
            'finished' => $terminal,
            'message' => $scan->options['message'] ?? ($scan->status === Scan::STATUS_QUEUED ? 'Waiting for the scan worker to pick this up.' : ''),
            'assets' => $scan->options['assets'] ?? 0,
            'vulnerabilities' => $scan->options['vulnerabilities'] ?? 0,
            'incidents' => $scan->options['incidents'] ?? 0,
            'group_url' => route('groups.show', $scan->asset_group_id),
        ];
    }

    public function stop(Scan $scan): void
    {
        Scan::whereKey($scan->id)->whereIn('status', [Scan::STATUS_RUNNING, Scan::STATUS_QUEUED])
            ->update(['status' => Scan::STATUS_STOPPED, 'stopped_at' => now()]);
    }

    /** Cumulative percent once a given stage is reached. */
    private function cumulativePercent(Scan $scan, int $stage): int
    {
        $weights = array_column(self::STAGES[$scan->type] ?? self::STAGES[Scan::TYPE_FULL], 'weight');

        if ($stage <= 0) {
            return 0;
        }

        return min(100, (int) array_sum(array_slice($weights, 0, max(1, $stage))));
    }

    private function expectedSeconds(Scan $scan): int
    {
        return self::EXPECTED_SECONDS[$scan->type] ?? self::EXPECTED_SECONDS[Scan::TYPE_FULL];
    }
}
