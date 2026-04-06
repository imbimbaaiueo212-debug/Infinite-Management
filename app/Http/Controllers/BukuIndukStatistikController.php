<?php

namespace App\Http\Controllers;

use App\Models\BukuInduk;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth; // Tambahkan ini

class BukuIndukStatistikController extends Controller
{
    public function index(Request $request)
    {
        $bulan = $request->input('bulan', now()->month);
        $tahun = $request->input('tahun', now()->year);

        $user = Auth::user(); // User yang sedang login

        // === CEK ROLE USER ===
        // Sesuaikan dengan cara kamu menyimpan role/unit
        // Contoh 1: pakai kolom 'role' di tabel users
        $isAdmin = in_array($user->role ?? '', ['admin', 'kepala_pusat', 'superadmin']);

        // Contoh 2: jika pakai package Spatie Permission
        // $isAdmin = $user->hasRole('admin');

        // Ambil unit dari user (misal kolom bimba_unit di tabel users)
        $userUnit = $user->bimba_unit ?? null; // Ganti sesuai nama kolom di tabel users kamu

        // === DETERMINASI UNIT YANG DIPAKAI ===
        $selectedUnit = $request->input('unit_id');

        if (!$isAdmin) {
            // User biasa: paksa pakai unit miliknya sendiri
            $selectedUnit = $userUnit;
        }
        // Jika admin dan pilih "semua", biarkan null/kosong

        $startDate = Carbon::create($tahun, $bulan, 1)->startOfMonth();
        $endDate   = $startDate->copy()->endOfMonth();

        // Query dasar
        $queryBase = BukuInduk::query();

        if ($selectedUnit && $selectedUnit !== 'semua') {
            $queryBase->where('bimba_unit', $selectedUnit);
        }

        // 1. Murid AKTIF di akhir periode
        $muridAktif = $queryBase->clone()
            ->where('tgl_masuk', '<=', $endDate)
            ->where(function ($q) use ($endDate) {
                $q->whereNull('tgl_keluar')
                  ->orWhere('tgl_keluar', '>', $endDate);
            })
            ->get();

        // 2. Murid BARU di periode ini
        $muridBaru = $queryBase->clone()
            ->whereBetween('tgl_masuk', [$startDate, $endDate])
            ->get();

        // 3. Murid KELUAR di periode ini
        $muridKeluar = $queryBase->clone()
            ->whereBetween('tgl_keluar', [$startDate, $endDate])
            ->get();

        $totalAktif = $muridAktif->count();

        // === REALISASI MURID ===
        $trialBaru = $muridBaru->where('status', 'baru')->count();
        $muridBaruCount = $muridBaru->count();
        $muridKeluarCount = $muridKeluar->count();
        $muridAktifCount = $totalAktif;

        $muridDhuafa = $muridAktif->filter(fn($m) => $m->note && str_contains(strtolower($m->note), 'dhuafa'))->count();
        $muridBNF = $muridAktif->filter(fn($m) => $m->note && str_contains(strtolower($m->note), 'bnf'))->count();

        // === BERDASARKAN USIA ===
        $dataUsia = [
            ['label' => '< 3 Tahun', 'jumlah' => $muridAktif->where('usia', '<', 3)->count()],
            ['label' => '3 Tahun', 'jumlah' => $muridAktif->where('usia', 3)->count()],
            ['label' => '4 Tahun', 'jumlah' => $muridAktif->where('usia', 4)->count()],
            ['label' => '5 Tahun', 'jumlah' => $muridAktif->where('usia', 5)->count()],
            ['label' => '6 Tahun', 'jumlah' => $muridAktif->where('usia', 6)->count()],
            ['label' => '> 6 Tahun', 'jumlah' => $muridAktif->where('usia', '>', 6)->count()],
        ];
        foreach ($dataUsia as &$item) {
            $item['persen'] = $totalAktif > 0 ? round(($item['jumlah'] / $totalAktif) * 100) : 0;
        }

        // === LAMA BELAJAR ===
        $extractBulan = fn($lama_bljr) => $lama_bljr ? (int) preg_replace('/[^0-9]/', '', $lama_bljr) : 0;
        $dataLama = [
            ['label' => '0 - 3 Bulan', 'jumlah' => $muridAktif->filter(fn($m) => ($b = $extractBulan($m->lama_bljr)) <= 3)->count()],
            ['label' => '4 - 6 Bulan', 'jumlah' => $muridAktif->filter(fn($m) => ($b = $extractBulan($m->lama_bljr)) >= 4 && $b <= 6)->count()],
            ['label' => '7 - 12 Bulan', 'jumlah' => $muridAktif->filter(fn($m) => ($b = $extractBulan($m->lama_bljr)) >= 7 && $b <= 12)->count()],
            ['label' => '13 - 18 Bulan', 'jumlah' => $muridAktif->filter(fn($m) => ($b = $extractBulan($m->lama_bljr)) >= 13 && $b <= 18)->count()],
            ['label' => '19 - 24 Bulan', 'jumlah' => $muridAktif->filter(fn($m) => ($b = $extractBulan($m->lama_bljr)) >= 19 && $b <= 24)->count()],
            ['label' => '> 24 Bulan', 'jumlah' => $muridAktif->filter(fn($m) => ($b = $extractBulan($m->lama_bljr)) > 24)->count()],
        ];
        foreach ($dataLama as &$item) {
            $item['persen'] = $totalAktif > 0 ? round(($item['jumlah'] / $totalAktif) * 100) : 0;
        }

        // === LAIN-LAIN ===
        $tahapPersiapan = $muridAktif->where('tahap', 'Persiapan')->count();
        $tahapLanjutan = $muridAktif->where('tahap', 'Lanjutan')->count();
        $aktifKembali = $muridAktif->filter(fn($m) => $m->note && str_contains(strtolower($m->note), 'aktif kembali'))->count();
        $cuti = $muridAktif->filter(fn($m) => $m->note && str_contains(strtolower($m->note), 'cuti'))->count();
        $garansi = $muridAktif->filter(fn($m) => $m->note && str_contains(strtolower($m->note), 'garansi'))->count();
        $pindahan = $muridAktif->filter(fn($m) => $m->note && str_contains(strtolower($m->note), 'pindahan'))->count();

        // === DROPDOWN OPTIONS ===
        $bulanOptions = [1=>'Januari',2=>'Februari',3=>'Maret',4=>'April',5=>'Mei',6=>'Juni',7=>'Juli',8=>'Agustus',9=>'September',10=>'Oktober',11=>'November',12=>'Desember'];
        $tahunOptions = range(now()->year - 5, now()->year + 1);

        // === UNIT OPTIONS — HANYA UNTUK ADMIN ===
        $unitOptions = [];
        if ($isAdmin) {
            $unitOptions = DB::table('units')
                ->whereNotNull('bimba_unit')
                ->where('bimba_unit', '!=', '')
                ->orderBy('bimba_unit')
                ->pluck('bimba_unit', 'bimba_unit')
                ->toArray();
            $unitOptions = ['' => 'Semua Unit'] + $unitOptions; // '' untuk semua
        }

        // Nama unit yang ditampilkan di badge
        if (!$isAdmin && $userUnit) {
            $namaUnitTerpilih = " - {$userUnit}";
        } elseif ($selectedUnit && $selectedUnit !== '') {
            $namaUnitTerpilih = " - {$selectedUnit}";
        } else {
            $namaUnitTerpilih = " - Semua Unit";
        }

        return view('buku_induk.statistik_ringkasan', compact(
            'bulan', 'tahun', 'bulanOptions', 'tahunOptions',
            'trialBaru', 'muridBaruCount', 'muridKeluarCount', 'muridAktifCount',
            'muridDhuafa', 'muridBNF', 'dataUsia', 'dataLama',
            'tahapPersiapan', 'tahapLanjutan', 'aktifKembali', 'cuti', 'garansi', 'pindahan',
            'unitOptions', 'selectedUnit', 'namaUnitTerpilih', 'isAdmin', 'userUnit'
        ));
    }
}