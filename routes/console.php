<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Reminder emails. Needs `php artisan schedule:run` every minute from cron (see README).
Schedule::command('family:remind')->dailyAt('07:00')->withoutOverlapping();
Schedule::command('family:digest')->weeklyOn(1, '07:00')->withoutOverlapping();
