<?php

namespace App\Http\Controllers;

use App\Models\Registration;
use App\Models\HargaSaptataruna;
use App\Models\Student;
use App\Models\Unit;
use App\Models\BukuInduk;
use App\Models\Profile;
use App\Models\Penerimaan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class RegistrationController extends Controller
{
    // ===== List + filter =====
    public function index(Request $request)
{
    $q           = trim((string) $request->get('q', ''));
    $status      = trim((string) $request->get('status', ''));
    $unitId      = $request->get('unit_id');   // ← Tambahkan ini
    $user        = Auth::user();
    $isAdmin     = $user && in_array($user->role ?? '', ['admin', 'superadmin']);

    Log::info('DEBUG REGISTRATION INDEX', [
        'user'            => $user?->name ?? '-',
        'role'            => $user?->role ?? '-',
        'user_bimba_unit' => $user?->bimba_unit ?? '-',
        'is_admin'        => $isAdmin,
        'search'          => $q,
        'status'          => $status,
        'unit_id'         => $unitId
    ]);

    $query = Registration::query()
        ->with(['student.bukuInduk'])
        ->latest('created_at');

    // Search
    if ($q !== '') {
        $query->whereHas('student', function ($sq) use ($q) {
            $sq->where('nim', 'like', "%{$q}%")
               ->orWhere('nama', 'like', "%{$q}%");
        });
    }

    // Filter Status
    if ($status !== '') {
        $query->where('status', $status);
    }

    // ========================
    // FILTER UNIT
    // ========================
    if (!$isAdmin) {
        $userUnit     = trim($user->bimba_unit ?? '');
        $userNoCabang = trim($user->no_cabang ?? '');

        $query->where(function ($qry) use ($userUnit, $userNoCabang) {
            if ($userUnit) {
                $qry->where('bimba_unit', 'LIKE', "%{$userUnit}%")
                    ->orWhereHas('student', fn($sq) => 
                        $sq->where('bimba_unit', 'LIKE', "%{$userUnit}%")
                    );
            }

            if ($userNoCabang) {
                $qry->orWhere('no_cabang', $userNoCabang)
                    ->orWhereHas('student', fn($sq) => 
                        $sq->where('no_cabang', $userNoCabang)
                    );
            }

            // Unit khusus yang diizinkan
            $qry->orWhere('bimba_unit', 'LIKE', '%VILLA BEKASI INDAH 2%')
                ->orWhere('no_cabang', '00340')
                ->orWhere('bimba_unit', 'LIKE', '%GRIYA PESONA MADANI%')
                ->orWhere('no_cabang', '05141')
                ->orWhere('bimba_unit', 'LIKE', '%SAPTA TARUNA IV%')
                ->orWhere('bimba_unit', 'LIKE', '%SAPTA TARUNA 4%')
                ->orWhere('no_cabang', '01045')

                ->orWhereHas('student', function ($sq) {
                    $sq->whereIn('no_cabang', ['00340', '05141', '01045'])
                       ->orWhere('bimba_unit', 'LIKE', '%VILLA BEKASI INDAH 2%')
                       ->orWhere('bimba_unit', 'LIKE', '%GRIYA PESONA MADANI%')
                       ->orWhere('bimba_unit', 'LIKE', '%SAPTA TARUNA%');
                });
        });
    } 
    // Admin bisa filter manual
    elseif ($unitId) {
        $unit = Unit::find($unitId);
        if ($unit) {
            $query->whereHas('student', function ($sq) use ($unit) {
                $sq->where('no_cabang', $unit->no_cabang)
                   ->where('bimba_unit', 'LIKE', "%{$unit->biMBA_unit}%");
            });
        }
    }

    $regs = $query->paginate(25)->withQueryString();

    // Unit Options untuk dropdown
    $unitOptions = Unit::orderBy('no_cabang')
        ->get()
        ->map(fn ($u) => [
            'value' => $u->id,
            'label' => trim(($u->no_cabang ?? '') . ' - ' . ($u->biMBA_unit ?? '')),
        ])
        ->toArray();

    return view('registrations.index', compact(
        'regs',
        'unitOptions',
        'unitId',      // ← Ini yang diperbaiki
        'q',
        'status'
    ));
}

private function extractUkuran($nama)
{
    if (preg_match('/\((S|M|L|XL|XXL|XXXL)\)/i', $nama, $matches)) {
        return strtoupper($matches[1]);
    }
    return 'Standar';
}

    // ===== Create form =====
    public function create(Request $request)
{
    $students = Student::with('muridTrial')
        ->orderBy('nama')
        ->get([
            'id',
            'nim',
            'nama',
            'bimba_unit',
            'no_cabang',
            'tgl_lahir',
            'tempat_lahir',
            'orangtua',
            'alamat'
        ]);

    $selectedStudentId = (int) $request->query('student_id');

    $prefilledNim = '';
    $prefilledNama = '';
    $prefilledUnit = '';
    $prefilledCabang = '';
    $prefilledTglLahir = '';
    $prefilledTmptLahir = '';
    $prefilledOrangtua = '';
    $prefilledInfo = '';

    $selectedStudent = null;

    if ($selectedStudentId) {

        $selectedStudent = Student::with('muridTrial','registrations')
            ->find($selectedStudentId);

        if ($selectedStudent) {

            $trial = $selectedStudent->muridTrial;

            // cek apakah user batal dari form pendaftaran
            $hasActiveReg = $selectedStudent->registrations()
                ->whereIn('status',['pending','verified','accepted'])
                ->exists();

            if(!$hasActiveReg && $trial?->status_trial === 'lanjut_daftar'){
                $trial->update(['status_trial'=>'aktif']);
            }

            $prefilledNim = $selectedStudent->nim
                ?? 'Akan digenerate otomatis setelah disimpan';

            $prefilledNama =
                $selectedStudent->nama
                ?? $trial?->nama
                ?? '';

            $prefilledUnit =
                $selectedStudent->bimba_unit
                ?? $trial?->bimba_unit
                ?? '';

            $prefilledCabang =
                $selectedStudent->no_cabang
                ?? $trial?->no_cabang
                ?? '';

            $prefilledTglLahir =
                $selectedStudent->tgl_lahir
                ?? $trial?->tgl_lahir
                ?? '';

            $prefilledTmptLahir =
                $selectedStudent->tempat_lahir
                ?? $trial?->tempat_lahir
                ?? '';

            $prefilledOrangtua =
                $selectedStudent->orangtua
                ?? $trial?->orangtua
                ?? '';

            $prefilledInfo =
                $selectedStudent->informasi_bimba
                ?? $trial?->info
                ?? '';
            $prefilledTmptLahir = $selectedStudent->tempat_lahir 
                ?? $trial?->tempat_lahir 
                ?? '';
            $prefilledHpAyah = $selectedStudent->hp_ayah 
                ?? $trial?->hp_ayah 
                ?? '';

            $prefilledHpIbu  = $selectedStudent->hp_ibu 
                ?? $trial?->hp_ibu 
                ?? '';
            $prefilledAlamat = $selectedStudent->alamat 
                ?? $trial?->alamat 
                ?? '';
            // Detail Alamat
            $prefilledNoRumah   = $selectedStudent->no_rumah ?? $trial?->no_rumah ?? '';
            $prefilledRt        = $selectedStudent->rt ?? $trial?->rt ?? '';
            $prefilledRw        = $selectedStudent->rw ?? $trial?->rw ?? '';
            $prefilledKelurahan = $selectedStudent->kelurahan ?? $trial?->kelurahan ?? '';
            $prefilledKecamatan = $selectedStudent->kecamatan ?? $trial?->kecamatan ?? '';
            $prefilledKodyaKab  = $selectedStudent->kodya_kab ?? $trial?->kodya_kab ?? '';
            $prefilledProvinsi  = $selectedStudent->provinsi ?? $trial?->provinsi ?? '';
            $prefilledHari = $selectedStudent->hari ?? $trial?->hari ?? '';
            $prefilledJam  = $selectedStudent->jam  ?? $trial?->jam  ?? '';
        }
    }

    $hargaSaptataruna = HargaSaptataruna::all();

    $kdOptions = ['A','B','C','D','E','F'];

    $sppMapping = [];

    foreach ($hargaSaptataruna as $row) {
        foreach ($kdOptions as $KD) {
            $col = strtolower($KD);
            $sppMapping[$row->kode][$KD] = (int) ($row->$col ?? 0);
        }
    }

    $tahapanOptions = ['Persiapan','Lanjutan'];
    $kelasOptions = ['biMBA AIUEO','English biMBA'];

    $guruOptions = Profile::where('jabatan','!=','Kepala Unit')
        ->orderBy('nama')
        ->pluck('nama')
        ->toArray();

    $kodeJadwalOptions = [
        '108','109','110','111','112','113','114','115','116',
        '208','209','210','211',
        '308','309','310','311'
    ];

        $levelOptions = ['Level 1', 'Level 2', 'Level 3', 'Level 4'];
        $jenisKbmOptions = ['Full TM', 'Full DLC', 'Kombinasi TM & DLC'];
        $infoOptions = ['Brosur', 'Event', 'Humas', 'Internet', 'Spanduk', 'Lainnya'];
        $asalModulOptions = ['biMBA IM', 'biMBA Unit'];

    $penerimaanPrefill = array_fill_keys([
        'kwitansi','via','bulan','tahun','tanggal',
        'daftar','voucher','spp_rp','spp','kaos',
        'kpk','sertifikat','stpb','tas','event','lain_lain'
    ],null);

    if($selectedStudent?->bukuInduk){

        $bi = $selectedStudent->bukuInduk;

        $penerimaanPrefill['spp_rp'] =
            $bi->spp ? (int)$bi->spp : null;

        $penerimaanPrefill['spp'] =
            trim(($bi->gol ? $bi->gol.'/' : '').($bi->kd ?? ''))
            ?: ($bi->kd ?? null);
    }

    $daftarList = HargaSaptataruna::whereIn('kode', ['bA', 'Eb'])
    ->orWhere('nama', 'LIKE', '%biMBA-AIUEO%')
    ->orWhere('nama', 'LIKE', '%English biMBA%')
    ->orderBy('nama')
    ->get()
    ->map(function ($item) {
        return [
            'kode'          => $item->kode,
            'nama'          => $item->nama,
            'harga_duafa'   => (float)($item->duafa ?? 0),
            'harga_promo'   => (float)($item->promo_2019 ?? 0),
            'harga_daftar'  => (float)($item->daftar_ulang ?? 0),
            'harga_spesial' => (float)($item->spesial ?? 0),
            'harga_umum1'   => (float)($item->umum1 ?? 0),
            'harga_umum2'   => (float)($item->umum2 ?? 0),
        ];
    });
    // =====================
// KAOS (Perbaikan Query)
$hargaKaos = HargaSaptataruna::where('kategori', 'PENJUALAN')
    ->where(function($q) {
        $q->where('nama', 'LIKE', '%kaos%')
          ->orWhere('nama', 'LIKE', '%KAS%')
          ->orWhere('kode', 'LIKE', '%KAS%');
    })
    ->get();

$kaosPendekList = $hargaKaos->filter(function ($item) {
    $nama = strtolower($item->nama ?? '');
    return strpos($nama, 'pendek') !== false || 
           strpos($nama, 'lengan pendek') !== false ||
           strpos($nama, 'pendek') !== false;
})->map(function ($item) {
    return [
        'kode'   => $item->kode,
        'nama'   => $item->nama,
        'harga'  => (float)($item->harga ?? 0),
    ];
})->values();

$kaosPanjangList = $hargaKaos->filter(function ($item) {
    $nama = strtolower($item->nama ?? '');
    return strpos($nama, 'panjang') !== false || 
           strpos($nama, 'lengan panjang') !== false;
})->map(function ($item) {
    return [
        'kode'   => $item->kode,
        'nama'   => $item->nama,
        'harga'  => (float)($item->harga ?? 0),
    ];
})->values();

// =====================
// KPK
// =====================

$kpkList = HargaSaptataruna::where('nama', 'LIKE', '%KPK%')
    ->orWhere('kode', 'LIKE', '%KPK%')
    ->orderBy('nama')
    ->get()
    ->map(function ($item) {
        return [
            'kode'  => $item->kode,
            'nama'  => $item->nama,
            'harga' => (float)$item->harga,
        ];
    })
    ->values();

// =====================
// TAS
// =====================

$tasList = HargaSaptataruna::where('nama', 'LIKE', '%TAS%')
    ->orWhere('kode', 'LIKE', '%TAS%')
    ->orderBy('nama')
    ->get()
    ->map(function ($item) {
        return [
            'kode'  => $item->kode,
            'nama'  => $item->nama,
            'harga' => (float)$item->harga,
        ];
    })
    ->values();

// =====================
// SERTIFIKAT
// =====================

$sertifikatList = HargaSaptataruna::where('nama', 'LIKE', '%SERTIFIKAT%')
    ->orWhere('nama', 'LIKE', '%STF%')
    ->orWhere('kode', 'LIKE', '%STF%')
    ->orderBy('nama')
    ->get()
    ->map(function ($item) {
        return [
            'kode'  => $item->kode,
            'nama'  => $item->nama,
            'harga' => (float)$item->harga,
        ];
    })
    ->values();

// =====================
// STPB
// =====================

$stpbList = HargaSaptataruna::where('nama', 'LIKE', '%STPB%')
    ->orWhere('kode', 'LIKE', '%STPB%')
    ->orderBy('nama')
    ->get()
    ->map(function ($item) {
        return [
            'kode'  => $item->kode,
            'nama'  => $item->nama,
            'harga' => (float)$item->harga,
        ];
    })
    ->values();

// =====================
// RBAS
// =====================

$rbasList = HargaSaptataruna::where('nama', 'LIKE', '%RBAS%')
    ->orWhere('kode', 'LIKE', '%RBAS%')
    ->orderBy('nama')
    ->get()
    ->map(function ($item) {
        return [
            'kode'  => $item->kode,
            'nama'  => $item->nama,
            'harga' => (float)$item->harga,
        ];
    })
    ->values();

// =====================
// BCABS01
// =====================

$bcabs01List = HargaSaptataruna::where('kode', 'BCABS.01')
    ->orWhere('kode', 'LIKE', '%BCABS01%')
    ->orderBy('nama')
    ->get()
    ->map(function ($item) {
        return [
            'kode'  => $item->kode,
            'nama'  => $item->nama,
            'harga' => (float)$item->harga,
        ];
    })
    ->values();

// =====================
// BCABS02
// =====================

$bcabs02List = HargaSaptataruna::where('kode', 'BCABS.02')
    ->orWhere('kode', 'LIKE', '%BCABS02%')
    ->orderBy('nama')
    ->get()
    ->map(function ($item) {
        return [
            'kode'  => $item->kode,
            'nama'  => $item->nama,
            'harga' => (float)$item->harga,
        ];
    })
    ->values();

    return view('registrations.create',compact(

    'students',
    'selectedStudentId',

    'prefilledNim',
    'prefilledNama',
    'prefilledUnit',
    'prefilledCabang',

    'prefilledTglLahir',
    'prefilledTmptLahir',
    'prefilledOrangtua',
    'prefilledInfo',
    'prefilledTmptLahir',
    'prefilledHpAyah',
    'prefilledHpIbu',
    'prefilledAlamat',
    'prefilledNoRumah',
    'prefilledRt',
    'prefilledRw',
    'prefilledKelurahan',
    'prefilledKecamatan',
    'prefilledKodyaKab',
    'prefilledProvinsi',
    'prefilledHari',
    'prefilledJam',

    'hargaSaptataruna',
    'kdOptions',
    'sppMapping',
    'levelOptions',
    'jenisKbmOptions',
    'infoOptions',
    'asalModulOptions',
    'tahapanOptions',
    'kelasOptions',
    'guruOptions',
    'kodeJadwalOptions',
    'penerimaanPrefill',
    'selectedStudent',

    // =====================
    // BIAYA
    // =====================
    'daftarList',
    'kaosPendekList',
    'kaosPanjangList',
    'kpkList',
    'tasList',
    'sertifikatList',
    'stpbList',
    'rbasList',
    'bcabs01List',
    'bcabs02List'

));
}


        // ===== STORE (CREATE) =====
    public function store(Request $request)
{
    $data = $request->validate([
        'student_id'         => 'required|exists:students,id',
        'status'             => ['required', Rule::in(['pending','verified','accepted','rejected'])],
        'tanggal_daftar'     => 'nullable|date',
        'tanggal_penerimaan' => 'nullable|date',
        'gelombang'          => 'nullable|string|max:100',
        'program'            => 'nullable|string|max:100',

        // BI Data
        'bi.nim'             => 'nullable|string',
        'bi.nama'            => 'nullable|string',
        'bi.tahap'           => 'nullable|string',
        'bi.kelas'           => 'nullable|string',
        'bi.gol'             => 'nullable|string',
        'bi.kd'              => 'nullable|string',
        'bi.guru'            => 'nullable|string',
        'bi.kode_jadwal'     => 'nullable|string',
        'bi.hari_jam'        => 'nullable|string',
        'bi.spp'             => 'nullable|string',

        // Biaya (Flat)
        'daftar'             => 'nullable|string',
        'voucher'            => 'nullable|string',
        'spp_rp'             => 'nullable|string',
        'kaos_pendek'        => 'nullable|string',
        'kaos_panjang'       => 'nullable|string',
        'kpk'                => 'nullable|string',
        'tas'                => 'nullable|string',
        'sertifikat'         => 'nullable|string',
        'stpb'               => 'nullable|string',
        'event'              => 'nullable|string',
        'lain_lain'          => 'nullable|string',
        'rbas'               => 'nullable|string',
        'BCABS01'            => 'nullable|string',
        'BCABS02'            => 'nullable|string',

        'attachment'         => 'nullable|file|mimes:pdf,jpg,jpeg,png,webp|max:3072',
    ]);

    $student = Student::with('muridTrial', 'bukuInduk')->findOrFail($data['student_id']);

    // ====================== BI DATA ======================
    $biInput = $request->input('bi', []);
    $bi = [
        'nim'         => $biInput['nim'] ?? $student->nim,
        'nama'        => $biInput['nama'] ?? $student->nama,
        'tahap'       => $biInput['tahap'] ?? null,
        'kelas'       => $biInput['kelas'] ?? 'biMBA AIUEO',
        'gol'         => strtoupper($biInput['gol'] ?? '-'),
        'kd'          => strtoupper($biInput['kd'] ?? '-'),
        'guru'        => $biInput['guru'] ?? '-',
        'kode_jadwal' => $biInput['kode_jadwal'] ?? null,
        'hari_jam'    => $biInput['hari_jam'] ?? null,
        'spp'         => null,
    ];

    // Hitung SPP
    if (!empty($biInput['spp'])) {
        $bi['spp'] = (int) preg_replace('/\D/', '', $biInput['spp']);
    } elseif (!empty($bi['gol']) && !empty($bi['kd'])) {
        $row = HargaSaptataruna::where('kode', $bi['gol'])->first();
        $col = strtolower($bi['kd']);
        $bi['spp'] = $row ? (int)($row->$col ?? 0) : null;
    }

    // ====================== DATA BIAYA (untuk prefill penerimaan) ======================
    $pay = [
        'kwitansi'   => $request->kwitansi ?? null,
        'via'        => $request->via ?? null,
        'bulan'      => $request->bulan ?? null,
        'tahun'      => $request->tahun ?? null,
        'tanggal_penerimaan' => $this->tryParseDateToYmd($request->tanggal_penerimaan ?? now()),

        'daftar'     => $this->parseMoney($request->daftar ?? 0),
        'voucher'    => $this->parseMoney($request->voucher ?? 0),
        'spp_rp'     => $this->parseMoney($request->spp_rp ?? 0),
        'kaos'       => $this->parseMoney(($request->kaos_pendek ?? 0) + ($request->kaos_panjang ?? 0)),
        'kpk'        => $this->parseMoney($request->kpk ?? 0),
        'tas'        => $this->parseMoney($request->tas ?? 0),
        'sertifikat' => $this->parseMoney($request->sertifikat ?? 0),
        'stpb'       => $this->parseMoney($request->stpb ?? 0),
        'event'      => $this->parseMoney($request->event ?? 0),
        'lain_lain'  => $this->parseMoney($request->lain_lain ?? 0),
    ];

    // ====================== FINAL DATA REGISTRASI ======================
    $finalData = [
        'student_id'         => $data['student_id'],
        'gelombang'          => $data['gelombang'] ?? null,
        'program'            => $data['program'] ?? null,
        'status'             => $data['status'],
        'tanggal_daftar'     => $data['tanggal_daftar'] ?? now()->format('Y-m-d'),
        'tahun_ajaran'       => Registration::currentAcademicYear() ?? date('Y'),
        
        'bimba_unit'         => $student->bimba_unit,
        'no_cabang'          => $student->no_cabang,

        'tahap'              => $bi['tahap'],
        'kelas'              => $bi['kelas'],
        'gol'                => $bi['gol'],
        'kd'                 => $bi['kd'],
        'spp'                => $bi['spp'],
        'guru'               => $bi['guru'],
        'kode_jadwal'        => $bi['kode_jadwal'],
        'hari_jam'           => $bi['hari_jam'],

        'kwitansi'           => $pay['kwitansi'],
        'via'                => $pay['via'],
        'bulan'              => $pay['bulan'],
        'tahun'              => $pay['tahun'],
        'tanggal_penerimaan' => $pay['tanggal_penerimaan'],
        'daftar'             => $pay['daftar'],
        'voucher'            => $pay['voucher'],
        'spp_rp'             => $pay['spp_rp'],
        'spp_keterangan'     => $request->spp ?? null,
        'kaos'               => $pay['kaos'],
        'kpk'                => $pay['kpk'],
        'sertifikat'         => $pay['sertifikat'],
        'stpb'               => $pay['stpb'],
        'tas'                => $pay['tas'],
        'event'              => $pay['event'],
        'lain_lain'          => $pay['lain_lain'],
    ];

    if ($request->hasFile('attachment')) {
        $finalData['attachment_path'] = $request->file('attachment')->store('registrations', 'public');
    }

    // ====================== SIMPAN ======================
    $reg = null;
    DB::transaction(function () use ($finalData, $student, $bi, $pay, &$reg) {
        $reg = Registration::create($finalData);

        if ($reg->status === 'accepted') {
            $this->commitBukuIndukWithPayload(
                $student,
                $reg->status,
                array_merge($bi, ['penerimaan' => $pay]),
                $student->bimba_unit,
                $student->no_cabang,
                $reg->tanggal_daftar
            );
        }
    });

    // ====================== REDIRECT KE PENERIMAAN CREATE ======================
    $message = 'Registrasi berhasil disimpan!';

    return redirect()->route('penerimaan.create', [
        'nim' => $student->nim ?? $bi['nim'],           // utama untuk prefill
        'student_id' => $student->id,
    ])->with('success', $message . ' Silakan lengkapi data penerimaan.');

}

    // ===== EDIT =====
   public function edit(Registration $registration)
{
    // Eager Loading Relasi
    $registration->load(['student.bukuInduk', 'muridTrial']);

    // Data Students lengkap
    $students = \App\Models\Student::orderBy('nama')
        ->get([
            'id', 'nim', 'nama',
            'tempat_lahir', 'tgl_lahir',
            'orangtua', 'alamat',
            'hp_ayah', 'hp_ibu',
            'bimba_unit', 'no_cabang',
            'hari', 'jam'
        ]);

    $biMaster = optional($registration->student)->bukuInduk;
    $trial    = $registration->muridTrial;
    $student  = $registration->student;

    // Data pendukung form
    $hargaSaptataruna = \App\Models\HargaSaptataruna::all();
    $kdOptions = ['A','B','C','D','E','F'];

    $sppMapping = [];
    foreach ($hargaSaptataruna as $row) {
        foreach ($kdOptions as $KD) {
            $col = strtolower($KD);
            $sppMapping[$row->kode][$KD] = (int) ($row->$col ?? 0);
        }
    }

    $tahapanOptions = ['Persiapan', 'Lanjutan'];
    $kelasOptions   = ['biMBA AIUEO', 'English biMBA'];
    $levelOptions = ['Level 1', 'Level 2', 'Level 3', 'Level 4'];
    $jenisKbmOptions = ['Full TM', 'Full DLC', 'Kombinasi TM & DLC'];
    $infoOptions = ['Brosur', 'Event', 'Humas', 'Internet', 'Spanduk', 'Lainnya'];
    $asalModulOptions = ['biMBA IM', 'biMBA Unit'];

    // Guru Options
    $guruOptions = [];
    $allGuru = Profile::whereIn('jabatan', ['Guru', 'Pengajar'])
                ->orderBy('nama')
                ->pluck('nama')
                ->toArray();

    if ($student && !empty($student->bimba_unit)) {
        $guruUnit = Profile::where('bimba_unit', $student->bimba_unit)
                    ->whereIn('jabatan', ['Guru', 'Pengajar'])
                    ->orderBy('nama')
                    ->pluck('nama')
                    ->toArray();
        $guruOptions = $guruUnit ?: $allGuru;
    } else {
        $guruOptions = $allGuru;
    }

    $savedGuru = $registration->guru ?? optional($biMaster)->guru;
    if ($savedGuru && !in_array($savedGuru, $guruOptions)) {
        $guruOptions[] = $savedGuru;
    }

    $kodeJadwalOptions = [
        '108','109','110','111','112','113','114','115','116',
        '208','209','210','211','308','309','310','311'
    ];

    // Prefill Data
    $biPrefill = [
        'tahap'        => $registration->tahap ?? optional($biMaster)->tahap,
        'kelas'        => $registration->kelas ?? optional($biMaster)->kelas,
        'gol'          => $registration->gol   ?? optional($biMaster)->gol,
        'kd'           => $registration->kd    ?? optional($biMaster)->kd,
        'spp'          => $registration->spp   ?? optional($biMaster)->spp,
        'guru'         => $registration->guru  ?? optional($biMaster)->guru,
        'kode_jadwal'  => $registration->kode_jadwal ?? optional($biMaster)->kode_jadwal,
        'hari'         => $registration->hari  ?? optional($biMaster)->hari ?? $student?->hari ?? $trial?->hari,
        'jam'          => $registration->jam   ?? optional($biMaster)->jam  ?? $student?->jam  ?? $trial?->jam,

        'tempat_lahir' => optional($biMaster)->tempat_lahir ?? $student?->tempat_lahir ?? $trial?->tempat_lahir,
        'tanggal_lahir'=> optional($biMaster)->tgl_lahir   ?? $student?->tgl_lahir   ?? $trial?->tgl_lahir,
        'orangtua'     => optional($biMaster)->orangtua    ?? $student?->orangtua    ?? $trial?->orangtua,
        'alamat'       => optional($biMaster)->alamat      ?? $student?->alamat      ?? $trial?->alamat,

        'no_telp'      => optional($biMaster)->no_telp ?? 
                          trim(implode(' / ', array_filter([
                              $student?->hp_ayah, 
                              $student?->hp_ibu
                          ]))),
    ];

    $isAdmin = auth()->check() && (
        auth()->user()->role === 'admin' || 
        (auth()->user()->is_admin ?? false)
    );

    return view('registrations.edit', compact(
        'registration',
        'students',
        'hargaSaptataruna',
        'kdOptions',
        'sppMapping',
        'tahapanOptions',
        'kelasOptions',
        'levelOptions',
        'jenisKbmOptions',
        'infoOptions',
        'asalModulOptions',
        'biPrefill',
        'guruOptions',
        'kodeJadwalOptions',
        'isAdmin',
        'trial',
        'student'
    ));
}

    // ===== UPDATE =====
    public function update(Request $request, Registration $registration)
    {
        $data = $request->validate([
            'student_id' => ['required', 'exists:students,id'],
            'gelombang' => ['nullable', 'string', 'max:100'],
            'program' => ['nullable', 'string', 'max:100'],
            'status' => ['required', Rule::in(['pending', 'verified', 'accepted', 'rejected'])],
            'tanggal_daftar' => ['nullable', 'date'],
            'bi' => ['array'],
            'penerimaan' => ['nullable', 'array'],
            'attachment' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:3072'],
        ]);

        $user = Auth::user();
        $isAdmin = $user && ($user->role === 'admin' || ($user->is_admin ?? false));

        if (!$isAdmin && $data['status'] === 'accepted') {
            return back()->withErrors(['status' => 'Hanya Admin yang boleh mengubah status menjadi Accepted.'])->withInput();
        }
        if (!$isAdmin) {
            $data['status'] = $registration->status; // tetap status lama
        }

        $student = Student::with('bukuInduk')->findOrFail($data['student_id']);
        $bimbaUnit = $student->bimba_unit;
        $noCabang = $student->no_cabang;

        $oldStatus = $registration->status; // penting!

        // --- Proses BI & Penerimaan (sama seperti store) ---
        $bi = $request->input('bi', []);
        $bi['tahap'] = $request->input('bi.tahap');
        $bi['kelas'] = $request->input('bi.kelas');
        $bi['gol'] = $request->input('bi.gol');
        $bi['kd'] = strtoupper($request->input('bi.kd') ?? '');
        $bi['guru'] = $request->input('bi.guru') ?? null;
        $bi['kode_jadwal'] = $request->input('bi.kode_jadwal') ?? null;
        $bi['hari_jam'] = $request->input('bi.jam') ?? null;

        $rawSpp = $request->input('bi.spp');
        if ($rawSpp !== null && trim($rawSpp) !== '') {
            $bi['spp'] = (int) preg_replace('/\D/', '', $rawSpp);
        } else {
            if (!empty($bi['gol']) && !empty($bi['kd'])) {
                $row = HargaSaptataruna::where('kode', $bi['gol'])->first();
                $col = strtolower($bi['kd']);
                $bi['spp'] = $row && isset($row->$col) ? (int) $row->$col : null;
            } else {
                $bi['spp'] = null;
            }
        }

        $p = $request->input('penerimaan', []);
        $pay = [
            'kwitansi' => $p['kwitansi'] ?? null,
            'via' => $p['via'] ?? null,
            'bulan' => $p['bulan'] ?? null,
            'tahun' => $p['tahun'] ?? null,
            'tanggal_penerimaan' => $this->tryParseDateToYmd($p['tanggal'] ?? $p['tanggal_penerimaan'] ?? null),
            'daftar' => $this->parseMoney($p['daftar'] ?? null),
            'voucher' => $this->parseMoney($p['voucher'] ?? null),
            'spp_rp' => $this->parseMoney($p['spp_rp'] ?? $p['spp (rp)'] ?? null),
            'spp' => $p['spp'] ?? null,
            'kaos' => $this->parseMoney($p['kaos'] ?? null),
            'kpk' => $this->parseMoney($p['kpk'] ?? null),
            'sertifikat' => $this->parseMoney($p['sertifikat'] ?? null),
            'stpb' => $this->parseMoney($p['stpb'] ?? null),
            'tas' => $this->parseMoney($p['tas'] ?? null),
            'event' => $this->parseMoney($p['event'] ?? null),
            'lain_lain' => $this->parseMoney($p['lain_lain'] ?? null),
        ];

        $registration->update(array_merge($data, [
            'bimba_unit' => $bimbaUnit,
            'no_cabang' => $noCabang,
            'tahap' => $bi['tahap'] ?? null,
            'kelas' => $bi['kelas'] ?? null,
            'gol' => $bi['gol'] ?? null,
            'kd' => $bi['kd'] ?? null,
            'spp' => $bi['spp'] ?? null,
            'guru' => $bi['guru'] ?? null,
            'kode_jadwal' => $bi['kode_jadwal'] ?? null,
            'hari_jam' => $bi['hari_jam'] ?? null,

            'kwitansi' => $pay['kwitansi'],
            'via' => $pay['via'],
            'bulan' => $pay['bulan'],
            'tahun' => $pay['tahun'],
            'tanggal_penerimaan' => $pay['tanggal_penerimaan'],
            'daftar' => $pay['daftar'],
            'voucher' => $pay['voucher'],
            'spp_rp' => $pay['spp_rp'],
            'spp_keterangan' => $pay['spp'] ?? null,
            'kaos' => $pay['kaos'],
            'kpk' => $pay['kpk'],
            'sertifikat' => $pay['sertifikat'],
            'stpb' => $pay['stpb'],
            'tas' => $pay['tas'],
            'event' => $pay['event'],
            'lain_lain' => $pay['lain_lain'],
        ]));

        if ($request->hasFile('attachment')) {
            if ($registration->attachment_path && Storage::disk('public')->exists($registration->attachment_path)) {
                Storage::disk('public')->delete($registration->attachment_path);
            }
            $registration->attachment_path = $request->file('attachment')->store('registrations', 'public');
            $registration->save();
        }

        // PERUBAHAN PALING PENTING: Hanya commit saat status BERUBAH menjadi accepted
if ($oldStatus !== 'accepted' && $registration->status === 'accepted') {
    Log::info("[UPDATE] Registrasi diubah menjadi ACCEPTED → Commit Buku Induk | NIM: " . optional($student)->nim . " | Nama: " . optional($student)->nama);
    $bi['penerimaan'] = $pay;
    $this->commitBukuIndukWithPayload(
        $student,
        $registration->status,
        $bi,
        $bimbaUnit,
        $noCabang,
        $registration->tanggal_daftar   // kirim tanggal_daftar dari registration
    );
}

        return redirect()->route('registrations.index')->with('success', 'Registrasi berhasil diperbarui!');
    }

    public function destroy(Registration $registration)
    {
        if ($registration->attachment_path && Storage::disk('public')->exists($registration->attachment_path)) {
            Storage::disk('public')->delete($registration->attachment_path);
        }
        $registration->delete();
        return redirect()->route('registrations.index')->with('success', 'Registrasi dihapus.');
    }

protected function commitBukuIndukWithPayload(
    Student $student,
    string $regStatus,
    array $bi = [],
    ?string $bimbaUnit = null,
    ?string $noCabang = null,
    ?string $tanggalDaftar = null
): void {

    if ($regStatus !== 'accepted') {
        Log::info("Commit BukuInduk dibatalkan: {$regStatus}");
        return;
    }

    DB::transaction(function () use ($student, $bi, $bimbaUnit, $noCabang, $tanggalDaftar) {

                // ====================== NIM GENERATION (PER UNIT) ======================
        if (empty($student->nim)) {

            $noCabangClean = trim($noCabang ?? '');
            $bimbaUnitClean = trim($bimbaUnit ?? $student->bimba_unit ?? '');

            Log::info('NIM Generation Start', [
                'no_cabang'  => $noCabangClean,
                'bimba_unit' => $bimbaUnitClean,
                'student_id' => $student->id
            ]);

            // Prefix selalu 5 digit dari no_cabang
            $prefix = str_pad($noCabangClean, 5, '0', STR_PAD_LEFT);

            // Ambil NIM TERAKHIR berdasarkan prefix saja (paling penting)
            $lastNIM = BukuInduk::where('nim', 'LIKE', $prefix . '%')
                ->lockForUpdate()
                ->orderByRaw('CAST(SUBSTRING(nim, 6) AS UNSIGNED) DESC')
                ->value('nim');

            // Jika tidak ada, coba cari dengan nama unit juga (fallback)
            if (!$lastNIM && $bimbaUnitClean) {
                $lastNIM = BukuInduk::where('nim', 'LIKE', $prefix . '%')
                    ->where('bimba_unit', 'LIKE', "%{$bimbaUnitClean}%")
                    ->lockForUpdate()
                    ->orderByRaw('CAST(SUBSTRING(nim, 6) AS UNSIGNED) DESC')
                    ->value('nim');
            }

            $nextNumber = $lastNIM 
                ? (int) substr($lastNIM, 5) + 1 
                : 1;

            $student->nim = $prefix . str_pad((string)$nextNumber, 4, '0', STR_PAD_LEFT);

            // Safety anti-duplikat
            $attempt = 0;
            while (BukuInduk::where('nim', $student->nim)->exists() && $attempt < 10) {
                $nextNumber++;
                $student->nim = $prefix . str_pad((string)$nextNumber, 4, '0', STR_PAD_LEFT);
                $attempt++;
            }

            $student->save();

            Log::info("✅ NIM Generated", [
                'nim'        => $student->nim,
                'prefix'     => $prefix,
                'last_nim'   => $lastNIM ?? 'TIDAK ADA',
                'next_number'=> $nextNumber,
                'unit'       => $bimbaUnitClean
            ]);
        }

        // ====================== PREPARE DATA ======================
        $pay = $bi['penerimaan'] ?? [];
        $tanggalPenerimaan = $pay['tanggal_penerimaan'] ?? $pay['tanggal'] ?? null;

        $tglDaftarFinal = $tanggalDaftar 
            ?? $bi['tanggal_daftar'] 
            ?? $tanggalPenerimaan 
            ?? $student->tanggal_daftar 
            ?? now()->format('Y-m-d');

        $tglMasukFinal = $tanggalPenerimaan 
            ?? $bi['tanggal_masuk'] 
            ?? $tglDaftarFinal;

        $biNorm = [
            'nama' => $bi['nama'] ?? $student->nama,
            'kelas' => $bi['kelas'] ?? 'biMBA AIUEO',
            'gol' => $bi['gol'] ?? '-',
            'kd' => $bi['kd'] ?? '-',
            'guru' => $bi['guru'] ?? '-',
            'tahap' => $bi['tahap'] ?? null,
            'spp' => $bi['spp'] ?? null,
            'kode_jadwal' => $bi['kode_jadwal'] ?? null,
            'tmpt_lahir' => $bi['tmpt_lahir'] ?? $student->tempat_lahir ?? null,
            'tanggal_lahir' => $bi['tanggal_lahir'] ?? $student->tgl_lahir ?? null,
        ];

        $statusBI = 'Baru';
        $sumberForm = strtolower($student->sumber_pendaftaran ?? '');
        $infoBimba = strtolower($student->informasi_bimba ?? '');

        if (str_contains($sumberForm, 'mutasi') || str_contains($sumberForm, 'pindah') ||
            str_contains($infoBimba, 'mutasi') || str_contains($infoBimba, 'pindah') ||
            ($student->status_trial ?? '') === 'mutasi') {
            $statusBI = 'Mutasi Baru';
        }

        // ====================== CLEAN BIMBA UNIT ======================
        $cleanBimbaUnit = $this->cleanUnitName($bimbaUnit ?? $student->bimba_unit ?? '');

        // ====================== UPDATE / CREATE BUKU INDUK ======================
        $trial = $student->muridTrial;

        BukuInduk::updateOrCreate(
            ['nim' => $student->nim],
            [
                'nama'           => $biNorm['nama'],
                'bimba_unit'     => $cleanBimbaUnit,     // ← Hanya nama unit bersih
                'no_cabang'      => $noCabang,           // ← No Cabang tetap tersimpan
                'status'         => $statusBI,

                'tgl_keluar'       => null,
                'kategori_keluar'  => null,
                'alasan'           => null,
                'status_pindah'    => $statusBI === 'Mutasi Baru' ? 'Pindah Masuk' : null,
                'tanggal_pindah'   => $statusBI === 'Mutasi Baru' ? $tglDaftarFinal : null,

                'tgl_daftar'     => $tglDaftarFinal,
                'tgl_masuk'      => $tglMasukFinal,
                'tanggal_masuk'  => $tglMasukFinal,

                'tahap'          => $biNorm['tahap'],
                'kelas'          => $biNorm['kelas'],
                'gol'            => $biNorm['gol'],
                'kd'             => $biNorm['kd'],
                'spp'            => $biNorm['spp'],
                'guru'           => $biNorm['guru'],
                'kode_jadwal'    => $biNorm['kode_jadwal'],

                'orangtua'       => $student->orangtua ?? $trial?->orangtua ?? null,
                'tmpt_lahir'     => $biNorm['tmpt_lahir'],
                'info'           => $student->informasi_bimba ?? $trial?->info ?? null,
                'tgl_lahir'      => $biNorm['tanggal_lahir'],
            ]
        );

        // ====================== PENERIMAAN ======================
        if (!empty($pay)) {
            $dt = Carbon::parse($tanggalPenerimaan ?? now());
            
            $penerimaanPayload = [
                'via' => $pay['via'] ?? 'Tunai',
                'bulan' => $pay['bulan'] ?? $dt->translatedFormat('F'),
                'tahun' => $pay['tahun'] ?? (int)$dt->format('Y'),
                'tanggal' => $dt->toDateString(),
                'nim' => $student->nim,
                'nama_murid' => $student->nama,
                'bimba_unit' => $cleanBimbaUnit,        // ← Bersih juga di penerimaan
                'no_cabang' => $noCabang,
                'status' => $statusBI,
                'guru' => $biNorm['guru'],
                'kelas' => $biNorm['kelas'],
                'gol' => $biNorm['gol'],
                'kd' => $biNorm['kd'],
                
                'daftar' => (int)($pay['daftar'] ?? 0),
                'voucher' => (int)($pay['voucher'] ?? 0),
                'spp' => (int)($pay['spp_rp'] ?? 0),
                'nilai_spp' => (int)($pay['spp_rp'] ?? 0),
                'kaos' => (int)($pay['kaos'] ?? 0),
                'kpk' => (int)($pay['kpk'] ?? 0),
                'sertifikat' => (int)($pay['sertifikat'] ?? 0),
                'stpb' => (int)($pay['stpb'] ?? 0),
                'tas' => (int)($pay['tas'] ?? 0),
                'event' => (int)($pay['event'] ?? 0),
                'lain_lain' => (int)($pay['lain_lain'] ?? 0),
            ];

            $penerimaanPayload['total'] = array_sum([
                $penerimaanPayload['daftar'], $penerimaanPayload['voucher'],
                $penerimaanPayload['spp'], $penerimaanPayload['kaos'],
                $penerimaanPayload['kpk'], $penerimaanPayload['sertifikat'],
                $penerimaanPayload['stpb'], $penerimaanPayload['tas'],
                $penerimaanPayload['event'], $penerimaanPayload['lain_lain']
            ]);

            $kwitansi = $pay['kwitansi'] ?? ('REG-' . $student->nim . '-' . time());

            Penerimaan::updateOrCreate(
                ['nim' => $student->nim, 'bulan' => strtolower(trim($penerimaanPayload['bulan'])), 'tahun' => $penerimaanPayload['tahun']],
                array_merge($penerimaanPayload, ['kwitansi' => $kwitansi])
            );
        }

        Log::info("✅ SUKSES Commit Buku Induk | NIM: {$student->nim} | Unit: {$cleanBimbaUnit} | No Cabang: {$noCabang}");
    });
}

/**
 * Bersihkan nama unit (hapus nomor cabang di depan)
 */
private function cleanUnitName(?string $unitName): string
{
    if (empty($unitName)) {
        return '';
    }

    // Hapus pola seperti "05141 | ", "05141|", "05141 - ", dll
    $clean = trim(preg_replace('/^\s*\d+\s*[\|\-]\s*/', '', $unitName));
    
    return $clean ?: trim($unitName);
}

        
    // ===== Helper functions =====
    protected function enforceTrialStatus(Student $student, array &$data): void
    {
        if ($student->murid_trial_id && optional($student->muridTrial)->status_trial === 'batal') {
            $data['status'] = 'rejected';
        }
    }

    protected function parseMoney($v): ?int
    {
        if ($v === null || $v === '')
            return null;
        $raw = preg_replace('/[^\d]/', '', (string) $v);
        return $raw === '' ? null : (int) $raw;
    }

    protected function tryParseDateToYmd($val): ?string
    {
        if (!$val)
            return null;
        try {
            return Carbon::parse($val)->format('Y-m-d');
        } catch (\Throwable $e) {
            return null;
        }
    }

    public function firstOrCreateFor(Student $student): Registration
    {
        return DB::transaction(function () use ($student) {
            $existing = Registration::where('student_id', $student->id)
                ->whereIn('status', ['pending', 'verified', 'accepted'])
                ->latest('id')
                ->first();

            if ($existing)
                return $existing;

            $payload = [
                'student_id' => $student->id,
                'status' => 'pending',
                'tanggal_daftar' => now(),
            ];

            if (Schema::hasColumn('registrations', 'tahun_ajaran')) {
                $payload['tahun_ajaran'] = Registration::currentAcademicYear();
            }

            return Registration::create($payload);
        });
    }
    public function show(Registration $registration)
    {
        // Kalau mau langsung ke halaman edit:
        return redirect()->route('registrations.edit', $registration->id);

        // Atau kalau nanti mau bikin halaman detail sendiri,
        // kamu bisa ganti jadi:
        // return view('registrations.show', compact('registration'));
    }
}