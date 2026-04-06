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
        $schedule->command('imbalan:generate-bulan-ini')
            ->monthlyOn(26, '01:00');
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

    // TAMBAHKAN INI: Bind path public ke public_html (naik satu level dari bootstrap)
    ->create();
    //->usePublicPath(dirname(__DIR__) . '/public_html');  // <-- tambah baris ini
