<?php

namespace App\Services;

use App\Models\Scan;

class ScanProgressService
{
    /** @return array<int, string> */
    public function stagesFor(Scan $scan): array
    {
        return ['Validate scope', 'Capture HTTPS response', 'Analyze evidence', 'Save findings'];
    }

    public function progress(Scan $scan): array
    {
        $stage = (int) ($scan->options['stage'] ?? 0);
        $terminal = ! $scan->isLive();
        $elapsed = $scan->started_at ? max(0, ($scan->finished_at ?? $scan->stopped_at ?? now())->getTimestamp() - $scan->started_at->getTimestamp()) : 0;

        return [
            'status' => $scan->status,
            'mode' => 'live',
            'percent' => $scan->status === Scan::STATUS_COMPLETED ? 100 : min(95, $stage * 25),
            'stage_index' => $stage,
            'stages' => array_map(fn (string $name, int $index): array => [
                'name' => $name,
                'state' => $scan->status === Scan::STATUS_COMPLETED || $index < $stage ? 'complete' : ($terminal ? $scan->status : ($index === $stage ? $scan->status : 'pending')),
            ], $this->stagesFor($scan), array_keys($this->stagesFor($scan))),
            'elapsed_seconds' => $elapsed,
            'finished' => $terminal,
            'message' => $scan->options['message'] ?? ($scan->status === Scan::STATUS_QUEUED ? 'Waiting for queue worker.' : ''),
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
}
