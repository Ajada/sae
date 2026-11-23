<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('xflow:process-scheduled-messages')
    ->everyMinute()
    ->withoutOverlapping();

Schedule::command('xflow:check-reminder-due-dates')
    ->dailyAt('08:00')
    ->withoutOverlapping();

Schedule::command('xflow:process-recurring-messages')
    ->everyMinute()
    ->withoutOverlapping();

Schedule::command('sae:notify-asos')
    ->dailyAt('08:00')
    ->withoutOverlapping();
