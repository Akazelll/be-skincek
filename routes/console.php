<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Foundation\Inspiring;


Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('backup:database')->dailyAt('02:00')->withoutOverlapping();
Schedule::command('users:prune')->dailyAt('03:00')->withoutOverlapping();
