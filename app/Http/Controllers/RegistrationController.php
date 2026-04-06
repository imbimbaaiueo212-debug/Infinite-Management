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
    $q        = trim((string) $request->get('q', ''));
    $status   = trim((string) $request->get('status', ''));
    $unitId   = $request->get('unit_id'); // ⬅️ dari dropdown

    $query = Registration::query()
        ->with(['student.bukuInduk'])
        ->latest('created_at');

    // =========================
    // FILTER NIM / NAMA
    // =========================
    if ($q !== '') {
        $query->whereHas('student', function ($sq) use ($q) {
            $sq->where('nim', 'like', "%{$q}%")
               ->orWhere('nama', 'like', "%{$q}%");
        });
    }

    // =========================
    // FILTER STATUS REGISTRASI
    // =========================
    if ($status !== '') {
        $query->where('status', $status);
    }

    // =========================
    // ✅ FILTER CABANG + BIMBA UNIT (DIGABUNG)
    // =========================
    if ($unitId) {
        $unit = Unit::find($unitId);
        if ($unit) {
            $query->whereHas('student', function ($sq) use ($unit) {
                $sq->where('no_cabang', $unit->no_cabang)
                   ->where('bimba_unit', $unit->biMBA_unit);
            });
        }
    }

    $regs = $query->paginate(25)->withQueryString();

    // =========================
    // OPTION STUDENT (JIKA DIPAKAI)
    // =========================
    $studentOptions = Student::orderBy('nama')
        ->get(['id', 'nim', 'nama'])
        ->mapWithKeys(fn ($s) => [
            $s->id => "{$s->nim} - {$s->nama}"
        ])
        ->toArray();

    // =========================
    // ✅ OPTION UNIT (CABANG + UNIT DIGABUNG)
    // =========================
    $unitOptions = Unit::orderBy('no_cabang')
        ->get()
        ->map(fn ($u) => [
            'value' => $u->id,
            'label' => trim(($u->no_cabang ?? '') . ' - ' . ($u->biMBA_unit ?? '')),
        ])
        ->toArray();

    return view('registrations.index', compact(
        'regs',
        'studentOptions',
        'unitOptions'
    ));
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

        'hargaSaptataruna',
        'kdOptions',
        'sppMapping',
        'tahapanOptions',
        'kelasOptions',
        'guruOptions',
        'kodeJadwalOptions',
        'penerimaanPrefill',
        'selectedStudent'

    ));
}


    // ===== STORE (CREATE) =====
    public function store(Request $request)
{
    $data = $request->validate([
        'student_id'         => ['required','exists:students,id'],
        'gelombang'          => ['nullable','string','max:100'],
        'program'            => ['nullable','string','max:100'],
        'status'             => ['required',Rule::in(['pending','verified','accepted','rejected'])],
        'tanggal_daftar'     => ['nullable','date'],
        'tanggal_penerimaan' => ['nullable','date'],

        'bi' => ['array'],
        'bi.nim'   => ['nullable','string'],
        'bi.nama'  => ['nullable','string'],
        'bi.tahap' => ['nullable','string','max:100'],
        'bi.kelas' => ['nullable','string','max:100'],
        'bi.gol'   => ['nullable','string','max:50'],
        'bi.kd'    => ['nullable','string','max:10'],
        'bi.guru'  => ['nullable','string','max:255'],
        'bi.kode_jadwal' => ['nullable','string'],
        'bi.hari_jam'    => ['nullable','string'],
        'bi.spp'         => ['nullable'],

        'penerimaan' => ['nullable','array'],
        'attachment' => ['nullable','file','mimes:pdf,jpg,jpeg,png,webp','max:3072'],
    ]);

    $student = Student::with('muridTrial','bukuInduk')->findOrFail($data['student_id']);

    $bimbaUnit = $student->bimba_unit;
    $noCabang  = $student->no_cabang;

    $data['tanggal_daftar'] = $data['tanggal_daftar'] ?? now();

    if (Schema::hasColumn('registrations','tahun_ajaran')) {
        $data['tahun_ajaran'] = Registration::currentAcademicYear();
    }

    $biInput = $request->input('bi',[]);

    $bi = [
        'nim'   => $biInput['nim']  ?? $student->nim,
        'nama'  => $biInput['nama'] ?? $student->nama,
        'tahap' => $biInput['tahap'] ?? null,
        'kelas' => $biInput['kelas'] ?? 'biMBA AIUEO',
        'gol'   => $biInput['gol'] ?? '-',
        'kd'    => strtoupper($biInput['kd'] ?? '-'),
        'guru'  => $biInput['guru'] ?? '-',
        'kode_jadwal' => $biInput['kode_jadwal'] ?? null,
        'hari_jam'    => $biInput['hari_jam'] ?? null,
        'spp' => null,
    ];

    $rawSpp = $biInput['spp'] ?? null;

    if($rawSpp){
        $bi['spp'] = (int) preg_replace('/\D/','',$rawSpp);
    }else{
        if(!empty($bi['gol']) && !empty($bi['kd'])){
            $row = HargaSaptataruna::where('kode',$bi['gol'])->first();
            $col = strtolower($bi['kd']);
            $bi['spp'] = $row->$col ?? null;
        }
    }

    $p = $request->input('penerimaan',[]);

    $pay = [
        'kwitansi' => $p['kwitansi'] ?? null,
        'via'      => $p['via'] ?? null,
        'bulan'    => $p['bulan'] ?? null,
        'tahun'    => $p['tahun'] ?? null,

        'tanggal_penerimaan' => $this->tryParseDateToYmd(
            $request->input('tanggal_penerimaan')
            ?? $p['tanggal']
            ?? now()
        ),

        'daftar'     => $this->parseMoney($p['daftar'] ?? null),
        'voucher'    => $this->parseMoney($p['voucher'] ?? null),
        'spp_rp'     => $this->parseMoney($p['spp_rp'] ?? null),
        'spp'        => $p['spp'] ?? null,
        'kaos'       => $this->parseMoney($p['kaos'] ?? null),
        'kpk'        => $this->parseMoney($p['kpk'] ?? null),
        'sertifikat' => $this->parseMoney($p['sertifikat'] ?? null),
        'stpb'       => $this->parseMoney($p['stpb'] ?? null),
        'tas'        => $this->parseMoney($p['tas'] ?? null),
        'event'      => $this->parseMoney($p['event'] ?? null),
        'lain_lain'  => $this->parseMoney($p['lain_lain'] ?? null),
    ];

    $finalData = array_merge($data,[

        'bimba_unit'=>$bimbaUnit,
        'no_cabang'=>$noCabang,

        'tahap'=>$bi['tahap'],
        'kelas'=>$bi['kelas'],
        'gol'=>$bi['gol'],
        'kd'=>$bi['kd'],
        'spp'=>$bi['spp'],
        'guru'=>$bi['guru'],
        'kode_jadwal'=>$bi['kode_jadwal'],
        'hari_jam'=>$bi['hari_jam'],

        'kwitansi'=>$pay['kwitansi'],
        'via'=>$pay['via'],
        'bulan'=>$pay['bulan'],
        'tahun'=>$pay['tahun'],
        'tanggal_penerimaan'=>$pay['tanggal_penerimaan'],
        'daftar'=>$pay['daftar'],
        'voucher'=>$pay['voucher'],
        'spp_rp'=>$pay['spp_rp'],
        'spp_keterangan'=>$pay['spp'],
        'kaos'=>$pay['kaos'],
        'kpk'=>$pay['kpk'],
        'sertifikat'=>$pay['sertifikat'],
        'stpb'=>$pay['stpb'],
        'tas'=>$pay['tas'],
        'event'=>$pay['event'],
        'lain_lain'=>$pay['lain_lain'],
    ]);

    if($request->hasFile('attachment')){
        $finalData['attachment_path'] =
        $request->file('attachment')->store('registrations','public');
    }

    DB::transaction(function() use ($student,$bi,$pay,$bimbaUnit,$noCabang,$finalData){

        $reg = Registration::create($finalData);

        if($reg->status === 'accepted'){

            $bi['penerimaan'] = $pay;

            $this->commitBukuIndukWithPayload(
                $student,
                $reg->status,
                $bi,
                $bimbaUnit,
                $noCabang
            );

        }

    });

    return redirect()
        ->route('registrations.index')
        ->with('success','Registrasi berhasil disimpan!');
}

    // ===== EDIT =====
    public function edit(Registration $registration)
    {
        $students = \App\Models\Student::orderBy('nama')->get(['id', 'nim', 'nama']);

        // Ambil BI master via NIM (kalau sudah ada di buku_induk)
        $biMaster = optional($registration->student)->bukuInduk;

        // Harga + opsi KD untuk hitung SPP otomatis
        $hargaSaptataruna = \App\Models\HargaSaptataruna::all();
        $kdOptions = [];
        if ($first = $hargaSaptataruna->first()) {
            foreach ($first->getAttributes() as $key => $val) {
                if (in_array($key, ['a', 'b', 'c', 'd', 'e', 'f']))
                    $kdOptions[] = strtoupper($key);
            }
        }
        $sppMapping = [];
        foreach ($hargaSaptataruna as $row) {
            foreach ($kdOptions as $KD) {
                $col = strtolower($KD);
                $sppMapping[$row->kode][$KD] = (int) ($row->$col ?? 0);
            }
        }

        // Opsi (samakan dengan create)
        $tahapanOptions = ['Persiapan', 'Lanjutan'];
        $kelasOptions = ['biMBA AIUEO', 'English biMBA'];

        // === GURU OPTIONS: FILTER BERDASARKAN BIMBA UNIT MURID ===
        $guruOptions = [];

        $student = $registration->student;

        if ($student && !empty($student->bimba_unit)) {
            $guruOptions = Profile::where('bimba_unit', $student->bimba_unit)
                ->whereIn('jabatan', ['Guru', 'Pengajar'])
                ->orderBy('nama')
                ->pluck('nama')
                ->toArray();
        } else {
            $guruOptions = Profile::whereIn('jabatan', ['Guru', 'Pengajar'])
                ->orderBy('nama')
                ->pluck('nama')
                ->toArray();
        }
        $kodeJadwalOptions = [
            '108',
            '109',
            '110',
            '111',
            '112',
            '113',
            '114',
            '115',
            '116',
            '208',
            '209',
            '210',
            '211',
            '308',
            '309',
            '310',
            '311'
        ];

        // ⬇⬇⬇ PREFILL: ambil dulu dari registrations, fallback ke buku_induk
        $biPrefill = [
    'tahap' => $registration->tahap ?? ($biMaster->tahap ?? null),
    'kelas' => $registration->kelas ?? ($biMaster->kelas ?? null),
    'gol'   => $registration->gol   ?? ($biMaster->gol   ?? null),
    'kd'    => $registration->kd    ?? ($biMaster->kd    ?? null),
    'spp'   => $registration->spp   ?? ($biMaster->spp   ?? null),
    'guru'        => $registration->guru ?? ($biMaster->guru ?? null),
    'kode_jadwal' => $registration->kode_jadwal ?? ($biMaster->kode_jadwal ?? null),
    'jam'         => $registration->hari_jam ?? ($biMaster->hari_jam ?? null),
];


        // prefill penerimaan jika ada di registration; jika tidak ada fallback ke buku_induk
        $penerimaanPrefill = [
            'kwitansi' => $registration->kwitansi ?? null,
            'via' => $registration->via ?? null,
            'bulan' => $registration->bulan ?? null,
            'tahun' => $registration->tahun ?? null,
            'tanggal' => optional($registration->tanggal_penerimaan)->format('Y-m-d') ?? null,
            'daftar' => $registration->daftar ?? null,
            'voucher' => $registration->voucher ?? null,
            'spp_rp' => $registration->spp_rp ?? null,
            'spp' => $registration->spp_keterangan ?? null,
            'kaos' => $registration->kaos ?? null,
            'kpk' => $registration->kpk ?? null,
            'sertifikat' => $registration->sertifikat ?? null,
            'stpb' => $registration->stpb ?? null,
            'tas' => $registration->tas ?? null,
            'event' => $registration->event ?? null,
            'lain_lain' => $registration->lain_lain ?? null,
        ];

        // fallback spp dari buku_induk bila kosong
        if (($penerimaanPrefill['spp_rp'] === null || $penerimaanPrefill['spp_rp'] === '') && $biMaster && !empty($biMaster->spp)) {
            $penerimaanPrefill['spp_rp'] = (int) $biMaster->spp;
        }
        if (($penerimaanPrefill['spp'] === null || $penerimaanPrefill['spp'] === '') && $biMaster) {
            $penerimaanPrefill['spp'] = trim(($biMaster->gol ? $biMaster->gol . '/' : '') . ($biMaster->kd ?? '')) ?: ($biMaster->kd ?? null);
        }

        return view('registrations.edit', compact(
            'registration',
            'students',
            'hargaSaptataruna',
            'kdOptions',
            'sppMapping',
            'tahapanOptions',
            'kelasOptions',
            'biPrefill',
            'guruOptions',
            'kodeJadwalOptions',
            'penerimaanPrefill'
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
            Log::info("[CREATE] Registrasi langsung ACCEPTED → Commit Buku Induk | NIM: " . optional($student)->nim . " | Nama: " . optional($student)->nama);
            $bi['penerimaan'] = $pay;
            $this->commitBukuIndukWithPayload($student, $registration->status, $bi, $bimbaUnit, $noCabang);
        } else {
            Log::info("[UPDATE] Status tetap {$registration->status} (sebelum: {$oldStatus}) → Tidak ada commit Buku Induk | Nama: {$student->nama}");
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
    ?string $noCabang = null
): void {

    if ($regStatus !== 'accepted') {
        Log::info("Commit BukuInduk dibatalkan karena status bukan accepted: {$regStatus}");
        return;
    }

    DB::transaction(function () use ($student, $bi, $bimbaUnit, $noCabang) {

        /*
        |--------------------------------------------------------------------------
        | Generate NIM jika belum ada
        |--------------------------------------------------------------------------
        */

        if (empty($student->nim)) {

            $last = BukuInduk::whereRaw('nim REGEXP "^[0-9]+$"')
                ->lockForUpdate()
                ->orderByRaw('CAST(nim AS UNSIGNED) DESC')
                ->value('nim');

            $next = $last ? (int)$last + 1 : 10450001;

            $student->nim = str_pad((string)$next, 9, '0', STR_PAD_LEFT);
            $student->save();
        }


        /*
        |--------------------------------------------------------------------------
        | Ambil data penerimaan
        |--------------------------------------------------------------------------
        */

        $pay = $bi['penerimaan'] ?? [];

        $tanggalPenerimaan = $pay['tanggal_penerimaan']
            ?? $pay['tanggal']
            ?? null;


        /*
        |--------------------------------------------------------------------------
        | Tentukan tanggal masuk
        |--------------------------------------------------------------------------
        */

        $tanggalMasuk =
            $bi['tanggal_masuk']
            ?? $tanggalPenerimaan
            ?? $student->tanggal_masuk
            ?? now()->toDateString();


        /*
        |--------------------------------------------------------------------------
        | Normalisasi BI agar tidak null
        |--------------------------------------------------------------------------
        */

        $bi['nama']        = $bi['nama']        ?? $student->nama;
        $bi['kelas']       = $bi['kelas']       ?? 'biMBA AIUEO';
        $bi['gol']         = $bi['gol']         ?? '-';
        $bi['kd']          = $bi['kd']          ?? '-';
        $bi['guru']        = $bi['guru']        ?? '-';
        $bi['tahap']       = $bi['tahap']       ?? null;
        $bi['spp']         = $bi['spp']         ?? null;
        $bi['kode_jadwal'] = $bi['kode_jadwal'] ?? null;


        /*
        |--------------------------------------------------------------------------
        | Tentukan status Buku Induk
        |--------------------------------------------------------------------------
        */

        $statusBI = 'Baru';

        $sumberForm = strtolower($student->sumber_pendaftaran ?? '');
        $infoBimba  = strtolower($student->informasi_bimba ?? '');

        if (
            str_contains($sumberForm, 'mutasi') ||
            str_contains($sumberForm, 'pindah') ||
            str_contains($infoBimba, 'mutasi') ||
            str_contains($infoBimba, 'pindah') ||
            ($student->status_trial ?? '') === 'mutasi'
        ) {
            $statusBI = 'Mutasi Baru';
        }


        /*
        |--------------------------------------------------------------------------
        | Simpan Buku Induk
        |--------------------------------------------------------------------------
        */

        $trial = $student->muridTrial;

BukuInduk::updateOrCreate(

    ['nim' => $student->nim],

    [
        'nama'        => $bi['nama'],
        'bimba_unit'  => $bimbaUnit,
        'no_cabang'   => $noCabang,
        'status'      => $statusBI,

        'tahap'       => $bi['tahap'],
        'kelas'       => $bi['kelas'] ?? $student->kelas ?? null,
        'gol'         => $bi['gol'],
        'kd'          => $bi['kd'],
        'spp'         => $bi['spp'],
        'guru'        => $bi['guru'],
        'kode_jadwal' => $bi['kode_jadwal'],

        'orangtua' => $student->orangtua
            ?? $trial?->orangtua
            ?? null,

'tmpt_lahir' =>
    $bi['tmpt_lahir']
    ?? $student->tempat_lahir
    ?? $trial?->tempat_lahir
    ?? null,
    
        'info' => $student->informasi_bimba
            ?? $trial?->info
            ?? null,

        'tgl_lahir' =>
            $bi['tanggal_lahir']
            ?? $student->tgl_lahir
            ?? $trial?->tgl_lahir
            ?? null,

        'tgl_masuk' => $tanggalMasuk,

        'status_pindah' =>
            $statusBI === 'Mutasi Baru'
                ? 'Pindah Masuk'
                : null,

        'tanggal_pindah' =>
            $statusBI === 'Mutasi Baru'
                ? $tanggalMasuk
                : null,
    ]
);


        /*
        |--------------------------------------------------------------------------
        | Simpan Data Penerimaan
        |--------------------------------------------------------------------------
        */

        if (!empty($pay)) {

            $dt = Carbon::parse($tanggalPenerimaan ?? now());

            $bulan = $pay['bulan'] ?? $dt->translatedFormat('F');
            $tahun = $pay['tahun'] ?? (int)$dt->format('Y');

            $sppRp = (int)($pay['spp_rp'] ?? 0);


            $penerimaanPayload = [

                'via'        => $pay['via'] ?? 'Tunai',
                'bulan'      => $bulan,
                'tahun'      => $tahun,
                'tanggal'    => $dt->toDateString(),

                'nim'        => $student->nim,
                'nama_murid' => $student->nama,
                'bimba_unit' => $bimbaUnit,
                'no_cabang'  => $noCabang,

                'kelas'  => $bi['kelas'],
                'gol'    => $bi['gol'],
                'kd'     => $bi['kd'],
                'status' => $statusBI,
                'guru'   => $bi['guru'],

                'daftar'     => (int)($pay['daftar'] ?? 0),
                'voucher'    => (int)($pay['voucher'] ?? 0),
                'spp'        => $sppRp,
                'nilai_spp'  => $sppRp,
                'kaos'       => (int)($pay['kaos'] ?? 0),
                'kpk'        => (int)($pay['kpk'] ?? 0),
                'sertifikat' => (int)($pay['sertifikat'] ?? 0),
                'stpb'       => (int)($pay['stpb'] ?? 0),
                'tas'        => (int)($pay['tas'] ?? 0),
                'event'      => (int)($pay['event'] ?? 0),
                'lain_lain'  => (int)($pay['lain_lain'] ?? 0),
            ];


            $penerimaanPayload['total'] = array_sum([
                $penerimaanPayload['daftar'],
                $penerimaanPayload['voucher'],
                $penerimaanPayload['spp'],
                $penerimaanPayload['kaos'],
                $penerimaanPayload['kpk'],
                $penerimaanPayload['sertifikat'],
                $penerimaanPayload['stpb'],
                $penerimaanPayload['tas'],
                $penerimaanPayload['event'],
                $penerimaanPayload['lain_lain'],
            ]);


            $kwitansi = $pay['kwitansi']
                ?? ('REG-' . $student->nim . '-' . time());


            $existing = Penerimaan::where('nim', $student->nim)
                ->whereRaw('LOWER(TRIM(bulan)) = ?', [strtolower(trim($bulan))])
                ->where('tahun', $tahun)
                ->first();


            if ($existing) {

                $existing->update($penerimaanPayload);

            } else {

                $penerimaanPayload['kwitansi'] = $kwitansi;

                Penerimaan::create($penerimaanPayload);
            }
        }


        Log::info(
            "Commit BukuInduk & Penerimaan berhasil | NIM: {$student->nim} | Nama: {$student->nama}"
        );

    });
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