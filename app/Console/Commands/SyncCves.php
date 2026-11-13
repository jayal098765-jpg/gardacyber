<?php

namespace App\Console\Commands;

use App\Models\Cve;
use App\Services\CveSyncService;
use Illuminate\Console\Command;

class SyncCves extends Command
{
    protected $signature = 'cve:sync
        {--year=* : Sync a specific year (repeatable, e.g. --year=2023 --year=2024)}
        {--all : Sync every year from 1999 through the current year}
        {--kev : Also refresh the CISA Known Exploited Vulnerabilities flags}
        {--kev-only : Only refresh the CISA KEV flags, skip vulnerability sync}
        {--fresh : Truncate the cves table before syncing}';

    protected $description = 'Sync the local NVD CVE database and optionally CISA KEV exploited flags.';

    public function handle(CveSyncService $sync): int
    {
        if ($this->option('fresh') && ! $this->confirm('Truncate the full cves table before syncing? This cannot be undone.')) {
            return self::FAILURE;
        }

        if ($this->option('kev-only')) {
            return $this->runKevOnly($sync);
        }

        if ($this->option('fresh')) {
            Cve::query()->truncate();
            $this->info('Truncated cves table.');
        }

        if ($this->option('all')) {
            $this->info('Syncing the complete NVD CVE database…');
            $totals = $sync->syncAll();
            $totals['years'] = $totals['years'] ?? 0;
        } else {
            $years = $this->resolveYears();

            $this->info('Syncing NVD CVE data for '.count($years).' year(s)…');
            $bar = $this->output->createProgressBar(count($years));

            $totals = ['inserted' => 0, 'updated' => 0, 'fetched' => 0];

            foreach ($years as $year) {
                $result = $sync->syncYear($year);
                $totals['inserted'] += $result['inserted'];
                $totals['updated'] += $result['updated'];
                $totals['fetched'] += $result['fetched'];
                $bar->advance();
            }

            $bar->finish();
            $this->newLine(2);
        }

        $this->table(
            ['Metric', 'Value'],
            [
                ['Years synced', $totals['years'] ?? count($years ?? [])],
                ['CVE records fetched', $totals['fetched'] ?? 0],
                ['Inserted', $totals['inserted']],
                ['Updated', $totals['updated']],
                ['Total CVE rows in DB', Cve::count()],
            ]
        );

        if ($this->option('kev')) {
            $this->runKev($sync);
        }

        return self::SUCCESS;
    }

    /**
     * @return array<int, int>
     */
    private function resolveYears(): array
    {
        if ($this->option('all')) {
            return range(CveSyncService::FIRST_YEAR, (int) now()->format('Y'));
        }

        $years = array_map('intval', $this->option('year'));

        if ($years === []) {
            $years = [(int) now()->format('Y')];
        }

        return array_values(array_unique($years));
    }

    private function runKevOnly(CveSyncService $sync): int
    {
        [$result, $ok] = $this->runKev($sync);

        if (! $ok) {
            return self::FAILURE;
        }

        $this->table(
            ['Metric', 'Value'],
            [
                ['KEV entries in feed', $result['total']],
                ['Local CVE rows flagged exploited', $result['flagged']],
            ]
        );

        return self::SUCCESS;
    }

    /**
     * @return array{0: array{total: int, flagged: int}, 1: bool}
     */
    private function runKev(CveSyncService $sync): array
    {
        $this->info('Refreshing CISA KEV exploited flags…');

        try {
            $result = $sync->syncKev();
        } catch (\RuntimeException $e) {
            $this->error($e->getMessage());

            return [['total' => 0, 'flagged' => 0], false];
        }

        $this->info('KEV: '.$result['total'].' entries in the feed, '.$result['flagged'].' local rows flagged exploited.');

        return [$result, true];
    }
}
