<?php

namespace App\Http\Controllers;

use Illuminate\Validation\Rule;
use App\Models\BukuInduk;
use App\Models\HargaSaptataruna;
use App\Models\Profile;
use App\Models\Unit;
use Maatwebsite\Excel\Facades\Excel;
use App\Imports\BukuIndukImport;
use App\Exports\BukuIndukExport;
use App\Models\BukuIndukHistory;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB; // Tambahan untuk ambil data units
use Illuminate\Support\Facades\Auth; // Tambahan untuk cek user login

class BukuIndukController extends Controller
{
    public function index(Request $request)
    {
        $now = Carbon::now();
        $currentYear = $now->year;
        $currentMonth = $now->month;

        BukuInduk::where('status', 'baru')
    ->whereNotNull('tgl_masuk')
    ->whereDate('tgl_masuk', '<=', now()->subDays(30))
    ->update([
        'status' => 'aktif'
    ]);

        // ========================================================
        // PAGINATION & QUERY
        // ========================================================
        $perPage = $request->input('perPage', 50);

        $query = BukuInduk::query()
            ->orderByRaw("
            CASE 
                WHEN status = 'aktif' THEN 0
                WHEN status = 'baru' THEN 1
                ELSE 2
            END
        ")
            ->orderBy('nim', 'asc');

        // ========================================================
        // DROPDOWN OPTIONS — KEMBALI KE BENTUK COLLECTION (AMAN UNTUK BLADE LAMA)
        // ========================================================
        $muridOptions = BukuInduk::orderBy('nim', 'asc')
            ->get(['nim', 'nama']);
        // Hasil: Collection of objects {nim: ..., nama: ...}
        // Cocok dengan @foreach ($muridOptions as $murid) {{ $murid->nim }}

        $unitOptions = DB::table('units')
            ->whereNotNull('bimba_unit')
            ->where('bimba_unit', '!=', '')
            ->distinct()
            ->orderBy('bimba_unit')
            ->pluck('bimba_unit');

        // ========================================================
        // FILTERS
        // ========================================================
        if ($request->filled('murid')) {
            $query->where('nim', $request->murid);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('nim', 'like', "%{$search}%")
                    ->orWhere('nama', 'like', "%{$search}%");
            });
        }

        if ($request->filled('status')) {
            if ($request->status === 'baru') {
                $query->where('status', 'baru');
            } else {
                $query->where('status', $request->status);
            }
        }

        if ($request->filled('start_date') && $request->filled('end_date')) {
            $query->whereBetween('tgl_masuk', [$request->start_date, $request->end_date]);
        }

        if ($request->filled('unit')) {
            $query->where('bimba_unit', $request->unit);
        }

        // ========================================================
        // HITUNG TOTAL
        // ========================================================
        $filteredQuery = clone $query;
        $totalBaru = (clone $filteredQuery)->where('status', 'baru')->count();
        $totalAktif = (clone $filteredQuery)->where('status', 'aktif')->count();
        $totalKeluar = (clone $filteredQuery)->where('status', 'keluar')->count();

        // ========================================================
        // PAGINATE & RETURN
        // ========================================================
        $bukuInduk = $query->paginate($perPage)->appends($request->all());

        // Tambahkan info jadwal untuk setiap murid
    foreach ($bukuInduk as $item) {
        $item->info_jadwal = $this->hitungPertemuanTerlewatDiBulanMasuk($item);
    }
        return view('buku_induk.index', compact(
            'bukuInduk',
            'perPage',
            'totalBaru',
            'totalAktif',
            'totalKeluar',
            'muridOptions',
            'unitOptions'
        ));
    }

    public function create()
    {
        $HargaSaptataruna = HargaSaptataruna::all();
        $profil = Profile::where('jabatan', '!=', 'Kepala Unit')->get();

        // KD options & SPP mapping
        $firstRow = $HargaSaptataruna->first();
        $kdOptions = [];
        if ($firstRow) {
            foreach ($firstRow->getAttributes() as $key => $value) {
                if (in_array($key, ['a', 'b', 'c', 'd', 'e', 'f'])) {
                    $kdOptions[] = strtoupper($key);
                }
            }
        }

        $sppMapping = [];
        foreach ($HargaSaptataruna as $item) {
            foreach ($kdOptions as $kd) {
                $columnName = strtolower($kd);
                $sppMapping[$item->kode][$kd] = $item->$columnName ?? 0;
            }
        }

        // Semua unit (untuk admin)
        $units = Unit::orderBy('bimba_unit')
            ->pluck('no_cabang', 'bimba_unit')
            ->toArray();

        $unitsJson = json_encode($units);

        // Tentukan status admin & data user
        $isAdmin = Auth::user()->is_admin ?? false;
        $userUnit = null;
        $userNoCabang = null;

        if (!$isAdmin) {
            $userUnit = Auth::user()->bimba_unit;
            if ($userUnit) {
                $userNoCabang = Unit::where('bimba_unit', $userUnit)->value('no_cabang');
            }
        }

        // Dropdown options lainnya (tetap sama)
        $tahapanOptions = ['Persiapan', 'Lanjutan'];
        $kategoriKeluarOptions = ['Belum bayar SPP', 'Belum kondusif', 'Ganti Golongan', 'Masuk SD', 'Masuk TK', 'Sudah SD', 'Sudah TK', 'Perpanjang Bea', 'Pindah biMBA', 'Pindah rumah', 'Sakit/rehat', 'Tdk ada yg antar', 'Tidak ada kabar', 'Lain-lain', 'Order Sertifikat', 'Order STPB', 'Order Sertifikat & STPB'];
        $kelasOptions = ['biMBA-AIUEO', 'English biMBA'];
        $noteOptions = ['Aktif Kembali', 'Cuti', 'Ganti Gol', 'Pindahan', 'Belum Bayar SPP', 'Murid Mutasi Masuk', 'Murid Mutasi Keluar', 'Garansi'];
        $noteGaransiOptions = ['Sudah Diberikan'];
        $periodeOptions = ['Ke1', 'Ke2', 'Ke3', 'Ke4', 'Ke5', 'Ke6', 'Ke7', 'Ke8', 'Ke9', 'Ke10', 'Ke11', 'Ke12'];
        $asalModulOptions = ['biMBA IM', 'biMBA Unit'];
        $levelOptions = ['Level 1', 'Level 2', 'Level 3', 'Level 4'];
        $jenisKbmOptions = ['Full TM', 'Full DLC', 'Kombinasi TM & DLC'];
        $kodeJadwalOptions = ['108', '109', '110', '111', '112', '113', '114', '115', '116', '208', '209', '210', '211', '308', '309', '310', '311'];
        $statusPindahOptions = ['1'];
        $keBimbaIntervioOptions = ['biMba Intervio'];
        $statusOptions = ['Aktif', 'Keluar', 'Baru'];
        $infoOptions = ['Brosur', 'Event', 'Humas', 'Internet', 'Spanduk', 'Lainnya'];

        // Hitung NIM otomatis (untuk non-admin atau default)
$autoNim = null;
$autoNimSuffix = null;

if (!$isAdmin && $userUnit) {
    // Non-admin → gunakan unit user
    $lastNim = BukuInduk::where('bimba_unit', $userUnit)
        ->orderBy('nim', 'desc')
        ->value('nim');

    $nextSuffix = 1;
    if ($lastNim) {
        $lastSuffix = (int) substr($lastNim, -4); // ambil 4 digit terakhir
        $nextSuffix = $lastSuffix + 1;
    }

    $paddedSuffix = str_pad($nextSuffix, 4, '0', STR_PAD_LEFT);
    $autoNimSuffix = $paddedSuffix;
    $autoNim = $userNoCabang . $paddedSuffix;
} elseif ($isAdmin) {
    // Admin → NIM akan dihitung via JS/AJAX setelah pilih unit
    // Kosongkan dulu, JS akan handle
    $autoNim = null;
    $autoNimSuffix = null;
}

        return view('buku_induk.create', compact(
            'HargaSaptataruna',
            'profil',
            'kdOptions',
            'tahapanOptions',
            'kategoriKeluarOptions',
            'kelasOptions',
            'noteOptions',
            'noteGaransiOptions',
            'periodeOptions',
            'asalModulOptions',
            'levelOptions',
            'jenisKbmOptions',
            'kodeJadwalOptions',
            'statusPindahOptions',
            'keBimbaIntervioOptions',
            'sppMapping',
            'statusOptions',
            'units',
            'unitsJson',
            'infoOptions',
            'isAdmin',
            'userUnit',
            'userNoCabang',
            'autoNim',          // ← baru
    'autoNimSuffix'     // ← baru
        ));
    }

    public function store(Request $request)
    {
        $user = Auth::user();
        $isAdmin = $user->is_admin ?? false;

        // Force unit & cabang untuk non-admin yang sudah punya unit
        if (!$isAdmin && $user->bimba_unit) {
            $request->merge([
                'bimba_unit' => $user->bimba_unit,
                'no_cabang'  => Unit::where('bimba_unit', $user->bimba_unit)->value('no_cabang')
            ]);
        }

        // Hitung usia & lama belajar
        $request->merge([
            'usia' => $request->filled('tgl_lahir')
                ? Carbon::parse($request->tgl_lahir)->age
                : null,

            'lama_bljr' => $request->filled('tgl_masuk') ? (function () use ($request) {
                $masuk = Carbon::parse($request->tgl_masuk);
                $today = Carbon::now();
                $bulan = ($today->year - $masuk->year) * 12 + ($today->month - $masuk->month);
                if ($today->day < $masuk->day) $bulan--;
                return ($bulan >= 0 ? $bulan : 0) . ' bulan';
            })() : null,
        ]);

        // Kosongkan tanggal jika tidak diisi
        $dateFields = ['tgl_lahir', 'tgl_masuk', 'tgl_keluar', 'tgl_mulai', 'tgl_akhir', 'tgl_bayar', 'tgl_selesai', 'tanggal_pindah'];
        foreach ($dateFields as $field) {
            if (!$request->filled($field)) $request->merge([$field => null]);
        }

        // Bersihkan SPP
        if ($request->has('spp') && $request->spp !== null) {
            $cleanSpp = (int) preg_replace('/[^0-9]/', '', $request->spp);
            $request->merge(['spp' => $cleanSpp]);
        }

        // Validasi lengkap
        $data = $request->validate([
            'nim_suffix' => 'required|string|max:10',
            'nim' => 'required|string|max:20|unique:buku_induk,nim',
            'nama' => 'required|string|max:100',
            'tmpt_lahir' => 'nullable|string|max:50',
            'tgl_lahir' => 'nullable|date',
            'tgl_masuk' => 'required|date',
            'usia' => 'nullable|integer',
            'lama_bljr' => 'nullable|string|max:50',
            'tahap' => 'nullable|string|in:Persiapan,Lanjutan',
            'tgl_keluar' => 'nullable|date',
            'kategori_keluar' => 'nullable|string',
            'alasan' => 'nullable|string',
            'kelas' => 'required|string|in:biMBA-AIUEO,English biMBA',
            'gol' => 'required|string',
            'kd' => 'required|string|size:1|in:A,B,C,D,E,F',
            'spp' => 'required|integer|min:0|max:999999999',
            'petugas_trial' => 'nullable|string',
            'guru' => 'required|string',
            'orangtua' => 'nullable|string',
            'no_telp_hp' => 'nullable|string|max:20',
            'note' => 'nullable|string',
            'no_cab_merge' => 'nullable|string',
            'no_pembayaran_murid' => 'nullable|string',
            'note_garansi' => 'nullable|string',
            'periode' => 'nullable|string',
            'tgl_mulai' => 'nullable|date',
            'tgl_akhir' => 'nullable|date',
            'alert' => 'nullable|string',
            'tgl_bayar' => 'nullable|date',
            'tgl_selesai' => 'nullable|date',
            'alert2' => 'nullable|string',
            'asal_modul' => 'nullable|string',
            'keterangan_optional' => 'nullable|string',
            'level' => 'nullable|string|in:Level 1,Level 2,Level 3,Level 4',
            'jenis_kbm' => 'nullable|string',
            'kode_jadwal' => 'required|string',
            'hari_jam' => 'nullable|string',
            'alamat_murid' => 'nullable|string',
            'status_pindah' => 'nullable|string',
            'tanggal_pindah' => 'nullable|date',
            'ke_bimba_intervio' => 'nullable|string',
            'keterangan' => 'nullable|string',
            'info' => 'required|string|in:Brosur,Event,Humas,Internet,Spanduk,Lainnya',
            'bimba_unit' => 'required|string|exists:units,bimba_unit',
            'no_cabang' => 'required|string|max:20',
        ]);

        // Verifikasi no_cabang sesuai bimba_unit
        $correctNoCabang = Unit::where('bimba_unit', $data['bimba_unit'])->value('no_cabang');
        if (!$correctNoCabang || $data['no_cabang'] !== $correctNoCabang) {
            return back()->withErrors(['bimba_unit' => 'No. Cabang tidak sesuai unit terpilih.'])->withInput();
        }

        if (!str_starts_with($data['nim'], $correctNoCabang)) {
            return back()->withErrors(['nim' => 'NIM harus diawali dengan no cabang unit (' . $correctNoCabang . ')'])->withInput();
        }

        $data['nim'] = trim($data['nim']);
        $data['no_cabang'] = $correctNoCabang;
        $data['status'] = 'Baru';

        $existing = BukuInduk::whereRaw('TRIM(nim) = ?', [$data['nim']])->first();

        if ($existing) {
            $oldData = $existing->toArray();
            foreach ($data as $key => $value) {
                if (is_null($existing->$key) || $existing->$key === '' || $existing->$key === '0') {
                    $existing->$key = $value ?? null;
                }
            }
            $existing->save();

            BukuIndukHistory::create([
                'buku_induk_id' => $existing->id,
                'action' => 'update_partial',
                'user' => $user->name ?? 'system',
                'old_data' => $oldData,
                'new_data' => $existing->toArray(),
            ]);

            $message = 'Data murid berhasil diperbarui (NIM sudah ada sebelumnya)';
        } else {
            $newRecord = BukuInduk::create($data);

            BukuIndukHistory::create([
                'buku_induk_id' => $newRecord->id,
                'action' => 'create',
                'user' => $user->name ?? 'system',
                'old_data' => null,
                'new_data' => $data,
            ]);

            $message = 'Data murid baru berhasil ditambahkan!';
        }

        return redirect()->route('buku_induk.index')->with('success', $message);
    }

    public function edit($id)
{
    $bukuInduk = BukuInduk::findOrFail($id);

    $HargaSaptataruna = HargaSaptataruna::all();
    $profil = Profile::whereIn('jabatan', ['Guru', 'Kepala Unit', 'Kepala', 'Unit Head'])
                 ->orderBy('nama')
                 ->get();

    // KD Options
    $kdOptions = ['A', 'B', 'C', 'D', 'E', 'F'];

    // Mapping SPP
    $sppMapping = [];
    foreach ($HargaSaptataruna as $item) {
        foreach ($kdOptions as $kd) {
            $col = strtolower($kd);
            $sppMapping[$item->kode][$kd] = $item->$col ?? 0;
        }
    }

    // Units
    $units = DB::table('units')
        ->orderBy('bimba_unit')
        ->pluck('no_cabang', 'bimba_unit')
        ->toArray();

    // Dropdown options
    $tahapanOptions = ['Persiapan', 'Lanjutan'];
    $kategoriKeluarOptions = [
        'Belum bayar SPP','Belum kondusif','Ganti Golongan','Masuk SD','Masuk TK',
        'Sudah SD','Sudah TK','Perpanjang Bea','Pindah biMBA','Pindah rumah',
        'Sakit/rehat','Tdk ada yg antar','Tidak ada kabar','Lain-lain',
        'Order Sertifikat','Order STPB','Order Sertifikat & STPB'
    ];

    $kelasOptions = ['biMBA-AIUEO', 'English biMBA'];
    $noteOptions = [
        'Aktif Kembali','Cuti','Ganti Gol','Pindahan',
        'Belum Bayar SPP','Murid Mutasi Masuk','Murid Mutasi Keluar','Garansi'
    ];

    $periodeOptions = ['Ke-1','Ke-2','Ke-3','Ke-4','Ke-5','Ke-6','Ke-7','Ke-8','Ke-9','Ke-10','Ke-11','Ke-12'];
    $levelOptions = ['Level 1','Level 2','Level 3','Level 4'];
    $jenisKbmOptions = ['Full TM','Full DLC','Kombinasi TM & DLC'];
    $kodeJadwalOptions = [
        '108','109','110','111','112','113','114','115','116',
        '208','209','210','211',
        '308','309','310','311'
    ];

    $noteGaransiOptions = [
    'Tidak Memenuhi Syarat',
    'Berkebutuhan Khusus',
];

    $infoOptions = ['Brosur','Event','Humas','Internet','Spanduk','Lainnya'];

    return view('buku_induk.edit', compact(
        'bukuInduk',
        'HargaSaptataruna',
        'profil',
        'kdOptions',
        'sppMapping',
        'tahapanOptions',
        'kategoriKeluarOptions',
        'noteGaransiOptions', // ← TAMBAHKAN INI
        'kelasOptions',
        'noteOptions',
        'periodeOptions',
        'levelOptions',
        'jenisKbmOptions',
        'kodeJadwalOptions',
        'units',
        'infoOptions'
    ));
}


    public function update(Request $request, $id)
{
    $bukuInduk = BukuInduk::findOrFail($id);
    $oldData = $bukuInduk->toArray();

    // pastikan NIM bersih
    $request->merge(['nim' => trim($request->nim)]);

    /* =====================================================
     * VALIDASI — SELARAS 100% DENGAN MODEL
     * ===================================================== */
    $data = $request->validate([
        'nim' => ['required','string','max:20', Rule::unique('buku_induk','nim')->ignore($id)],
        'nama' => 'required|string|max:100',

        'tmpt_lahir' => 'nullable|string|max:50',
        'tgl_lahir'  => 'nullable|date',
        'tgl_masuk'  => 'required|date',
        'tgl_keluar' => 'nullable|date',

        'usia'      => 'nullable|integer',
        'lama_bljr' => 'nullable|string',

        'tahap' => 'nullable|string|in:Persiapan,Lanjutan',
        'kelas' => 'required|string|in:biMBA-AIUEO,English biMBA',
        'gol'   => 'required|string',
        'kd'    => 'required|string|in:A,B,C,D,E,F',

        'spp' => 'required|integer|min:0',

        'guru' => 'required|string',
        'petugas_trial' => 'nullable|string',
        'orangtua' => 'nullable|string',
        'no_telp_hp' => 'nullable|string',

        'kategori_keluar' => 'nullable|string',
        'alasan'          => 'nullable|string',

        'note' => 'nullable|string',
        'note_garansi' => 'nullable|string',
        'no_cab_merge' => 'nullable|string',
        'no_pembayaran_murid' => 'nullable|string',

        // ==== BEASISWA / GARANSI ====
        'periode'   => 'nullable|string',
        'tgl_mulai' => 'nullable|date',
        'tgl_akhir' => 'nullable|date',
        'alert'     => 'nullable|string',
        'alert2'    => 'nullable|string',

        'tgl_bayar'   => 'nullable|date',
        'tgl_selesai' => 'nullable|date',

        'asal_modul' => 'nullable|string',
        'level' => 'nullable|string',
        'jenis_kbm' => 'nullable|string',
        'kode_jadwal' => 'required|string',
        'hari_jam' => 'nullable|string',

        'alamat_murid' => 'nullable|string',
        'status_pindah' => 'nullable|string',
        'tanggal_pindah' => 'nullable|date',
        'ke_bimba_intervio' => 'nullable|string',

        'keterangan' => 'nullable|string',
        'keterangan_optional' => 'nullable|string',

        'bimba_unit' => 'required|string|exists:units,bimba_unit',
        'no_cabang'  => 'required|string',
        'info'       => 'required|string',
    ]);

    /* =====================================================
     * NORMALISASI TANGGAL KOSONG
     * ===================================================== */
    foreach ([
        'tgl_lahir','tgl_masuk','tgl_keluar','tgl_mulai',
        'tgl_akhir','tgl_bayar','tgl_selesai','tanggal_pindah'
    ] as $f) {
        if (empty($data[$f])) {
            $data[$f] = null;
        }
    }

    /* =====================================================
     * HITUNG USIA & LAMA BELAJAR
     * ===================================================== */
    if ($data['tgl_lahir']) {
        $data['usia'] = Carbon::parse($data['tgl_lahir'])->age;
    }

    if ($data['tgl_masuk']) {
        $masuk = Carbon::parse($data['tgl_masuk']);
        $today = Carbon::now();

        $bulan = ($today->year - $masuk->year) * 12 + ($today->month - $masuk->month);
        if ($today->day < $masuk->day) {
            $bulan--;
        }

        $data['lama_bljr'] = max($bulan, 0) . ' bulan';
    }

    /* =====================================================
     * ALERT BEASISWA OTOMATIS
     * ===================================================== */
    if ($data['tgl_mulai'] && $data['tgl_akhir']) {
        $now = Carbon::today();
        $data['alert'] = $now->between(
            Carbon::parse($data['tgl_mulai']),
            Carbon::parse($data['tgl_akhir'])
        ) ? 'aktif' : null;
    }

    /* =====================================================
     * VALIDASI CABANG & NIM
     * ===================================================== */
    $correctNoCabang = DB::table('units')
        ->where('bimba_unit', $data['bimba_unit'])
        ->value('no_cabang');

    if ($data['no_cabang'] !== $correctNoCabang) {
        return back()->withErrors(['bimba_unit' => 'No cabang tidak sesuai'])->withInput();
    }

    if (!str_starts_with($data['nim'], $correctNoCabang)) {
        return back()->withErrors(['nim' => "NIM harus diawali {$correctNoCabang}"])->withInput();
    }

    /* =====================================================
     * SIMPAN & HISTORY
     * ===================================================== */
    $bukuInduk->update($data);

    $newData = $bukuInduk->fresh()->toArray();
    $changedOld = [];
    $changedNew = [];

    foreach ($oldData as $k => $v) {
        if (array_key_exists($k, $newData) && $newData[$k] != $v) {
            $changedOld[$k] = $v;
            $changedNew[$k] = $newData[$k];
        }
    }

    if ($changedOld) {
        BukuIndukHistory::create([
            'buku_induk_id' => $bukuInduk->id,
            'action' => 'update',
            'user' => Auth::user()?->name ?? 'system',
            'old_data' => $changedOld,
            'new_data' => $changedNew,
        ]);
    }

    return redirect()
        ->route('buku_induk.index')
        ->with('success', 'Data murid berhasil diperbarui!');
}



    public function destroy($id)
    {
        $bukuInduk = BukuInduk::findOrFail($id);
        $bukuInduk->delete();
        return redirect()->route('buku_induk.index')->with('success', 'Data berhasil dihapus!');
    }

    public function import(Request $request)
    {
        $request->validate([
            'file' => 'required|mimes:xlsx,xls,csv',
        ]);

        Excel::import(new BukuIndukImport, $request->file('file'));

        return redirect()->route('buku_induk.index')->with('success', 'Data berhasil diimport!');
    }

    public function history($id)
    {
        // Cek apakah user login dan punya role 'admin'
        if (!Auth::check() || Auth::user()->role !== 'admin') {
            abort(403, 'Hanya admin yang diizinkan melihat riwayat perubahan.');
        }

        $bukuInduk = BukuInduk::findOrFail($id);
        $histories = $bukuInduk->histories()->latest()->paginate(10);

        return view('buku_induk.history', compact('bukuInduk', 'histories'));
    }

public function show($id)
{
    $bukuInduk = BukuInduk::findOrFail($id);

    // ambil history yang terkait
    $histories = $bukuInduk->histories()->latest()->get();

    // Hitung info pertemuan terlewat di bulan masuk
    $infoJadwal = $this->hitungPertemuanTerlewatDiBulanMasuk($bukuInduk);

    return view('buku_induk.show', compact(
        'bukuInduk',
        'histories',
        'infoJadwal'   // ← ini yang hilang sebelumnya
    ));
}
    public function allHistory()
    {
        // Sama seperti di atas
        if (!Auth::check() || Auth::user()->role !== 'admin') {
            abort(403, 'Hanya admin yang diizinkan melihat semua riwayat.');
        }

        $histories = BukuIndukHistory::with('bukuInduk')->latest()->paginate(20);

        return view('buku_induk.all_history', compact('histories'));
    }
    public function export(Request $request)
    {
        $filters = $request->only(['murid', 'unit', 'status']);

        return Excel::download(
            new BukuIndukExport($filters),
            'Data_Buku_Induk_' . now()->format('Y-m-d_His') . '.xlsx'
        );
    }

  /**
 * Menghitung pertemuan terlewat dan sisa jadwal di bulan mulai efektif
 * - Jika masuk di tanggal >= 21 → mulai efektif bulan depan (terlewat = 0)
 * - Jika masuk < 21 → hitung terlewat di bulan masuk
 * - Exclude tanggal merah nasional & cuti bersama
 *
 * @param BukuInduk $murid
 * @return array
 */
 private function hitungPertemuanTerlewatDiBulanMasuk($murid)
{
    if (!$murid->tgl_masuk) {
        return [
            'status'            => 'error',
            'pesan'             => 'Tanggal masuk kosong',
            'pertemuan_diambil' => 0,
            'sisa'              => null,
            'total_hardcode'    => 0,
            'bulan_tampil'      => '-',
            'shift'             => '-',
            'catatan'           => null,
        ];
    }

    $masuk = \Carbon\Carbon::parse($murid->tgl_masuk);
    $kode  = (int) ($murid->kode_jadwal ?? 0);

    // Mapping shift, total hardcode, dan hari jadwal
    $namaShift     = '-';
    $totalHardcode = 0;
    $hariJadwal    = [];

    if ($kode >= 108 && $kode <= 116) {
        $namaShift     = 'SRJ';
        $totalHardcode = 12;
        $hariJadwal    = [0, 2, 4]; // Senin, Rabu, Jumat
    } elseif ($kode >= 208 && $kode <= 211) {
        $namaShift     = 'SKS';
        $totalHardcode = 12;
        $hariJadwal    = [1, 3, 5]; // Selasa, Kamis, Sabtu
    } elseif ($kode >= 308 && $kode <= 311) {
        $namaShift     = 'S6';
        $totalHardcode = 24;
        $hariJadwal    = [0, 1, 2, 3, 4, 5]; // Senin–Sabtu
    }

    if ($totalHardcode === 0) {
        return [
            'status'            => 'error',
            'pesan'             => 'Kode jadwal tidak dikenali',
            'pertemuan_diambil' => 0,
            'sisa'              => null,
            'total_hardcode'    => 0,
            'bulan_tampil'      => '-',
            'shift'             => '-',
            'catatan'           => null,
        ];
    }

    // Threshold: jika masuk >= tanggal 21 → aktif mulai bulan depan
    $thresholdHari = 21;
    $bulanAktif    = $masuk->copy();
    $catatan       = null;
    $pertemuanDiambil = 0;

    if ($masuk->day >= $thresholdHari) {
        $bulanAktif->addMonthNoOverflow()->startOfMonth();
        $catatan = "Masuk akhir bulan → aktif mulai {$bulanAktif->translatedFormat('F Y')}";
        // Karena aktif full bulan depan → pertemuan_diambil = 0, sisa = total full
        $pertemuanDiambil = 0;
    } else {
        // Masuk di bulan yang sama → hitung pertemuan dari tanggal masuk sampai akhir bulan
        $start = $masuk->copy();
        $end   = $masuk->copy()->endOfMonth();

        $current = $start->copy();
        while ($current->lte($end)) {
            if (in_array($current->weekday(), $hariJadwal)) {
                $pertemuanDiambil++;
            }
            $current->addDay();
        }
    }

    // Sisa = total_hardcode - pertemuan_diambil
    $sisa = $totalHardcode - $pertemuanDiambil;
    $sisa = max(0, $sisa); // jaga-jaga kalau negatif

    return [
        'status'            => 'ok',
        'shift'             => $namaShift,
        'bulan_tampil'      => $bulanAktif->translatedFormat('F Y'),
        'tanggal_masuk'     => $masuk->translatedFormat('d F Y'),
        'hari_masuk'        => $masuk->translatedFormat('l, d F Y'),
        'pertemuan_diambil' => $pertemuanDiambil,
        'sisa'              => $sisa,
        'total_hardcode'    => $totalHardcode,
        'catatan'           => $catatan,
    ];
}
public function nextSuffix(Request $request)
{
    $bimbaUnit = $request->query('bimba_unit');
    if (!$bimbaUnit) return response()->json(['next_suffix' => '0001']);

    $lastNim = BukuInduk::where('bimba_unit', $bimbaUnit)
        ->orderBy('nim', 'desc')
        ->value('nim');

    if (!$lastNim) return response()->json(['next_suffix' => '0001']);

    $lastSuffix = (int) substr($lastNim, -4);
    $next = $lastSuffix + 1;

    return response()->json(['next_suffix' => $next]);
}
}
