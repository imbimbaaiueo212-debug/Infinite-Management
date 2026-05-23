<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\DataProduk;
use App\Models\Produk;
use App\Models\PenerimaanProduk;
use App\Models\PemakaianProduk;
use App\Models\Unit;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;

class DataProdukController extends Controller
{
    /**
     * ==============================
     * INDEX — REKAP STOK BULANAN
     * ==============================
     */
    public function index(Request $request)
{
    $user = Auth::user();

    $periodeInput = $request->input('periode');
    $unitIdInput  = $request->input('unit_id');
    $searchKode   = $request->input('search');

    $periode = $periodeInput
        ? Carbon::createFromFormat('Y-m', $periodeInput)->format('Y-m')
        : now()->format('Y-m');

    // Tentukan unit berdasarkan role user
    $unitId = null;

    if ($user->isAdminUser()) {
        if ($unitIdInput !== null && $unitIdInput !== '' && is_numeric($unitIdInput)) {
            $unitId = (int) $unitIdInput;
        }
    } else {
        if ($user->bimba_unit) {
            $unit = Unit::where('biMBA_unit', $user->bimba_unit)->first();
            if ($unit) $unitId = $unit->id;
        }
    }

    // Jika unit belum ditentukan → tampilkan pilihan unit
    if (!$unitId) {
        $units = Unit::orderBy('no_cabang')->get();
        $produks = Produk::where('pendataan', 1)
            ->orderBy('kode')
            ->get(['kode', 'label', 'jenis']);

        return view('data_produk.index', compact('units', 'produks', 'periode'))->with([
            'items'              => collect(),
            'unitId'             => null,
            'hasData'            => false,
            'showGenerateButton' => false,
            'searchKode'         => null,
            'message' => $user->isAdminUser()
                ? 'Silakan pilih unit biMBA untuk melihat rekap stok.'
                : 'Unit biMBA Anda tidak terdeteksi. Hubungi admin.',
        ]);
    }

    // Data pendukung tampilan
    $units = Unit::orderBy('no_cabang')->get();
    $produks = Produk::where('pendataan', 1)
        ->orderBy('kode')
        ->get(['kode', 'label', 'jenis']);

    // =====================================
    // SYNC OTOMATIS — URUTAN SANGAT PENTING!
    // =====================================
    Log::info("[SYNC START] periode: {$periode}, unit: {$unitId}");

    // 1. Sync saldo awal DARI BULAN SEBELUMNYA → harus paling dulu
    $this->syncSaldoAwalFromPrevious($periode, $unitId);

    // 2. Sync penerimaan bulan ini
    $this->syncTerimaFromPenerimaan($periode, $unitId);

    // 3. Sync pemakaian bulan ini
    $this->syncPakaiFromPemakaian($periode, $unitId);
    // Tambahkan baris ini:
    $this->syncSldAwalFromOpname($periode, $unitId);

    Log::info("[SYNC FINISH] periode: {$periode}, unit: {$unitId}");

    // =====================================
    // Ambil data rekap untuk tampilan
    // =====================================
    $query = DataProduk::with('unit')
        ->where('periode', $periode)
        ->where('unit_id', $unitId)
        ->whereHas('produk', fn ($q) => $q->where('pendataan', 1));

    if ($searchKode) {
        $query->where('kode', $searchKode);
    }

    $items = $query->orderBy('kode', 'asc')
                   ->paginate(500)
                   ->withQueryString();

    $hasData = $items->isNotEmpty();

    $totalRekap = DataProduk::where('periode', $periode)
        ->where('unit_id', $unitId)
        ->whereHas('produk', fn ($q) => $q->where('pendataan', 1))
        ->count();

    $totalProduk = $produks->count();
    $showGenerateButton = $totalRekap < $totalProduk;

    return view('data_produk.index', compact(
        'items', 'units', 'produks', 'unitId', 'periode',
        'showGenerateButton', 'hasData', 'searchKode'
    ));
}
    /**
     * ==============================
     * CREATE
     * ==============================
     */
    public function create()
{
    $units = Unit::orderBy('no_cabang')->get();

    $currentMonth = now()->format('Y-m');
    $periodes = collect();

    for ($i = 0; $i < 6; $i++) {
        $periodes->push(now()->subMonths($i)->format('Y-m'));
    }

    $produks = Produk::where('pendataan', 1)
        ->orderBy('kode')
        ->get(['kode', 'label', 'jenis', 'satuan', 'harga']);

    $user = auth()->user();

    // cek admin
    $isAdmin = in_array($user->role ?? '', ['admin', 'superadmin']);

    // cari unit user
    $userUnit = null;

    if (!$isAdmin && $user) {
        $userUnit = Unit::where('id', $user->unit_id)
            ->orWhere('no_cabang', $user->no_cabang)
            ->orWhere('biMBA_unit', $user->bimba_unit)
            ->first();
    }

    return view('data_produk.create', compact(
        'produks',
        'units',
        'periodes',
        'currentMonth',
        'userUnit',
        'isAdmin'
    ));
}

    public function store(Request $request)
{
    $request->validate([
        'unit_id'  => 'required|exists:units,id',
        'periode'  => 'required|date_format:Y-m',
        'kode'     => 'required|string|unique:data_produk,kode,NULL,id,periode,'.$request->periode.',unit_id,'.$request->unit_id,
        'jenis'    => 'required|string',
        'label'    => 'required|string',
        'satuan'   => 'required|string',
        'harga'    => 'required|numeric|min:0',
        'min_stok' => 'required|integer|min:0',
        'sld_awal' => 'required|integer|min:0',
        'opname'   => 'required|integer|min:0',
    ]);

    $data = $request->only([
        'unit_id', 'periode', 'kode', 'jenis', 'label', 'satuan', 'harga',
        'min_stok', 'sld_awal', 'opname'
    ]);

    $data['terima'] = 0;
    $data['pakai']  = 0;

    DataProduk::create($data);

    // Sync otomatis
    $this->syncPakaiFromPemakaian($request->periode, $request->unit_id);
    $this->syncTerimaFromPenerimaan($request->periode, $request->unit_id);

    return redirect()->route('data_produk.index', [
        'periode' => $request->periode,
        'unit_id' => $request->unit_id
    ])->with('success', 'Data rekap stok berhasil disimpan!');
}

    /**
     * ==============================
     * EDIT
     * ==============================
     */
    public function edit($id)
    {
        $item = DataProduk::findOrFail($id);
        $units = Unit::orderBy('no_cabang')->get();

        $produks = Produk::where('pendataan', 1)
            ->orderBy('kode')
            ->get(['kode', 'label', 'jenis', 'satuan', 'harga']);

        return view('data_produk.edit', compact('item', 'produks', 'units'));
    }

    /**
     * ==============================
     * UPDATE
     * ==============================
     */
    public function update(Request $request, $id)
    {
        $item = DataProduk::findOrFail($id);

        $request->validate([
            'unit_id'  => 'required|exists:units,id',
            'periode'  => 'required|date_format:Y-m',
            'kode'     => 'required|string|unique:data_produk,kode,'.$id.',id,periode,'.$request->periode.',unit_id,'.$request->unit_id,
            'jenis'    => 'required|string',
            'label'    => 'required|string',
            'satuan'   => 'required|string',
            'harga'    => 'required|numeric|min:0',
            'min_stok' => 'required|integer|min:0',
            'sld_awal' => 'required|integer|min:0',
            'opname'   => 'required|integer|min:0',
            'selisih'  => 'nullable|integer',   // tambahkan
            'nilai'    => 'nullable|numeric|min:0',   // atau 'nullable|decimal:0,2' jika ingin batasi desimal
        ]);

        $data = $request->only([
            'unit_id','periode','kode','jenis','label','satuan','harga',
            'min_stok','sld_awal','opname','selisih','nilai'
        ]);

        $item->update($data);

        $this->syncPakaiFromPemakaian($request->periode, $request->unit_id);
        $this->syncTerimaFromPenerimaan($request->periode, $request->unit_id);
        // === TAMBAHKAN INI ===
        $this->syncSldAwalFromOpname($request->periode, $request->unit_id);

        return redirect()->route('data_produk.index', [
            'periode' => $request->periode,
            'unit_id' => $request->unit_id
        ])->with('success', 'Data rekap stok berhasil diperbarui!');
    }

    /**
     * ==============================
     * DELETE
     * ==============================
     */
    public function destroy($id)
    {
        $item = DataProduk::findOrFail($id);

        $params = [
            'periode' => $item->periode,
            'unit_id' => $item->unit_id
        ];

        $item->delete();

        return redirect()->route('data_produk.index', $params)
            ->with('success', 'Data berhasil dihapus!');
    }

    /**
     * ==============================
     * SYNC TERIMA
     * ==============================
     */
    public static function syncTerimaFromPenerimaan(string $periode, ?int $unitId = null)
{
    $query = PenerimaanProduk::select('label', DB::raw('COALESCE(SUM(jumlah),0) as total'))
        ->whereRaw("DATE_FORMAT(tanggal, '%Y-%m') = ?", [$periode]);

    if ($unitId) $query->where('unit_id', $unitId);

    $penerimaans = $query->groupBy('label')->pluck('total', 'label');

    $dataQuery = DataProduk::where('periode', $periode);
    if ($unitId) $dataQuery->where('unit_id', $unitId);

    $dataQuery->chunkById(200, function ($records) use ($penerimaans) {
        foreach ($records as $record) {
            $baru = $penerimaans->get($record->label, 0);

            $changed = false;

            if ($record->terima != $baru) {
                $record->terima = $baru;
                $changed = true;
            }

            // ==================== PERBAIKAN ====================
            // Hitung saldo teoritis (saldo akhir menurut sistem)
            $stokTeoritis = (int)$record->sld_awal 
                          + (int)$record->terima 
                          - (int)$record->pakai;

            $sldAkhirBaru = max(0, $stokTeoritis);
            // ===================================================

            // Simpan jika ada perubahan
            if ($changed || $record->sld_akhir != $sldAkhirBaru) {
                $record->sld_akhir = $sldAkhirBaru;
                $record->saveQuietly();
            }
        }
    });
}



    private function syncPakaiFromPemakaian(string $periode, ?int $unitId = null)
{
    $query = PemakaianProduk::select('label', DB::raw('COALESCE(SUM(jumlah),0) as total'))
        ->whereRaw("DATE_FORMAT(tanggal, '%Y-%m') = ?", [$periode]);

    if ($unitId) $query->where('unit_id', $unitId);

    $pemakaian = $query->groupBy('label')->pluck('total', 'label');

    $dataQuery = DataProduk::where('periode', $periode);
    if ($unitId) $dataQuery->where('unit_id', $unitId);

    $dataQuery->chunkById(200, function ($records) use ($pemakaian) {
        foreach ($records as $record) {
            $baru = $pemakaian->get($record->label, 0);

            $changed = false;

            if ($record->pakai != $baru) {
                $record->pakai = $baru;
                $changed = true;
            }

            // ==================== PERBAIKAN ====================
            $stokTeoritis = (int)$record->sld_awal 
                          + (int)$record->terima 
                          - (int)$record->pakai;

            $sldAkhirBaru = max(0, $stokTeoritis);
            // ===================================================

            if ($changed || $record->sld_akhir != $sldAkhirBaru) {
                $record->sld_akhir = $sldAkhirBaru;
                $record->saveQuietly();
            }
        }
    });
}

    /**
     * ==============================
     * SYNC SALDO AWAL
     * ==============================
     */
    private function syncSaldoAwalFromPrevious(string $periode, ?int $unitId = null)
{
    $prevPeriode = Carbon::createFromFormat('Y-m', $periode)
        ->subMonth()
        ->format('Y-m');

    Log::info("=== MULAI SYNC SALDO AWAL ===");
    Log::info("Periode saat ini: {$periode} | Unit: " . ($unitId ?? 'ALL'));

    $prevQuery = DataProduk::where('periode', $prevPeriode);
    if ($unitId) $prevQuery->where('unit_id', $unitId);

    // Ambil opname DAN sld_akhir sekaligus
    $prevData = $prevQuery
        ->get(['kode', 'opname', 'sld_akhir'])
        ->mapWithKeys(function ($item) {
            // Prioritas: opname jika ada (dan bukan 0), kalau tidak pakai sld_akhir
            $nilai = ($item->opname !== null && $item->opname != 0)
                ? $item->opname
                : $item->sld_akhir;

            return [$item->kode => (int) $nilai];
        });

    if ($prevData->isEmpty()) {
        Log::info("Tidak ada data di periode sebelumnya {$prevPeriode}. Saldo awal tetap 0.");
        return;
    }

    Log::info("Nilai yang akan digunakan sebagai saldo awal (kode => nilai): " . json_encode($prevData->toArray()));

    $currentQuery = DataProduk::where('periode', $periode);
    if ($unitId) $currentQuery->where('unit_id', $unitId);

    $updated = 0;

    $currentQuery->chunkById(200, function ($records) use ($prevData, &$updated) {
        foreach ($records as $record) {
            $nilaiBaru = $prevData->get($record->kode, 0);

            if ($record->sld_awal != $nilaiBaru) {
                $record->sld_awal = $nilaiBaru;
                // Reset sld_akhir ke nilai pembukaan (akan di-update lagi oleh sync terima/pakai)
                $record->sld_akhir = $nilaiBaru;
                $record->saveQuietly();
                $updated++;
                
                Log::info("Update kode {$record->kode}: sld_awal & sld_akhir → {$nilaiBaru} (dari " . 
                           ($record->opname ? 'opname' : 'sld_akhir') . " bulan sebelumnya)");
            }
        }
    });

    Log::info("Sync saldo awal selesai. Total record di-update: {$updated}");
}

    /**
     * ==============================
     * GENERATE TEMPLATE
     * ==============================
     */
    /**
 * ==============================
 * GENERATE TEMPLATE (Support User + Admin)
 * ==============================
 */
/**
 * ==============================
 * GENERATE TEMPLATE (Support User + Admin)
 * ==============================
 */
public function generateTemplate(Request $request)
{
    $user = Auth::user();

    $request->validate([
        'unit_id'  => 'required|integer|exists:units,id',
        'periode'  => 'required|date_format:Y-m'
    ]);

    $unitId  = (int) $request->unit_id;
    $periode = $request->periode;

    // ======================
    // AUTHORIZATION CHECK
    // ======================
    if (!$user->isAdminUser()) {
        if (empty($user->bimba_unit)) {
            return redirect()->back()->with('error', 'Unit Anda tidak terdeteksi. Silakan hubungi admin.');
        }

        $userUnit = Unit::where('biMBA_unit', $user->bimba_unit)->first();

        if (!$userUnit || $userUnit->id !== $unitId) {
            return redirect()->back()->with('error', 'Anda tidak memiliki izin untuk generate template unit ini.');
        }
    }

    // ======================
    // PROSES GENERATE
    // ======================
    $produks = Produk::where('pendataan', 1)
        ->orderBy('kode')
        ->get();

    if ($produks->isEmpty()) {
        return redirect()->back()->with('error', 'Tidak ada produk master yang diset untuk pendataan.');
    }

    // 1. Sync saldo awal dari bulan sebelumnya
    $this->syncSaldoAwalFromPrevious($periode, $unitId);

    $updated = 0;
    $created = 0;

    $prevPeriode = Carbon::createFromFormat('Y-m', $periode)
                         ->subMonth()
                         ->format('Y-m');

    // Ambil data saldo awal dari periode sebelumnya
    $prevData = DataProduk::where('periode', $prevPeriode)
        ->where('unit_id', $unitId)
        ->get(['kode', 'opname', 'sld_akhir'])
        ->mapWithKeys(function ($item) {
            $nilai = ($item->opname !== null && $item->opname != 0)
                        ? (int) $item->opname 
                        : (int) $item->sld_akhir;
            return [$item->kode => $nilai];
        });

    foreach ($produks as $produk) {
        $record = DataProduk::firstOrNew([
            'kode'     => $produk->kode,
            'periode'  => $periode,
            'unit_id'  => $unitId,
        ]);

        $sldAwalBaru = $prevData->get($produk->kode, 0);

        if ($record->exists) {
            // Update existing record
            $record->jenis     = $produk->jenis;
            $record->label     = $produk->label;
            $record->satuan    = $produk->satuan;
            $record->harga     = $produk->harga;
            $record->min_stok  = $produk->min_stok ?? 10;   // Ambil dari master jika ada
            $record->sld_awal  = $sldAwalBaru;
            $record->sld_akhir = $sldAwalBaru;
            $record->saveQuietly();
            $updated++;
        } else {
            // Create new record
            $record->fill([
                'jenis'     => $produk->jenis,
                'label'     => $produk->label,
                'satuan'    => $produk->satuan,
                'harga'     => $produk->harga,
                'min_stok'  => $produk->min_stok ?? 10,     // Ambil dari master
                'sld_awal'  => $sldAwalBaru,
                'sld_akhir' => $sldAwalBaru,
                'terima'    => 0,
                'pakai'     => 0,
                'opname' => null,
            ]);
            $record->save();
            $created++;
        }
    }

    // 3. Sync transaksi bulan ini
    $this->syncTerimaFromPenerimaan($periode, $unitId);
    $this->syncPakaiFromPemakaian($periode, $unitId);
    $this->syncSldAwalFromOpname($periode, $unitId);   // tambahkan

    $total = $created + $updated;
    $message = $total > 0 
        ? "✅ Generate template berhasil. Baru: {$created}, Di-update: {$updated}." 
        : "✅ Semua produk sudah ada dan telah disinkronkan.";

    return redirect()->route('data_produk.index', [
        'periode' => $periode,
        'unit_id' => $unitId
    ])->with('success', $message);
}

    /**
     * ==============================
     * MANUAL REFRESH
     * ==============================
     */
    public function refreshTerima(Request $request)
    {
        $request->validate([
            'periode' => 'required|date_format:Y-m',
            'unit_id' => 'nullable|exists:units,id'
        ]);

        $this->syncTerimaFromPenerimaan($request->periode, $request->unit_id);

        return redirect()->route('data_produk.index', $request->only(['periode','unit_id']))
            ->with('success', 'Kolom TERIMA berhasil diperbarui!');
    }

    public function refreshPakai(Request $request)
    {
        $request->validate([
            'periode' => 'required|date_format:Y-m',
            'unit_id' => 'nullable|exists:units,id'
        ]);

        $this->syncPakaiFromPemakaian($request->periode, $request->unit_id);

        return redirect()->route('data_produk.index', $request->only(['periode','unit_id']))
            ->with('success', 'Kolom PAKAI berhasil diperbarui!');
    }

   /**
 * ==========================================
 * SYNC STOCK OPNAME + SALDO SISTEM
 * ==========================================
 *
 * Logic:
 *
 * saldo_sistem =
 * sld_awal + terima - pakai
 *
 * Jika BELUM opname:
 * - sld_akhir mengikuti saldo sistem
 *
 * Jika SUDAH opname:
 * - sld_akhir mengikuti stok fisik opname
 *
 * selisih =
 * fisik - saldo_sistem
 *
 * ==========================================
 */
private function syncSldAwalFromOpname(string $periode, ?int $unitId = null)
{
    $query = DataProduk::where('periode', $periode);

    if ($unitId) {
        $query->where('unit_id', $unitId);
    }

    $query->chunkById(200, function ($records) {

        foreach ($records as $record) {

            /*
            =====================================
            SALDO SISTEM / TEORITIS
            =====================================
            */

            $saldoSistem =
                (int) $record->sld_awal +
                (int) $record->terima -
                (int) $record->pakai;

            $saldoSistem = max(0, $saldoSistem);

            /*
            =====================================
            CEK SUDAH OPNAME ATAU BELUM
            =====================================

            NULL = belum opname

            Jika Anda memakai default 0
            di database, maka:
            > 0 dianggap sudah opname
            */

            $hasOpname =
                $record->opname !== null &&
                (int) $record->opname > 0;

            /*
            =====================================
            STOK FISIK
            =====================================
            */

            if ($hasOpname) {

                $fisik = (int) $record->opname;

            } else {

                /*
                =================================
                BELUM OPNAME
                gunakan saldo sistem
                =================================
                */

                $fisik = $saldoSistem;
            }

            /*
            =====================================
            SELISIH
            =====================================
            */

            $selisih = $fisik - $saldoSistem;

            /*
            =====================================
            NILAI SELISIH
            =====================================
            */

            $nilai =
                abs($selisih) *
                (int) $record->harga;

            /*
            =====================================
            STATUS
            =====================================
            */

            if (!$hasOpname) {

                $status = 'BELUM OPNAME';

            } elseif ($selisih == 0) {

                $status = 'COCOK';

            } elseif ($selisih > 0) {

                $status = 'LEBIH';

            } else {

                $status = 'KURANG';
            }

            /*
            =====================================
            SALDO AKHIR FINAL
            =====================================

            - sebelum opname:
              ikut saldo sistem

            - sesudah opname:
              ikut stok fisik
            */

            $record->sld_akhir = $fisik;

            /*
            =====================================
            SIMPAN DATA
            =====================================
            */

            $record->saldo_sistem = $saldoSistem;

            $record->selisih = $selisih;

            $record->nilai = $nilai;

            $record->adjustment_status = $status;

            $record->saveQuietly();
        }
    });
}
public function adjustment(Request $request, $id)
{
    $request->validate([
        'jenis_adjustment' => 'required|string',
        'qty_adjustment'   => 'required|integer|min:1',
        'keterangan'       => 'nullable|string',
    ]);

    DB::beginTransaction();

    try {

        $item = DataProduk::lockForUpdate()->findOrFail($id);

        $qty   = (int) $request->qty_adjustment;
        $jenis = $request->jenis_adjustment;

        /*
        =====================================
        STOK SISTEM TERKINI
        =====================================

        Gunakan sld_akhir karena:
        - sudah termasuk adjustment sebelumnya
        - lebih akurat daripada hitung ulang dari sld_awal
        */

        $stokSistem = (int) $item->sld_akhir;

        /*
        =====================================
        STOK FISIK HASIL OPNAME
        =====================================
        */

        $fisik = (int) $item->opname;

        /*
        =====================================
        SIMPAN NILAI SEBELUM
        =====================================
        */

        $stokSebelum   = $stokSistem;
        $fisikSebelum  = $fisik;
        $selisihAwal   = $fisik - $stokSistem;

        /*
        =====================================
        PROSES ADJUSTMENT
        =====================================
        */

        switch ($jenis) {

            /*
            =====================================
            BARANG HILANG / RUSAK / REJECT

            Yang berubah:
            - stok sistem turun

            Yang TIDAK berubah:
            - fisik

            Karena fisik opname sudah mencerminkan
            kondisi real di lapangan.
            =====================================
            */

            case 'hilang':
            case 'rusak':
            case 'reject':

                $stokSistem -= $qty;

                break;

            /*
            =====================================
            BARANG DITEMUKAN KEMBALI / SELIP

            Yang berubah:
            - stok sistem naik
            - fisik ikut naik

            Karena barang fisik memang ditemukan.
            =====================================
            */

            case 'ditemukan_kembali':
            case 'selip':

                $stokSistem += $qty;

                $fisik += $qty;

                break;

            default:

                throw new \Exception(
                    'Jenis adjustment tidak valid.'
                );
        }

        /*
        =====================================
        CEGAH NILAI MINUS
        =====================================
        */

        if ($stokSistem < 0) {
            $stokSistem = 0;
        }

        if ($fisik < 0) {
            $fisik = 0;
        }

        /*
        =====================================
        HITUNG ULANG SELISIH
        =====================================
        */

        $selisihBaru = $fisik - $stokSistem;

        /*
        =====================================
        HITUNG NILAI SELISIH
        =====================================
        */

        $nilaiSelisih =
            abs($selisihBaru) * (int) $item->harga;

        /*
        =====================================
        STATUS ADJUSTMENT
        =====================================
        */

        if ($selisihBaru == 0) {

            $status = 'COCOK';

        } elseif ($selisihBaru > 0) {

            $status = 'LEBIH';

        } else {

            $status = 'KURANG';
        }

        /*
        =====================================
        UPDATE DATA PRODUK
        =====================================

        NOTE:
        - JANGAN ubah sld_awal
        - karena itu histori awal bulan
        */

        $item->sld_akhir = $stokSistem;

        $item->opname = $fisik;

        $item->selisih = $selisihBaru;

        $item->nilai = $nilaiSelisih;

        $item->adjustment_status = $status;

        $item->adjustment_qty = $qty;

        $item->adjustment_type = $jenis;

        $item->adjustment_note = $request->keterangan;

        $item->adjustment_at = now();

        $item->adjustment_by = Auth::id();

        $item->save();

        /*
        =====================================
        SIMPAN HISTORY ADJUSTMENT
        =====================================
        */

        DB::table('data_produk_adjustments')->insert([

            'data_produk_id'   => $item->id,

            'kode'             => $item->kode,

            'jenis_adjustment' => $jenis,

            'qty_adjustment'   => $qty,

            /*
            ===============================
            DATA SEBELUM
            ===============================
            */

            'stok_sebelum'     => $stokSebelum,

            'fisik_sebelum'    => $fisikSebelum,

            'selisih_sebelum'  => $selisihAwal,

            /*
            ===============================
            DATA SESUDAH
            ===============================
            */

            'stok_sesudah'     => $stokSistem,

            'fisik_sesudah'    => $fisik,

            'selisih_sesudah'  => $selisihBaru,

            /*
            ===============================
            INFO
            ===============================
            */

            'keterangan'       => $request->keterangan,

            'user_id'          => Auth::id(),

            'created_at'       => now(),

            'updated_at'       => now(),
        ]);

        /*
        =====================================
        COMMIT
        =====================================
        */

        DB::commit();

        
        return back()->with(
            'success',
            'Adjustment berhasil. Selisih sekarang: ' . $selisihBaru
        );

    } catch (\Throwable $e) {

        DB::rollBack();

        Log::error('ERROR ADJUSTMENT', [

            'message' => $e->getMessage(),

            'line'    => $e->getLine(),

            'file'    => $e->getFile(),
        ]);

        return back()->with(
            'error',
            'Adjustment gagal: ' . $e->getMessage()
        );
    }
}
}
