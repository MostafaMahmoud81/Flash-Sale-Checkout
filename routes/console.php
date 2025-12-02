<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');


Schedule::command('holds:release-expired --batch=200 --max-seconds=2')
    ->everyThirtySeconds()
    ->withoutOverlapping();

Schedule::command('order:cancel-stale-orders --batch=200 --max-seconds=2')
    ->everyMinute()
    ->withoutOverlapping();