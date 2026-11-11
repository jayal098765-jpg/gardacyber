<?php

namespace App\Jobs;

use App\Models\Asset;
use App\Models\Finding;
use App\Models\PostureSnapshot;
use App\Models\Scan;
use App\Services\SecurityRatingService;
use App\Services\SurfaceScanner;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Throwable;

class RunSurfaceScan implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 60;

    public function __construct(public int $scanId) {}

    public function handle(SurfaceScanner $scanner, SecurityRatingService $rating): void
    {
        if (! Scan::whereKey($this->scanId)->where('status', Scan::STATUS_QUEUED)->update(['status' => Scan::STATUS_RUNNING, 'started_at' => now()])) {
            return;
        }

        $scan = Scan::with('group')->findOrFail($this->scanId);
        $demo = ($scan->options['mode'] ?? 'demo') === 'demo';

        try {
            $this->stage($scan, 1, $demo ? 'Reading local training fixture. Discovering demo assets.' : 'Resolving public DNS and discovering live subdomains.');

            $capture = $demo ? $scanner->demoCapture() : $scanner->capture($scan->group->root_domain);
            $subdomains = $demo ? [] : $scanner->discoverSubdomains($scan->group->root_domain);
            $openPorts = $demo ? [80, 443] : $scanner->probePorts($capture['ip']);

            $this->stage($scan, 2, $demo ? 'Evaluating simulated defense signals and compromise signatures.' : 'Probing security headers, sensitive exposures, and web injection indicators.');

            $findings = $scanner->analyze($capture, $scan->type);

            if (! $demo && $scan->type !== Scan::TYPE_INCIDENT) {
                $activeFindings = $scanner->probeActiveVulnerabilities($scan->group->root_domain, $capture['ip']);
                $findings = array_merge($findings, $activeFindings);
            }

            $this->stage($scan, 3, 'Persisting observed perimeter assets, open ports, and evidence.');

            DB::transaction(function () use ($scan, $capture, $subdomains, $openPorts, $findings, $demo, $rating): void {
                $current = Scan::lockForUpdate()->find($scan->id);

                if (! $current || $current->status !== Scan::STATUS_RUNNING) {
                    return;
                }

                preg_match('/<title[^>]*>(.*?)<\/title>/is', $capture['body'], $title);
                $rootAsset = Asset::updateOrCreate(
                    ['asset_group_id' => $scan->asset_group_id, 'hostname' => $scan->group->root_domain],
                    [
                        'ip_address' => $capture['ip'],
                        'http_status' => $capture['status'],
                        'title' => mb_substr(strip_tags($title[1] ?? ''), 0, 255),
                        'server_header' => mb_substr(implode(' ', $capture['headers']['server'] ?? []), 0, 255),
                        'ports' => $openPorts,
                        'last_seen_at' => now(),
                    ]
                );

                $assetCount = 1;

                foreach ($subdomains as $sub) {
                    Asset::updateOrCreate(
                        ['asset_group_id' => $scan->asset_group_id, 'hostname' => $sub['hostname']],
                        [
                            'ip_address' => $sub['ip'],
                            'http_status' => 200,
                            'ports' => [443],
                            'last_seen_at' => now(),
                        ]
                    );
                    $assetCount++;
                }

                foreach ($findings as $finding) {
                    $record = Finding::firstOrNew([
                        'asset_group_id' => $scan->asset_group_id,
                        'asset_id' => $rootAsset->id,
                        'kind' => $finding['kind'],
                        'title' => $finding['title'],
                    ]);

                    $record->fill($finding + ['last_seen_at' => now()]);

                    if (! $record->exists) {
                        $record->status = Finding::STATUS_OPEN;
                        $record->first_seen_at = now();
                    }

                    $record->evidence = ($demo ? '[DEMO FIXTURE] ' : '[LIVE PROBE] ').$finding['evidence'];
                    $record->save();
                }

                $vulnCount = count(array_filter($findings, fn (array $f): bool => $f['kind'] === 'vulnerability'));
                $incidentCount = count(array_filter($findings, fn (array $f): bool => $f['kind'] === 'incident'));

                $current->update([
                    'status' => Scan::STATUS_COMPLETED,
                    'finished_at' => now(),
                    'options' => array_merge($current->options ?? [], [
                        'stage' => 4,
                        'assets' => $assetCount,
                        'vulnerabilities' => $vulnCount,
                        'incidents' => $incidentCount,
                        'message' => 'Completed. '.($demo ? 'Demo training fixture evaluated.' : 'Live perimeter scanned (HTTP '.$capture['status'].').').' '.$vulnCount.' vulnerabilities and '.$incidentCount.' incident signals recorded.',
                    ]),
                ]);

                $scan->group->update(['last_scanned_at' => now()]);

                $group = $scan->group->refresh();
                $groupRatings = $rating->rateGroup($group);

                PostureSnapshot::create([
                    'asset_group_id' => $group->id,
                    'assets' => $group->assets()->count(),
                    'open_vulns' => $group->openVulnerabilities()->count(),
                    'open_incidents' => $group->openIncidents()->count(),
                    'vuln_score' => $groupRatings['vuln']['score'],
                    'incident_score' => $groupRatings['incident']['score'],
                ]);
            });
        } catch (Throwable $exception) {
            report($exception);
            $this->failed($exception);
        }
    }

    public function failed(?Throwable $exception): void
    {
        $scan = Scan::find($this->scanId);

        if ($scan?->isLive()) {
            $msg = $exception ? $exception->getMessage() : 'Scan failed. Check public DNS, target reachability, and network connection.';
            $scan->update([
                'status' => Scan::STATUS_FAILED,
                'finished_at' => now(),
                'options' => array_merge($scan->options ?? [], [
                    'message' => 'Assessment interrupted: '.mb_substr($msg, 0, 200),
                ]),
            ]);
        }
    }

    private function stage(Scan $scan, int $stage, string $message): void
    {
        $scan->refresh();

        if ($scan->status !== Scan::STATUS_RUNNING) {
            throw new \RuntimeException('Scan stopped before next stage.');
        }

        $scan->update(['options' => array_merge($scan->options ?? [], compact('stage', 'message'))]);
    }
}
