<?php

namespace Database\Seeders;

use App\Models\Asset;
use App\Models\AssetGroup;
use App\Models\Finding;
use App\Models\Report;
use App\Models\Scan;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment('local') && ! env('GARDA_SEED_FORCE')) {
            return;
        }

        DB::transaction(function (): void {
            $demo = User::firstOrCreate(['email' => 'demo@gardacyber.test'], [
                'name' => 'Demo Workspace',
                'display_name' => 'Demo Workspace',
                'workspace_name' => 'GardaCyber Demo Workspace',
                'password' => 'password',
                'terms_accepted_at' => now(),
            ]);

            foreach (['training.gardacyber.test', 'demo.gardacyber.test', 'shop.gardacyber.test'] as $index => $domain) {
                $group = AssetGroup::firstOrCreate(['user_id' => $demo->id, 'root_domain' => $domain], [
                    'slug' => str($domain)->slug()->toString(),
                    'last_scanned_at' => now()->subMinutes(5),
                ]);

                $asset = Asset::firstOrCreate(['asset_group_id' => $group->id, 'hostname' => $domain], [
                    'ip_address' => '192.0.2.'.($index + 10),
                    'http_status' => 200,
                    'title' => 'GardaCyber simulated demo asset',
                    'server_header' => 'nginx',
                    'technologies' => ['nginx'],
                    'ports' => [80, 443],
                    'first_seen_at' => now()->subDays(7),
                    'last_seen_at' => now()->subMinutes(5),
                ]);

                if ($index === 0) {
                    foreach ([
                        ['vulnerability', 'high', 'exposure', 'Demo: exposed administration interface'],
                        ['vulnerability', 'medium', 'misconfiguration', 'Demo: missing Content-Security-Policy'],
                        ['vulnerability', 'low', 'tech-detect', 'Demo: server version disclosed'],
                        ['incident', 'critical', 'defacement', 'Demo: simulated website defacement'],
                        ['incident', 'high', 'gambling-injection', 'Demo: simulated gambling content injection'],
                    ] as [$kind, $severity, $category, $title]) {
                        Finding::firstOrCreate(['asset_group_id' => $group->id, 'asset_id' => $asset->id, 'title' => $title], [
                            'kind' => $kind,
                            'severity' => $severity,
                            'category' => $category,
                            'description' => 'Synthetic GardaCyber demo finding. No live target was scanned.',
                            'evidence' => 'Simulated fixture for '.$domain,
                            'status' => Finding::STATUS_OPEN,
                            'first_seen_at' => now()->subDays(2),
                            'last_seen_at' => now()->subMinutes(5),
                        ]);
                    }
                }

                Scan::firstOrCreate(['asset_group_id' => $group->id, 'type' => Scan::TYPE_FULL], [
                    'status' => Scan::STATUS_COMPLETED,
                    'options' => ['demo' => true, 'auto_rescan' => 'off'],
                    'stage_count' => 6,
                    'started_at' => now()->subMinutes(10),
                    'finished_at' => now()->subMinutes(5),
                ]);

                $items = $group->findings()->with('asset')->where('status', Finding::STATUS_OPEN)->get()->map(fn (Finding $finding): array => [
                    'host' => $finding->asset?->hostname,
                    'title' => $finding->title,
                    'kind' => $finding->kind,
                    'severity' => $finding->severity,
                    'category' => $finding->category,
                    'status' => $finding->status,
                    'last_seen_at' => $finding->last_seen_at?->toDateTimeString(),
                ])->all();

                Report::firstOrCreate(['user_id' => $demo->id, 'asset_group_id' => $group->id, 'scope' => Report::SCOPE_GROUP, 'kind' => Report::KIND_HTML], [
                    'item_count' => count($items),
                    'payload' => $items,
                    'generated_at' => now(),
                ]);
            }
        });
    }
}
