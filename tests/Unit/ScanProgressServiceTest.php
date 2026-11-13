<?php

namespace Tests\Unit;

use App\Models\Scan;
use App\Services\ScanProgressService;
use Tests\TestCase;

class ScanProgressServiceTest extends TestCase
{
    private function scan(array $overrides = []): Scan
    {
        return new Scan(array_merge([
            'type' => Scan::TYPE_FULL,
            'status' => Scan::STATUS_RUNNING,
            'options' => ['stage' => 0],
            'started_at' => now()->subSeconds(30),
            'asset_group_id' => 1,
        ], $overrides));
    }

    public function test_full_scan_reports_weighted_percent_per_stage(): void
    {
        $service = new ScanProgressService;

        $expectations = [
            1 => 8,
            2 => 38,
            3 => 52,
            4 => 70,
            5 => 84,
            6 => 90,
            7 => 95,
        ];

        foreach ($expectations as $stage => $percent) {
            $data = $service->progress($this->scan(['options' => ['stage' => $stage]]));

            $this->assertSame($percent, $data['percent'], "stage {$stage}");
        }
    }

    public function test_incident_scan_has_its_own_stage_list(): void
    {
        $service = new ScanProgressService;
        $data = $service->progress($this->scan([
            'type' => Scan::TYPE_INCIDENT,
            'options' => ['stage' => 1],
        ]));

        $this->assertCount(4, $data['stages']);
        $this->assertSame('Probe edge & capture', $data['stages'][0]['name']);
        $this->assertSame(45, $data['percent']);
    }

    public function test_completed_scan_reports_100_percent(): void
    {
        $service = new ScanProgressService;
        $data = $service->progress($this->scan([
            'status' => Scan::STATUS_COMPLETED,
            'finished_at' => now(),
            'options' => ['stage' => 8, 'message' => 'Completed.'],
        ]));

        $this->assertTrue($data['finished']);
        $this->assertSame(100, $data['percent']);
        $this->assertSame('Completed.', $data['message']);
    }

    public function test_eta_falls_back_to_planning_estimate_before_progress(): void
    {
        $service = new ScanProgressService;
        $data = $service->progress($this->scan());

        $this->assertSame(180, $data['eta_seconds']);
    }

    public function test_eta_uses_measured_rate_once_progress_exists(): void
    {
        $service = new ScanProgressService;

        // 30s elapsed at stage 2 (38%) => 30 * 62 / 38 ≈ 49s
        $data = $service->progress($this->scan(['options' => ['stage' => 2]]));

        $this->assertSame(49, $data['eta_seconds']);
    }

    public function test_terminal_statuses_mark_all_stages_in_that_state(): void
    {
        $service = new ScanProgressService;
        $data = $service->progress($this->scan([
            'status' => Scan::STATUS_STOPPED,
            'stopped_at' => now(),
            'options' => ['stage' => 2],
        ]));

        $this->assertTrue($data['finished']);
        $this->assertSame('complete', $data['stages'][0]['state']);
        $this->assertSame('complete', $data['stages'][1]['state']);
        $this->assertSame('stopped', $data['stages'][2]['state']);
    }

    public function test_queued_scan_explains_the_wait(): void
    {
        $service = new ScanProgressService;
        $data = $service->progress($this->scan(['status' => Scan::STATUS_QUEUED]));

        $this->assertSame('Waiting for the scan worker to pick this up.', $data['message']);
        $this->assertSame(0, $data['percent']);
        $this->assertFalse($data['finished']);
    }
}
