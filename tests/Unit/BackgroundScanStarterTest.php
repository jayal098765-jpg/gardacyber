<?php

namespace Tests\Unit;

use App\Jobs\RunSurfaceScan;
use App\Services\BackgroundScanStarter;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class BackgroundScanStarterTest extends TestCase
{
    public function test_boot_worker_spawns_a_one_shot_database_worker(): void
    {
        Process::fake();

        (new BackgroundScanStarter)->bootWorker();

        Process::assertRan([
            PHP_BINARY,
            'artisan',
            'queue:work',
            'database',
            '--queue=default',
            '--once',
            '--stop-when-empty',
            '--timeout=300',
            '--no-interaction',
        ]);
    }

    public function test_boot_workers_spawns_two_workers_by_default(): void
    {
        Process::fake();

        (new BackgroundScanStarter)->bootWorkers();

        Process::assertRanTimes([
            PHP_BINARY,
            'artisan',
            'queue:work',
            'database',
            '--queue=default',
            '--once',
            '--stop-when-empty',
            '--timeout=300',
            '--no-interaction',
        ], 2);
    }

    public function test_php_binary_resolves_to_a_real_executable(): void
    {
        $binary = (new BackgroundScanStarter)->phpBinary();

        $this->assertNotSame('', trim($binary));
        $this->assertTrue(is_file($binary) || $binary === PHP_BINARY);
    }

    public function test_ensures_async_processing_dispatches_the_scan_job(): void
    {
        Queue::fake();
        Process::fake();
        Config::set('queue.default', 'sync');

        (new BackgroundScanStarter)->ensureAsyncProcessing(1);

        Queue::assertPushed(RunSurfaceScan::class);
    }

    public function test_ensures_async_processing_never_spawns_a_worker_on_a_real_queue(): void
    {
        Queue::fake();
        Process::fake();
        Config::set('queue.default', 'database');

        (new BackgroundScanStarter)->ensureAsyncProcessing(1);

        Queue::assertPushed(RunSurfaceScan::class);
        Process::assertNothingRan();
    }

    public function test_worker_boot_can_be_disabled_via_config(): void
    {
        Process::fake();
        Config::set('scanner.async_worker', false);

        (new BackgroundScanStarter)->startIfNeeded();

        Process::assertNothingRan();
    }
}
