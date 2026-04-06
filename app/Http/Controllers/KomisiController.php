<?php

namespace App\Http\Controllers;

use App\Models\Komisi;
use App\Models\Profile;
use App\Models\Penerimaan;
use App\Models\BukuInduk;
use App\Models\Unit; // <--- tambah ini di bagian use
use Illuminate\Support\Facades\Log;
use App\Models\Spp;
use App\Models\MuridTrial;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class KomisiController extends Controller
{


    private function bulanToAngka($bulan)
{
    $map = [
        'januari'   => 1, 'februari' => 2,  'maret'    => 3,
        'april'     => 4, 'mei'      => 5,  'juni'     => 6,
        'juli'      => 7, 'agustus'  => 8,  'september'=> 9,
        'oktober'   => 10,'november' => 11, 'desember' => 12,
    ];
    return $map[strtolower(trim($bulan))] ?? null;
}
    public function index(Request $request)
{
    $tahunAwal  = $request->input('tahun_awal', date('Y'));
    $bulanAwal  = $request->input('bulan_awal');
    $tahunAkhir = $request->input('tahun_akhir', date('Y'));
    $bulanAkhir = $request->input('bulan_akhir');
    $unitId     = $request->input('unit_id');           // ← tambah ini

    $query = Komisi::query();

    // Filter periode (tahun & bulan) - tetap sama
    if ($tahunAwal && $bulanAwal) {
        $query->where(function ($q) use ($tahunAwal, $bulanAwal) {
            $q->where('tahun', '>', $tahunAwal)
              ->orWhere(function ($qq) use ($tahunAwal, $bulanAwal) {
                  $qq->where('tahun', $tahunAwal)->where('bulan', '>=', $bulanAwal);
              });
        });
    }

    if ($tahunAkhir && $bulanAkhir) {
        $query->where(function ($q) use ($tahunAkhir, $bulanAkhir) {
            $q->where('tahun', '<', $tahunAkhir)
              ->orWhere(function ($qq) use ($tahunAkhir, $bulanAkhir) {
                  $qq->where('tahun', $tahunAkhir)->where('bulan', '<=', $bulanAkhir);
              });
        });
    }

    // Tambah filter unit (asumsi relasi profile punya kolom unit_id)
    $unitId = $request->input('unit_id');

    if ($unitId) {
        // Cari dulu nama unit berdasarkan ID yang dipilih
        $unit = \App\Models\Unit::find($unitId);
        
        if ($unit) {
            $namaUnit = $unit->biMBA_unit;  // atau trim(strtolower($unit->biMBA_unit)) jika perlu

            $query->where('bimba_unit', $namaUnit);
            // Alternatif case-insensitive:
            // $query->whereRaw('LOWER(TRIM(bimba_unit)) = ?', [strtolower(trim($namaUnit))]);
        }
    }

    $data_komisi = $query->with('profile:id,nama,nik,unit_id')   // tambah unit_id di with
                         ->orderBy('tahun')
                         ->orderBy('bulan')
                         ->orderBy('nomor_urut')
                         ->get();

    $namaBulan = [1=>'Januari',2=>'Februari',3=>'Maret',4=>'April',5=>'Mei',6=>'Juni',
                  7=>'Juli',8=>'Agustus',9=>'September',10=>'Oktober',11=>'November',12=>'Desember'];

    $periodeText = 'Semua Periode';
    if ($bulanAwal && $tahunAwal && $bulanAkhir && $tahunAkhir) {
        $awal  = $namaBulan[(int)$bulanAwal] . ' ' . $tahunAwal;
        $akhir = $namaBulan[(int)$bulanAkhir] . ' ' . $tahunAkhir;
        $periodeText = "$awal → $akhir";
    } elseif ($bulanAwal && $tahunAwal) {
        $periodeText = $namaBulan[(int)$bulanAwal] . ' ' . $tahunAwal . ' ke atas';
    } elseif ($bulanAkhir && $tahunAkhir) {
        $periodeText = 'Sampai ' . $namaBulan[(int)$bulanAkhir] . ' ' . $tahunAkhir;
    }

    // Kirim data unit ke view (harus disiapkan di controller atau view composer)
    $unitOptions = $this->getUnitOptions();   // ← sesuaikan dengan cara kamu ambil list unit

    return view('komisi.index', compact(
        'data_komisi', 
        'tahunAwal', 'bulanAwal', 'tahunAkhir', 'bulanAkhir', 
        'unitId', 'unitOptions', 'periodeText'
    ));
}

// Contoh method pembantu (bisa di controller atau dibuat service/trait)
private function getUnitOptions()
{
    // Sesuaikan dengan struktur data kamu
    return Unit::select('id', 'biMBA_unit')
        ->orderBy('biMBA_unit')
        ->get()
        ->map(function ($unit) {
            return [
                'value' => $unit->id,
                'label' => $unit->biMBA_unit
            ];
        })->toArray();
}

    public function create()
    {
        $karyawan = Profile::whereIn('jabatan', ['Kepala Unit', 'Guru'])
                           ->where('status_karyawan', 'Aktif')
                           ->orderBy('no_urut')
                           ->get();

        return view('komisi.create', compact('karyawan'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'profile_id' => 'required|exists:profiles,id',
            'tahun'      => 'required|integer',
            'bulan'      => 'required|integer|between:1,12',
            'nomor_urut' => 'required|integer',
            // Komisi & data murid
            'komisi_mb_bimba'    => 'required|integer',
            'komisi_mt_bimba'    => 'required|integer',
            'komisi_mb_english'  => 'required|integer',
            'komisi_mt_english'  => 'required|integer',
            'sudah_dibayar'      => 'required|integer',
            // Data murid (sesuaikan semua field yang kamu butuhkan)
            'am1_bimba' => 'required|integer',
            'am2_bimba' => 'required|integer',
            'mgrs'      => 'required|integer',
            'mdf'       => 'required|integer',
            'bnf'       => 'required|integer',
            'bnf2'      => 'required|integer',
            'murid_mb_bimba' => 'required|integer',
            'mk_bimba'  => 'required|integer',
            'murid_mt_bimba' => 'required|integer',
            'am1_english'    => 'required|integer',
            'am2_english'    => 'required|integer',
            'murid_mb_english' => 'required|integer',
            'mk_english'     => 'required|integer',
            'murid_mt_english' => 'required|integer',
            'mb_umum_ku'     => 'nullable|integer',
            'mb_insentif_ku' => 'nullable|integer',
            'keterangan'     => 'nullable|string',
        ]);

        $profile = Profile::findOrFail($request->profile_id);

        // AMBIL TOTAL SPP dari penerimaan berdasarkan guru + bulan + tahun
        $sppBimba = Penerimaan::where('guru', $profile->nama)
                    ->where('bulan', $request->bulan)
                    ->where('tahun', $request->tahun)
                    ->where(function($q) {
                        $q->where('daftar', 'like', '%MBA%')
                          ->orWhere('kelas', 'like', '%MBA%')
                          ->orWhere('kelas', 'like', '%AIUEO%');
                    })
                    ->sum('spp');

        $sppEnglish = Penerimaan::where('guru', $profile->nama)
                    ->where('bulan', $request->bulan)
                    ->where('tahun', $request->tahun)
                    ->where(function($q) {
                        $q->where('daftar', 'like', '%English%')
                          ->orWhere('kelas', 'like', '%English%');
                    })
                    ->sum('spp');

        Komisi::create([
            'profile_id'      => $profile->id,
            'tahun'           => $request->tahun,
            'bulan'           => $request->bulan,
            'nomor_urut'      => $request->nomor_urut,

            // OTOMATIS DARI PROFILE
            'nama'            => $profile->nama,
            'jabatan'         => $profile->jabatan,
            'status'          => $profile->status_karyawan,
            'departemen'      => $profile->departemen,
            'masa_kerja'      => $profile->masa_kerja,

            // OTOMATIS DARI PENERIMAAN
            'spp_bimba'       => $sppBimba,
            'spp_english'     => $sppEnglish,

            // DARI INPUT
            'komisi_mb_bimba'    => $request->komisi_mb_bimba,
            'komisi_mt_bimba'    => $request->komisi_mt_bimba,
            'komisi_mb_english'  => $request->komisi_mb_english,
            'komisi_mt_english'  => $request->komisi_mt_english,
            'sudah_dibayar'      => $request->sudah_dibayar,

            // Data murid
            'am1_bimba'       => $request->am1_bimba,
            'am2_bimba'       => $request->am2_bimba,
            'mgrs'            => $request->mgrs,
            'mdf'             => $request->mdf,
            'bnf'             => $request->bnf,
            'bnf2'            => $request->bnf2,
            'murid_mb_bimba'  => $request->murid_mb_bimba,
            'mk_bimba'        => $request->mk_bimba,
            'murid_mt_bimba'  => $request->murid_mt_bimba,
            'am1_english'     => $request->am1_english,
            'am2_english'     => $request->am2_english,
            'murid_mb_english'=> $request->murid_mb_english,
            'mk_english'      => $request->mk_english,
            'murid_mt_english'=> $request->murid_mt_english,
            'mb_umum_ku'      => $request->mb_umum_ku ?? 0,
            'mb_insentif_ku'  => $request->mb_insentif_ku ?? 0,
            'keterangan'      => $request->keterangan,

            'total_komisi' => $request->komisi_mb_bimba + $request->komisi_mt_bimba +
                              $request->komisi_mb_english + $request->komisi_mt_english,
        ]);

        return redirect()->route('komisi.index')->with('success', 'Komisi berhasil disimpan!');
    }

public function sync(Request $request)
{
    $bulanMap = [
        'januari' => 1, 'februari' => 2, 'maret' => 3, 'april' => 4,
        'mei' => 5, 'juni' => 6, 'juli' => 7, 'agustus' => 8,
        'september' => 9, 'oktober' => 10, 'november' => 11, 'desember' => 12
    ];

    $user = auth()->user();
    $isKepalaUnit = $user && str_contains(strtolower($user->jabatan ?? $user->posisi ?? ''), 'kepala unit');
    $userUnit = null;

    if ($isKepalaUnit) {
        $profileLogin = Profile::where('nama', $user->name)
            ->orWhere('nik', $user->nik ?? null)
            ->first();

        $userUnit = $profileLogin?->bimba_unit 
                 ?? $profileLogin?->departemen 
                 ?? $user->bimba_unit 
                 ?? $user->unit 
                 ?? null;

        Log::info("Sync oleh Kepala Unit: {$user->name}, Unit: " . ($userUnit ?? 'tidak ditemukan'));
    }

    $periodes = Penerimaan::select('bulan', 'tahun')->distinct()->get();
    if ($periodes->isEmpty()) {
        return back()->with('info', 'Tidak ada data penerimaan untuk disinkronkan.');
    }

    // Ambil karyawan unik (guru & kepala unit aktif)
    $uniqueQuery = Profile::whereIn('jabatan', ['Kepala Unit', 'Guru'])
        ->where('status_karyawan', 'Aktif');

    if ($isKepalaUnit && $userUnit) {
        $uniqueQuery->where(function ($q) use ($userUnit) {
            $q->where('bimba_unit', $userUnit)
              ->orWhere('departemen', $userUnit);
        });
    }

    $uniqueKaryawan = $uniqueQuery->select('nama', 'bimba_unit', 'departemen')
        ->distinct()
        ->orderBy('no_urut')
        ->get();

    $karyawan = collect();
    foreach ($uniqueKaryawan as $uk) {
        $profileQuery = Profile::where('nama', $uk->nama);

        if ($uk->bimba_unit) $profileQuery->where('bimba_unit', $uk->bimba_unit);
        else $profileQuery->whereNull('bimba_unit');

        if ($uk->departemen) $profileQuery->where('departemen', $uk->departemen);
        else $profileQuery->whereNull('departemen');

        $profile = $profileQuery->orderBy('id')->first();
        if ($profile) $karyawan->push($profile);
    }

    if ($karyawan->isEmpty()) {
        return back()->with('warning', $isKepalaUnit 
            ? "Tidak ada guru/kepala unit aktif di unit {$userUnit}."
            : 'Tidak ada guru/kepala unit aktif.');
    }

    Log::info("Jumlah karyawan unik yang diproses: " . $karyawan->count());

    $created = $updated = 0;

    foreach ($periodes as $p) {
        $bulanStr   = strtolower(trim($p->bulan));
        $bulanAngka = $bulanMap[$bulanStr] ?? null;
        if (!$bulanAngka) continue;

        // ────────────────────────────────────────────────────────────────
        // 1. TOTAL SPP per unit (untuk kepala unit)
        // ────────────────────────────────────────────────────────────────
        $totalSppPerUnit = [];
        $gurus = $karyawan->where('jabatan', 'Guru');

        foreach ($gurus as $guru) {
            $unitKey = strtoupper(trim($guru->bimba_unit ?? $guru->departemen ?? 'UNKNOWN'));

            $sppBimba = Penerimaan::where('guru', trim($guru->nama))
                ->where('bulan', $p->bulan)
                ->where('tahun', $p->tahun)
                ->where(function ($q) {
                    $q->where('daftar', 'like', '%MBA%')
                      ->orWhere('kelas', 'like', '%MBA%')
                      ->orWhere('kelas', 'like', '%AIUEO%');
                })
                ->sum('spp') ?: 0;

            $sppEnglish = Penerimaan::where('guru', trim($guru->nama))
                ->where('bulan', $p->bulan)
                ->where('tahun', $p->tahun)
                ->where(function ($q) {
                    $q->where('daftar', 'like', '%English%')
                      ->orWhere('kelas', 'like', '%English%');
                })
                ->sum('spp') ?: 0;

            $totalSppPerUnit[$unitKey] = $totalSppPerUnit[$unitKey] ?? ['bimba' => 0, 'english' => 0];
            $totalSppPerUnit[$unitKey]['bimba']   += $sppBimba;
            $totalSppPerUnit[$unitKey]['english'] += $sppEnglish;
        }

        // ────────────────────────────────────────────────────────────────
        // 2. MB per unit
        // ────────────────────────────────────────────────────────────────
        $mbQuery = Penerimaan::where('penerimaan.bulan', $p->bulan)
            ->where('penerimaan.tahun', $p->tahun)
            ->join('buku_induk', 'penerimaan.nim', '=', 'buku_induk.nim')
            ->where('buku_induk.status', 'Baru')
            ->join('profiles', 'buku_induk.guru', '=', 'profiles.nama')
            ->where('profiles.jabatan', 'Guru')
            ->where(function ($q) {
                $q->where('buku_induk.kelas', 'like', '%MBA%')
                  ->orWhere('buku_induk.kelas', 'like', '%AIUEO%');
            });

        if ($isKepalaUnit && $userUnit) {
            $mbQuery->where('profiles.bimba_unit', $userUnit);
        }

        $mbBimbaPerUnit = $mbQuery->selectRaw(
            'UPPER(TRIM(COALESCE(profiles.bimba_unit, profiles.departemen))) as unit_key, COUNT(*) as jml'
        )->groupBy('unit_key')->pluck('jml', 'unit_key')->toArray();

        // ────────────────────────────────────────────────────────────────
        // 3. MK per guru & per unit
        // ────────────────────────────────────────────────────────────────
        $mkQueryGuru = BukuInduk::where('status', 'Keluar')
            ->whereNotNull('tgl_keluar')
            ->whereYear('tgl_keluar', $p->tahun)
            ->whereMonth('tgl_keluar', $bulanAngka)
            ->where(function ($q) {
                $q->where('kelas', 'like', '%MBA%')
                  ->orWhere('kelas', 'like', '%AIUEO%');
            });

        if ($isKepalaUnit && $userUnit) {
            $mkQueryGuru->where('bimba_unit', $userUnit);
        }

        $mkBimbaPerGuru = $mkQueryGuru->selectRaw('TRIM(guru) as guru_nama, COUNT(*) as jml')
            ->groupBy('guru_nama')
            ->pluck('jml', 'guru_nama')
            ->toArray();

        $mkBimbaPerUnit = (clone $mkQueryGuru)->join('profiles', 'buku_induk.guru', '=', 'profiles.nama')
            ->where('profiles.jabatan', 'Guru')
            ->selectRaw(
                'UPPER(TRIM(COALESCE(profiles.bimba_unit, profiles.departemen))) as unit_key, COUNT(*) as jml'
            )->groupBy('unit_key')->pluck('jml', 'unit_key')->toArray();

        // ────────────────────────────────────────────────────────────────
        // LOOP UTAMA: Hitung & simpan per karyawan (tanpa MT)
        // ────────────────────────────────────────────────────────────────
        foreach ($karyawan as $profile) {
            $namaTrim  = trim($profile->nama);
            $namaLower = strtolower($namaTrim);
            $unitKey   = strtoupper(trim($profile->bimba_unit ?? $profile->departemen ?? 'UNKNOWN'));

            // Tentukan bimba_unit & no_cabang
            $bimba_unit = $profile->bimba_unit;
            $no_cabang  = null;
            if ($bimba_unit) {
                $unitModel = Unit::whereRaw('LOWER(TRIM(biMBA_unit)) = ?', [strtolower(trim($bimba_unit))])->first();
                $no_cabang = $unitModel?->no_cabang;
            } else {
                $firstBuku = BukuInduk::where('guru', $namaTrim)
                    ->whereNotNull('bimba_unit')
                    ->first();
                if ($firstBuku) {
                    $bimba_unit = $firstBuku->bimba_unit;
                    $unitModel  = Unit::whereRaw('LOWER(TRIM(biMBA_unit)) = ?', [strtolower(trim($bimba_unit))])->first();
                    $no_cabang  = $unitModel?->no_cabang;
                }
            }

            // SPP
            $sppBimba = $profile->jabatan === 'Guru'
                ? Penerimaan::where('guru', $namaTrim)
                    ->where('bulan', $p->bulan)->where('tahun', $p->tahun)
                    ->where(function ($q) {
                        $q->where('daftar', 'like', '%MBA%')
                          ->orWhere('kelas', 'like', '%MBA%')
                          ->orWhere('kelas', 'like', '%AIUEO%');
                    })
                    ->sum('spp') ?: 0
                : ($totalSppPerUnit[$unitKey]['bimba'] ?? 0);

            $sppEnglish = $profile->jabatan === 'Guru'
                ? Penerimaan::where('guru', $namaTrim)
                    ->where('bulan', $p->bulan)->where('tahun', $p->tahun)
                    ->where(function ($q) {
                        $q->where('daftar', 'like', '%English%')
                          ->orWhere('kelas', 'like', '%English%');
                    })
                    ->sum('spp') ?: 0
                : ($totalSppPerUnit[$unitKey]['english'] ?? 0);

            // MB / MK
            $mb = $profile->jabatan === 'Kepala Unit' ? ($mbBimbaPerUnit[$unitKey] ?? 0) : 0;
            $mk = $profile->jabatan === 'Guru' 
                ? ($mkBimbaPerGuru[$namaTrim] ?? 0) 
                : ($mkBimbaPerUnit[$unitKey] ?? 0);

            // AM1 & AM2
            $am1 = $profile->jabatan === 'Guru'
                ? BukuInduk::where('guru', $namaTrim)
                    ->where('status', 'Aktif')
                    ->where(function ($q) {
                        $q->where('kelas', 'like', '%MBA%')
                          ->orWhere('kelas', 'like', '%AIUEO%');
                    })
                    ->count()
                : BukuInduk::join('profiles', 'buku_induk.guru', '=', 'profiles.nama')
                    ->where('profiles.jabatan', 'Guru')
                    ->whereRaw('UPPER(TRIM(COALESCE(profiles.bimba_unit, profiles.departemen))) = ?', [$unitKey])
                    ->where('buku_induk.status', 'Aktif')
                    ->where(function ($q) {
                        $q->where('buku_induk.kelas', 'like', '%MBA%')
                          ->orWhere('buku_induk.kelas', 'like', '%AIUEO%');
                    })
                    ->count();

            $am2 = $profile->jabatan === 'Guru'
                ? Penerimaan::where('guru', $namaTrim)
                    ->where('bulan', $p->bulan)->where('tahun', $p->tahun)
                    ->where('penerimaan.spp', '>', 0)
                    ->where(function ($q) {
                        $q->where('penerimaan.kelas', 'like', '%MBA%')
                          ->orWhere('penerimaan.kelas', 'like', '%AIUEO%');
                    })
                    ->distinct('penerimaan.nim')->count('penerimaan.nim')
                : Penerimaan::where('penerimaan.bulan', $p->bulan)
                    ->where('penerimaan.tahun', $p->tahun)
                    ->where('penerimaan.spp', '>', 0)
                    ->where(function ($q) {
                        $q->where('penerimaan.kelas', 'like', '%MBA%')
                          ->orWhere('penerimaan.kelas', 'like', '%AIUEO%');
                    })
                    ->join('buku_induk', 'penerimaan.nim', '=', 'buku_induk.nim')
                    ->join('profiles', 'buku_induk.guru', '=', 'profiles.nama')
                    ->where('profiles.jabatan', 'Guru')
                    ->whereRaw('UPPER(TRIM(COALESCE(profiles.bimba_unit, profiles.departemen))) = ?', [$unitKey])
                    ->distinct('penerimaan.nim')->count('penerimaan.nim');

            // ────────────────────────────────────────────────────────────────
            // SIMPAN / UPDATE KOMISI (tanpa MT)
            // ────────────────────────────────────────────────────────────────
            $upsert = Komisi::updateOrCreate(
                [
                    'nama'  => $namaTrim,
                    'bulan' => $bulanAngka,
                    'tahun' => $p->tahun,
                ],
                [
                    'profile_id'       => $profile->id,
                    'nomor_urut'       => $profile->no_urut ?? 999,
                    'jabatan'          => $profile->jabatan,
                    'status'           => $profile->status_karyawan,
                    'departemen'       => $profile->departemen,
                    'masa_kerja'       => $profile->masa_kerja ?? '-',
                    'bimba_unit'       => $bimba_unit,
                    'no_cabang'        => $no_cabang,
                    'nik'              => $profile->nik ?? null,

                    'spp_bimba'        => $sppBimba,
                    'spp_english'      => $sppEnglish,

                    'murid_mb_bimba'   => $mb,
                    'mk_bimba'         => $mk,

                    'komisi_mb_bimba'  => $mb * 50000,
                    'sudah_dibayar'    => ($mb) * 50000,  // MT sudah di-handle observer, jadi hanya MB
                    'mb_umum_ku'       => $mb * 50000,

                    'am1_bimba'        => $am1,
                    'am2_bimba'        => $am2,

                    'keterangan'       => 'Sync manual (SPP/MB/MK/AM) - ' . now()->format('d/m/Y H:i') . 
                                      ($isKepalaUnit ? " (oleh Kepala Unit {$user->name})" : ''),
                ]
            );

            $upsert->wasRecentlyCreated ? $created++ : $updated++;
        }
    }

    return back()->with(
        'success',
        "Sync KOMISI BERHASIL! " . 
        ($isKepalaUnit ? "Hanya unit {$userUnit} yang diproses." : "Semua unit diproses.") . 
        " {$created} data baru, {$updated} diperbarui. MT sudah otomatis via observer."
    );
}



public function cetakPembayaran($profile_id, $bulan, $tahun)
{
    $komisi = Komisi::where('profile_id', $profile_id)
                    ->where('bulan', $bulan)
                    ->where('tahun', $tahun)
                    ->with('profile')
                    ->firstOrFail();

    $profile = $komisi->profile;

    // Hitung total komisi yang harus dibayar
    $totalKomisiBimba = $komisi->komisi_mb_bimba + $komisi->komisi_mt_bimba + $komisi->mb_insentif_ku;
    $totalKomisiEnglish = $komisi->komisi_mb_english + $komisi->komisi_mt_english;

    return view('komisi.cetak', compact(
        'komisi', 'profile', 'bulan', 'tahun',
        'totalKomisiBimba', 'totalKomisiEnglish'
    ));
}

}