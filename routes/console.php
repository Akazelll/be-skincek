<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('backup:database')->dailyAt('02:00')->withoutOverlapping();
Schedule::command('users:prune')->dailyAt('03:00')->withoutOverlapping();
Schedule::command('scan-photos:purge')->dailyAt('04:00')->withoutOverlapping();
Schedule::command('subscriptions:expire')->dailyAt('05:00')->withoutOverlapping();
