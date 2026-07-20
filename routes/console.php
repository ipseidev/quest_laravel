<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('quest:purge-expired')->dailyAt('03:00');
Schedule::command('quest:generate-monthly-chapters')->monthlyOn(1, '04:00');
// Quests complete any day, so scan daily and close the arc within a day of finishing.
Schedule::command('quest:generate-quest-chapters')->dailyAt('04:30');
