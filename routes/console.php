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

// Rekap Progresif - Jalankan setiap tanggal 26 pukul 01:00 pagi
Schedule::command('rekap:generate-bulanan')
    ->monthlyOn(26, '01:00')
    ->withoutOverlapping();        // ← Sangat disarankan

// Command lain yang sudah ada
Schedule::command('trial:auto-activate')
    ->dailyAt('01:00')
    ->withoutOverlapping();        // ← Tambahkan ini juga