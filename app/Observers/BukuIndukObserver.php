<?php

namespace App\Observers;

use App\Models\BukuInduk;
use App\Services\JadwalSyncService;

class BukuIndukObserver
{
    public function created(BukuInduk $bukuInduk)
    {
        JadwalSyncService::syncMurid($bukuInduk);
    }

    public function updated(BukuInduk $bukuInduk)
    {
        if ($bukuInduk->wasChanged([
            'kode_jadwal',
            'guru',
            'kelas',
            'jenis_kbm',
            'status'
        ])) {
            JadwalSyncService::syncMurid($bukuInduk);
        }
    }
}
