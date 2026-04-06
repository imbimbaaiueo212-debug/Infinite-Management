<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\DataProduk;
use App\Models\Produk;
use App\Models\PenerimaanProduk;
use App\Models\PemakaianProduk;
use App\Models\Unit;
use Illuminate\Support\Facades\DB;
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
    \Log::info("[SYNC START] periode: {$periode}, unit: {$unitId}");

    // 1. Sync saldo awal DARI BULAN SEBELUMNYA → harus paling dulu
    $this->syncSaldoAwalFromPrevious($periode, $unitId);

    // 2. Sync penerimaan bulan ini
    $this->syncTerimaFromPenerimaan($periode, $unitId);

    // 3. Sync pemakaian bulan ini
    $this->syncPakaiFromPemakaian($periode, $unitId);

    \Log::info("[SYNC FINISH] periode: {$periode}, unit: {$unitId}");

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
                   ->paginate(20)
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

        $produks = Produk::where('pendataan', 1)
            ->orderBy('kode')
            ->get(['kode', 'label', 'jenis', 'satuan', 'harga']);

        return view('data_produk.create', compact('produks', 'units'));
    }

    /**
     * ==============================
     * STORE
     * ==============================
     */
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
            'unit_id','periode','kode','jenis','label','satuan','harga',
            'min_stok','sld_awal','opname'
        ]);

        $data['terima'] = 0;
        $data['pakai']  = 0;

        DataProduk::create($data);

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

            // Selalu hitung ulang sld_akhir berdasarkan data terkini
            $sldAkhirBaru = $record->sld_awal + $record->terima - $record->pakai;

            // Simpan jika ada perubahan pada terima ATAU sld_akhir tidak sesuai
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

            // Selalu hitung ulang sld_akhir
            $sldAkhirBaru = $record->sld_awal + $record->terima - $record->pakai;

            // Simpan jika ada perubahan pada pakai ATAU sld_akhir tidak sesuai
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

    \Log::info("=== MULAI SYNC SALDO AWAL ===");
    \Log::info("Periode saat ini: {$periode} | Unit: " . ($unitId ?? 'ALL'));

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
        \Log::info("Tidak ada data di periode sebelumnya {$prevPeriode}. Saldo awal tetap 0.");
        return;
    }

    \Log::info("Nilai yang akan digunakan sebagai saldo awal (kode => nilai): " . json_encode($prevData->toArray()));

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
                
                \Log::info("Update kode {$record->kode}: sld_awal & sld_akhir → {$nilaiBaru} (dari " . 
                           ($record->opname ? 'opname' : 'sld_akhir') . " bulan sebelumnya)");
            }
        }
    });

    \Log::info("Sync saldo awal selesai. Total record di-update: {$updated}");
}

    /**
     * ==============================
     * GENERATE TEMPLATE
     * ==============================
     */
    public function generateTemplate(Request $request)
{
    $request->validate([
        'unit_id' => 'required|exists:units,id',
        'periode' => 'required|date_format:Y-m'
    ]);

    $unitId  = $request->unit_id;
    $periode = $request->periode;

    $produks = Produk::where('pendataan', 1)
        ->orderBy('kode')
        ->get();

    if ($produks->isEmpty()) {
        return redirect()->back()->with('error', 'Tidak ada produk master yang diset untuk pendataan.');
    }

    // 1. Paksa sync saldo awal dulu (dengan prioritas opname)
    $this->syncSaldoAwalFromPrevious($periode, $unitId);

    $updated = 0;
    $created = 0;

    $prevPeriode = Carbon::createFromFormat('Y-m', $periode)->subMonth()->format('Y-m');

    // 2. Ambil nilai starting point dengan prioritas: opname > sld_akhir
    $prevData = DataProduk::where('periode', $prevPeriode)
        ->where('unit_id', $unitId)
        ->get(['kode', 'opname', 'sld_akhir'])
        ->mapWithKeys(function ($item) {
            // Jika ada opname yang valid (bukan null dan bukan 0), gunakan itu
            if ($item->opname !== null && $item->opname != 0) {
                return [$item->kode => (int) $item->opname];
            }
            // Jika tidak, gunakan sld_akhir (hitungan sistem)
            return [$item->kode => (int) $item->sld_akhir];
        });

    foreach ($produks as $produk) {
        $record = DataProduk::firstOrNew([
            'kode'     => $produk->kode,
            'periode'  => $periode,
            'unit_id'  => $unitId,
        ]);

        // Ambil nilai yang sudah mempertimbangkan opname
        $sldAwalBaru = $prevData->get($produk->kode, 0);

        $record->sld_awal = $sldAwalBaru;
        $record->sld_akhir = $sldAwalBaru;  // starting point sebelum transaksi bulan ini

        if ($record->exists) {
            $record->jenis     = $produk->jenis;
            $record->label     = $produk->label;
            $record->satuan    = $produk->satuan;
            $record->harga     = $produk->harga;
            $record->min_stok  = 10;
            $record->saveQuietly();
            $updated++;
        } else {
            $record->fill([
                'jenis'     => $produk->jenis,
                'label'     => $produk->label,
                'satuan'    => $produk->satuan,
                'harga'     => $produk->harga,
                'min_stok'  => 10,
                'pakai'     => 0,
                'terima'    => 0,
                'opname'    => 0,
                'sld_awal'  => $sldAwalBaru,
                'sld_akhir' => $sldAwalBaru,
            ]);
            $record->save();
            $created++;
        }
    }

    // 3. Update transaksi bulan berjalan (ini akan mengubah sld_akhir lagi)
    $this->syncTerimaFromPenerimaan($periode, $unitId);
    $this->syncPakaiFromPemakaian($periode, $unitId);

    // Opsional: sync ulang saldo awal (untuk konsistensi maksimal, tapi biasanya sudah cukup dari langkah 1)
    // $this->syncSaldoAwalFromPrevious($periode, $unitId);

    $message = ($created + $updated) > 0
        ? "Generate berhasil: {$created} produk baru dibuat, {$updated} di-update. Saldo awal menggunakan opname jika ada."
        : "Semua produk sudah ada dan sinkron. Tidak ada perubahan.";

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
}
