<?php

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
