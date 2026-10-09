<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('billing:process')->hourly()->withoutOverlapping();

// Lets the admin dashboard show whether the scheduler is actually running.
Schedule::call(fn () => cache()->put('scheduler.heartbeat', now()->timestamp, 600))->everyMinute()->name('scheduler-heartbeat');
