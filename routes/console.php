<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');
Schedule::command('app:check-plan-expirations')->dailyAt('00:05')->timezone('Asia/Tashkent');
Schedule::command('app:end-expired-challenges')->dailyAt('00:10')->timezone('Asia/Tashkent');
Schedule::command('app:generate-daily-plans')->dailyAt('08:00')->timezone('Asia/Tashkent');
Schedule::command('app:send-task-reminders')->everyFifteenMinutes();
Schedule::command('app:send-daily-summaries')->everyFifteenMinutes();
Schedule::command('app:send-challenge-reminders')->everyFifteenMinutes();
Schedule::command('app:send-weekly-ad')->weeklyOn(1, '10:00')->timezone('Asia/Tashkent');
