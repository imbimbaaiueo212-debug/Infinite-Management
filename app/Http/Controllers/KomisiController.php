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
use Illuminate\Support\Facades\Auth;

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
    // === DEFAULT = BULAN LALU (karena komisi dibayar bulan ini untuk bulan lalu) ===
    $defaultBulan = now()->subMonth()->month;
    $defaultTahun = now()->subMonth()->year;

    $tahunAwal  = $request->input('tahun_awal', $defaultTahun);
    $bulanAwal  = $request->input('bulan_awal', $defaultBulan);
    $tahunAkhir = $request->input('tahun_akhir', $defaultTahun);
    $bulanAkhir = $request->input('bulan_akhir', $defaultBulan);
    $unitId     = $request->input('unit_id');

    $query = Komisi::query();

    // Filter Periode (perbaikan logika filter)
    $query->where(function ($q) use ($tahunAwal, $bulanAwal, $tahunAkhir, $bulanAkhir) {
        $q->where('tahun', '>=', $tahunAwal)
          ->where('tahun', '<=', $tahunAkhir);
    });

    // Filter bulan lebih tepat
    if ($tahunAwal == $tahunAkhir) {
        $query->whereBetween('bulan', [$bulanAwal, $bulanAkhir]);
    } else {
        // Jika lintas tahun (jarang)
        $query->where(function ($q) use ($tahunAwal, $bulanAwal, $tahunAkhir, $bulanAkhir) {
            $q->where('tahun', $tahunAwal)->where('bulan', '>=', $bulanAwal)
              ->orWhere('tahun', $tahunAkhir)->where('bulan', '<=', $bulanAkhir);
        });
    }

    // Filter Unit
    if ($unitId) {
        $unit = \App\Models\Unit::find($unitId);
        if ($unit) {
            $query->where('bimba_unit', $unit->biMBA_unit);
        }
    }

    $data_komisi = $query->with('profile:id,nama,nik,unit_id')
                         ->orderBy('tahun')
                         ->orderBy('bulan')
                         ->orderBy('nomor_urut')
                         ->get();

    // Periode Text
    $namaBulan = [1=>'Januari',2=>'Februari',3=>'Maret',4=>'April',5=>'Mei',6=>'Juni',
                  7=>'Juli',8=>'Agustus',9=>'September',10=>'Oktober',11=>'November',12=>'Desember'];

    $periodeText = 'Semua Periode';
    if ($bulanAwal && $tahunAwal && $bulanAkhir && $tahunAkhir) {
        $awal  = $namaBulan[(int)$bulanAwal] . ' ' . $tahunAwal;
        $akhir = $namaBulan[(int)$bulanAkhir] . ' ' . $tahunAkhir;
        $periodeText = $awal === $akhir ? $awal : "$awal → $akhir";
    }

    $unitOptions = $this->getUnitOptions();

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
    DB::beginTransaction();

    try {
        $bulanMap = [
            1=>'januari',2=>'februari',3=>'maret',4=>'april',
            5=>'mei',6=>'juni',7=>'juli',8=>'agustus',
            9=>'september',10=>'oktober',11=>'november',12=>'desember'
        ];

        $user = Auth::user();
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
        }

        // ============================================================
        // 🔥 1. AMBIL SEMUA KARYAWAN (ANTI HILANG)
        // ============================================================
        $karyawanQuery = Profile::whereIn('jabatan', ['Kepala Unit', 'Guru'])
            ->where('status_karyawan', 'Aktif');

        if ($isKepalaUnit && $userUnit) {
            $karyawanQuery->where(function ($q) use ($userUnit) {
                $q->where('bimba_unit', $userUnit)
                  ->orWhere('departemen', $userUnit);
            });
        }

        $karyawan = $karyawanQuery
            ->orderBy('no_urut')
            ->get();

        if ($karyawan->isEmpty()) {
            return back()->with('warning', 'Tidak ada karyawan.');
        }

        // ============================================================
        // 🔥 2. TENTUKAN PERIODE (WAJIB LOOP, WALAU DATA KOSONG)
        // ============================================================
        $bulan = $request->bulan ?? now()->subMonth()->month;
        $tahun = $request->tahun ?? now()->subMonth()->year;

        $bulanText = $bulanMap[$bulan];

        // ============================================================
        // 🔥 3. RESET DATA BULAN INI (ANTI NYANGKUT)
        // ============================================================
        Komisi::where('bulan', $bulan)
            ->where('tahun', $tahun)
            ->when($isKepalaUnit && $userUnit, function ($q) use ($userUnit) {
                $q->where('bimba_unit', $userUnit);
            })
            ->update([
                'spp_bimba' => 0,
                'spp_english' => 0,
                'murid_mb_bimba' => 0,
                'mk_bimba' => 0,
                'komisi_mb_bimba' => 0,
                'sudah_dibayar' => 0,
                'mb_umum_ku' => 0,
                'am1_bimba' => 0,
                'am2_bimba' => 0,
            ]);

        // ============================================================
        // 🔥 4. LOOP SEMUA KARYAWAN (PASTI MUNCUL)
        // ============================================================
        $created = $updated = 0;

        foreach ($karyawan as $profile) {

            $nama = trim($profile->nama);
            $unitKey = strtoupper(trim($profile->bimba_unit ?? $profile->departemen ?? 'UNKNOWN'));

            // ========================================================
            // 🔹 SPP
            // ========================================================
            $sppBimba = Penerimaan::where('guru', $nama)
                ->where('bulan', $bulanText)
                ->where('tahun', $tahun)
                ->where(function ($q) {
                    $q->where('daftar', 'like', '%MBA%')
                      ->orWhere('kelas', 'like', '%MBA%')
                      ->orWhere('kelas', 'like', '%AIUEO%');
                })
                ->sum('spp') ?: 0;

            $sppEnglish = Penerimaan::where('guru', $nama)
                ->where('bulan', $bulanText)
                ->where('tahun', $tahun)
                ->where(function ($q) {
                    $q->where('daftar', 'like', '%English%')
                      ->orWhere('kelas', 'like', '%English%');
                })
                ->sum('spp') ?: 0;

            // ========================================================
            // 🔹 MB
            // ========================================================
            $mb = 0;
            if ($profile->jabatan === 'Kepala Unit') {
                $mb = Penerimaan::where('bulan', $bulanText)
                    ->where('tahun', $tahun)
                    ->join('buku_induk', 'penerimaan.nim', '=', 'buku_induk.nim')
                    ->join('profiles', 'buku_induk.guru', '=', 'profiles.nama')
                    ->where('profiles.jabatan', 'Guru')
                    ->whereRaw('UPPER(TRIM(COALESCE(profiles.bimba_unit, profiles.departemen))) = ?', [$unitKey])
                    ->where('buku_induk.status', 'Baru')
                    ->count();
            }

            // ========================================================
            // 🔹 MK
            // ========================================================
            $mk = BukuInduk::where('status', 'Keluar')
                ->whereYear('tgl_keluar', $tahun)
                ->whereMonth('tgl_keluar', $bulan)
                ->when($profile->jabatan === 'Guru', function ($q) use ($nama) {
                    $q->where('guru', $nama);
                })
                ->when($profile->jabatan === 'Kepala Unit', function ($q) use ($unitKey) {
                    $q->join('profiles', 'buku_induk.guru', '=', 'profiles.nama')
                      ->whereRaw('UPPER(TRIM(COALESCE(profiles.bimba_unit, profiles.departemen))) = ?', [$unitKey]);
                })
                ->count();

            // ========================================================
            // 🔹 AM
            // ========================================================
            $am1 = BukuInduk::where('guru', $nama)
                ->where('status', 'Aktif')
                ->count();

            $am2 = Penerimaan::where('guru', $nama)
                ->where('bulan', $bulanText)
                ->where('tahun', $tahun)
                ->where('spp', '>', 0)
                ->distinct('nim')
                ->count('nim');

            // ========================================================
            // 🔥 SIMPAN (PASTI ADA)
            // ========================================================
            $upsert = Komisi::updateOrCreate(
                [
                    'nama'  => $nama,
                    'bulan' => $bulan,
                    'tahun' => $tahun,
                ],
                [
                    'profile_id' => $profile->id,
                    'nomor_urut' => $profile->no_urut ?? 999,
                    'jabatan'    => $profile->jabatan,
                    'status'     => $profile->status_karyawan,
                    'departemen' => $profile->departemen,
                    'masa_kerja' => $profile->masa_kerja ?? '-',
                    'bimba_unit' => $profile->bimba_unit,
                    'nik'        => $profile->nik,

                    'spp_bimba'  => $sppBimba,
                    'spp_english'=> $sppEnglish,

                    'murid_mb_bimba'  => $mb,
                    'mk_bimba'        => $mk,
                    'komisi_mb_bimba' => $mb * 50000,
                    'sudah_dibayar'   => $mb * 50000,
                    'mb_umum_ku'      => $mb * 50000,

                    'am1_bimba' => $am1,
                    'am2_bimba' => $am2,

                    'keterangan' => 'AUTO SYNC FINAL - ' . now()->format('d/m/Y H:i'),
                ]
            );

            $upsert->wasRecentlyCreated ? $created++ : $updated++;
        }

        DB::commit();

        return back()->with('success',
            "🔥 SYNC BERHASIL | {$created} baru | {$updated} update"
        );

    } catch (\Exception $e) {
        DB::rollBack();

        return back()->with('error', $e->getMessage());
    }
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