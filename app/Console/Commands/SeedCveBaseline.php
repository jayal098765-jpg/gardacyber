<?php

namespace App\Console\Commands;

use App\Models\Cve;
use App\Services\CveBaseline;
use Illuminate\Console\Command;

class SeedCveBaseline extends Command
{
    protected $signature = 'cve:baseline';

    protected $description = 'Seed the curated CVE baseline for common web-stack products.';

    public function handle(): int
    {
        $affected = CveBaseline::seed();

        $this->info("CVE baseline seeded ({$affected} rows upserted). Total local rows: ".Cve::count().'.');

        return self::SUCCESS;
    }
}
