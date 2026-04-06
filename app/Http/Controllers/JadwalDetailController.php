<?php

namespace App\Http\Controllers;

use App\Models\BukuInduk;
use App\Models\JadwalDetail;
use App\Models\Profile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;  // ← ADD THIS LINE
use Throwable;

class JadwalDetailController extends Controller
{
    /**
     * Menampilkan daftar jadwal, difilter berdasarkan guru/pengampu.
     */
   public function index(Request $request)
{
    $guruNama     = trim($request->input('guru') ?? '');
    $selectedUnit = trim($request->input('unit') ?? '');

    // Normalisasi unit: hilangkan spasi ekstra, ubah ke uppercase, hilangkan karakter aneh
    $selectedUnitNorm = strtoupper(trim(preg_replace('/\s+/', ' ', urldecode($selectedUnit))));

    // Otomatis sync
    if (JadwalDetail::count() === 0 || $request->query('sync') === 'auto') {
        $this->generateSilent();
    }

    $jabatanGuru = [
        'Pengajar', 'Guru', 'Pengajar Tetap', 'Pengajar Honorer', 'Tutor',
        'Pengajar Senior', 'Guru Tetap', 'Kepala Unit', 'Ka Unit', 'KU', 'Kepalaunit',
    ];

    $gurusQuery = Profile::whereNotNull('nik')
        ->whereRaw("TRIM(nik) <> ''")
        ->whereNotNull('jabatan')
        ->where(function ($q) use ($jabatanGuru) {
            foreach ($jabatanGuru as $jab) {
                $q->orWhereRaw("TRIM(UPPER(jabatan)) = ?", [strtoupper(trim($jab))]);
            }
        })
        ->select('nama', 'nik', 'bimba_unit', 'no_cabang', 'jabatan')
        ->orderBy('nama');

    $isAdmin = Auth::check() && Auth::user()->is_admin;

    // Filter guru berdasarkan unit
    if ($isAdmin && $selectedUnit !== '' && $selectedUnit !== 'SEMUA') {
        $gurusQuery->whereRaw("TRIM(UPPER(bimba_unit)) = ?", [$selectedUnitNorm]);
    }

    // DEBUG: log untuk melihat apa yang terjadi
    Log::debug('Jadwal Index Debug', [
        'user_id'          => Auth::id(),
        'is_admin'         => $isAdmin,
        'selected_unit_raw'=> $selectedUnit,
        'selected_unit_norm'=> $selectedUnitNorm,
        'guru_query_sql'   => $gurusQuery->toSql(),
        'guru_query_bindings' => $gurusQuery->getBindings(),
        'guru_count_before' => $gurusQuery->count(),
    ]);

    $gurus = $gurusQuery->get();

    // Safety net (pakai array, skip jika nama kosong)
    $namaGuruDiJadwal = JadwalDetail::whereNotNull('guru')
        ->when($isAdmin && $selectedUnit !== '' && $selectedUnit !== 'SEMUA', function ($q) use ($selectedUnitNorm) {
            $q->whereHas('murid', fn($sq) => $sq->whereRaw("TRIM(UPPER(bimba_unit)) = ?", [$selectedUnitNorm]));
        })
        ->whereRaw("TRIM(guru) <> ''")
        ->where('guru', '!=', 'TANPA GURU')
        ->distinct()
        ->pluck('guru');

    $namaSudahAda = $gurus->pluck('nama')->map(fn($n) => trim($n));
    $namaKurang   = $namaGuruDiJadwal->map(fn($n) => trim($n))->diff($namaSudahAda);

    if ($namaKurang->isNotEmpty()) {
        $extra = $namaKurang->map(function ($nama) {
            $trimmed = trim($nama);
            if ($trimmed === '') return null;
            return [
                'nama'       => $trimmed,
                'nik'        => '—',
                'jabatan'    => 'Pengampu (dari jadwal)',
                'bimba_unit' => null,
                'no_cabang'  => null,
            ];
        })->filter();

        $gurusArray = $gurus->map(fn($guru) => (array) $guru)
            ->merge($extra)
            ->unique('nama')
            ->filter(fn($item) => !empty($item['nama']))
            ->sortBy('nama')
            ->values();

        $gurus = collect($gurusArray);
    }

    // Fallback
    if ($gurus->isEmpty()) {
        $gurus = collect([[
            'nama'    => 'TANPA GURU',
            'nik'     => '—',
            'jabatan' => 'Sistem',
        ]]);
    }

    // Daftar unit
    $units = BukuInduk::whereIn('status', ['Aktif', 'Baru'])
        ->whereNotNull('bimba_unit')
        ->whereRaw("TRIM(bimba_unit) <> ''")
        ->distinct()
        ->orderBy('bimba_unit')
        ->pluck('bimba_unit', 'bimba_unit')
        ->toArray();

    // Query jadwal
    $query = JadwalDetail::with('murid')->orderBy('jam_ke', 'asc');

    if ($isAdmin && $selectedUnit !== '' && $selectedUnit !== 'SEMUA') {
        $query->whereHas('murid', function ($q) use ($selectedUnitNorm) {
            $q->whereRaw("TRIM(UPPER(bimba_unit)) = ?", [$selectedUnitNorm]);
        });
    }

    if ($guruNama !== 'SEMUA' && $guruNama !== '') {
        $query->whereRaw("TRIM(UPPER(guru)) = ?", [strtoupper(trim($guruNama))]);
    }

    $jadwal = $query->get()->groupBy('jam_ke');

    $jabatanGuru = null;
    if ($guruNama !== 'SEMUA' && $guruNama !== '') {
        $profile = Profile::whereRaw("TRIM(nama) = ?", [$guruNama])->first();
        $jabatanGuru = $profile?->jabatan;
    }

    return view('jadwal.index', compact(
        'jadwal', 'gurus', 'guruNama', 'jabatanGuru', 'units', 'selectedUnit'
    ));
}

    /**
     * Sinkronisasi / generate ulang JadwalDetail dari BukuInduk.
     * Idempotent (bisa dijalankan berulang tanpa duplikasi berlebih).
     */
    public function generate()
    {
        DB::beginTransaction();

        try {
            $totalBaru = 0;
            $totalSkip = 0;
            $totalUpdate = 0;

            BukuInduk::query()
                ->whereIn('status', ['Aktif', 'Baru'])
                ->whereNotNull('kode_jadwal')
                ->whereRaw("TRIM(kode_jadwal) <> ''")
                ->select(['id', 'guru', 'kelas', 'kode_jadwal', 'jenis_kbm'])
                ->orderBy('id')
                ->chunkById(100, function ($muridList) use (&$totalBaru, &$totalSkip, &$totalUpdate) {

                    foreach ($muridList as $murid) {
                        $kodeStr = trim((string) $murid->kode_jadwal);
                        $kode = (int) preg_replace('/\D+/', '', $kodeStr);

                        if ($kode === 0 || empty($kodeStr)) {
                            $totalSkip++;
                            continue;
                        }

                        // Tentukan shift & hari
                        $shift = null;
                        $hariList = [];

                        if ($kode >= 108 && $kode <= 116) {
                            $shift = 'SRJ';
                            $hariList = ['Senin', 'Rabu', 'Jumat'];
                        } elseif ($kode >= 208 && $kode <= 211) {
                            $shift = 'SKS';
                            $hariList = ['Selasa', 'Kamis', 'Sabtu'];
                        } elseif ($kode >= 308 && $kode <= 311) {
                            $shift = 'S6';
                            $hariList = ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
                        } else {
                            $totalSkip++;
                            continue;
                        }

                        // Tentukan jam ke
                        $jam_ke = match ($kode) {
                            108, 208, 308 => 1,
                            109, 209, 309 => 2,
                            110, 210, 310 => 3,
                            111, 211, 311 => 4,
                            112 => 5, 113 => 6, 114 => 7, 115 => 8, 116 => 9,
                            default => 1,
                        };

                        // Normalisasi nama guru
                        $guruNama = trim((string) $murid->guru);
                        if (empty($guruNama) || $guruNama === '-') {
                            $guruNama = 'TANPA GURU';
                        }

                        // Buat / update per hari
                        foreach ($hariList as $hari) {
                            $jd = JadwalDetail::updateOrCreate(
                                [
                                    'murid_id' => $murid->id,
                                    'hari'     => $hari,
                                    'shift'    => $shift,
                                    'jam_ke'   => $jam_ke,
                                ],
                                [
                                    'guru'        => $guruNama,
                                    'kelas'       => $murid->kelas ?? '-',
                                    'kode_jadwal' => $murid->kode_jadwal ?? '-',
                                    'jenis_kbm'   => $murid->jenis_kbm ?? '-',
                                ]
                            );

                            if ($jd->wasRecentlyCreated) {
                                $totalBaru++;
                            } elseif ($jd->wasChanged()) {
                                $totalUpdate++;
                            } else {
                                $totalSkip++;
                            }
                        }
                    }
                });

            DB::commit();

            return redirect()->route('jadwal.index')
                ->with('success', "Sinkronisasi selesai!  
                    Baru: {$totalBaru} | Update: {$totalUpdate} | Dilewati: {$totalSkip}");

        } catch (Throwable $e) {
            DB::rollBack();
            Log::error('Gagal sinkron jadwal', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            report($e);

            return back()->with('error', 'Gagal sinkronisasi jadwal. Coba lagi atau hubungi admin.');
        }
    }
    private function generateSilent()
{
    try {
        DB::beginTransaction();

        $totalBaru = 0;
        $totalSkip = 0;
        $totalUpdate = 0;

        // ... copy seluruh isi try dari method generate() kamu ...

        // Ganti return redirect menjadi:
        DB::commit();
        Log::info("Sinkronisasi otomatis selesai (silent)", [
            'baru' => $totalBaru,
            'update' => $totalUpdate,
            'skip' => $totalSkip
        ]);

    } catch (Throwable $e) {
        DB::rollBack();
        Log::error('Gagal sinkron otomatis', ['error' => $e->getMessage()]);
    }
}
}