<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Http\Controllers\ImbalanRekapController;
use Carbon\Carbon;

class GenerateImbalanRekapMonthly extends Command
{
    protected $signature = 'imbalan:generate-bulan-ini';
    protected $description = 'Generate ImbalanRekap untuk bulan berjalan';

    public function handle()
    {
        // misal: selalu generate untuk bulan sebelumnya
        $labelBulan = Carbon::now()->subMonth()->locale('id')->translatedFormat('F Y');

        // cara cepat (agak "nakal" tapi jalan 😅)
        $controller = app(ImbalanRekapController::class);
        $result = $controller->createRekapsForPeriode($labelBulan);

        $this->info("Selesai: {$result['message']}");
        return Command::SUCCESS;
    }
}
