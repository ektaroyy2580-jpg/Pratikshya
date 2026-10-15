<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Background jobs (run `php artisan schedule:work` locally, or cron `schedule:run` in production).
use Illuminate\Support\Facades\Schedule;

Schedule::command('dalal:scan')->everyFifteenMinutes()->withoutOverlapping();
Schedule::command('integrity:prune')->daily();
Schedule::command('games:expire-stale')->everyFiveMinutes()->withoutOverlapping();
