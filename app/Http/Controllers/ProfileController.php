<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use App\Models\Profile;
use App\Models\BukuInduk;
use App\Models\PenyesuaianRbGuru;
use App\Models\Ktr;
use App\Models\Unit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\ProfileExport;
use App\Imports\ProfileImport;

class ProfileController extends Controller
{
    use AuthorizesRequests;

    private $jabatanOptions = [
        'Kepala Unit', 'Asisten KU', 'Guru', 'Asisten Guru',
        'Staff Mobile', 'Admin', 'Bendahara', 'Satpam',
        'Office Boy', 'Office Girl'
    ];

    private $statusOptions = ['Aktif', 'Magang', 'Non-Aktif', 'Resign'];
    private $departemenOptions = ['biMBA-AIUEO', 'English biMBA', 'biMBA + English', 'Part Time English'];

    // ===================================================================
    // INDEX – Tampilkan daftar + hitung otomatis
    // ===================================================================

    public function index(Request $request)
{
    $search     = $request->filled('search') ? $request->input('search') : null;
    $unitFilter = $request->filled('unit') ? $request->input('unit') : null;

    // QUERY DASAR: Hanya jabatan yang diizinkan
    $baseQuery = Profile::whereIn('jabatan', $this->jabatanOptions);

    // DROPDOWN UNIT: Ambil semua unit unik
    $unitOptions = (clone $baseQuery)
        ->whereNotNull('biMBA_unit')
        ->where('biMBA_unit', '!=', '')
        ->distinct()
        ->orderBy('biMBA_unit')
        ->pluck('biMBA_unit')
        ->values();

    // DROPDOWN NAMA/NIK: DINAMIS — IKUT FILTER UNIT
    $profileOptionsQuery = (clone $baseQuery)
        ->select('id', 'nik', 'nama', 'biMBA_unit')
        ->orderBy('nama');

    if ($unitFilter) {
        $profileOptionsQuery->where('biMBA_unit', $unitFilter);
    }

    $profileOptions = $profileOptionsQuery->get();

    // QUERY UTAMA UNTUK TABEL — pakai data yang sudah tersimpan
    $query = (clone $baseQuery)
        ->withCount([
            'bukuIndukMba as jumlah_murid_mba_count',  // optional, hanya jika butuh real-time check
            'bukuInduk as total_murid_count'
        ]);

    if ($unitFilter) {
        $query->where('biMBA_unit', $unitFilter);
    }

    if ($search) {
        $query->where('id', $search);
    }

    $profiles = $query->orderBy('nama')->get();

    // === OPSI RB & KTR (ini ringan, biarkan tetap) ===
    $rbOptions = Ktr::where('waktu', 'like', 'RB%')
        ->orderByRaw("CAST(SUBSTRING(waktu, 3) AS UNSIGNED)")
        ->pluck('waktu')
        ->unique()
        ->values();

    $ktrOptions = Ktr::where('kategori', 'like', 'KTR%')
        ->orderByRaw("
            CAST(SUBSTRING(kategori, 5, INSTR(SUBSTRING(kategori, 5), ' ') - 1) AS UNSIGNED),
            SUBSTRING(kategori, INSTR(kategori, ' ') + 1)
        ")
        ->pluck('kategori')
        ->unique()
        ->values();

    // === HITUNG TOTAL MURID & ROMBIM PER DEPARTEMEN (untuk Kepala Unit) ===
    // Ini masih diperlukan, tapi bisa di-cache atau dijalankan jarang
    $deptAgg = Profile::query()
        ->where('jabatan', 'Guru')
        ->groupBy('departemen')
        ->selectRaw('TRIM(COALESCE(departemen, "")) as departemen, 
                     SUM(COALESCE(jumlah_murid_mba, 0)) as sum_murid, 
                     SUM(COALESCE(jumlah_rombim, 0)) as sum_rombim')
        ->get();

    $deptTotals = [];
    $deptRombimTotals = [];
    foreach ($deptAgg as $row) {
        $key = $row->departemen === '' ? null : $row->departemen;
        $deptTotals[$key] = (int) $row->sum_murid;
        $deptRombimTotals[$key] = (int) $row->sum_rombim;
    }

    // === HAPUS LOOP PERHITUNGAN BERAT INI ===
    // foreach ($profiles as $profile) { ... }  ← DIHAPUS atau dikomentari

    // Jika ingin tetap ada perhitungan ulang (misal data baru masuk), buat tombol refresh manual
    // atau panggil recalculateMuridDanKtr() hanya untuk profile tertentu via AJAX

    // === MENTOR UNTUK MAGANG ===
    $mentors = Profile::whereIn('jabatan', ['Guru', 'Kepala Unit'])
        ->orderBy('nama')
        ->get(['nama']);

    $adaMagang = (clone $baseQuery)
        ->when($unitFilter, fn($q) => $q->where('biMBA_unit', $unitFilter))
        ->when($search, fn($q) => $q->where('id', $search))
        ->whereNotNull('tgl_magang')
        ->exists();

    $adaNonAktif = (clone $baseQuery)
        ->whereNotNull('tgl_non_aktif')
        ->exists();

    $adaResign = (clone $baseQuery)
        ->whereNotNull('tgl_resign')
        ->exists();

    return view('profiles.index', compact(
        'profiles',
        'profileOptions',
        'unitOptions',
        'mentors',
        'rbOptions',
        'ktrOptions',
        'search',
        'unitFilter',
        'adaMagang',
        'adaNonAktif',
        'adaResign'
    ));
}
    // ===================================================================
    // CREATE & STORE
    // ===================================================================
public function create()
{
    $unitCollection = Unit::orderBy('no_cabang')->get();

    $units = $unitCollection->mapWithKeys(function ($unit) {
        $label = $unit->no_cabang
            ? $unit->no_cabang . ' - ' . $unit->biMBA_unit
            : $unit->biMBA_unit;
        return [$unit->biMBA_unit => $label];
    })->sort();

    $unitNoCabang = $unitCollection->pluck('no_cabang', 'biMBA_unit')->toArray();

    return view('profiles.create', [
        'jabatanOptions'    => $this->jabatanOptions,
        'statusOptions'     => $this->statusOptions,
        'departemenOptions' => $this->departemenOptions,
        'units'             => $units,
        'unitNoCabang'      => $unitNoCabang,
    ]);
}

   public function store(Request $request)
{
    $validated = $this->validateProfileRequest($request);
    //dd($validated);  // ← hapus atau comment ini setelah debug selesai
    $validated = array_map(fn($v) => $v === '' ? null : $v, $validated);

    // 🔥 Mapping otomatis
    $status = $validated['status_karyawan'] ?? null;

    if ($status === 'Magang' && !empty($validated['tgl_masuk'])) {
        $validated['tgl_magang'] = $validated['tgl_masuk'];
        $validated['tgl_masuk'] = null;
    }

    $this->autoActivateMagangInArray($validated);

    if (($validated['jabatan'] ?? null) === 'Guru') {
        $this->calculateGuruCounts($validated);
    }

    $this->assignKtrAndRp($validated);

    $validated['rp'] = $validated['rp'] ? floatval($validated['rp']) : null;

    $profile = Profile::create($validated);

    $this->calculateAndSaveMasaKerja($profile);

    return redirect()->route('profiles.index')
        ->with('success', 'Profile berhasil ditambahkan');
}
    // ===================================================================
    // SHOW & EDIT
    // ===================================================================
    public function show(Profile $profile)
    {
        return view('profiles.show', compact('profile'));
    }

    // Di dalam method edit()
public function edit(Profile $profile)
{
    $this->recalculateProfileData($profile);

    // ───────────────────────────────────────────────
    // Ambil jabatan unik dari tabel skim
    // ───────────────────────────────────────────────
    $jabatanOptions = \App\Models\Skim::query()
        ->distinct()
        ->orderBy('jabatan')
        ->pluck('jabatan', 'jabatan')   // key = value (cocok untuk select)
        ->toArray();

    // kalau mau pakai pluck biasa (value saja)
    // $jabatanOptions = \App\Models\Skim::distinct()->orderBy('jabatan')->pluck('jabatan')->toArray();

    $statusOptions     = $this->statusOptions;
    $departemenOptions = $this->departemenOptions;

    // Ambil data unit sama seperti di create
    $unitCollection = Unit::orderBy('no_cabang')->get();

    $units = $unitCollection->mapWithKeys(function ($unit) {
        $label = $unit->no_cabang
            ? $unit->no_cabang . ' - ' . $unit->biMBA_unit
            : $unit->biMBA_unit;
        return [$unit->biMBA_unit => $label];
    })->sort();

    $unitNoCabang = $unitCollection->pluck('no_cabang', 'biMBA_unit')->toArray();

    // Ambil RB dan KTR options (sudah ada)
    $rbOptions = \App\Models\Ktr::where('waktu', 'like', 'RB%')
        ->orderByRaw("CAST(SUBSTRING(waktu, 3) AS UNSIGNED)")
        ->pluck('waktu')
        ->unique()
        ->values();

    $ktrOptions = \App\Models\Ktr::where('kategori', 'like', 'KTR%')
        ->orderByRaw("
            CAST(SUBSTRING(kategori, 5, INSTR(SUBSTRING(kategori, 5), ' ') - 1) AS UNSIGNED),
            SUBSTRING(kategori, INSTR(kategori, ' ') + 1)
        ")
        ->pluck('kategori')
        ->unique()
        ->values();

    return view('profiles.edit', compact(
        'profile',
        'jabatanOptions',
        'statusOptions',
        'departemenOptions',
        'units',          // ← tambahkan ini
        'unitNoCabang',   // ← tambahkan ini
        'rbOptions',
        'ktrOptions'
    ));
}

    // ===================================================================
    // UPDATE
    // ===================================================================
    public function update(Request $request, Profile $profile)
{
    $validated = $this->validateProfileRequest($request, $profile);

    $validated = array_map(fn($v) => $v === '' ? null : $v, $validated);

    $this->autoActivateMagangInArray($validated);

    if ($profile->jabatan === 'Guru' || ($validated['jabatan'] ?? null) === 'Guru') {
        $this->calculateGuruCounts($validated);
    }

    $this->assignKtrAndRp($validated);

    $validated['rp'] = $validated['rp'] ? floatval($validated['rp']) : null;

    // UPDATE PROFILE
    $profile->update($validated);

    // Rekalkulasi masa kerja umum (sudah ada)
    $this->calculateAndSaveMasaKerja($profile);

    // TAMBAHKAN: Hitung masa kerja jabatan
    $this->calculateAndSaveMasaKerjaJabatan($profile);   // ← baris baru ini

    // Rekap imbalan
    $labelBulan = Carbon::now()->locale('id')->translatedFormat('F Y');
    app(ImbalanRekapController::class)->createRekapsForPeriode($labelBulan);

    return redirect()->route('profiles.index')
        ->with('success', 'Profile berhasil diperbarui dan rekap imbalan telah disinkronkan');
}

/**
 * Hitung masa kerja jabatan dalam bulan dari tgl_mutasi_jabatan sampai hari ini
 * dan simpan ke kolom masa_kerja_jabatan
 */
protected function calculateAndSaveMasaKerjaJabatan(Profile $profile)
{
    if (!$profile->tgl_mutasi_jabatan) {
        $profile->masa_kerja_jabatan = 0;
        $profile->saveQuietly();
        return;
    }

    // Pastikan jadi Carbon instance
    $start = Carbon::parse($profile->tgl_mutasi_jabatan);
    $today = Carbon::today();  // tanpa jam, lebih aman & konsisten

    if ($start->isFuture()) {
        $profile->masa_kerja_jabatan = 0;
        $profile->saveQuietly();
        return;
    }

    // Hitung bulan penuh
    $months = $start->diffInMonths($today);

    // Cek sisa hari di bulan terakhir (pembulatan umum: >=15 hari → +1 bulan)
    $startNextMonth = $start->copy()->addMonths($months);
    $remainingDays = $today->diffInDays($startNextMonth);

    if ($remainingDays >= 15) {
        $months++;
    }

    $profile->masa_kerja_jabatan = max(0, $months);
    $profile->saveQuietly();  // gunakan ini kalau ada observer
    // atau $profile->save() kalau tidak masalah
}

    // ===================================================================
    // DESTROY
    // ===================================================================
    public function destroy(Profile $profile)
    {
        $profile->delete();
        return redirect()->route('profiles.index')->with('success', 'Profile berhasil dihapus');
    }

    // ===================================================================
    // AJAX: INLINE UPDATE RB
    // ===================================================================
    public function inlineUpdate(Request $request, Profile $profile)
    {
        if (!Auth::check()) {
            return response()->json(['status' => 'error', 'message' => 'Unauthorized'], 401);
        }

        $validator = Validator::make($request->only('rb'), ['rb' => 'nullable|string|max:10']);
        if ($validator->fails()) {
            return response()->json(['status' => 'error', 'errors' => $validator->errors()], 422);
        }

        $raw = trim($request->rb ?? '');
        $rbLabel = $raw === '' ? null : $this->normalizeRbLabel($raw);

        // === CEK APAKAH RB ADA DI TABEL KTR ===
        $isValidRb = $rbLabel ? Ktr::where('waktu', $rbLabel)->exists() : true;

        if ($raw !== '' && !$isValidRb) {
            // RB tidak valid → simpan, tapi RP jadi null
            $profile->rb = $rbLabel;
            $profile->rb_tambahan = $rbLabel;
            $profile->ktr = null;
            $profile->ktr_tambahan = null;
            $profile->rp = null;
            $profile->jumlah_rombim = null;
            $profile->save();

            return response()->json([
                'status' => 'ok',
                'profile' => [
                    'id'             => $profile->id,
                    'rb'             => $profile->rb,
                    'rb_calc'        => $profile->rb_tambahan,
                    'ktr'            => $profile->ktr,
                    'ktr_tambahan'   => $profile->ktr_tambahan,
                    'jumlah_rombim'  => $profile->jumlah_rombim,
                    'rp'             => null,
                ]
            ]);
        }

        // === RB VALID → HITUNG SEMUA ===
        $jumlahMurid = $this->getJumlahMuridForProfile($profile);

        $profile->rb = $rbLabel;
        $profile->rb_tambahan = $rbLabel;

        $ktrFromRb = $rbLabel ? $this->resolveKtrFromRb($rbLabel) : null;

        if ($profile->jabatan === 'Guru') {
            $profile->ktr = $ktrFromRb ?? $this->formatKtrFromCountForGuru($jumlahMurid);
            $profile->ktr_tambahan = $profile->ktr;
            $profile->jumlah_rombim = $this->resolveSlotRombimFromCount($jumlahMurid);
        } elseif ($profile->jabatan === 'Kepala Unit') {
            $profile->ktr_tambahan = $ktrFromRb ?? $this->formatKtrFromCountForKepala($jumlahMurid);
            $profile->ktr = null;
        }

        $priorityKtr = $profile->jabatan === 'Guru' ? $profile->ktr : $profile->ktr_tambahan;
        $profile->rp = $this->resolveRpFromJumlahMuridWithKtr($jumlahMurid, $priorityKtr, $rbLabel);

        $profile->save();
        $labelBulan = Carbon::now()->locale('id')->translatedFormat('F Y');
    app(ImbalanRekapController::class)->createRekapsForPeriode($labelBulan);

        return response()->json([
    'status' => 'ok',
    'profile' => [
        'rb'               => $profile->rb,
        'rb_tambahan'      => $profile->rb_tambahan,
        'ktr'              => $profile->ktr,
        'ktr_tambahan'     => $profile->ktr_tambahan,
        'rp'               => $profile->rp,
        'jumlah_rombim'    => $profile->jumlah_rombim,
        'jumlah_murid_jadwal' => $profile->jumlah_murid_jadwal, // jika perlu
    ]
]);
    }

    // ===================================================================
    // AJAX: INLINE UPDATE KTR
    // ===================================================================
   public function inlineUpdateKtr(Request $request, Profile $profile)
{
    $request->validate(['ktr' => 'nullable|string|max:10']);

    $inputKtr = $request->filled('ktr') ? trim($request->ktr) : null;

    // Jika user pilih "Otomatis" atau kosong → reset ke nilai otomatis
    if ($inputKtr === '' || $inputKtr === 'Otomatis' || $inputKtr === null) {
        // Reset manual
        $profile->ktr_tambahan = null;

        // Hitung ulang otomatis (panggil fungsi calculate biar konsisten)
        $this->calculateKepalaUnitData($profile);

        // KTR sekarang sudah otomatis di $profile->ktr
        $ktrFinal = $profile->ktr;
    } else {
        // Input manual → simpan ke tambahan dan ktr
        $isValidKtr = Ktr::where('kategori', $inputKtr)->exists();

        if ($isValidKtr) {
            $profile->ktr_tambahan = $inputKtr;
            $profile->ktr = $inputKtr;

            // Hitung RP berdasarkan manual
            $jumlahMurid = $this->getJumlahMuridForProfile($profile);
            $profile->rp = $this->resolveRpFromJumlahMuridWithKtr($jumlahMurid, $inputKtr, $profile->rb);
        } else {
            // Invalid → RP null
            $profile->rp = null;
        }

        $ktrFinal = $inputKtr;
    }

    $profile->save();

    // Rekap imbalan
    $labelBulan = Carbon::now()->locale('id')->translatedFormat('F Y');
    app(ImbalanRekapController::class)->createRekapsForPeriode($labelBulan);

    return response()->json([
        'status' => 'ok',
        'profile' => [
            'rb'               => $profile->rb,
            'rb_tambahan'      => $profile->rb_tambahan,
            'ktr'              => $profile->ktr,             // otomatis atau manual
            'ktr_tambahan'     => $profile->ktr_tambahan,   // manual atau null
            'rp'               => $profile->rp,
            'jumlah_rombim'    => $profile->jumlah_rombim,
            'jumlah_murid_jadwal' => $profile->jumlah_murid_jadwal,
        ]
    ]);
}

    // ===================================================================
    // HELPER: VALIDASI REQUEST
    // ===================================================================
    private function validateProfileRequest(Request $request, Profile $profile = null): array
{
    $ignoreId = $profile?->id ?? 'NULL';

    return $request->validate([
        'nik'               => "required|string|max:20|unique:profiles,nik,{$ignoreId}",
        'nama'              => 'required|string|max:100',
        'jabatan'           => 'nullable|string',
        'status_karyawan'   => 'nullable|string',
        'departemen'        => 'nullable|string',

        // INI YANG WAJIB DITAMBAHKAN — BIAR MASUK KE DATABASE
        'biMBA_unit'        => 'required|string|max:100',        // WAJIB & disimpan
        'no_cabang'         => 'nullable|string|max:20',         // Opsional, otomatis dari JS

        'tgl_masuk'         => 'nullable|date',
        'tgl_lahir'         => 'nullable|date',
        'tgl_magang'        => 'nullable|date',
        'tgl_non_aktif'     => 'nullable|date',
        'tgl_resign'        => 'nullable|date',

        // Field guru (hanya divalidasi jika jabatan Guru)
        'jumlah_murid_mba'  => 'nullable|integer|min:0',
        'jumlah_murid_eng'  => 'nullable|integer|min:0',
        'rb'                => 'nullable|string',
        'ktr'               => 'nullable|string',
        'rp'                => 'nullable|numeric',

        // Kontak & bank
        'no_telp'           => 'nullable|string',
        'email'             => 'nullable|email',
        'no_rekening'       => 'nullable|string',
        'bank'              => 'nullable|string',
        'atas_nama'         => 'nullable|string',

        // Mutasi & lainnya
        'jenis_mutasi'      => 'nullable|string',
        'tgl_mutasi_jabatan'=> 'nullable|date',
        'masa_kerja_jabatan'=> 'nullable|integer',

        'ukuran'            => 'nullable|string',
        'status_lain' => 'nullable|string|in:Belum Terima,Sudah Terima',
        'keterangan'        => 'nullable|string',

        // TAMBAHKAN 5 KOLOM SERAGAM BARU INI:
        'tgl_ambil_seragam'      => 'nullable|date',
        'kaos_kuning_hitam'        => 'nullable|in:S,M,L,XL,XXL',
        'kaos_merah_kuning_biru'   => 'nullable|in:S,M,L,XL,XXL',
        'kemeja_kuning_hitam'      => 'nullable|in:S,M,L,XL,XXL',
        'blazer_merah'             => 'nullable|in:S,M,L,XL,XXL',
        'blazer_biru'              => 'nullable|in:S,M,L,XL,XXL',
    ]);
}

    // ===================================================================
    // AUTO ACTIVATE MAGANG – DINONAKTIFKAN
    // ===================================================================
    private function autoActivateMagangIfNeeded(Profile $profile): void
    {
        // Logika 30 hari dihapus.
        // Status Magang hanya akan diubah jika tgl_selesai_magang diisi via inlineUpdateField.
        return;
    }

    private function autoActivateMagangInArray(array &$data): void
    {
        // Logika otomatis Magang -> Aktif berdasarkan tgl_masuk (30 hari)
        // sudah dihapus/dinonaktifkan. Tidak ada perubahan status di sini.
        return;
    }

    // ===================================================================
    // HITUNG JUMLAH MURID
    // ===================================================================
    private function calculateGuruCounts(array &$data): void
    {
        $nama = $data['nama'];
        $data['jumlah_murid_mba'] = $this->getJumlahMuridMBA($nama);
        $data['total_murid'] = BukuInduk::where('guru', $nama)->count();
    }

    private function getJumlahMuridForProfile(Profile $profile): int
    {
        return $profile->jabatan === 'Guru'
            ? (int) ($profile->jumlah_murid_jadwal ?? $profile->jumlah_murid_mba ?? $this->getJumlahMuridMBA($profile->nama))
            : BukuInduk::whereIn('guru', function ($q) use ($profile) {
                $q->select('nama')->from('profiles')
                    ->where('departemen', $profile->departemen)
                    ->where('jabatan', 'Guru');
            })->count();
    }

    private function getJumlahMuridMBA(string $namaGuru): int
    {
        $possibleCols = ['program', 'departemen', 'kelas', 'jenis_program', 'paket'];
        $column = collect($possibleCols)->first(fn($col) => Schema::hasColumn('buku_induk', $col));

        $query = BukuInduk::where('guru', $namaGuru);
        if ($column) {
            $query->where($column, 'like', '%MBA%');
        }
        return $query->count();
    }

    // ===================================================================
    // PERHITUNGAN GURU & KEPALA UNIT
    // ===================================================================
    private function calculateGuruData(Profile $profile): void
{
    // === INI YANG HARUS DITAMBAHKAN: HITUNG ULANG DARI BUKU INDUK! ===
    $jumlahMuridMba = $this->getJumlahMuridMBA($profile->nama);
    $profile->jumlah_murid_mba = $jumlahMuridMba;

    $jadwal = (int) $jumlahMuridMba;  // sekarang pakai nilai REAL dari Buku Induk

    $profile->jumlah_murid_jadwal = $jadwal > 0 ? $jadwal : null;
    $profile->jumlah_rombim       = $this->resolveSlotRombimFromCount($jadwal);

    if ($jadwal === 0) {
        $profile->rb = 'RB30';
        $profile->ktr = 'KTR 1A';
    } else {
        $rbDariSistem = $this->resolveRbFromCount($jadwal);
        $rbSekarang   = $profile->rb ? (int) preg_replace('/\D/', '', $profile->rb) : null;

        if ($rbSekarang !== $rbDariSistem && $rbDariSistem !== null) {
            $profile->rb = 'RB' . $rbDariSistem;
        }

        if (empty($profile->rb) && $rbDariSistem !== null) {
            $profile->rb = 'RB' . $rbDariSistem;
        }

        if (empty($profile->ktr)) {
            $profile->ktr = $this->formatKtrFromCountForGuru($jadwal);
        }
    }

    $profile->rb_tambahan  = $profile->rb;
    $profile->ktr_tambahan = $profile->ktr;
    $profile->rp = $this->resolveRpFromJumlahMuridWithKtr($jadwal, $profile->ktr, $profile->rb);

    // === TAMBAHKAN SAVE BIAR DATA TERSIMPAN PERMANEN ===
    $profile->save();
}

   private function calculateKepalaUnitData(Profile $profile): void
{
    // Status aktif
    $statusAktif = ['Aktif', 'aktif', 'AKTIF'];

    // Hitung murid bawahan
    $muridBawahan = BukuInduk::whereIn('guru', function ($q) use ($profile) {
        $q->select('nama')->from('profiles')
            ->where('biMBA_unit', $profile->biMBA_unit)
            ->where('jabatan', 'Guru')
            ->where('status_karyawan', 'Aktif');
    })
    ->whereIn('status', $statusAktif)
    ->count();

    // Hitung murid pribadi
    $muridPribadi = BukuInduk::where('guru', $profile->nama)
                              ->whereIn('status', $statusAktif)
                              ->count();

    $totalEfektif = $muridBawahan + $muridPribadi;

    // Simpan nilai dasar
    $profile->total_murid_bawahan = $muridBawahan;
    $profile->jumlah_murid_mba    = $muridPribadi;
    $profile->jumlah_murid_jadwal = $totalEfektif > 0 ? $totalEfektif : null;

    // RB dikunci
    if (empty(trim($profile->rb ?? ''))) {
        $profile->rb = 'RB40';
    }

    // Hitung KTR otomatis
    $ktrOtomatis = $this->formatKtrFromCountForKepala($totalEfektif);

// Minimal KTR untuk Kepala Unit adalah 2A
$ktrList = [
    'KTR 1B',
    'KTR 2A',
    'KTR 2B',
    'KTR 3A',
    'KTR 3B',
    'KTR 4A',
    'KTR 4B',
    'KTR 5A',
    'KTR 5B',
    'KTR 6A',
    'KTR 6B',
    'KTR 7A',
    'KTR 7B',
    'KTR 8A',
];

if (array_search($ktrOtomatis, $ktrList) < array_search('KTR 2A', $ktrList)) {
    $ktrOtomatis = 'KTR 2A';
}

    // KTR: prioritaskan manual di ktr_tambahan, jika tidak ada pakai otomatis
    if (!empty(trim($profile->ktr_tambahan ?? ''))) {
        $profile->ktr = $profile->ktr_tambahan; // pakai manual
    } else {
        $profile->ktr = $ktrOtomatis; // pakai otomatis
        // JANGAN isi ktr_tambahan → biarkan kosong supaya bisa diisi manual kapan saja
    }

    // Field tambahan
    $profile->rb_tambahan = $profile->rb;

    // RP pakai prioritas manual jika ada
    $finalKtr = !empty(trim($profile->ktr_tambahan ?? '')) ? $profile->ktr_tambahan : $profile->ktr;

    $profile->rp = $this->resolveRpFromJumlahMuridWithKtr(
        $totalEfektif,
        $finalKtr,
        $profile->rb
    );

    // Simpan
    $profile->save();
}

    private function recalculateProfileData(Profile $profile): void
    {
        $profile->jumlah_murid_mba = $this->getJumlahMuridMBA($profile->nama);
        $profile->total_murid = BukuInduk::where('guru', $profile->nama)->count();

        if ($profile->jabatan === 'Guru') {
            $jadwal = (int) ($profile->jumlah_murid_mba ?? 0);
            $profile->jumlah_murid_jadwal = $jadwal > 0 ? $jadwal : null;
            $profile->jumlah_rombim = $this->resolveSlotRombimFromCount($jadwal);
            $ktr = $profile->rb ? $this->resolveKtrFromRb($profile->rb) : null;
            $profile->rp = $this->resolveRpFromJumlahMuridWithKtr($jadwal, $ktr, $profile->rb);
        }

        if ($profile->jabatan === 'Kepala Unit') {
            $total = BukuInduk::whereIn('guru', function ($q) use ($profile) {
                $q->select('nama')->from('profiles')
                    ->where('departemen', $profile->departemen)
                    ->where('jabatan', 'Guru');
            })->count();

            $profile->total_murid_bawahan = $total;

            if (empty($profile->ktr_tambahan) && $total > 0) {
                $profile->ktr_tambahan = $this->formatKtrFromCountForKepala($total);
            }
            $profile->rp = $this->resolveRpFromJumlahMuridWithKtr($total, $profile->ktr_tambahan);
        }

        $profile->rb_tambahan = $profile->rb;
        $profile->ktr_tambahan = $profile->ktr;
    }

    // ===================================================================
    // ASSIGN KTR & RP (UNTUK STORE/UPDATE)
    // ===================================================================
    private function assignKtrAndRp(array &$data): void
{
    $jabatan        = $data['jabatan'] ?? null;
    $murid          = (int) ($data['jumlah_murid_jadwal'] ?? $data['jumlah_murid_mba'] ?? 0);
    $total          = (int) ($data['total_murid'] ?? $data['total_murid_bawahan'] ?? 0);
    $masaKerjaBulan = (int) ($data['masa_kerja'] ?? 0);
    $status         = $data['status_karyawan'] ?? 'Aktif'; // default Aktif jika kosong

    $hasManualRb           = !empty(trim($data['rb'] ?? ''));
    $hasManualKtr          = !empty(trim($data['ktr'] ?? ''));
    $hasManualKtrTambahan  = !empty(trim($data['ktr_tambahan'] ?? ''));
    $hasManualRp           = isset($data['rp']) && $data['rp'] !== null;

    // Reset default
    $data['ktr']           = null;
    $data['ktr_tambahan']  = null;
    $data['rp']            = null;
    $data['rb']            = null;
    $data['jumlah_rombim'] = null;

    // Logika khusus Guru & Kepala Unit (tetap seperti semula)
    if ($jabatan === 'Guru') {
        if ($murid <= 0) {
            $data['ktr']           = $hasManualKtr ? $data['ktr'] : null;
            $data['ktr_tambahan']  = $hasManualKtrTambahan ? $data['ktr_tambahan'] : null;
            $data['rp']            = $hasManualRp ? floatval($data['rp']) : null;
        } else {
            if (!$hasManualRb) {
                $rb = $this->resolveRbFromCount($murid);
                $data['rb'] = $rb ? 'RB' . $rb : null;
            }

            $rbNormalized = $this->normalizeRbValue($data['rb']);
            if (!$hasManualKtr) {
                $data['ktr'] = $rbNormalized
                    ? $this->resolveKtrFromRb("RB{$rbNormalized}")
                    : $this->formatKtrFromCountForGuru($murid);
            }

            if (!$hasManualKtrTambahan) {
                $data['ktr_tambahan'] = $data['ktr'];
            }

            if (!$hasManualRp) {
                $data['rp'] = $this->resolveRpFromJumlahMuridWithKtr(
                    $murid,
                    $data['ktr_tambahan'] ?? $data['ktr'],
                    $data['rb']
                );
            }

            $data['jumlah_rombim'] = $this->resolveSlotRombimFromCount($murid);
        }
    } elseif ($jabatan === 'Kepala Unit') {
        $jumlahEfektif = $total > 0 ? $total : $murid;

        if (!$hasManualRb) {
            $data['rb'] = 'RB40';
        }

        if (!$hasManualKtrTambahan) {
            $data['ktr_tambahan'] = $this->formatKtrFromCountForKepala($jumlahEfektif) ?? 'KTR 1B';
        }

        if (!$hasManualRp) {
            $data['rp'] = $this->resolveRpFromJumlahMuridWithKtr(
                $jumlahEfektif,
                $data['ktr_tambahan'],
                $data['rb']
            );
        }
    } else {
        // Jabatan lain: ambil RP dari tabel skim berdasarkan jabatan + masa_kerja + status

        // Tentukan range masa kerja
        $range = $masaKerjaBulan >= 24 ? '>= 24 Bulan' : '< 24 Bulan';

        // Query skim utama (pakai status asli, termasuk Magang)
        $skim = \DB::table('skim')
            ->where('jabatan', $jabatan)
            ->where('masa_kerja', $range)
            ->where('status', $status)
            ->first();

        // Jika tidak ditemukan skim khusus untuk status ini, fallback ke status 'Aktif'
        if (!$skim) {
            $skim = \DB::table('skim')
                ->where('jabatan', $jabatan)
                ->where('masa_kerja', $range)
                ->where('status', 'Aktif')
                ->first();
        }

        // Ambil nilai RP dari kolom 'thp' (nilai utama skim kamu)
        $data['rp'] = $skim ? (float) $skim->thp : 0;

        // Jika manual RP ada → prioritaskan
        if ($hasManualRp) {
            $data['rp'] = floatval($data['rp']);
        }
    }
}

    // ===================================================================
    // RB, KTR, RP HELPERS
    // ===================================================================
    private function normalizeRbLabel(string $raw): ?string
    {
        return preg_match('/(\d{1,3})/', $raw, $m) ? 'RB' . $m[1] : null;
    }

    private function resolveRbFromCount(int $count): ?int
{
    if (!class_exists(PenyesuaianRbGuru::class)) {
        return $count >= 36 ? 40
            : ($count >= 31 ? 35
            : ($count >= 26 ? 30
            : ($count >= 21 ? 25 : null)));
    }

    $mappings = PenyesuaianRbGuru::orderBy('id')->get();
    if ($mappings->isEmpty()) {
        return null; // atau fallback ke aturan lama kalau mau
    }

    foreach ($mappings as $map) {
        [$min, $max] = $this->parseRangeString($map->jumlah_murid) ?: [0, 0];
        if ($count >= $min && $count <= $max) {
            return $this->normalizeRbValue($map->penyesuaian_rb); // 0–25 → 30, 26–35 → 35, dll
        }
    }

    // Kalau jumlah murid di atas range tertinggi (misal 50), pakai yang tertinggi
    $highest = $mappings->sortByDesc(function ($map) {
        [$min, $max] = $this->parseRangeString($map->jumlah_murid) ?: [0, 0];
        return $max;
    })->first();

    return $highest ? $this->normalizeRbValue($highest->penyesuaian_rb) : null;
}

    private function resolveSlotRombimFromCount(int $count): ?int
{
    if (!class_exists(PenyesuaianRbGuru::class)) {
        return $count > 0 ? (int) ceil($count / 8) : null;
    }

    $mappings = PenyesuaianRbGuru::orderBy('id')->get();
    if ($mappings->isEmpty()) {
        return $count > 0 ? (int) ceil($count / 8) : null;
    }

    foreach ($mappings as $map) {
        [$min, $max] = $this->parseRangeString($map->jumlah_murid) ?: [0, 0];
        if ($count >= $min && $count <= $max) {
            return $this->normalizeSlotRombimValue($map->slot_rombim); // 0–25 → 8
        }
    }

    // Kalau di atas range tertinggi → pakai yang tertinggi
    $highest = $mappings->sortByDesc(function ($map) {
        [$min, $max] = $this->parseRangeString($map->jumlah_murid) ?: [0, 0];
        return $max;
    })->first();

    return $highest ? $this->normalizeSlotRombimValue($highest->slot_rombim) : null;
}

    private function resolveKtrFromRb(?string $rbLabel): ?string
    {
        if (!$rbLabel) return null;
        $rb = (int) preg_replace('/\D/', '', $rbLabel);
        if ($rb >= 1 && $rb <= 30) return 'KTR 1A';
        if ($rb >= 31 && $rb <= 40) return 'KTR 2A';
        if ($rb >= 41 && $rb <= 60) return 'KTR 2B';
        if ($rb > 60) return 'KTR 3A';
        return null;
    }

    private function formatKtrFromCountForGuru(int $count): ?string
    {
        if ($count >= 1 && $count <= 30) return 'KTR 1A';
        if ($count >= 31 && $count <= 40) return 'KTR 2A';
        if ($count >= 41 && $count <= 60) return 'KTR 2B';
        if ($count > 60) return 'KTR 3A';
        return null;
    }

    private function formatKtrFromCountForKepala(int $count): ?string
{
    if ($count <= 0) return null;  // atau 'KTR 1B' jika ingin minimal

    if ($count <= 25)   return 'KTR 1B';
    if ($count <= 75)   return 'KTR 2A';
    if ($count <= 100)  return 'KTR 2B';
    if ($count <= 125)  return 'KTR 3A';
    if ($count <= 150)  return 'KTR 3B';
    if ($count <= 175)  return 'KTR 4A';
    if ($count <= 200)  return 'KTR 4B';
    if ($count <= 225)  return 'KTR 5A';
    if ($count <= 250)  return 'KTR 5B';
    if ($count <= 275)  return 'KTR 6A';
    if ($count <= 300)  return 'KTR 6B';
    if ($count <= 325)  return 'KTR 7A';
    if ($count <= 350)  return 'KTR 7B';
    if ($count <= 375)  return 'KTR 8A';
    if ($count <= 400)  return 'KTR 8B';
    if ($count <= 425)  return 'KTR 9A';
    if ($count <= 450)  return 'KTR 9B';
    if ($count <= 475)  return 'KTR 10A';

    // > 475 (maksimal 500 atau lebih)
    return 'KTR 10B';
}

    private function resolveRpFromJumlahMuridWithKtr(
        int $jumlahMurid,
        ?string $forcedKtr = null,
        ?string $forcedRbLabel = null
    ): ?float {
        if (!class_exists(Ktr::class)) return null;

        $rbLabel = $forcedRbLabel
            ?? ($jumlahMurid > 0
                ? ($this->resolveRbFromCount($jumlahMurid)
                    ? 'RB' . $this->resolveRbFromCount($jumlahMurid)
                    : null)
                : null);

        $ktrPriority = $forcedKtr ?? ($rbLabel ? $this->resolveKtrFromRb($rbLabel) : null);

        if ($rbLabel) {
            $rbNumber = (int) preg_replace('/\D/', '', $rbLabel);
            $likeKey = "%RB" . ltrim((string) $rbNumber, '0') . "%";
            $rows = Ktr::where('waktu', 'like', $likeKey)->orderBy('id')->get();

            if ($rows->isNotEmpty()) {
                $norm = fn($s) => $s ? preg_replace('/[^A-Z0-9]/', '', strtoupper(trim($s))) : null;
                $targetNorm = $norm($ktrPriority);
                $targetShort = $targetNorm && preg_match('/(\d+[A-Z]?)$/', $targetNorm, $m) ? $m[1] : null;

                foreach ($rows as $r) {
                    if ($norm($r->kategori ?? '') === $targetNorm) {
                        $v = $this->extractRpFromRow($r);
                        if ($v !== null) return $v;
                    }
                }

                if ($targetShort) {
                    foreach ($rows as $r) {
                        if (stripos($norm($r->kategori ?? ''), $targetShort) !== false) {
                            $v = $this->extractRpFromRow($r);
                            if ($v !== null) return $v;
                        }
                    }
                }

                foreach ($rows as $r) {
                    $v = $this->extractRpFromRow($r);
                    if ($v !== null) return $v;
                }
            }
        }

        $mappings = Ktr::orderBy('id')->get();
        if ($mappings->isEmpty()) return null;

        foreach ($this->parseRangeMappings($mappings, 'waktu') as $p) {
            if ($jumlahMurid >= $p['min'] && $jumlahMurid <= $p['max']) {
                return $this->extractRpFromRow($p['map']);
            }
        }

        return null;
    }

    private function parseRangeString(?string $s): ?array
    {
        if (
            !$s
            || !preg_match(
                '/^(\d+)-(\d+)$/',
                preg_replace('/[-\x{2013}\x{2014}\x{2212}]/u', '-', trim($s)),
                $m
            )
        ) {
            return null;
        }
        $min = (int) $m[1];
        $max = (int) $m[2];
        return [$min > $max ? $max : $min, $min > $max ? $min : $max];
    }

    private function parseRangeMappings($mappings, $column)
    {
        $parsed = [];
        foreach ($mappings as $map) {
            if (!preg_match('/RB\s*0*?(\d{1,3})/i', strtoupper(trim($map->{$column} ?? '')), $m)) continue;
            $rb = (int) $m[1];
            $parsed[] = ['min' => max(1, $rb - 4), 'max' => $rb, 'map' => $map];
        }
        return $parsed;
    }

    private function extractRpFromRow($row): ?float
    {
        if (!$row) return null;
        $cols = ['jumlah', 'rp', 'nominal', 'harga', 'jumlah_rp', 'biaya', 'nilai'];
        foreach ($cols as $col) {
            if (isset($row->{$col})) {
                $v = $this->normalizeCurrencyToInt($row->{$col});
                if ($v !== null) return $v;
            }
        }
        foreach ($row->getAttributes() as $v) {
            $clean = $this->normalizeCurrencyToInt($v);
            if ($clean !== null) return (float) $clean;
        }
        return null;
    }

    private function normalizeCurrencyToInt($value): ?int
    {
        if ($value === null || !is_scalar($value)) return null;
        $s = preg_replace('/[Rp\s]/i', '', trim((string) $value));
        $s = preg_replace('/[^0-9,.]/', '', $s);
        if (strpos($s, ',') !== false && strpos($s, '.') !== false) {
            $s = str_replace('.', '', $s);
            $s = str_replace(',', '.', $s);
        } elseif (strpos($s, ',') !== false) {
            $s = str_replace(',', '.', $s);
        }
        return is_numeric($s) ? (int) round((float) $s) : null;
    }

    private function normalizeRbValue($rbRaw): ?int
    {
        return $rbRaw && preg_match('/(\d+)/', (string) $rbRaw, $m) ? (int) $m[1] : null;
    }

    private function normalizeSlotRombimValue($raw): ?int
    {
        return $raw && preg_match('/(\d+)/', (string) $raw, $m) ? (int) $m[1] : null;
    }

    // ===================================================================
    // HITUNG & SIMPAN MASA KERJA (DALAM BULAN)
    // ===================================================================
// Ubah dari private menjadi protected (atau public jika mau dipanggil dari luar controller)
// Ubah dari private menjadi protected (atau public/static jika perlu)
protected function calculateAndSaveMasaKerja($profile)
{
    if (!$profile->tgl_masuk) {
        $profile->masa_kerja = 0;
        $profile->save();
        return;
    }

    $start = \Carbon\Carbon::parse($profile->tgl_masuk);

    if ($profile->tgl_resign) {
        $end = \Carbon\Carbon::parse($profile->tgl_resign);
    } elseif ($profile->tgl_non_aktif) {
        $end = \Carbon\Carbon::parse($profile->tgl_non_aktif);
    } else {
        $end = now();
    }

    $months = $start->diffInMonths($end);

    $profile->masa_kerja = $months;
    $profile->save();
}

    public function inlineUpdateField(Request $request, Profile $profile)
{
    if (!Auth::check()) {
        return response()->json(['status' => 'error', 'message' => 'Unauthorized'], 401);
    }

    $field = $request->input('field');
    $value = $request->input('value');

    $allowedFields = [
        'mentor_magang',
        'periode',
        'tgl_selesai_magang',
        'ukuran',
        'tgl_ambil_seragam',
        'status_lain',
        'keterangan'
    ];

    if (!in_array($field, $allowedFields)) {
        return response()->json([
            'status'  => 'error',
            'message' => 'Field tidak diizinkan'
        ], 422);
    }

    // Khusus tgl_selesai_magang: ubah status Magang → Aktif jika tanggal sudah lewat
    if ($field === 'tgl_selesai_magang') {

    $newValue = $value === '' ? null : $value;

    $profile->tgl_selesai_magang = $newValue;

    if ($newValue) {

        $tglSelesai = Carbon::parse($newValue);
        $today      = Carbon::today();

        // 🔥 PENTING: isi tgl_masuk dari tgl_selesai_magang
        $profile->tgl_masuk = $newValue;

        // Ubah status jadi Aktif jika masih Magang
        if ($profile->status_karyawan === 'Magang') {
            $profile->status_karyawan = 'Aktif';
        }

        Log::info("Relawan {$profile->nama} otomatis aktif. 
                   tgl_masuk diisi dari tgl_selesai_magang ({$newValue})");
    }
    else {
        // Jika tanggal dihapus
        $profile->tgl_masuk = null;

        if ($profile->status_karyawan === 'Aktif') {
            $profile->status_karyawan = 'Magang';
        }
    }
} else {
        // Field lain: langsung set
        $profile->{$field} = $value === '' ? null : $value;
    }

    // Simpan semua perubahan
    $profile->save();

    // Hitung ulang masa kerja jika field yang berpengaruh berubah
    if (
        in_array($field, ['tgl_selesai_magang', 'status_karyawan']) ||
        $profile->wasChanged(['tgl_selesai_magang', 'status_karyawan'])
    ) {
        $this->calculateAndSaveMasaKerja($profile);
    }

    // Format response untuk tanggal
    $formattedValue = $value;
    if (in_array($field, ['tgl_selesai_magang', 'tgl_ambil_seragam']) && $value) {
        try {
            $formattedValue = Carbon::parse($value)->format('d-m-Y');
        } catch (\Exception $e) {
            $formattedValue = $value;
        }
    }

    return response()->json([
        'status'          => 'ok',
        'field'           => $field,
        'value'           => $formattedValue,
        'raw'             => $value,
        'status_karyawan' => $profile->status_karyawan ?? null,
        'masa_kerja'      => $profile->masa_kerja,
        'masa_kerja_text' => $this->formatMasaKerjaText($profile->masa_kerja ?? 0)
    ]);
}

private function formatMasaKerjaText(?int $months): string
{
    if ($months === null || $months < 0) {
        return '-';
    }

    $years  = floor($months / 12);
    $remain = $months % 12;

    $parts = [];
    if ($years > 0) {
        $parts[] = "$years tahun";
    }
    if ($remain > 0) {
        $parts[] = "$remain bulan";
    }

    return $parts ? implode(' ', $parts) : 'Kurang dari 1 bulan';
}
    public function updateSeragamKolom(Request $request, Profile $profile)
{
    $field = $request->field;
    $ukuran = $request->ukuran ? strtoupper(trim($request->ukuran)) : null;

    // Validasi hanya boleh S, M, L, XL, XXL atau kosong
    if ($ukuran && !in_array($ukuran, ['S', 'M', 'L', 'XL', 'XXL'])) {
        return response()->json(['status' => 'Ukuran tidak valid'], 422);
    }

    // Simpan langsung ke kolom
    $profile->{$field} = $ukuran;
    $profile->save();

    // Auto isi tanggal ambil seragam kalau baru pertama kali ada seragam
    $adaSeragam = $profile->kaos_kuning_hitam || $profile->kaos_merah_kuning_biru ||
                  $profile->kemeja_kuning_hitam || $profile->blazer_merah || $profile->blazer_biru;

    if ($adaSeragam && !$profile->tgl_ambil_seragam) {
        $profile->tgl_ambil_seragam = now();
        $profile->save();
    }

    return response()->json(['status' => 'ok', 'ukuran' => $ukuran]);
}
public function import(Request $request)
{
    $request->validate([
        'file' => 'required|mimes:xlsx,xls,csv|max:10240', // max 10MB
    ]);

    try {
        Excel::import(new ProfileImport, $request->file('file'));

        return redirect()->route('profiles.index')
            ->with('success', 'Data profile berhasil diimport!');
    } catch (\Throwable $e) {
        Log::error('Gagal import Profile', [
            'message' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
        ]);

        return back()->with('error', 'Gagal import: ' . $e->getMessage());
    }
}
public function export(Request $request)
{
    $filters = $request->only(['unit', 'search']);

    return Excel::download(
        new ProfileExport($filters),
        'Daftar_Profile_' . now()->format('Y-m-d_His') . '.xlsx'
    );
}

/**
 * Rekalkulasi jumlah murid + KTR + RP untuk satu profile (Guru atau Kepala Unit)
 */
public function recalculateMuridDanKtr(Profile $profile): void
{
    if (!in_array($profile->jabatan, ['Guru', 'Kepala Unit'])) {
        return;
    }

    $totalMurid = 0;

    if ($profile->jabatan === 'Guru') {
        // Hitung murid langsung berdasarkan nama guru (MBA + English)
        $jumlahMba = BukuInduk::where('guru', $profile->nama)
            ->where('status', 'Aktif')
            ->where(function ($q) {
                $possibleCols = ['program', 'departemen', 'kelas', 'jenis_program', 'paket'];
                foreach ($possibleCols as $col) {
                    if (Schema::hasColumn('buku_induk', $col)) {
                        $q->orWhere($col, 'like', '%MBA%');
                    }
                }
                if (empty($q->getQuery()->wheres)) {
                    $q->whereRaw('1 = 1');
                }
            })
            ->count();

        $jumlahEng = BukuInduk::where('guru', $profile->nama)
            ->where('status', 'Aktif')
            ->where(function ($q) {
                $possibleCols = ['program', 'departemen', 'kelas', 'jenis_program', 'paket'];
                foreach ($possibleCols as $col) {
                    if (Schema::hasColumn('buku_induk', $col)) {
                        $q->orWhere($col, 'like', '%English%');
                    }
                }
                if (empty($q->getQuery()->wheres)) {
                    $q->whereRaw('1 = 1');
                }
            })
            ->count();

        $totalMurid = $jumlahMba + $jumlahEng;

        $profile->jumlah_murid_mba     = $jumlahMba > 0 ? $jumlahMba : null;
        $profile->jumlah_murid_jadwal  = $totalMurid > 0 ? $totalMurid : 0;
        $profile->jumlah_rombim        = $totalMurid > 0 ? $this->resolveSlotRombimFromCount($totalMurid) : null;

        // Gunakan method yang sudah ada untuk Guru
        $this->calculateGuruData($profile);

    } elseif ($profile->jabatan === 'Kepala Unit') {
        // Untuk Kepala Unit: total murid bawahan = semua murid aktif di unit/departemen
        $totalMurid = BukuInduk::whereIn('guru', function ($q) use ($profile) {
            $q->select('nama')->from('profiles')
                ->where('biMBA_unit', $profile->biMBA_unit)  // ← pakai unit, bukan hanya departemen
                ->where('jabatan', 'Guru');
        })
        ->where('status', 'Aktif')
        ->count();

        $profile->total_murid_bawahan = $totalMurid;

        // Gunakan method yang sudah ada untuk Kepala Unit
        $this->calculateKepalaUnitData($profile, $totalMurid, null);
    }

    $profile->saveQuietly();
}

public function getNextNikNoUrut(Request $request)
{
    $bimba_unit = $request->bimba_unit;

    if (!$bimba_unit) {
        return response()->json(['error' => 'Unit kosong'], 400);
    }

    $unit = Unit::where('biMBA_unit', $bimba_unit)->first();

    if (!$unit) {
        return response()->json(['error' => 'Unit tidak ditemukan'], 404);
    }

    $noCabang = $unit->no_cabang;

    // ===============================
    // AMBIL NO URUT DARI NIK TERAKHIR
    // ===============================
    $lastNik = Profile::where('biMBA_unit', $bimba_unit)
        ->whereNotNull('nik')
        ->orderByDesc('nik')
        ->value('nik');

    if ($lastNik) {
        $lastUrut = (int) substr($lastNik, -4); // ambil 4 digit terakhir
    } else {
        $lastUrut = 0;
    }

    $nextUrut = $lastUrut + 1;

    $nextUrutPadded = str_pad($nextUrut, 4, '0', STR_PAD_LEFT);

    $nik = $noCabang . '01' . $nextUrutPadded;

    return response()->json([
        'nik' => $nik,
        'no_urut' => $nextUrut,
        'no_cabang' => $noCabang
    ]);
}
}
