<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\OrderModul;
use App\Models\DataProduk;
use App\Models\Produk;
use App\Models\Unit;
use App\Imports\OrderModulImport;
use Maatwebsite\Excel\Facades\Excel;
use Carbon\Carbon;

class OrderModulController extends Controller
{
    private $allowedJenis = [
        'Modul Baca',
        'Modul Tulis',
        'Modul Matematika',
        'Modul Dikte',
        'Modul Huruf Sambung',
        'Modul SD',
        'Modul Evaluasi',
        'Modul Mewarnai',
        'Hore Aku Bisa Baca',
        'Modul Eksklusif',
        'Kaos Anak',
        'Administrasi',
        'ATK biMBA',
        'Attribut Promosi',
        'Furniture',
        'Souvenir',
        'Buku Cerita Anak Bilingual',
        'Pic Card',
        'Fun Worksheet',
        'ATK English',
    ];

    private function getStatusStokMap($periodeRekap, $unitId = null)
    {
        $query = DataProduk::where('periode', $periodeRekap);

        if ($unitId) {
            $query->where('unit_id', $unitId);
        }

        return $query->get(['label', 'sld_awal', 'terima', 'pakai', 'min_stok'])
            ->mapWithKeys(function ($item) {
                if (empty($item->label)) return [];

                $sld_akhir = $item->sld_awal + $item->terima - $item->pakai;
                $status = $sld_akhir >= $item->min_stok ? 1 : 0;

                return [$item->label => $status];
            })
            ->filter()
            ->toArray();
    }

    public function getStatusStok(Request $request)
    {
        try {
            $periodeRekap = $request->query('periode_rekap');
            $unitId = $request->query('unit_id');

            if (!$periodeRekap || !preg_match('/^\d{4}-\d{2}$/', $periodeRekap)) {
                return response()->json(['error' => 'Periode rekap tidak valid'], 400);
            }

            if ($unitId && !is_numeric($unitId)) {
                return response()->json(['error' => 'Unit ID tidak valid'], 400);
            }

            $unitId = $unitId ? (int)$unitId : null;

            $statusStokMap = $this->getStatusStokMap($periodeRekap, $unitId);

            $carbon = Carbon::createFromFormat('Y-m', $periodeRekap);
            $formatted = $carbon ? $carbon->translatedFormat('F Y') : 'Periode tidak valid';

            return response()->json([
                'status_stok' => $statusStokMap,
                'periode_formatted' => $formatted
            ]);

        } catch (\Exception $e) {
            \Log::error('Error getStatusStok: ' . $e->getMessage());
            return response()->json(['error' => 'Terjadi kesalahan server'], 500);
        }
    }

    private function getHargaMap()
    {
        return Produk::whereIn('jenis', $this->allowedJenis)
                     ->pluck('harga', 'label')
                     ->toArray();
    }

    public function index(Request $request)
    {
        $kode         = $request->input('kode');
        $minggu       = $request->input('minggu');
        $tahun        = $request->input('tahun', date('Y'));
        $periodeRekap = $request->input('periode_rekap', now()->format('Y-m'));
        $unitId       = $request->input('unit_id');

        $user = auth()->user();

        $query = OrderModul::with('unit')
                           ->whereYear('tanggal_order', $tahun)
                           ->whereNotNull('unit_id');  // ← KRITIS: hilangkan order tanpa unit

        // Non-admin: paksa pakai unit login sendiri
        if (!$user->is_admin) {
            $userUnit = $user->bimba_unit;
            if ($userUnit) {
                $unit = Unit::where('biMBA_unit', $userUnit)->first();
                if ($unit) {
                    $query->where('unit_id', $unit->id);
                } else {
                    // Jika unit login tidak ditemukan → kosongkan hasil
                    $query->where('unit_id', 0); // trik agar tidak ada data
                }
            }
        } 
        // Admin: pakai filter request jika ada
        else {
            if ($request->filled('unit_id') && is_numeric($unitId)) {
                $query->where('unit_id', $unitId);
            }
            // Jika admin pilih "Semua Unit" (kosong) → semua unit OK (tapi null sudah di-exclude)
        }

        // Filter kode
        if ($kode) {
            $query->where(function ($q) use ($kode) {
                for ($i = 1; $i <= 5; $i++) {
                    $q->orWhere('kode' . $i, 'like', '%' . $kode . '%');
                }
            });
        }

        // Filter minggu
        if ($minggu) {
            $query->whereNotNull('kode' . $minggu);
        }

        $orders = $query->orderByDesc('tanggal_order')->get();

        $units = Unit::orderBy('no_cabang')->get();

        $produks = Produk::whereIn('jenis', $this->allowedJenis)
                         ->orderBy('kode')
                         ->get(['kode', 'label', 'jenis', 'harga']);

        // Hitung total
        $totalHrgPerMinggu = [];
        for ($i = 1; $i <= 5; $i++) {
            $sumQuery = OrderModul::whereYear('tanggal_order', $tahun)
                                  ->whereNotNull('unit_id');
            if (!$user->is_admin) {
                $unit = Unit::where('biMBA_unit', $user->bimba_unit)->first();
                if ($unit) $sumQuery->where('unit_id', $unit->id);
            } elseif ($request->filled('unit_id')) {
                $sumQuery->where('unit_id', $unitId);
            }
            $totalHrgPerMinggu['hrg' . $i] = $sumQuery->sum('hrg' . $i);
        }
        $grandTotalTahun = array_sum($totalHrgPerMinggu);

        $totalOrderPerMinggu = [];
        for ($i = 1; $i <= 5; $i++) {
            $countQuery = OrderModul::whereYear('tanggal_order', $tahun)
                                    ->whereNotNull('unit_id')
                                    ->whereNotNull('kode' . $i)
                                    ->where('jml' . $i, '>', 0);
            if (!$user->is_admin) {
                $unit = Unit::where('biMBA_unit', $user->bimba_unit)->first();
                if ($unit) $countQuery->where('unit_id', $unit->id);
            } elseif ($request->filled('unit_id')) {
                $countQuery->where('unit_id', $unitId);
            }
            $totalOrderPerMinggu['minggu' . $i] = $countQuery->count();
        }

        $totalOrderTahunQuery = OrderModul::whereYear('tanggal_order', $tahun)
                                          ->whereNotNull('unit_id');
        if (!$user->is_admin) {
            $unit = Unit::where('biMBA_unit', $user->bimba_unit)->first();
            if ($unit) $totalOrderTahunQuery->where('unit_id', $unit->id);
        } elseif ($request->filled('unit_id')) {
            $totalOrderTahunQuery->where('unit_id', $unitId);
        }
        $totalOrderTahun = $totalOrderTahunQuery->count();

        return view('order_modul.index', compact(
            'orders', 'units', 'totalHrgPerMinggu', 'totalOrderPerMinggu',
            'grandTotalTahun', 'totalOrderTahun', 'kode', 'minggu', 'tahun',
            'periodeRekap', 'produks'
        ));
    }

    public function create()
{
    $units = Unit::orderBy('no_cabang')->get();

    $statusStokMap = [];
    $periodeRekap = now()->format('Y-m');
    $hargaMap = $this->getHargaMap();

    $produks = collect();

    // Hitung minggu default berdasarkan hari ini
    $defaultTanggal = now();
    $mingguDefault = $defaultTanggal->weekOfMonth; // 1 sampai 5 atau 6

    return view('order_modul.create', compact(
        'produks',
        'units',
        'statusStokMap',
        'periodeRekap',
        'hargaMap',
        'mingguDefault' // ← kirim ke view
    ));
}

    public function store(Request $request)
    {
        $request->validate([
            'tanggal_order' => 'required|date',
            'unit_id'       => 'required|exists:units,id', // Wajib pilih unit
        ]);

        $data = $request->all();
        $periodeRekap = Carbon::parse($data['tanggal_order'])->format('Y-m');

        $statusStokMap = $this->getStatusStokMap($periodeRekap);
        $hargaMap = $this->getHargaMap();

        // Hitung total harga dan status stok untuk setiap slot (1-5)
        for ($i = 1; $i <= 5; $i++) {
            $kode = $data['kode' . $i] ?? null;
            $jml  = (int) ($data['jml' . $i] ?? 0);

            $hargaSatuan = $hargaMap[$kode] ?? 0;
            $data['hrg' . $i] = $jml * $hargaSatuan;

            $data['sts' . $i] = ($kode && isset($statusStokMap[$kode]))
                ? $statusStokMap[$kode]
                : null;
        }

        OrderModul::create($data);

        return redirect()->route('order_modul.index')
                         ->with('success', 'Order modul berhasil disimpan!');
    }

    public function edit($id)
{
    $order = OrderModul::with('unit')->findOrFail($id);
    $units = Unit::orderBy('no_cabang')->get();

    $statusStokMap = [];
    $periodeRekap = Carbon::parse($order->tanggal_order)->format('Y-m');
    $hargaMap = $this->getHargaMap();

    $produks = collect();  // kosong dulu, load via AJAX

    return view('order_modul.edit', compact(
        'order',
        'produks',
        'units',
        'statusStokMap',
        'periodeRekap',
        'hargaMap'
    ));
}

    public function update(Request $request, $id)
    {
        $order = OrderModul::findOrFail($id);

        $request->validate([
            'tanggal_order' => 'required|date',
            'unit_id'       => 'required|exists:units,id',
        ]);

        $data = $request->all();
        $periodeRekap = Carbon::parse($data['tanggal_order'])->format('Y-m');

        $statusStokMap = $this->getStatusStokMap($periodeRekap);
        $hargaMap = $this->getHargaMap();

        for ($i = 1; $i <= 5; $i++) {
            $kode = $data['kode' . $i] ?? null;
            $jml  = (int) ($data['jml' . $i] ?? 0);

            $hargaSatuan = $hargaMap[$kode] ?? 0;
            $data['hrg' . $i] = $jml * $hargaSatuan;

            $data['sts' . $i] = ($kode && isset($statusStokMap[$kode]))
                ? $statusStokMap[$kode]
                : null;
        }

        $order->update($data);

        return redirect()->route('order_modul.index')
                         ->with('success', 'Order modul berhasil diperbarui!');
    }

    public function destroy($id)
    {
        $order = OrderModul::findOrFail($id);
        $order->delete();

        return redirect()->route('order_modul.index')
                         ->with('success', 'Data order modul berhasil dihapus!');
    }

    public function import(Request $request)
    {
        $request->validate([
            'file' => 'required|mimes:xlsx,xls,csv'
        ]);

        try {
            Excel::import(new OrderModulImport, $request->file('file'));

            // Set tanggal default jika kosong
            OrderModul::whereNull('tanggal_order')
                      ->update(['tanggal_order' => now()->format('Y-m-d')]);

            return redirect()->back()->with('success', 'Data berhasil diimport!');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Gagal import: ' . $e->getMessage());
        }
    }
    public function getProduksByUnit(Request $request)
{
    $unitId = $request->query('unit_id');

    if (!$unitId || !is_numeric($unitId)) {
        return response()->json(['produks' => []]);
    }

    $produks = Produk::where('unit_id', $unitId)  // atau where('bimba_unit', $unit->biMBA_unit)
                     ->whereIn('jenis', $this->allowedJenis)
                     ->orderBy('kode')
                     ->get(['kode', 'label', 'jenis', 'harga']);

    return response()->json([
        'produks' => $produks->map(function ($p) {
            return [
                'label' => $p->label,
                'jenis' => $p->jenis,
                'harga' => $p->harga,
            ];
        })
    ]);
}
}