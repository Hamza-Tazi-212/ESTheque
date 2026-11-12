<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('library:send-overdue-reminders')
    ->dailyAt(config('library.overdue_reminder_time'))
    ->timezone(config('library.timezone'))
    ->withoutOverlapping();
