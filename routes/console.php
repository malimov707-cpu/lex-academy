<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('academy:remind-deadlines')->hourly()->withoutOverlapping()->onOneServer();
Schedule::command('academy:backup')->dailyAt('02:00')->withoutOverlapping()->onOneServer()->environments(['production']);

Schedule::command('courtroom:prune-transcripts')->daily()->withoutOverlapping()->onOneServer();
Schedule::command('legal:sources:check')->dailyAt('03:15')->withoutOverlapping(60)->onOneServer();
