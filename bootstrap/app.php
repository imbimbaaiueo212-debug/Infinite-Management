<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Console\Scheduling\Schedule;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withSchedule(function (Schedule $schedule) {
        
        // Rekap Progresif - Setiap tanggal 26 jam 01:00
        $schedule->command('rekap:generate-bulanan')
                 ->monthlyOn(26, '01:00')
                 ->withoutOverlapping();

        // Command lain yang sudah ada
        $schedule->command('imbalan:generate-bulan-ini')
                 ->monthlyOn(26, '01:00')
                 ->withoutOverlapping();

        // Command trial (jika masih dipakai)
        $schedule->command('trial:auto-activate')
                 ->dailyAt('01:00')
                 ->withoutOverlapping();
    })
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->use([
            \App\Http\Middleware\TrustProxies::class,
        ]);

        $middleware->alias([
            'unit.selected' => \App\Http\Middleware\EnsureUnitSelected::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })
    ->create();