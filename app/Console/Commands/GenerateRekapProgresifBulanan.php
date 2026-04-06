<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Carbon\Carbon;
use App\Models\Profile;
use App\Models\RekapProgresif;
use App\Http\Controllers\RekapProgresifController;
use Illuminate\Support\Facades\Log;

class GenerateRekapProgresifBulanan extends Command
{
    protected $signature = 'rekap:generate-bulanan';
    protected $description = 'Generate Rekap Progresif otomatis untuk BULAN SEBELUMNYA setiap tanggal 26';

    public function handle()
    {
        $today = Carbon::now();

        // SAFETY: hanya jalan di tanggal 26
        if ($today->day !== 26) {
            $this->info('Bukan tanggal 26. Command dilewati.');
            return Command::SUCCESS;
        }

        // Ambil BULAN SEBELUMNYA
        $prevMonth = $today->copy()->subMonth();
        $bulanNama = strtolower($prevMonth->translatedFormat('F')); // 'februari', 'maret', dll
        $tahun     = $prevMonth->year;

        $this->info("Memulai generate Rekap Progresif untuk BULAN SEBELUMNYA: {$bulanNama} {$tahun}");

        $controller = app(RekapProgresifController::class);

        $totalGenerated = 0;
        $totalSkipped   = 0;
        $errors         = [];

        // Ambil semua profile aktif yang relevan (Guru + Kepala Unit)
        $profiles = Profile::whereIn('jabatan', ['Guru', 'Kepala Unit', 'KU'])
            ->whereNotIn('status_karyawan', ['Resign', 'Keluar', 'Pensiun', 'Non Aktif', 'Non-aktif'])
            ->get();

        foreach ($profiles as $profile) {
            // Cek duplikat untuk bulan lalu
            $exists = RekapProgresif::where('nama', $profile->nama)
                ->where('bulan', $bulanNama)
                ->where('tahun', $tahun)
                ->exists();

            if ($exists) {
                $totalSkipped++;
                continue;
            }

            try {
                // Panggil method auto-generate di controller
                $controller->autoGenerateForPreviousMonth($profile, $bulanNama, $tahun);
                $totalGenerated++;
            } catch (\Throwable $e) {
                $errors[] = [
                    'nama'   => $profile->nama,
                    'error'  => $e->getMessage(),
                ];
                Log::error('Gagal generate rekap progresif untuk bulan lalu', [
                    'profile_id' => $profile->id,
                    'nama'       => $profile->nama,
                    'bulan'      => $bulanNama,
                    'tahun'      => $tahun,
                    'error'      => $e->getMessage(),
                ]);
            }
        }

        $this->info("Selesai generate bulan lalu.");
        $this->info("Dibuat: {$totalGenerated} record");
        $this->info("Dilewati (sudah ada): {$totalSkipped} record");

        if (!empty($errors)) {
            $errorsCount = count($errors);
            $this->error("Ada {$errorsCount} error:");
            foreach ($errors as $err) {
                $this->line(" - {$err['nama']}: {$err['error']}");
            }
        }

        return Command::SUCCESS;
    }
}