<?php

namespace App\Http\Controllers;

use App\Models\Student;
use App\Models\MuridTrial;
use App\Models\Registration;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;
use App\Models\BukuInduk;
use Illuminate\Support\Facades\Auth;
use App\Models\StudentHistory;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use App\Models\Unit;

class StudentController extends Controller
{
    /**
     * Pemetaan header Google Form -> kolom tabel students
     */
    protected array $FORM_HEADER_MAP = [
        'Timestamp' => 'form_timestamp',
        'Email Address' => 'email',
        'Sumber Pendaftaran' => 'sumber_pendaftaran',
        'Nama Lengkap Murid' => 'nama',
        'Tanggal Lahir' => 'tgl_lahir',
        'Alamat' => 'alamat',
        'Tempat Lahir' => 'tempat_lahir',
        'Jenis Kelamin' => 'jenis_kelamin',
        'Agama' => 'agama_murid',
        'Kode Pos' => 'kode_pos',
        'No Rumah' => 'no_rumah',
        'Rt.' => 'rt',
        'Rw.' => 'rw',
        'Kelurahan' => 'kelurahan',
        'Kecamatan' => 'kecamatan',
        'Kodya / Kab' => 'kodya_kab',
        'Provinsi' => 'provinsi',
        'Nama Ayah' => 'nama_ayah',
        'Agama (Ayah)' => 'agama_ayah',
        'Pekerjaan (Ayah)' => 'pekerjaan_ayah',
        'Alamat Kantor (Ayah)' => 'alamat_kantor_ayah',
        'No. Telepon Kantor (Ayah)' => 'telepon_kantor_ayah',
        'No. HP/WA (Ayah)' => 'hp_ayah',
        'Nama Ibu' => 'nama_ibu',
        'Agama (Ibu)' => 'agama_ibu',
        'Pekerjaan (Ibu)' => 'pekerjaan_ibu',
        'Alamat Kantor (Ibu)' => 'alamat_kantor_ibu',
        'No. Telepon Kantor (Ibu)' => 'telepon_kantor_ibu',
        'No. HP/WA (Ibu)' => 'hp_ibu',
        'Tanggal Masuk Sekolah' => 'tanggal_masuk',
        'Biaya Pendaftaran' => 'biaya_pendaftaran',
        'SPP bulanan' => 'spp_bulanan',
        'Informasi biMBA-AIUEO didapat dari' => 'informasi_bimba',
        'Hari' => 'hari',
        'Jam' => 'jam',

        // === TAMBAHAN BARU: FOTO KK (semua kemungkinan header dari Google Form) ===
    'Upload Foto KK'                  => 'foto_kk',
    'Upload KK'                         => 'foto_kk',
    'Kartu Keluarga'                  => 'foto_kk',
    'Foto Kartu Keluarga'             => 'foto_kk',
    'Upload Kartu Keluarga'           => 'foto_kk',
    'KK'                              => 'foto_kk',
    'File KK'                         => 'foto_kk',
    'Upload File KK'                  => 'foto_kk',
    'Dokumen KK'                      => 'foto_kk',
    'Link Foto KK'                    => 'foto_kk',
    'Link Kartu Keluarga'             => 'foto_kk',

        // TAMBAHAN UNTUK CABANG & UNIT
        'Cabang' => 'no_cabang',
        'No Cabang' => 'no_cabang',
        'Unit' => 'bimba_unit',
        'Nama Unit' => 'bimba_unit',
        'Unit biMBA' => 'bimba_unit',
    ];

    // -------------------------------------------------------------------------
    // Helper: normalisasi nama unit (dipakai konsisten di banyak tempat)
    // -------------------------------------------------------------------------
    protected function normalizeUnitName(?string $s): ?string
    {
        if (!$s) return null;
        $s = trim(mb_strtolower((string) $s));
        // Hapus kata noise umum agar perbandingan lebih konsisten
        $s = preg_replace('/\b(unit|bi?mba|aiueo|cabang)\b/u', ' ', $s);
        // Hapus tanda baca / karakter non alnum/spasi
        $s = preg_replace('/[^\p{L}\p{N}\s]+/u', ' ', $s);
        // Collapse spaces
        $s = preg_replace('/\s+/u', ' ', $s);
        return trim($s);
    }

    // -------------------------------------------------------------------------
    // Helper: resolve no_cabang dari bimba_unit
    // - 1) exact normalized match
    // - 2) try code extraction (numbers)
    // - 3) aliases column (if exists)
    // - 4) limited fuzzy (levenshtein threshold)
    // - 5) otherwise null
    // -------------------------------------------------------------------------
    protected function resolveNoCabangFromBimbaUnit(?string $bimbaUnit): ?string
{
    if (empty($bimbaUnit)) return null;

    $raw   = trim($bimbaUnit);
    $lower = strtolower($raw);

    // === ATURAN KHUSUS – DIPAKSA BENAR OTOMATIS SELAMANYA ===
    if (str_contains($lower, 'griya') && str_contains($lower, 'pesona') && str_contains($lower, 'madani')) {
        return '05141'; // GRIYA PESONA MADANI = 05141 SELAMANYA
    }
    if (str_contains($lower, 'sapta taruna iv')) {
    return '01045';
}
    if (str_contains($lower, 'pondok indah')) {
        return '05141';
    }
    if (str_contains($lower, 'kebayoran')) {
        return '05142';
    }
    // tambah unit lain di sini kalau perlu...

    // === Coba ambil kode angka langsung dari teks (misal: "05141 Griya Pesona Madani") ===
    if (preg_match('/\b(05[0-9]{3,5})\b/', $raw, $m)) {
        return $m[1];
    }

    // === Coba cari di tabel units (fallback) ===
    $norm = $this->normalizeUnitName($raw);

    $unit = Unit::whereRaw('LOWER(bimba_unit) LIKE ?', ["%{$norm}%"])
                ->orWhereRaw('LOWER(bimba_unit) LIKE ?', ["%{$raw}%"])
                ->first(['no_cabang']);

    if ($unit?->no_cabang) {
        return $unit->no_cabang;
    }

    return null; // jangan asal tebak
}

    // -------------------------------------------------------------------------
    // INDEX
    // -------------------------------------------------------------------------
    public function index(Request $request)
{
    $q           = trim((string) $request->input('q', ''));
    $statusTrial = trim((string) $request->input('status_trial', ''));
    $unitId      = $request->input('unit_id');

    $query = Student::query()
        ->with('muridTrial')
        ->latest('id');

    // =========================
    // 🔍 SEARCH NIM / NAMA
    // =========================
    if ($q !== '') {
        $query->where(function ($w) use ($q) {
            $w->where('nim', $q)
              ->orWhere('nim', 'like', "%{$q}%")
              ->orWhere('nama', 'like', "%{$q}%");
        });
    }

    // =========================
    // 🎯 FILTER STATUS TRIAL
    // =========================
    if ($statusTrial !== '') {
        $key = strtolower($statusTrial);
        if ($key === 'bukan trial') $key = 'tanpa trial';

        $map = [
            'tanpa trial'   => 'tanpa_trial',
            'aktif'         => 'aktif',
            'lanjut_daftar' => 'lanjut_daftar',
            'batal'         => 'batal',
        ];

        if ($key === 'tanpa trial') {
            $query->whereNull('murid_trial_id');
        } else {
            $query->whereNotNull('murid_trial_id')
                  ->whereHas('muridTrial', function ($mt) use ($map, $key) {
                      $mt->where('status_trial', $map[$key] ?? $key);
                  });
        }
    }

    // =========================
    // 🏫 FILTER UNIT (CABANG + biMBA)
    // =========================
    if ($unitId) {
        $unit = Unit::find($unitId);
        if ($unit) {
            $query->where('no_cabang', $unit->no_cabang)
                  ->where('bimba_unit', $unit->biMBA_unit);
        }
    }

    // =========================
    // PAGINATION
    // =========================
    $students = $query->paginate(20)->withQueryString();

    // =========================
    // 🔽 DROPDOWN SEARCH NIM
    // =========================
    $studentOptions = Student::select('nim', 'nama')
        ->orderBy('nama')
        ->limit(1000)
        ->get()
        ->map(fn($s) => [
            'value' => $s->nim,
            'label' => "{$s->nim} | {$s->nama}",
        ])->toArray();

    // =========================
    // 🔽 DROPDOWN UNIT (CABANG + UNIT)
    // =========================
    $unitOptions = Unit::orderBy('no_cabang')
        ->get()
        ->map(fn($u) => [
            'value' => $u->id,
            'label' => trim(($u->no_cabang ?? '') . ' - ' . ($u->biMBA_unit ?? '')),
        ]);

    return view('students.index', compact(
        'students',
        'q',
        'statusTrial',
        'studentOptions',
        'unitOptions',
        'unitId'
    ));
}

    // -------------------------------------------------------------------------
    // CREATE FORM
    // -------------------------------------------------------------------------
    public function create()
    {
        $kelasList = \App\Models\BukuInduk::select('kelas')
            ->whereNotNull('kelas')
            ->distinct()
            ->orderBy('kelas')
            ->pluck('kelas');

        return view('students.create', compact('kelasList'));
    }

    // -------------------------------------------------------------------------
    // STORE (pendaftaran langsung)
    // -------------------------------------------------------------------------
    public function store(Request $request)
{
    $request->merge([
        'source' => $this->normalizeSource($request->input('source')),
    ]);

    $formRules = $this->formValidationRules();

    $data = $request->validate(array_merge([
        'nama'       => ['required', 'string', 'max:255'],
        'kelas'      => ['nullable', 'string', 'max:100'],
        'tgl_lahir'  => ['nullable', 'date'],
        'usia'       => ['nullable', 'integer', 'min:1', 'max:120'],
        'orangtua'   => ['nullable', 'string', 'max:255'],
        'no_telp'    => ['nullable', 'string', 'max:20'],
        'alamat'     => ['nullable', 'string'],
        'guru_wali'  => ['nullable', 'string', 'max:255'],
        'source'     => ['nullable', 'in:trial,direct'],
        'no_cabang'  => ['nullable', 'string', 'max:20'],
        'bimba_unit' => ['nullable', 'string', 'max:100'],
    ], $formRules));

    // --- hitung usia & source seperti punyamu ---
    if (empty($data['usia']) && !empty($data['tgl_lahir'])) {
        $data['usia'] = Carbon::parse($data['tgl_lahir'])->age;
    }
    if (empty($data['source']) && !empty($data['sumber_pendaftaran'])) {
        $data['source'] = $this->normalizeSourceFromForm($data['sumber_pendaftaran']) ?? 'direct';
    }
    if (!array_key_exists('source', $data) || $data['source'] === null) {
        $data['source'] = 'direct';
    }

    $this->castFormLikeColumns($data);

    // 1️⃣ DI SINI: PASTIKAN no_cabang ikut dari bimba_unit (kalau kosong)
    if (!empty($data['bimba_unit']) && empty($data['no_cabang'])) {
        $resolved = $this->resolveNoCabangFromBimbaUnit($data['bimba_unit']);
        if ($resolved) {
            $data['no_cabang'] = $resolved;
        }
    }

    // 2️⃣ BARU MASUK TRANSAKSI & BUAT NIM BERDASARKAN bimba_unit
    $student = DB::transaction(function () use ($data) {
        $nim = $this->generateNimFromBukuInduk($data['bimba_unit'] ?? null);

        // jaga-jaga kalau ada tabrakan NIM
        while (Student::where('nim', $nim)->lockForUpdate()->exists()) {
            $nim = $this->incrementNim($nim);
        }

        $data['nim'] = $nim;

        $student = Student::create($data);

        if ($student->source === 'trial') {
            $this->ensureTrialRelation($student, 'aktif');
        }

        if ($student->source === 'direct') {
            $hasActive = Registration::where('student_id', $student->id)
                ->whereIn('status', ['pending', 'verified', 'accepted'])
                ->exists();

            if (!$hasActive) {
                $payload = [
                    'student_id'     => $student->id,
                    'status'         => 'pending',
                    'tanggal_daftar' => now(),
                    'source'         => $student->source,
                    'created_by'     => Auth::id(),
                ];
                if (Schema::hasColumn('registrations', 'tahun_ajaran')) {
                    $payload['tahun_ajaran'] = method_exists(Registration::class, 'currentAcademicYear')
                        ? Registration::currentAcademicYear() : null;
                }
                Registration::create(array_filter($payload, fn($v) => $v !== null));
            }
        }

        return $student;
    });

    return redirect()->route('students.index')
        ->with('success', "Student baru ({$student->nama}) berhasil didaftarkan. NIM: {$student->nim}.");
}


    // -------------------------------------------------------------------------
    // Promote dari MuridTrial (simpan bimba_unit & no_cabang)
    // -------------------------------------------------------------------------
    public function promoteFromTrial(MuridTrial $murid)
    {
        abort_unless($murid->exists, 404);

        if ($murid->student) {
            return redirect()
                ->route('students.edit', $murid->student->id)
                ->with('info', 'Murid ini sudah menjadi student.');
        }

        // ambil nama unit dari murid trial
        $possibleUnit = $murid->bimba_unit ?? $murid->unit ?? null;
        $possibleUnit = is_string($possibleUnit) ? trim($possibleUnit) : null;

        // resolve no_cabang (akan memakai normalizeUnitName)
        $resolvedNoCabang = null;
        try {
            $resolvedNoCabang = $this->resolveNoCabangFromBimbaUnit($possibleUnit);
        } catch (\Throwable $e) {
            Log::warning('promoteFromTrial: resolveNoCabangFromBimbaUnit error', ['id' => $murid->id, 'err' => $e->getMessage()]);
        }

        $student = DB::transaction(function () use ($murid, $possibleUnit, $resolvedNoCabang) {
            $nim = $this->generateNimFromBukuInduk($possibleUnit);
            while (Student::where('nim', $nim)->lockForUpdate()->exists()) {
                $nim = $this->incrementNim($nim);
            }

            return Student::create([
                'nim' => $nim,
                'nama' => $murid->nama,
                'kelas' => $murid->kelas,
                'tgl_lahir' => $murid->tgl_lahir,
                'usia' => $murid->usia,
                'orangtua' => $murid->orangtua,
                'no_telp' => $murid->no_telp,
                'alamat' => $murid->alamat,
                'guru_wali' => $murid->guru_trial,
                'source' => 'trial',
                'murid_trial_id' => $murid->id,
                'promoted_at' => now(),
                // SIMPAN UNIT & CABANG
                'bimba_unit' => $possibleUnit,
                'no_cabang'  => $resolvedNoCabang,
            ]);
        });

        return redirect()
            ->route('students.edit', $student->id)
            ->with('success', 'Berhasil promote ke Students. Buku Induk akan dibuat saat registrasi di-accept.');
    }

    // -------------------------------------------------------------------------
    // Accept registration -> create Buku Induk
    // -------------------------------------------------------------------------
    public function acceptRegistration(Student $student)
    {
        $bi = BukuInduk::where('nim', $student->nim)->first();
        if ($bi) {
            $this->mergeStudentToBukuInduk($student, $bi);
            return redirect()
                ->route('students.edit', $student->id)
                ->with('success', 'Buku Induk sudah ada; data digabung (merge) dengan perubahan dari Student.');
        }

        $biData = $this->buildBukuIndukPayload($student, true);
        $biData['tanggal_masuk'] = $student->tanggal_masuk ?? Carbon::now()->toDateString();

        try {
            DB::transaction(function () use ($student, $biData) {
                $bukuInduk = BukuInduk::create($biData);

                StudentHistory::create([
                    'student_id' => $student->id,
                    'user_id' => Auth::id(),
                    'diff' => ['registration_status' => ['old' => 'Pending/Promoted', 'new' => 'Accepted (Buku Induk Created)']],
                    'ip' => request()->ip(),
                    'user_agent' => request()->userAgent(),
                ]);
            });

            return redirect()
                ->route('students.edit', $student->id)
                ->with('success', 'Pendaftaran berhasil diterima. Buku Induk (NIM: ' . $student->nim . ') telah berhasil dibuat.');

        } catch (\Exception $e) {
            return back()->with('error', 'Gagal membuat Buku Induk. Error: ' . $e->getMessage());
        }
    }

    // -------------------------------------------------------------------------
    // EDIT FORM
    // -------------------------------------------------------------------------
    public function edit(\App\Models\Student $student)
    {
        $bi = \App\Models\BukuInduk::where('nim', $student->nim)->first();

        $kelasList = \App\Models\BukuInduk::select('kelas')
            ->whereNotNull('kelas')
            ->distinct()
            ->orderBy('kelas')
            ->pluck('kelas');

        $student->load('muridTrial');

        $trialStatuses = [
            'aktif' => 'Trial Aktif',
            'lanjut_daftar' => 'Lanjut Daftar',
            'batal' => 'Batal',
            'tanpa_trial' => 'Tanpa Trial',
            'mutasi' => 'Mutasi',
        ];

        return view('students.edit', compact('student', 'bi', 'kelasList', 'trialStatuses'));
    }

    // -------------------------------------------------------------------------
    // UPDATE
    // -------------------------------------------------------------------------
    public function update(Request $request, Student $student)
    {
        $normalized = $this->normalizeSource($request->input('source'));
        $formRules = $this->formValidationRules();

        $data = $request->validate(array_merge([
            'nama' => ['required', 'string', 'max:255'],
            'kelas' => ['nullable', 'string', 'max:100'],
            'tgl_lahir' => ['nullable', 'date'],
            'usia' => ['nullable', 'integer', 'between:1,120'],
            'orangtua' => ['nullable', 'string', 'max:255'],
            'no_telp' => ['nullable', 'string', 'max:20'],
            'alamat' => ['nullable', 'string'],
            'guru_wali' => ['nullable', 'string', 'max:255'],
            'source' => ['nullable', 'in:trial,direct'],
            'status_trial' => ['nullable', 'string'],
            'no_cabang' => ['nullable', 'string', 'max:20'],
            'bimba_unit' => ['nullable', 'string', 'max:100'],
        ], $formRules));

        if ($request->has('source')) {
            $data['source'] = $normalized ?? ($student->source ?? 'direct');
        } else {
            unset($data['source']);
        }

        if (empty($data['source']) && !empty($data['sumber_pendaftaran'])) {
            $data['source'] = $this->normalizeSourceFromForm($data['sumber_pendaftaran']) ?? ($student->source ?? 'direct');
        }

        $this->castFormLikeColumns($data);

        // AUTOMATIC: resolve no_cabang from bimba_unit if changed or absent
        if (!empty($data['bimba_unit'])) {
            $shouldResolve = empty($data['no_cabang']) || (isset($data['bimba_unit']) && $data['bimba_unit'] !== $student->bimba_unit);
            if ($shouldResolve) {
                $resolved = $this->resolveNoCabangFromBimbaUnit($data['bimba_unit']);
                if ($resolved) {
                    $data['no_cabang'] = $resolved;
                }
            }
        }

        DB::transaction(function () use ($request, $student, $data) {
            $fields = [
                'nama','kelas','tgl_lahir','usia','orangtua','no_telp','telp_hp','hp_ayah','hp_ibu',
                'alamat','guru_wali','source','no_cabang','bimba_unit'
            ];
            $before = $student->only($fields);

            $student->update($data);

            if (($student->source ?? null) === 'trial') {
                $this->ensureTrialRelation($student, 'aktif');
            }

            $bi = BukuInduk::where('nim', $student->nim)->first();
            if ($bi) {
                $this->mergeStudentToBukuInduk($student, $bi);
            }

            $after = $student->only($fields);
            $diff = [];
            foreach ($fields as $k) {
                $old = $before[$k] ?? null;
                $nw = $after[$k] ?? null;
                if ($k === 'tgl_lahir') {
                    $old = $old ? substr((string) $old, 0, 10) : $old;
                    $nw = $nw ? substr((string) $nw, 0, 10) : $nw;
                }
                if ((string) $old !== (string) $nw) {
                    $diff[$k] = ['old' => $old, 'new' => $nw];
                }
            }

            if ($diff) {
                StudentHistory::create([
                    'student_id' => $student->id,
                    'user_id' => Auth::id(),
                    'diff' => $diff,
                    'ip' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                ]);
            }
        });

        return redirect()->route('students.index')
            ->with('success', 'Data student berhasil diperbarui.');
    }

    // -------------------------------------------------------------------------
    // Helper NIM dari Buku Induk
    // -------------------------------------------------------------------------
   protected function generateNimFromBukuInduk(?string $bimbaUnit = null): string
{
    return DB::transaction(function () use ($bimbaUnit) {
        // 1. Ambil kode unit / no_cabang 5 digit
        $kodeUnit = $this->resolveKodeUnit($bimbaUnit); // contoh: "05141"

        // 2. Cari NIM terakhir di buku_induk dengan prefix kodeUnit
        $lastNim = BukuInduk::whereRaw('LEFT(nim, 5) = ?', [$kodeUnit])
            ->whereRaw('LENGTH(nim) = 9')
            ->lockForUpdate()
            ->orderByRaw('CAST(SUBSTRING(nim, 6, 4) AS UNSIGNED) DESC')
            ->value('nim');

        if (!$lastNim) {
            $lastNim = Student::whereRaw('LEFT(nim, 5) = ?', [$kodeUnit])
                ->whereRaw('LENGTH(nim) = 9')
                ->orderByRaw('CAST(SUBSTRING(nim, 6, 4) AS UNSIGNED) DESC')
                ->value('nim');
        }

        // 3. Hitung nomor urut berikutnya (4 digit terakhir)
        if ($lastNim) {
            $urutanSekarang   = (int) substr($lastNim, 5);
            $urutanBerikutnya = $urutanSekarang + 1;
        } else {
            $urutanBerikutnya = 1;
        }

        // 4. Bentuk NIM: [no_cabang 5 digit][urut 4 digit]
        return $kodeUnit . str_pad($urutanBerikutnya, 4, '0', STR_PAD_LEFT);
        // contoh: 05141 + 0579 = 051410579
    });
}



protected function resolveKodeUnit(?string $bimbaUnit): string
{
    if (empty($bimbaUnit)) {
        return '01045'; // default Sapta Taruna IV kalau unit tidak diisi
    }

    $lower = mb_strtolower(trim($bimbaUnit));

    // ATURAN KHUSUS — DIPAKSA SELAMANYA BENAR
    if (str_contains($lower, 'griya') && str_contains($lower, 'pesona') && str_contains($lower, 'madani')) {
        return '05141';
    }
    if (str_contains($lower, 'sapta taruna iv') || str_contains($lower, 'sapta taruna 4')) {
        return '01045';
    }
    if (str_contains($lower, 'pondok indah')) {
        return '05141';
    }
    if (str_contains($lower, 'kebayoran')) {
        return '05142';
    }

    // Ambil angka 5 digit langsung dari teks (misal: "05141 Griya Pesona Madani")
    if (preg_match('/\b05[0-9]{3}\b/', $bimbaUnit, $m)) {
        return $m[0];
    }

    // Fallback: cek dari tabel units
    $unit = \App\Models\Unit::whereRaw('LOWER(bimba_unit) LIKE ?', ["%{$lower}%"])
        ->orWhereRaw('LOWER(bimba_unit) LIKE ?', ["%{$bimbaUnit}%"])
        ->first(['no_cabang']);

    // hasil akhir: selalu 5 digit kode cabang
    return $unit?->no_cabang ?? '01045';
}

    protected function incrementNim(string $nim): string
{
    $prefix = substr($nim, 0, 5);      // no_cabang
    $number = (int) substr($nim, 5);   // 4 digit urut

    $number++;

    return $prefix . str_pad((string) $number, 4, '0', STR_PAD_LEFT);
}


    protected function normalizeSource($value): ?string
    {
        if ($value === '' || $value === null)
            return null;

        $map = [
            'langsung' => 'direct',
            'promote' => 'trial',
            'trial' => 'trial',
            'direct' => 'direct',
        ];

        return $map[$value] ?? $value;
    }

    protected function normalizeSourceFromForm(?string $sumber): ?string
    {
        if ($sumber === null) return null;
        $x = strtolower(trim($sumber));
        $map = [
            'pendaftaran baru' => 'direct',
            'pendaftaran mutasi' => 'direct',
            'pendaftaran langsung' => 'direct',
            'daftar langsung' => 'direct',
            'langsung' => 'direct',
            'direct' => 'direct',
            'murid pendaftaran trial' => 'trial',
            'trial' => 'trial',
            'dari trial' => 'trial',
            'promote' => 'trial',
            'bukan trial' => 'direct',
            'tanpa trial' => 'direct',
        ];
        if (!isset($map[$x]) && str_contains($x, 'trial')) return 'trial';
        return $map[$x] ?? 'direct';
    }

    protected function formValidationRules(): array
    {
        return [
            'form_timestamp' => ['nullable', 'string'],
            'email' => ['nullable', 'email', 'max:255'],
            'sumber_pendaftaran' => ['nullable', 'string', 'max:255'],
            'tempat_lahir' => ['nullable', 'string', 'max:255'],
            'jenis_kelamin' => ['nullable', 'string', 'max:20'],
            'agama_murid' => ['nullable', 'string', 'max:50'],
            'kode_pos' => ['nullable', 'string', 'max:10'],
            'no_rumah' => ['nullable', 'string', 'max:50'],
            'rt' => ['nullable', 'string', 'max:10'],
            'rw' => ['nullable', 'string', 'max:10'],
            'kelurahan' => ['nullable', 'string', 'max:255'],
            'kecamatan' => ['nullable', 'string', 'max:255'],
            'kodya_kab' => ['nullable', 'string', 'max:255'],
            'provinsi' => ['nullable', 'string', 'max:255'],
            'nama_ayah' => ['nullable', 'string', 'max:255'],
            'agama_ayah' => ['nullable', 'string', 'max:50'],
            'pekerjaan_ayah' => ['nullable', 'string', 'max:255'],
            'alamat_kantor_ayah' => ['nullable', 'string'],
            'telepon_kantor_ayah' => ['nullable', 'string', 'max:30'],
            'hp_ayah' => ['nullable', 'string', 'max:30'],
            'nama_ibu' => ['nullable', 'string', 'max:255'],
            'agama_ibu' => ['nullable', 'string', 'max:50'],
            'pekerjaan_ibu' => ['nullable', 'string', 'max:255'],
            'alamat_kantor_ibu' => ['nullable', 'string'],
            'telepon_kantor_ibu' => ['nullable', 'string', 'max:30'],
            'hp_ibu' => ['nullable', 'string', 'max:30'],
            'tanggal_masuk' => ['nullable', 'string'],
            'biaya_pendaftaran' => ['nullable', 'string'],
            'spp_bulanan' => ['nullable', 'string'],
            'informasi_bimba' => ['nullable', 'string', 'max:255'],
            'hari' => ['nullable', 'string', 'max:50'],
            'jam' => ['nullable', 'string', 'max:50'],
        ];
    }

    protected function castFormLikeColumns(array &$data): void
    {
        foreach (['tgl_lahir', 'tanggal_masuk'] as $d) {
            if (!empty($data[$d])) {
                $val = (string) $data[$d];
                $val = str_replace('/', '-', $val);
                try {
                    $data[$d] = Carbon::parse($val)->format('Y-m-d');
                } catch (\Throwable $e) {
                }
            }
        }

        if (!empty($data['form_timestamp'])) {
            $ts = (string) $data['form_timestamp'];
            $ts = str_replace('/', '-', $ts);
            $ts = preg_replace('/(\s\d{1,2})\.(\d{2})(?:\.(\d{2}))?$/', '$1:$2:$3', $ts);
            try {
                $data['form_timestamp'] = Carbon::parse($ts);
            } catch (\Throwable $e) {
                unset($data['form_timestamp']);
            }
        }

        foreach (['biaya_pendaftaran', 'spp_bulanan'] as $m) {
            if (isset($data[$m]) && $data[$m] !== null && $data[$m] !== '') {
                $raw = preg_replace('/[^0-9,\.]/', '', (string) $data[$m]);
                $raw = str_replace('.', '', $raw);
                $raw = str_replace(',', '.', $raw);
                $data[$m] = is_numeric($raw) ? (float) $raw : null;
            }
        }

        if (!empty($data['hari'])) {
            $h = strtolower(trim((string) $data['hari']));
            $map = [
                'seini' => 'Senin','senin' => 'Senin','selasa' => 'Selasa','rabu' => 'Rabu',
                'kamis' => 'Kamis','jumat' => 'Jumat',"jum'at" => 'Jumat','sabtu' => 'Sabtu','minggu' => 'Minggu',
            ];
            if (str_contains($h, ',')) $h = trim(strtok($h, ','));
            $data['hari'] = $map[$h] ?? ucwords($h);
        }

        if (!empty($data['jam'])) {
            $jam = trim((string) $data['jam']);
            if (str_contains($jam, ',')) {
                $parts = explode(',', $jam);
                $jam = trim($parts[1] ?? $jam);
            }
            $jam = preg_replace('/^jam\s*/i', '', $jam);
            $jam = str_replace('.', ':', $jam);
            $data['jam'] = $jam;
        }

        if (empty($data['no_telp'])) {
            $data['no_telp'] = $data['hp_ayah'] ?? $data['hp_ibu'] ?? null;
        }

        if (empty($data['orangtua'])) {
            $ayah = isset($data['nama_ayah']) ? trim((string) $data['nama_ayah']) : '';
            $ibu = isset($data['nama_ibu']) ? trim((string) $data['nama_ibu']) : '';
            $join = trim(implode(' & ', array_filter([$ayah ?: null, $ibu ?: null])));
            if ($join !== '') $data['orangtua'] = $join;
        }
    }

    public function destroy(Student $student)
    {
        DB::transaction(function () use ($student) {
            BukuInduk::where('nim', $student->nim)->delete();
            $student->delete();
        });

        return redirect()
            ->route('students.index')
            ->with('success', "Data student {$student->nama} (NIM: {$student->nim}) telah dihapus.");
    }

    // -------------------------------------------------------------------------
    // importFromSheet: panggil command dan lalu buat registrasi otomatis
    // serta pasca-import isi no_cabang secara deterministik
    // -------------------------------------------------------------------------
   public function importFromSheet(Request $request)
{
    $sheet = $request->input('sheet', 'Registrasi');

    $exit   = Artisan::call('forms:import-students', ['sheet' => $sheet]);
    $output = trim(Artisan::output());

    // === 1. Buat Registration otomatis untuk student direct ===
    $directStudents = \App\Models\Student::where('source', 'direct')
        ->whereDoesntHave('registrations', function ($q) {
            $q->whereIn('status', ['pending', 'verified', 'accepted']);
        })
        ->get();

    foreach ($directStudents as $student) {
        $payload = [
            'student_id'     => $student->id,
            'status'         => 'pending',
            'tanggal_daftar' => now(),
            'source'         => $student->source,
            'created_by'     => Auth::id(),
        ];

        if (Schema::hasColumn('registrations', 'tahun_ajaran')) {
            $payload['tahun_ajaran'] = method_exists(\App\Models\Registration::class, 'currentAcademicYear')
                ? \App\Models\Registration::currentAcademicYear()
                : null;
        }

        \App\Models\Registration::create(array_filter($payload, fn($v) => $v !== null));
    }

    // === 2. PERBAIKI SEMUA no_cabang – BAIK YANG KOSONG MAUPUN YANG SALAH! ===
    $studentsToFix = Student::whereNotNull('bimba_unit')
        ->where('bimba_unit', '!=', '')
        ->select('id', 'bimba_unit', 'no_cabang')
        ->get();

    $filled  = 0;
    $updated = 0;

    foreach ($studentsToFix as $student) {
        $correctCode = $this->resolveNoCabangFromBimbaUnit($student->bimba_unit);

        if ($correctCode && $student->no_cabang !== $correctCode) {
            $oldCode = $student->no_cabang;

            $student->no_cabang = $correctCode;
            $student->saveQuietly();

            if (is_null($oldCode)) {
                $filled++;
            } else {
                $updated++;
            }

            Log::info('no_cabang diperbaiki otomatis', [
                'student_id' => $student->id,
                'bimba_unit' => $student->bimba_unit,
                'old_no_cabang' => $oldCode,
                'new_no_cabang' => $correctCode,
            ]);
        }
    }

    // === 3. Pesan sukses yang jelas ===
    $message = "Import dari sheet '{$sheet}' selesai!"
             . " Registrasi otomatis dibuat: {$directStudents->count()}."
             . " no_cabang diisi baru: {$filled}, diperbaiki (salah → benar): {$updated}.";

    return back()->with('success', $message);
}

    public function historyJson(Student $student)
    {
        $histories = $student->histories()->with('user')->latest()->take(50)->get();

        $data = $histories->map(function ($h) {
            return [
                'date' => $h->created_at->format('Y-m-d H:i'),
                'user' => optional($h->user)->name ?? 'System',
                'ip' => $h->ip,
                'diff' => $h->diff,
            ];
        });

        return response()->json(['data' => $data]);
    }

    protected function ensureTrialRelation(Student $student, string $status = 'aktif'): void
{
    // Jika bukan trial, keluar
    if ($student->source !== 'trial') {
        return;
    }

    // Jika sudah punya relasi trial, PASTIKAN unit & cabangnya benar (update jika kosong/salah)
    if ($student->murid_trial_id) {
        $trial = $student->muridTrial;
        $needsUpdate = false;
        $updates = [];

        if (empty($trial->bimba_unit) && !empty($student->bimba_unit)) {
            $updates['bimba_unit'] = $student->bimba_unit;
            $needsUpdate = true;
        }
        if (empty($trial->no_cabang) && !empty($student->no_cabang)) {
            $updates['no_cabang'] = $student->no_cabang;
            $needsUpdate = true;
        }

        if ($needsUpdate) {
            $trial->update($updates);
        }
        return;
    }

    // Jika belum ada → buat baru, dan PASTIKAN unit & cabang ikut
    $trial = MuridTrial::create([
        'nama'         => $student->nama,
        'status_trial' => $status,
        'kelas'        => $student->kelas ?? null,
        'tgl_lahir'    => $student->tgl_lahir ?? null,
        'usia'         => $student->usia ?? null,
        'orangtua'     => $student->orangtua ?? null,
        'no_telp'      => $student->no_telp ?? null,
        'alamat'       => $student->alamat ?? null,
        'guru_trial'   => $student->guru_wali ?? null,

        // INI YANG WAJIB DITAMBAH: UNIT & CABANG!
        'bimba_unit'   => $student->bimba_unit,
        'no_cabang'    => $student->no_cabang,
    ]);

    $student->murid_trial_id = $trial->id;
    $student->saveQuietly(); // gunakan saveQuietly agar tidak trigger event berulang
}

    protected function buildBukuIndukPayload(Student $student, bool $forCreate = false): array
    {
        $payload = [
            'nim' => $student->nim,
            'nama' => $student->nama ?? null,
            'tmpt_lahir' => $student->tempat_lahir ?? null,
            'tgl_lahir' => $student->tgl_lahir ?? null,
            'alamat_murid' => $student->alamat ?? null,
            'alamat' => $student->alamat ?? null,
            'usia' => $student->usia ?? null,
            'kelas' => $student->kelas ?? null,
            'orangtua' => $student->orangtua ?? null,
            'nama_ayah' => $student->nama_ayah ?? null,
            'nama_ibu' => $student->nama_ibu ?? null,
            'no_telp_hp' => $student->no_telp ?? $student->telp_hp ?? $student->hp_ayah ?? $student->hp_ibu ?? null,
            'no_telp' => $student->no_telp ?? $student->telp_hp ?? null,
            'petugas_trial' => $student->petugas_trial,
            'kode_jadwal' => $student->kode_jadwal ?? null,
            'hari_jam' => $student->hari ?? null,
            'no_cabang' => $student->no_cabang,
            'bimba_unit' => $student->bimba_unit,
            'note' => null,
        ];

        if ($forCreate) {
            $payload['tanggal_masuk'] = $student->tanggal_masuk ?? null;
            $payload['periode'] = $student->periode ?? null;
            $payload['level'] = $student->level ?? null;
        }

        return $payload;
    }

    protected function mergeStudentToBukuInduk(Student $student, BukuInduk $bi): void
{
    // Daftar kolom yang TIDAK BOLEH di-overwrite (kecuali manual)
    $protectedFields = [
        'kelas', 'tahap', 'gol', 'kd', 'spp', 'kode_jadwal',
        'lama_bljr', 'tgl_keluar', 'kategori_keluar', 'alasan', 'no_pembayaran_murid'
        // 'petugas_trial' diHAPUS dari sini → biar boleh di-update otomatis!
    ];

    // Kolom yang SELALU di-overwrite kalau ada nilai baru
    $alwaysOverwrite = [
        'nama', 'alamat_murid', 'alamat', 'orangtua',
        'no_telp_hp', 'no_telp', 'nama_ayah', 'nama_ibu',
        'no_cabang', 'bimba_unit',
        'petugas_trial', // <<< INI YANG BARU DITAMBAH! SELALU UPDATE
    ];

    $biPayload = $this->buildBukuIndukPayload($student, false);
    $updates   = [];

    foreach ($biPayload as $col => $val) {
        if (!Schema::hasColumn('buku_induk', $col)) {
            continue;
        }

        // Jangan sentuh kolom yang dilindungi
        if (in_array($col, $protectedFields, true)) {
            continue;
        }

        // SELALU overwrite kolom penting (termasuk petugas_trial)
        if (in_array($col, $alwaysOverwrite, true)) {
            if ($val !== null && $val !== '') {
                $updates[$col] = $val;
            }
            continue;
        }

        // Untuk kolom lain: isi hanya kalau masih kosong
        $current = $bi->$col ?? null;
        if (($current === null || $current === '') && $val !== null && $val !== '') {
            $updates[$col] = $val;
        }
    }

    if (!empty($updates)) {
        BukuInduk::where('nim', $student->nim)->update($updates);
        Log::info('mergeStudentToBukuInduk applied', [
            'nim'      => $student->nim,
            'updates'  => array_keys($updates),
            'petugas_trial' => $updates['petugas_trial'] ?? '(tidak berubah)',
        ]);
    }
}

    // -------------------------------------------------------------------------
    // MUTASI MASUK → LANGSUNG MASUK BUKU INDUK (Versi FINAL – NO TYPO!)
    // -------------------------------------------------------------------------
    public function mutasiMasuk(Request $request, Student $student)
    {
        $data = $request->validate([
            'tanggal_mutasi' => 'required|date',
            'asal_unit'      => 'nullable|string|max:255',
            'asal_kode'      => 'nullable|string|max:50',
            'tahap'          => 'required|string|max:50',
            'gol'            => 'required|string|max:50',
            'kd'             => 'required|in:a,b,c,d,e,f,A,B,C,D,E,F',
            'spp'            => 'nullable|numeric|min:0',
            'status_pindah'  => 'nullable|string|max:100',
            'keterangan'     => 'nullable|string|max:1000',
        ]);

        // Auto hitung SPP kalau kosong (dari tabel harga_sapta_taruna)
        if (empty($data['spp']) && class_exists(\App\Models\HargaSaptaTaruna::class)) {
            $harga = \App\Models\HargaSaptaTaruna::where('kode', $data['gol'])->first();
            if ($harga) {
                $col = strtolower($data['kd']);
                $data['spp'] = (int) ($harga->{$col} ?? 0);
            }
        }

        return DB::transaction(function () use ($student, $data) {
            // 1. Update data student (unit & tanggal masuk)
            $student->update([
                'tanggal_masuk' => $data['tanggal_mutasi'],
                'bimba_unit'    => $data['asal_unit'] ?? $student->bimba_unit,
                'no_cabang'     => $this->resolveNoCabangFromBimbaUnit($data['asal_unit'] ?? $student->bimba_unit),
            ]);

            // 2. BUAT / UPDATE BUKU INDUK → INI INTINYA!
            BukuInduk::updateOrCreate(
                ['nim' => $student->nim],
                [
                    'nama'           => $student->nama,
                    'kelas'          => $student->kelas ?? '-',
                    'status'         => 'Baru', // atau 'Mutasi Masuk' kalau mau beda
                    'tanggal_masuk'  => $data['tanggal_mutasi'],
                    'tanggal_pindah' => $data['tanggal_mutasi'],
                    'status_pindah'  => $data['status_pindah'] ?? 'Pindah Masuk',
                    'tahap'          => $data['tahap'],
                    'gol'            => $data['gol'],
                    'kd'             => strtoupper($data['kd']),
                    'spp'            => (int) ($data['spp'] ?? 0),
                    'tempat_lahir'   => $student->tempat_lahir,
                    'tgl_lahir'      => $student->tgl_lahir,
                    'usia'           => $student->usia ?? Carbon::parse($data['tanggal_mutasi'])->age,
                    'alamat_murid'   => $student->alamat,
                    'alamat'         => $student->alamat,
                    'orangtua'       => $student->orangtua,
                    'no_telp_hp'     => $student->no_telp ?? $student->hp_ayah ?? $student->hp_ibu,
                    'no_telp'        => $student->no_telp,
                    'guru'           => $student->guru_wali,
                    'no_cabang'      => $student->no_cabang,
                    'bimba_unit'     => $student->bimba_unit,
                    'keterangan'     => $data['keterangan'],
                ]
            );

            // 3. Kalau masih nyantol ke trial → ubah status jadi mutasi
            if ($student->muridTrial()->exists()) {
                $student->muridTrial()->update(['status_trial' => 'mutasi']);
            }

            // 4. Log history
            StudentHistory::create([
                'student_id' => $student->id,
                'user_id'    => auth()->id(),
                'diff'       => ['mutasi_masuk' => $data],
            ]);

            return redirect()->route('students.edit', $student->id)
                ->with('success', "Mutasi masuk {$student->nama} berhasil! Data sudah masuk Buku Induk dengan lengkap.");
        });
    }
}
