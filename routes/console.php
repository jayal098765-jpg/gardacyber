<?php

use App\Models\Cve;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Keep the local NVD CVE database current. Full rebuild every Monday,
// KEV flags refreshed daily; both tolerate overlapping runs gracefully.
Schedule::command('cve:sync --all --kev')
    ->weeklyOn(1, '03:00')
    ->withoutOverlapping()
    ->onFailure(function () {
        report(new RuntimeException('Scheduled full CVE sync failed.'));
    });

Schedule::command('cve:sync --kev')
    ->dailyAt('04:30')
    ->withoutOverlapping()
    ->onFailure(function () {
        report(new RuntimeException('Scheduled KEV refresh failed.'));
    });

// Fresh-deployment bootstrap: fill the local CVE database automatically
// until it has enough data, then the weekly full sync takes over.
Schedule::command('cve:sync --all --kev')
    ->everyFiveMinutes()
    ->when(fn (): bool => config('cve.bootstrap_on_boot', false) && Cve::query()->count() < 1000)
    ->withoutOverlapping()
    ->onFailure(function () {
        report(new RuntimeException('CVE bootstrap sync failed.'));
    });
