<?php

namespace App\Services;

use App\Jobs\RunSurfaceScan;
use Illuminate\Contracts\Process\InvokedProcess;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Process;

/**
 * Keeps scan launches non-blocking even when the application is running on
 * the `sync` queue driver (the local default). A scan job is still queued
 * through the standard machinery — it is pushed to the `database` queue and
 * a one-shot worker is booted to pick it up, so the HTTP request returns
 * immediately and the operator lands on the live scan console while the
 * assessment is still running.
 *
 * In environments with a real queue worker (database/redis via supervisor,
 * Laravel Cloud, etc.) the dispatcher is used untouched.
 */
final class BackgroundScanStarter
{
    private const WORKER_LOCK_KEY = 'garda.scan-worker';

    /**
     * Queue the scan for asynchronous execution. On a sync queue connection a
     * background worker is started to process it; everywhere else the job is
     * dispatched to the configured queue as usual.
     */
    public function ensureAsyncProcessing(int $scanId): void
    {
        if ($this->queueIsSync() && ! app()->runningUnitTests()) {
            RunSurfaceScan::dispatch($scanId)->onConnection('database')->afterCommit();

            try {
                $this->startIfNeeded();
            } catch (\Throwable) {
                // Background workers are unavailable (e.g. proc_open is
                // disabled): run the scan inline so the launch still
                // completes rather than leaving it queued forever.
                $this->runInline($scanId);
            }

            return;
        }

        RunSurfaceScan::dispatch($scanId)->afterCommit();
    }

    /**
     * Boot a one-shot worker if none is already running. The worker processes
     * whatever is queued and exits once the queue is empty, so a single boot
     * covers scans launched back-to-back.
     */
    public function startIfNeeded(): void
    {
        if (! (bool) config('scanner.async_worker', true) || app()->runningUnitTests()) {
            return;
        }

        $lock = Cache::lock(self::WORKER_LOCK_KEY, 60);

        if (! $lock->get()) {
            return;
        }

        try {
            $this->bootWorkers();
        } catch (\Throwable $e) {
            report($e);
        } finally {
            $lock->release();
        }
    }

    /**
     * Boot the configured number of one-shot workers. Workers exit once the
     * queue is empty, so the second worker mostly adds resilience (if one
     * process dies) and lets several queued scans run in parallel.
     *
     * @return array<int, InvokedProcess>
     */
    public function bootWorkers(): array
    {
        $count = max(1, (int) config('scanner.async_worker_count', 2));
        $workers = [];

        for ($i = 0; $i < $count; $i++) {
            $workers[] = $this->bootWorker();
        }

        return $workers;
    }

    /**
     * Start `queue:work` in a detached child process. The array form is used
     * so no shell is involved and paths with spaces are handled correctly.
     */
    public function bootWorker(): InvokedProcess
    {
        $log = storage_path('logs/scan-worker.log');

        return Process::path(base_path())
            ->timeout(360)
            ->start(
                [
                    $this->phpBinary(),
                    'artisan',
                    'queue:work',
                    'database',
                    '--queue=default',
                    '--once',
                    '--stop-when-empty',
                    '--timeout=300',
                    '--no-interaction',
                ],
                static function (string $type, string $line) use ($log): void {
                    file_put_contents($log, trim($line).PHP_EOL, FILE_APPEND);
                }
            );
    }

    /**
     * The PHP CLI binary to run the worker with. Web SAPIs report themselves
     * via PHP_BINARY (php-cgi.exe under CGI, for example), which cannot run
     * console commands, so the CLI binary next to it is used instead.
     */
    public function phpBinary(): string
    {
        $configured = config('scanner.php_binary');

        if (is_string($configured) && trim($configured) !== '') {
            return $configured;
        }

        if (PHP_OS_FAMILY === 'Windows') {
            $cli = dirname(PHP_BINARY).DIRECTORY_SEPARATOR.'php.exe';

            if (is_file($cli)) {
                return $cli;
            }
        }

        return PHP_BINARY;
    }

    private function queueIsSync(): bool
    {
        return config('queue.default') === 'sync';
    }

    private function runInline(int $scanId): void
    {
        (new RunSurfaceScan($scanId))->handle(
            app(SurfaceScanner::class),
            app(SecurityRatingService::class)
        );
    }
}
