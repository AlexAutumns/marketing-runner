<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Resume timed waiting workflows on a fixed scheduler window.
// withoutOverlapping() prevents a second resume cycle from starting if the prior one is still running.
Schedule::command('workflow:resume-waiting --limit=50')
    ->cron('*/20 * * * *')
    ->withoutOverlapping();
