<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;


/*
|--------------------------------------------------------------------------
| Default command
|--------------------------------------------------------------------------
*/
Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');


/*
|--------------------------------------------------------------------------
| Scheduler Rekap Progresif Bulanan
|--------------------------------------------------------------------------
*/
Schedule::command('rekap:generate-bulanan')
    ->monthlyOn(26, '01:00'); // SETIAP TANGGAL 26 JAM 01:00

Schedule::command('trial:auto-activate')->dailyAt('01:00');
