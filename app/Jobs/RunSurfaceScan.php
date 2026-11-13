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

    public int $timeout = 240;

    public function __construct(public int $scanId) {}

    public function handle(SurfaceScanner $scanner, SecurityRatingService $rating): void
    {
        if (! Scan::whereKey($this->scanId)->where('status', Scan::STATUS_QUEUED)->update(['status' => Scan::STATUS_RUNNING, 'started_at' => now()])) {
            return;
        }

        $scan = Scan::with('group')->findOrFail($this->scanId);

        $incidentOnly = $scan->type === Scan::TYPE_INCIDENT;
        $advanced = ! $incidentOnly && (bool) config('scanner.active_probes', true);

        try {
            $this->stage(
                $scan,
                1,
                $incidentOnly
                    ? 'Resolving DNS, probing the edge and open ports, and capturing the application.'
                    : 'Resolving public DNS and discovering live subdomains.'
            );

            $capture = $scanner->capture($scan->group->root_domain);
            $subdomains = $scanner->discoverSubdomains($scan->group->root_domain);
            $openPorts = $scanner->probePorts($capture['ip']);

            $this->stage(
                $scan,
                2,
                $incidentOnly
                    ? 'Auditing defacement, gambling, and SEO compromise heuristics.'
                    : 'Probing the CDN/WAF edge, capturing the application, and mapping open ports.'
            );

            $findings = $scanner->analyze($capture, $scan->type);
            $findings = [...$findings, ...$scanner->wafFindings($capture, $scan->group->root_domain)];

            if ($advanced) {
                $this->stage($scan, 3, 'Auditing security headers, sensitive exposures, and visible compromise signatures.');

                $findings = [...$findings, ...$scanner->probeActiveVulnerabilities($scan->group->root_domain, $capture['ip'])];

                $this->stage($scan, 4, 'Probing common directories, backup files, and archive paths.');

                $findings = [...$findings, ...$scanner->probeDirectoryExposure($scan->group->root_domain, $capture['ip'], $capture)];

                $this->stage($scan, 5, 'Running injection reflection and brute-force posture probes.');

                $findings = [
                    ...$findings,
                    ...$scanner->probeAdvancedXss($scan->group->root_domain, $capture['ip']),
                    ...$scanner->probeAuthBruteForceResistance($scan->group->root_domain, $capture['ip'], $capture),
                ];

                $this->stage($scan, 6, 'Checking subdomain takeover indicators.');

                $findings = [...$findings, ...$scanner->probeSubdomainTakeover($subdomains)];
            }

            $persistStage = $advanced ? 7 : 3;

            $this->stage($scan, $persistStage, 'Persisting observed perimeter assets, open ports, and evidence.');

            DB::transaction(function () use ($scan, $capture, $subdomains, $openPorts, $findings, $rating, $persistStage): void {
                $current = Scan::lockForUpdate()->find($scan->id);

                if (! $current || $current->status !== Scan::STATUS_RUNNING) {
                    return;
                }

                preg_match('/<title[^>]*>(.*?)<\/title>/is', $capture['body'], $title);

                $attributes = [
                    'ip_address' => $capture['ip'],
                    'http_status' => $capture['status'],
                    'title' => mb_substr(strip_tags($title[1] ?? ''), 0, 255),
                    'server_header' => mb_substr(implode(' ', $capture['headers']['server'] ?? []), 0, 255),
                    'ports' => $openPorts,
                    'last_seen_at' => now(),
                ];

                $cdn = $capture['waf']['provider'] ?? null;

                // Only overwrite the stored edge provider when this scan
                // actually observed one, otherwise a direct (non-CDN) scan
                // would erase what an earlier pass recorded.
                if (is_string($cdn) && $cdn !== '' && $cdn !== 'unknown') {
                    $attributes['cdn'] = $cdn;
                }

                $rootAsset = Asset::updateOrCreate(
                    ['asset_group_id' => $scan->asset_group_id, 'hostname' => $scan->group->root_domain],
                    $attributes
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

                    $record->evidence = '[LIVE PROBE] '.$finding['evidence'];
                    $record->save();
                }

                $vulnCount = count(array_filter($findings, fn (array $f): bool => $f['kind'] === 'vulnerability'));
                $incidentCount = count(array_filter($findings, fn (array $f): bool => $f['kind'] === 'incident'));

                $message = 'Completed. Live perimeter scanned (HTTP '.$capture['status'].'). '.$vulnCount.' vulnerabilities and '.$incidentCount.' incident signals recorded.';

                if (! empty($capture['via_browser'])) {
                    $message = 'Completed. The CDN/WAF challenge was cleared in a real browser session, so checks ran against the site as a verified visitor sees it (HTTP '.$capture['status'].'). '.$vulnCount.' vulnerabilities and '.$incidentCount.' incident signals recorded.';
                } elseif (! empty($capture['via_origin'])) {
                    $message = 'Completed. CDN/WAF edge bypassed; checks ran against origin '.$capture['ip'].' (HTTP '.$capture['status'].'). '.$vulnCount.' vulnerabilities and '.$incidentCount.' incident signals recorded.';
                } elseif (! empty($capture['blocked'])) {
                    $message = 'Completed with limited coverage. The CDN/WAF blocked the scanner (HTTP '.$capture['status'].'), so header, exposure, and injection checks are partial. '.$vulnCount.' vulnerabilities and '.$incidentCount.' incident signals recorded.';
                }

                $current->update([
                    'status' => Scan::STATUS_COMPLETED,
                    'finished_at' => now(),
                    'options' => array_merge($current->options ?? [], [
                        'stage' => $persistStage + 1,
                        'assets' => $assetCount,
                        'vulnerabilities' => $vulnCount,
                        'incidents' => $incidentCount,
                        'message' => $message,
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
                    'message' => 'Scan interrupted: '.mb_substr($msg, 0, 200),
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
