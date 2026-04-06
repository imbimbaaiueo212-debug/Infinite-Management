<?php

namespace App\Http\Controllers;

use App\Models\MuridTrial;
use App\Models\ParentCommitment;
use App\Models\Registration;
use App\Models\Student;
use App\Models\Unit;
use App\Models\Profile;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class MuridTrialController extends Controller
{
    public function index(Request $request)
{
    $this->autoActivateTrial();
    $status       = $request->get('status');
    $rawQ         = $request->get('q');
    $plainSearch  = trim((string) $request->get('search', ''));
    $selectedUnitId = $request->get('unit_id'); // ⬅️ GANTI INI

    $searchTerm = null;
    if (!empty($rawQ)) {
        $searchTerm = str_contains($rawQ, '||')
            ? trim(explode('||', $rawQ)[0])
            : trim($rawQ);
    }

    // ===============================
    // ✅ UNIT OPTIONS (CABANG + BIMBA)
    // ===============================
    $unitOptions = Unit::orderBy('no_cabang')
        ->get()
        ->map(fn ($u) => [
            'value' => $u->id,
            'label' => trim(($u->no_cabang ?? '') . ' - ' . ($u->biMBA_unit ?? '')),
        ]);

    $murid_trials = MuridTrial::with('student')

        // SEARCH UTAMA
        ->when($searchTerm, fn($q) => $q->where(fn($sq) => $sq
            ->where('nama', 'like', "%{$searchTerm}%")
            ->orWhere('no_telp', 'like', "%{$searchTerm}%")
            ->orWhereHas('student', fn($ssq) => $ssq
                ->where('nama', 'like', "%{$searchTerm}%")
                ->orWhere('hp_ayah', 'like', "%{$searchTerm}%")
                ->orWhere('hp_ibu', 'like', "%{$searchTerm}%")
            )
        ))

        // SEARCH BEBAS
        ->when($plainSearch, fn($q) => $q->where(fn($sq) => $sq
            ->where('nama', 'like', "%{$plainSearch}%")
            ->orWhere('no_telp', 'like', "%{$plainSearch}%")
            ->orWhere('bimba_unit', 'like', "%{$plainSearch}%")
            ->orWhere('no_cabang', 'like', "%{$plainSearch}%")
        ))

        // ===============================
        // ✅ FILTER UNIT (MASTER)
        // ===============================
        ->when($selectedUnitId, function ($q) use ($selectedUnitId) {
            $unit = Unit::find($selectedUnitId);
            if ($unit) {
                $q->where('bimba_unit', $unit->biMBA_unit)
                  ->where('no_cabang', $unit->no_cabang);
            }
        })

        // STATUS
        ->when($status, fn($q) =>
            $status === 'kosong'
                ? $q->whereNull('status_trial')
                : $q->where('status_trial', $status)
        )

        ->latest('waktu_submit')
        ->paginate(25)
        ->withQueryString();

    // ================================
// GURU + KEPALA UNIT (dengan filter unit)
// ================================

$daftarGuru = ['' => '- Pilih Guru -'];

// 1. Ambil guru biasa (tetap pakai scope guru())
$guruQuery = Profile::guru();

// 2. Ambil kepala unit berdasarkan jabatan saja (case-insensitive)
$kepalaUnitQuery = Profile::whereRaw('LOWER(jabatan) LIKE ?', ['%kepala unit%'])
                         ->orWhereRaw('LOWER(jabatan) LIKE ?', ['%kepala cabang%'])
                         ->orWhereRaw('LOWER(jabatan) LIKE ?', ['%kepsek%']);  // tambah variasi jika perlu

// Jika ada filter unit dari dropdown
if ($selectedUnitId) {
    $unit = Unit::find($selectedUnitId);
    if ($unit) {
        $guruQuery->where('bimba_unit', $unit->biMBA_unit);
        $kepalaUnitQuery->where('bimba_unit', $unit->biMBA_unit);
    }
}
// Jika user login adalah kepala unit → paksa filter ke unitnya sendiri
// (cek berdasarkan jabatan user login, karena tidak ada kolom role)
elseif (auth()->check()) {
    $userProfile = auth()->user()->profile; // asumsi relasi user -> profile ada
    $isKepalaUnit = $userProfile && str_contains(strtolower($userProfile->jabatan ?? ''), 'kepala unit');

    if ($isKepalaUnit) {
        $unitLogin = $userProfile->bimba_unit 
                     ?? $userProfile->biMBA_unit 
                     ?? null;
        
        if ($unitLogin) {
            $guruQuery->where('bimba_unit', $unitLogin);
            $kepalaUnitQuery->where('bimba_unit', $unitLogin);
        }
    }
}

// Eksekusi query
$gurus = $guruQuery->get()->unique('nama')->sortBy('nama');
$kepalaUnits = $kepalaUnitQuery->get()->unique('nama')->sortBy('nama');

// 3. Gabung ke array dropdown
foreach ($gurus as $g) {
    $label = trim($g->nama);
    if ($g->bimba_unit) {
        $label .= ' - ' . $g->bimba_unit;
    }
    $daftarGuru[$g->nama] = $label;
}

foreach ($kepalaUnits as $ku) {
    $label = trim($ku->nama);
    if ($ku->bimba_unit) {
        $label .= ' - ' . $ku->bimba_unit;
    }
    $label .= ' (Kepala Unit)';
    
    // Hindari duplikat key
    $key = $ku->nama;
    if (isset($daftarGuru[$key])) {
        $daftarGuru[$key] .= ' (Guru)';           // tambah penanda di yang sudah ada
        $key .= ' (KU)';                          // buat key unik untuk kepala unit
    }
    $daftarGuru[$key] = $label;
}

// Urutkan ulang berdasarkan label
asort($daftarGuru);

    return view('murid_trials.index', compact(
        'murid_trials',
        'daftarGuru',
        'unitOptions'
    ))->with(compact(
        'plainSearch',
        'rawQ',
        'status',
        'selectedUnitId'
    ));
}

    public function store(Request $request)
{
    $rules = [
        'nama'       => 'required|string|max:255',
        'tgl_lahir'  => 'nullable|date',
        'usia'       => 'nullable|integer',   // tambahkan kalau mau
        'orangtua'   => 'nullable|string|max:255',
        'no_telp'    => 'nullable|string|max:20',
        'alamat'     => 'nullable|string',
        'bimba_unit' => 'nullable|string|max:100',
        'no_cabang'  => 'nullable|string|max:10',
        'tgl_mulai'  => 'nullable|date',
        'guru_trial' => 'nullable|string|max:255',
        'info'       => 'nullable|string',
    ];

    $data = $request->validate($rules);

    if (empty($data['usia'] ?? null) && $data['tgl_lahir'] ?? null) {
        $data['usia'] = Carbon::parse($data['tgl_lahir'])->age;
    }

    // 🔹 Tambahkan ini
    $data['waktu_submit'] = now();
dd($data);
    MuridTrial::create($data);

    return redirect()->route('murid_trials.index')
        ->with('success', 'Data murid trial berhasil ditambahkan.');
}

    public function update(Request $request, MuridTrial $murid_trial)
    {
        $data = $request->validate([
            'nama'           => 'required|string|max:255',
            'tgl_lahir'      => 'nullable|date',
            'usia'           => 'nullable|integer|min:1|max:120',
            'orangtua'       => 'nullable|string|max:255',
            'no_telp'        => 'nullable|string|max:20',
            'alamat'         => 'nullable|string',
            'bimba_unit'     => 'nullable|string|max:100',
            'no_cabang'      => 'nullable|string|max:10',
            'tgl_mulai'      => 'nullable|date',
            'guru_trial'     => 'nullable|string|max:255',
            'info'           => 'nullable|string',
            'tanggal_aktif'  => 'nullable|date', // hanya untuk status aktif
            'status_trial'   => 'required|in:aktif,batal,lanjut_daftar,baru',
        ]);

        if (empty($data['usia']) && $data['tgl_lahir']) {
            $data['usia'] = Carbon::parse($data['tgl_lahir'])->age;
        }

        // Hanya status AKTIF yang boleh punya tanggal_aktif
        if ($data['status_trial'] === 'aktif') {
            $data['tanggal_aktif'] = $request->filled('tanggal_aktif')
                ? $request->tanggal_aktif
                : now()->format('Y-m-d');
        } else {
            $data['tanggal_aktif'] = null;
        }

        $murid_trial->update($data);

        $result = $this->processStatusPromotion($murid_trial);

        if (($result['action'] ?? null) === 'registration_create') {
            return redirect()->route('registrations.create', $result['params'])
                ->with($result['type'], $result['message']);
        }

        return redirect()->route('murid_trials.index')
            ->with($result['type'] ?? 'success', $result['message'] ?? 'Data berhasil diperbarui.');
    }

    public function updateStatus(Request $request, MuridTrial $murid_trial)
{
    $request->validate([
        'status_trial'       => 'required|in:daftar_baru,baru,aktif,batal,lanjut_daftar',
        'tanggal_aktif'      => 'nullable|date',
        'tanggal_trial_baru' => 'nullable|date',   // ← wajib ditambahkan
    ]);

    $statusBaru = $request->status_trial;

    $updateData = [
        'status_trial' => $statusBaru,
    ];

    // ----------------------------------------------------
    // CASE KHUSUS: status 'aktif'
    // ----------------------------------------------------
    if ($statusBaru === 'aktif') {
        if ($murid_trial->tanggal_aktif) {
            return back()->with('warning', 'Tanggal aktif sudah diatur sebelumnya dan tidak dapat diubah lagi.');
        }

        $updateData['tanggal_aktif'] = $request->filled('tanggal_aktif')
            ? $request->tanggal_aktif
            : now()->format('Y-m-d');

        // Optional: reset tanggal trial baru jika berubah ke aktif
        $updateData['tanggal_trial_baru'] = null;
    }

    // ----------------------------------------------------
    // CASE KHUSUS: status 'baru'
    // ----------------------------------------------------
    elseif ($statusBaru === 'baru') {
        if ($murid_trial->tanggal_trial_baru) {
            return back()->with('warning', 'Tanggal trial baru sudah diatur sebelumnya dan tidak dapat diubah lagi.');
        }

        $updateData['tanggal_trial_baru'] = $request->filled('tanggal_trial_baru')
            ? $request->tanggal_trial_baru
            : now()->format('Y-m-d');

        // Optional: reset tanggal aktif jika berubah ke 'baru'
        $updateData['tanggal_aktif'] = null;
    }

    // ----------------------------------------------------
    // Status lain (daftar_baru, lanjut_daftar, batal)
    // ----------------------------------------------------
    else {
        // Reset kedua tanggal khusus
        $updateData['tanggal_aktif']      = null;
        $updateData['tanggal_trial_baru'] = null;
    }

    // Lakukan update sekali saja
    $murid_trial->update($updateData);

    // Proses promotion / lanjut daftar jika diperlukan
    $result = $this->processStatusPromotion($murid_trial);

    if (($result['action'] ?? null) === 'registration_create') {
        return redirect()->route('registrations.create', $result['params'])
            ->with($result['type'], $result['message']);
    }

    // Pesan sukses yang lebih deskriptif (opsional tapi membantu debugging)
    $message = match ($statusBaru) {
        'aktif'         => 'Status diubah menjadi AKTIF dan tanggal aktif telah disimpan.',
        'baru'          => 'Status diubah menjadi TRIAL BARU dan tanggal trial telah disimpan.',
        'lanjut_daftar' => 'Status diubah menjadi LANJUT DAFTAR.',
        'batal'         => 'Status diubah menjadi BATAL.',
        default         => 'Status berhasil diubah menjadi ' . ucfirst($statusBaru) . '.',
    };

    return redirect()->route('murid_trials.index')
        ->with('success', $message);
}

    protected function processStatusPromotion(MuridTrial $murid_trial): array
    {
        // LANJUT DAFTAR → buka form pendaftaran
        if ($murid_trial->status_trial === 'lanjut_daftar') {
            $student = $murid_trial->student ?? $this->ensureStudentFor($murid_trial);

            ParentCommitment::updateOrCreate(
                ['murid_trial_id' => $murid_trial->id],
                [
                    'parent_name' => $murid_trial->orangtua ?: 'Orang Tua',
                    'child_name'  => $murid_trial->nama,
                    'phone'       => $murid_trial->no_telp,
                    'address'     => $murid_trial->alamat,
                    'agreed'      => true,
                    'signed_at'   => now(),
                    'student_id'  => $student->id,
                ]
            );

            return [
                'type'    => 'success',
                'message' => "Membuka form pendaftaran untuk {$student->nama}...",
                'action'  => 'registration_create',
                'params'  => [
                    'student_id'   => $student->id,
                    'tahun_ajaran' => Registration::currentAcademicYear(),
                    'from_trial'   => true,
                ],
            ];
        }

        // STATUS BARU → hanya penanda sudah diambil (TIDAK ada tanggal)
        if ($murid_trial->status_trial === 'baru') {
            $this->ensureStudentFor($murid_trial);
            return [
                'type'    => 'success',
                'message' => 'Status: BARU – Murid sudah diambil menjadi murid tetap.',
            ];
        }

        // STATUS AKTIF → punya tanggal aktif
        if ($murid_trial->status_trial === 'aktif') {
            $this->ensureStudentFor($murid_trial);
            $tgl = $murid_trial->tanggal_aktif?->format('d-m-Y') ?? 'hari ini';
            return [
                'type'    => 'info',
                'message' => "Trial AKTIF sejak: {$tgl}",
            ];
        }

        // BATAL
        if ($murid_trial->status_trial === 'batal') {
            if ($murid_trial->student) {
                $murid_trial->student->registrations()
                    ->where('tahun_ajaran', Registration::currentAcademicYear())
                    ->update(['status' => 'rejected']);
            }
            return ['type' => 'warning', 'message' => 'Status: BATAL.'];
        }

        return ['type' => 'success', 'message' => 'Status diperbarui.'];
    }

    protected function ensureStudentFor(MuridTrial $murid_trial): Student
    {
        return DB::transaction(function () use ($murid_trial) {
            if ($student = $murid_trial->student) {
                return $student;
            }

            return Student::create([
                'murid_trial_id' => $murid_trial->id,
                'nama'           => $murid_trial->nama,
                'kelas'          => $murid_trial->kelas,
                'tgl_lahir'      => $murid_trial->tgl_lahir,
                'usia'           => $murid_trial->usia,
                'orangtua'       => $murid_trial->orangtua,
                'no_telp'        => $murid_trial->no_telp,
                'alamat'         => $murid_trial->alamat,
                'guru_wali'      => $murid_trial->guru_trial,
                'source'         => 'trial',
                'tanggal_masuk'  => $murid_trial->tgl_mulai,
                'bimba_unit'     => $murid_trial->bimba_unit,
                'no_cabang'      => $murid_trial->no_cabang,
                'nim'            => null, // SELALU NULL — tidak masuk buku induk
            ]);
        });
    }

    public function destroy(MuridTrial $murid_trial)
    {
        $murid_trial->delete();
        return redirect()->route('murid_trials.index')
            ->with('success', 'Data murid trial berhasil dihapus.');
    }

    public function updateGuru(Request $request, MuridTrial $muridTrial)
{
    // Validasi: boleh kosong (nullable), bukan required
    $validated = $request->validate([
        'guru_trial' => 'nullable|string|max:255',  // ← ubah ke nullable
    ]);

    // Ambil nilai dengan aman (default null jika tidak ada)
    $guruBaru = $request->input('guru_trial');  // atau $validated['guru_trial'] ?? null

    // Jika dikirim string kosong → jadikan null
    if ($guruBaru === '') {
        $guruBaru = null;
    }

    // Update
    $muridTrial->update([
        'guru_trial' => $guruBaru,
    ]);

    $message = $guruBaru 
        ? "Guru trial diperbarui menjadi: {$guruBaru}"
        : 'Guru trial berhasil dikosongkan';

    return back()->with('success', $message);
}

    public function searchAjax(Request $request)
    {
        $term = trim((string) $request->get('q', ''));
        $unit = $request->get('unit');

        $query = MuridTrial::with('student');

        if ($term !== '') {
            $query->where(fn($q) => $q
                ->where('nama', 'like', "%{$term}%")
                ->orWhere('no_telp', 'like', "%{$term}%")
                ->orWhereHas('student', fn($sq) => $sq->where('nama', 'like', "%{$term}%"))
            );
        }

        if ($unit) {
            $query->where('bimba_unit', $unit);
        }

        return response()->json(
            $query->take(30)->get(['id', 'nama', 'no_telp', 'bimba_unit'])
                  ->map(fn($r) => [
                      'id'         => $r->id,
                      'text'       => $r->nama,
                      'nama'       => $r->nama,
                      'no_telp'    => $r->no_telp,
                      'bimba_unit' => $r->bimba_unit,
                  ])
        );
    }

    public function show($id)
    {
        abort(404);
    }
    protected function autoActivateTrial()
{
    $batas = now()->subDay();

    $trials = MuridTrial::where('status_trial', 'baru')
        ->whereNull('tanggal_aktif')
        ->where('waktu_submit', '<=', $batas)
        ->get();

    foreach ($trials as $trial) {
        $trial->update([
            'status_trial'  => 'aktif',
            'tanggal_aktif' => \Carbon\Carbon::parse($trial->waktu_submit)->addDay(),
        ]);
    }
}

}