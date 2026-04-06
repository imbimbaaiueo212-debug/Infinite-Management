<?php

namespace App\Http\Controllers;

use App\Models\RekapAbsensi;
use App\Models\Profile;
use App\Models\BukuInduk;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RekapAbsensiController extends Controller
{
    public function index()
    {
        // 1. Sinkron guru aktif dari Profile (termasuk update jumlah_rombim dari Profile)
        $this->syncGuruAktif();

        // 2. Hitung ulang kolom jadwal & jumlah_murid dari Buku Induk
        //    jumlah_rombim sudah di-set dari Profile di syncGuruAktif
        $this->hitungJadwalDariBukuInduk();

        // 3. Ambil data rekap untuk ditampilkan
        $rekap = RekapAbsensi::all();

        return view('rekap.index', compact('rekap'));
    }

    // Sinkron guru aktif dari Profile + update jumlah_rombim dari Profile
    private function syncGuruAktif()
    {
        $guruProfiles = Profile::where('jabatan', 'Guru')
            ->where('status_karyawan', 'Aktif')
            ->get();

        foreach ($guruProfiles as $guru) {
            RekapAbsensi::updateOrCreate(
                ['nama_relawan' => $guru->nama],
                [
                    'nik'            => $guru->nik ?? null,
                    'jabatan'        => $guru->jabatan,
                    'departemen'     => $guru->departemen ?? null,
                    'bimba_unit'     => $guru->bimba_unit ?? null,
                    'no_cabang'      => $guru->no_cabang ?? null,
                    'penyesuaian_rb' => 0,
                    'jumlah_rombim'  => $guru->jumlah_rombim ?? 0, // <-- AMBIL DARI PROFILE
                ]
            );
        }
    }

    // Hitung otomatis kolom jadwal & jumlah_murid dari Buku Induk
    // jumlah_rombim tetap dari Profile (tidak di-reset/diubah di sini)
    private function hitungJadwalDariBukuInduk()
    {
        // Reset hanya kolom jadwal & jumlah_murid (jumlah_rombim tetap dari Profile)
        RekapAbsensi::query()->update([
            'srj_108' => 0, 'srj_109' => 0, 'srj_110' => 0, 'srj_111' => 0,
            'srj_112' => 0, 'srj_113' => 0, 'srj_114' => 0, 'srj_115' => 0,
            'sks_208' => 0, 'sks_209' => 0, 'sks_210' => 0, 'sks_211' => 0,
            's6_308' => 0, 's6_309' => 0, 's6_310' => 0, 's6_311' => 0,
            'jumlah_murid' => 0,
            // jumlah_rombim TIDAK di-reset agar tetap dari Profile
        ]);

        // GANTI 'guru' jika nama kolomnya berbeda di buku_induk
        $kolomGuru = 'guru';

        $data = BukuInduk::whereNotNull('kode_jadwal')
            ->whereNotNull($kolomGuru)
            ->where('status', 'aktif')
            ->select(
                $kolomGuru,
                'bimba_unit',
                'no_cabang',
                'kode_jadwal',
                DB::raw('COUNT(*) as total_murid')
            )
            ->groupBy($kolomGuru, 'bimba_unit', 'no_cabang', 'kode_jadwal')
            ->get();

        foreach ($data as $item) {
            $namaGuruDb = trim(strtoupper($item->{$kolomGuru}));
            $unit       = $item->bimba_unit;
            $cabang     = $item->no_cabang;
            $kode       = $item->kode_jadwal;
            $murid      = $item->total_murid;

            $rekap = RekapAbsensi::whereRaw('UPPER(TRIM(nama_relawan)) = ?', [$namaGuruDb])
                ->where('bimba_unit', $unit)
                ->where('no_cabang', $cabang)
                ->first();

            if ($rekap && $murid > 0) {
                $kolom = null;

                if (in_array($kode, [108,109,110,111,112,113,114,115,116])) {
                    $kolom = 'srj_' . $kode;
                } elseif (in_array($kode, [206,207,208,209,210,211])) {
                    $kolom = 'sks_' . $kode;
                } elseif (in_array($kode, [306,307,308,309,310,311])) {
                    $kolom = 's6_' . $kode;
                }

                if ($kolom) {
                    // Kolom jadwal = jumlah murid di kode tersebut
                    $rekap->increment($kolom, $murid);

                    // Total murid semua jadwal
                    $rekap->increment('jumlah_murid', $murid);
                }
            }
        }
    }

    // Method create, store, edit, update, destroy, updateKodeJadwal tetap sama
    public function create()
    {
        $guruProfiles = Profile::where('jabatan', 'Guru')
            ->where('status_karyawan', 'Aktif')
            ->get();

        foreach ($guruProfiles as $guru) {
            RekapAbsensi::updateOrCreate(
                ['nama_relawan' => $guru->nama],
                [
                    'nik'            => $guru->nik ?? null,
                    'jabatan'        => $guru->jabatan,
                    'departemen'     => $guru->departemen ?? null,
                    'bimba_unit'     => $guru->bimba_unit ?? null,
                    'no_cabang'      => $guru->no_cabang ?? null,
                    'penyesuaian_rb' => 0,
                ]
            );
        }

        return redirect()->route('rekap.index')->with('success', 'Rekap guru aktif berhasil dibuat & diperbarui!');
    }

    public function store(Request $request)
    {
        $guru = Profile::where('nama', $request->nama_relawan)->first();

        RekapAbsensi::create([
            'nama_relawan'   => $request->nama_relawan,
            'nik'            => $guru->nik ?? null,
            'jabatan'        => $request->jabatan ?? $guru->jabatan ?? 'Guru',
            'departemen'     => $request->departemen ?? $guru->departemen ?? null,
            'bimba_unit'     => $guru->bimba_unit ?? null,
            'no_cabang'      => $guru->no_cabang ?? null,
            'penyesuaian_rb' => $request->penyesuaian_rb ?? 0,
        ]);

        return redirect()->route('rekap.index')->with('success', 'Data rekap berhasil ditambahkan.');
    }

    public function edit(RekapAbsensi $rekap)
    {
        return view('rekap.edit', compact('rekap'));
    }

    public function update(Request $request, RekapAbsensi $rekap)
    {
        $guru = Profile::where('nama', $rekap->nama_relawan)->first();

        $rekap->update([
            'nik'            => $guru->nik ?? $rekap->nik,
            'jabatan'        => $request->jabatan ?? $rekap->jabatan,
            'departemen'     => $request->departemen ?? $rekap->departemen,
            'bimba_unit'     => $guru->bimba_unit ?? $rekap->bimba_unit,
            'no_cabang'      => $guru->no_cabang ?? $rekap->no_cabang,
            'penyesuaian_rb' => $request->penyesuaian_rb ?? $rekap->penyesuaian_rb,
            'jumlah_rombim'     => $guru->jumlah_rombim,
        ]);

        return redirect()->route('rekap.index')->with('success', 'Data rekap berhasil diperbarui.');
    }

    public function destroy(RekapAbsensi $rekap)
    {
        $rekap->delete();
        return redirect()->route('rekap.index')->with('success', 'Data rekap berhasil dihapus.');
    }

    public function updateKodeJadwal()
    {
        $rekapGuru = RekapAbsensi::all();
        foreach ($rekapGuru as $rekap) {
            $guru = Profile::where('nama', $rekap->nama_relawan)->first();
            if ($guru) {
                $rekap->update([
                    'nik'        => $guru->nik,
                    'jabatan'    => $guru->jabatan,
                    'departemen' => $guru->departemen,
                    'bimba_unit'    => $guru->bimba_unit,
                    'no_cabang'  => $guru->no_cabang,
                    'jumlah_rombim'     => $guru->jumlah_rombim,
                ]);
            }
        }

        return redirect()->route('rekap.index')->with('success', 'Semua data rekap berhasil disinkronkan ulang!');
    }
}