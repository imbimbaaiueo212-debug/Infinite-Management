<?php

namespace App\Http\Controllers;

use App\Models\Penerimaan;
use App\Models\BukuInduk;
use App\Models\Unit;
use App\Imports\PenerimaanImport;
use App\Exports\PenerimaanExport;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Facades\Auth;
use App\Models\DaftarMuridDeposit;
use App\Models\HargaSaptataruna;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\Request;
use App\Models\VoucherLama;
use App\Models\VoucherHistori;
use Carbon\Carbon;
use Illuminate\Support\Str;
use App\Models\PindahGolongan;
use App\Traits\HasBulanIndo;
use Illuminate\Support\Facades\Schema;

class PenerimaanController extends Controller
{
    use HasBulanIndo;

    /**
     * Helper untuk membersihkan input mata uang berformat (e.g., "100.000") menjadi integer.
     * @param string|null $input
     * @return int
     */
    protected function cleanMoneyInput($input): int
    {
        return $input ? (int) str_replace(['.', ','], '', $input) : 0;
    }

    public function index(Request $request)
{
    /* ===============================
       PARAMETER FILTER
    =============================== */
    $perPage = (int) $request->input('per_page', 10);
    $search  = trim($request->input('search', ''));
    $bulan   = $request->input('bulan');
    $tahun   = $request->input('tahun');
    $bimbaUnit = $request->input('bimba_unit'); // ← Nama parameter sesuai kolom di penerimaan

    /* ===============================
       QUERY UTAMA
    =============================== */
    $query = Penerimaan::query();

    /* ===============================
       FILTER NIM | NAMA MURID
    =============================== */
    if (!empty($search)) {
        if (str_contains($search, '|')) {
            [$nim, $nama] = array_map('trim', explode('|', $search));
            $query->where('nim', $nim);
        } else {
            $query->where(function ($q) use ($search) {
                $q->where('nim', 'like', "%{$search}%")
                  ->orWhere('nama_murid', 'like', "%{$search}%");
            });
        }
    }

    /* ===============================
       FILTER BULAN & TAHUN
    =============================== */
    if ($bulan) {
        $query->where('bulan', $bulan);
    }

    if ($tahun) {
        $query->where('tahun', $tahun);
    }

    /* ===============================
       FILTER BIMBA UNIT (dari kolom bimba_unit)
    =============================== */
    if (!empty($bimbaUnit)) {
        $query->where('bimba_unit', $bimbaUnit);
    }

    /* ===============================
       CLONE UNTUK TOTAL
    =============================== */
    $queryForSum = clone $query;

    /* ===============================
       PAGINATION
    =============================== */
    $penerimaan = $query->orderBy('tanggal', 'desc')
        ->paginate($perPage)
        ->withQueryString();

    /* ===============================
       TOTAL PER KOMPONEN
    =============================== */
    $totalVoucher     = $queryForSum->sum('voucher');
    $totalSpp         = $queryForSum->sum('spp');
    $totalKaosPendek  = $queryForSum->sum('kaos');
    $totalKaosPanjang = $queryForSum->sum('kaos_lengan_panjang');
    $totalKpk         = $queryForSum->sum('kpk');
    $totalTas         = $queryForSum->sum('tas');
    $totalRbas        = $queryForSum->sum('RBAS');
    $totalBcabs01     = $queryForSum->sum('BCABS01');
    $totalBcabs02     = $queryForSum->sum('BCABS02');
    $totalSertifikat  = $queryForSum->sum('sertifikat');
    $totalStpb        = $queryForSum->sum('stpb');
    $totalEvent       = $queryForSum->sum('event');
    $totalLainLain    = $queryForSum->sum('lain_lain');

    /* ===============================
   RINGKASAN SPP
=============================== */
$bulanFilter = $bulan
    ? strtolower($bulan)
    : strtolower(now()->locale('id')->translatedFormat('F'));

$tahunFilter = $tahun ?? now()->year;

// Hitung akhir periode filter
$endOfPeriod = null;

if (!$bulan) {
    // Jika bulan kosong (semua bulan) → akhir tahun
    $endOfPeriod = \Carbon\Carbon::create($tahunFilter, 12, 31)->toDateString();
} else {
    // Ambil index bulan dari nama Indonesia
    $bulanMap = [
        'januari' => 1, 'februari' => 2, 'maret' => 3, 'april' => 4,
        'mei' => 5, 'juni' => 6, 'juli' => 7, 'agustus' => 8,
        'september' => 9, 'oktober' => 10, 'november' => 11, 'desember' => 12
    ];
    $monthNum = $bulanMap[strtolower($bulanFilter)] ?? 12;
    $endOfPeriod = \Carbon\Carbon::create($tahunFilter, $monthNum, 1)
                                 ->endOfMonth()
                                 ->toDateString();
}

// Ambil murid aktif/baru yang sudah bergabung sebelum/selama periode
$muridAktif = BukuInduk::whereIn(DB::raw('LOWER(status)'), ['aktif', 'baru'])
    ->where(function ($q) use ($endOfPeriod) {
        $q->whereNull('tgl_masuk')                    // kalau null, tetap hitung
          ->orWhere('tgl_masuk', '<=', $endOfPeriod); // join sebelum atau pada akhir periode
    })
    ->pluck('nim');

// Sudah bayar SPP di periode tersebut
$sudahBayar = Penerimaan::whereIn('nim', $muridAktif)
    ->whereRaw('LOWER(bulan) = ?', [$bulanFilter])
    ->where('tahun', $tahunFilter)
    ->where('spp', '>', 0)
    ->select('nim', 'spp')
    ->get();

// Belum bayar: murid aktif yang tidak ada di sudahBayar
$belumBayar = BukuInduk::whereIn('nim', $muridAktif)
    ->whereNotIn('nim', $sudahBayar->pluck('nim'))
    ->select('nim', 'nama as nama_murid')
    ->orderBy('nama_murid')
    ->get();

    /* ===============================
       DATA DROPDOWN
    =============================== */
    // Daftar murid aktif untuk dropdown filter nama
    $muridList = BukuInduk::whereIn(DB::raw('LOWER(status)'), ['aktif', 'baru'])
        ->orderBy('nama')
        ->select('nim', 'nama as nama_murid')
        ->get();

    // DROPDOWN BIMBA UNIT DARI TABEL MASTER UNITS
    $unitList = Unit::orderBy('biMBA_unit')
        ->get()
        ->pluck('label', 'biMBA_unit') // key = biMBA_unit (untuk filter), value = label cantik
        ->toArray();

    /* ===============================
       RETURN VIEW
    =============================== */
    return view('penerimaan.index', compact(
        'penerimaan',
        'totalVoucher',
        'totalSpp',
        'totalKaosPendek',
        'totalKaosPanjang',
        'totalKpk',
        'totalTas',
        'totalRbas',
        'totalBcabs01',
        'totalBcabs02',
        'totalSertifikat',
        'totalStpb',
        'totalEvent',
        'totalLainLain',
        'sudahBayar',
        'belumBayar',
        'muridList',
        'unitList',           // List unit dengan label cantik
        'perPage',
        'search',
        'bulan',
        'tahun',
        'bimbaUnit'           // Untuk retain selected di dropdown
    ));
}

public function updateUkuranKaos(Request $request)
{
    $request->validate([
        'id'    => 'required|integer|exists:penerimaan,id',
        'type'  => 'required|in:pendek,panjang',
        'ukuran'=> 'nullable|string|max:255',
    ]);

    $allowedSizes = ['KAS', 'KAM', 'KAL', 'KAXL', 'KAXXL', 'KAXXXL', 'KAXXXLS'];

    $ukuranString = null;

    if ($request->filled('ukuran')) {
        // Pisahkan berdasarkan koma
        $sizes = array_filter(array_map('trim', explode(',', strtoupper($request->ukuran))));

        // Validasi setiap ukuran
        foreach ($sizes as $size) {
            if (!in_array($size, $allowedSizes)) {
                return response()->json([
                    'success' => false,
                    'message' => "Ukuran tidak valid: {$size}. Pilih dari: " . implode(', ', $allowedSizes)
                ], 422);
            }
        }

        // Hilangkan duplikat dan urutkan (opsional, agar rapi)
        $sizes = array_unique($sizes);
        sort($sizes);

        // Gabung kembali jadi string koma
        $ukuranString = implode(',', $sizes);
    }
    // Jika ukuran kosong → simpan null (artinya hapus ukuran)

    try {
        $penerimaan = Penerimaan::findOrFail($request->id);

        if ($request->type === 'pendek') {
            $penerimaan->ukuran_kaos_pendek = $ukuranString;
        } else {
            $penerimaan->ukuran_kaos_panjang = $ukuranString;
        }

        $penerimaan->save();

        return response()->json([
            'success' => true,
            'ukuran'  => $ukuranString ?: '-'
        ]);

    } catch (\Exception $e) {
        \Log::error('Gagal update ukuran kaos: ' . $e->getMessage(), $request->all());

        return response()->json([
            'success' => false,
            'message' => 'Terjadi kesalahan server. Silakan coba lagi.'
        ], 500);
    }
}

    // FUNGSI BARU: Mengambil daftar bulan yang sudah dibayar via AJAX
    public function getPaidMonths(Request $request)
    {
        // Ambil NIM dan Tahun dari request query
        $nim = $request->query('nim');
        $tahun = $request->query('tahun');

        if (!$nim || !$tahun) {
            // Jika NIM atau Tahun tidak ada, kembalikan array kosong
            return response()->json([]);
        }

        // Cari semua bulan yang sudah dibayar untuk NIM dan Tahun tersebut
        // Pastikan kita hanya melihat pembayaran SPP (spp > 0)
        $paidMonths = Penerimaan::where('nim', $nim)
            ->where('tahun', $tahun)
            ->where('spp', '>', 0)
            ->pluck('bulan') // Ambil hanya nama bulannya
            ->unique()
            ->toArray();

        // Catatan: Nama bulan disimpan di database dalam format Indonesia (misal: "Januari")
        // JavaScript di frontend akan mencocokkan nilai ini.
        return response()->json($paidMonths);
    }


    public function create(Request $request)
{
    $murids = BukuInduk::whereIn(DB::raw('LOWER(status)'), ['aktif', 'baru'])
        ->get()
        ->map(function ($murid) {
            $nominal = $this->cleanMoneyInput($murid->spp);

            if ($nominal > 0 && $nominal < 1000) {
                $nominal = $nominal * 1000;
            }

            $murid->spp = $nominal;
            return $murid;
        });

    $nim = $request->nim ?? null;

    $vouchers = VoucherLama::query()
        ->whereNotNull('no_voucher')
        ->where('no_voucher', '<>', '')
        ->where('jumlah_voucher', '>', 0)
        ->when($nim, function ($q) use ($nim) {
            $q->where(function ($sub) use ($nim) {
                $sub->whereNull('nim')->orWhere('nim', $nim);
            });
        })
        ->orderBy('no_voucher')
        ->get();

    $sppLunas = Penerimaan::whereIn('nim', $murids->pluck('nim'))
        ->where('spp', '>', 0)
        ->select('nim', 'bulan', 'tahun')
        ->get()
        ->map(function ($item) {
            return strtolower("{$item->nim}-{$item->bulan}-{$item->tahun}");
        })
        ->unique()
        ->toArray();

    $unitOptions = BukuInduk::query()
        ->whereNotNull('bimba_unit')
        ->whereRaw("TRIM(COALESCE(bimba_unit,'')) <> ''")
        ->selectRaw('TRIM(bimba_unit) as bimba_unit')
        ->distinct()
        ->orderBy('bimba_unit')
        ->pluck('bimba_unit')
        ->toArray();

    $cabangOptions = BukuInduk::query()
        ->whereNotNull('no_cabang')
        ->whereRaw("TRIM(COALESCE(no_cabang,'')) <> ''")
        ->selectRaw('TRIM(no_cabang) as no_cabang')
        ->distinct()
        ->orderBy('no_cabang')
        ->pluck('no_cabang')
        ->toArray();

    return view('penerimaan.create', compact(
        'murids',
        'vouchers',
        'sppLunas',
        'unitOptions',
        'cabangOptions'
    ));
}

public function store(Request $request)
{
    // === Bersihkan semua nominal ===
    $spp               = $this->cleanMoneyInput($request->spp);
    $daftar            = $this->cleanMoneyInput($request->daftar);
    $kaosPendek        = $this->cleanMoneyInput($request->kaos_pendek);
    $kaosPanjang       = $this->cleanMoneyInput($request->kaos_panjang);
    $kpk               = $this->cleanMoneyInput($request->kpk);
    $sertifikat        = $this->cleanMoneyInput($request->sertifikat);
    $stpb              = $this->cleanMoneyInput($request->stpb);
    $tas               = $this->cleanMoneyInput($request->tas);
    $event             = $this->cleanMoneyInput($request->event);
    $lainLain          = $this->cleanMoneyInput($request->lain_lain);

    // === PROSES UKURAN KAOS ===
    $ukuranPendekInput = array_filter($request->input('ukuran_kaos_pendek', []));
    $ukuranKaosPendekString = !empty($ukuranPendekInput) ? implode(',', array_unique($ukuranPendekInput)) : null;

    $kaosPendekDetails = [];
    foreach ($ukuranPendekInput as $ukuran) {
        $kaosPendekDetails[$ukuran] = ($kaosPendekDetails[$ukuran] ?? 0) + 1;
    }
    $kaosPendekDetails = empty($kaosPendekDetails) ? null : $kaosPendekDetails;

    $ukuranPanjangInput = array_filter($request->input('ukuran_kaos_panjang', []));
    $ukuranKaosPanjangString = !empty($ukuranPanjangInput) ? implode(',', array_unique($ukuranPanjangInput)) : null;

    $kaosPanjangDetails = [];
    foreach ($ukuranPanjangInput as $ukuran) {
        $kaosPanjangDetails[$ukuran] = ($kaosPanjangDetails[$ukuran] ?? 0) + 1;
    }
    $kaosPanjangDetails = empty($kaosPanjangDetails) ? null : $kaosPanjangDetails;

    if ($spp > 0 && $spp < 1000) $spp *= 1000;

    // === VALIDASI ===
    $request->validate([
        'via'                => 'required|in:cash,transfer,edc',
        'tanggal'            => 'required|date',
        'nim'                => 'required|exists:buku_induk,nim',
        'nama_murid'         => 'required',
        'spp'                => 'required|numeric',
        'bukti_transfer'     => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:5120',
        'voucher'            => 'nullable|array',
        'voucher.*'          => 'string|distinct',
        'bulan_bayar.*'      => 'required_if:spp,>,0',
        'tahun_bayar.*'      => 'required_if:spp,>,0|numeric|min:2020',
    ]);

    // === DATA MURID ===
    $murid = BukuInduk::where('nim', $request->nim)->firstOrFail();

    $sppPerBulan = $this->cleanMoneyInput($murid->spp);
    if ($sppPerBulan > 0 && $sppPerBulan < 1000) $sppPerBulan *= 1000;

    $dataMurid = [
        'kelas'      => $murid->kelas ?? '',
        'status'     => $murid->status ?? 'aktif',
        'guru'       => $murid->guru ?? '',
        'gol'        => $murid->gol ?? '',
        'kd'         => $murid->kd ?? '',
        'bimba_unit' => $murid->bimba_unit ?? null,
        'no_cabang'  => $murid->no_cabang ?? null,
        'RBAS'       => $murid->RBAS ?? null,
        'BCABS01'    => $murid->BCABS01 ?? null,
        'BCABS02'    => $murid->BCABS02 ?? null,
    ];

    $rbas    = $request->filled('RBAS')    ? $request->RBAS    : $dataMurid['RBAS'];
    $bcabs01 = $request->filled('BCABS01') ? $request->BCABS01 : $dataMurid['BCABS01'];
    $bcabs02 = $request->filled('BCABS02') ? $request->BCABS02 : $dataMurid['BCABS02'];

    // === PERIODE SPP ===
    $periodeTerpilih = [];
    if ($spp > 0) {
        foreach (($request->bulan_bayar ?? []) as $i => $bulan) {
            if (!empty($bulan) && !empty($request->tahun_bayar[$i])) {
                $key = strtolower($bulan) . '-' . $request->tahun_bayar[$i];
                $periodeTerpilih[$key] = [
                    'bulan' => trim($bulan),
                    'tahun' => (int)$request->tahun_bayar[$i]
                ];
            }
        }

        if (empty($periodeTerpilih)) {
            return back()->withErrors(['bulan_bayar' => 'Pilih minimal satu bulan untuk SPP!'])->withInput();
        }

        foreach ($periodeTerpilih as $bt) {
            $exists = Penerimaan::where('nim', $request->nim)
                ->whereRaw('LOWER(TRIM(bulan)) = ?', [strtolower($bt['bulan'])])
                ->where('tahun', $bt['tahun'])
                ->where('spp', '>', 0)
                ->exists();

            if ($exists) {
                return back()->withErrors(['bulan_bayar' => "SPP {$bt['bulan']} {$bt['tahun']} sudah dibayar!"])->withInput();
            }
        }
    }

    // === HITUNG TOTAL ===
    $totalBiayaLain = $daftar + $kaosPendek + $kaosPanjang + $kpk + $sertifikat + $stpb + $tas + $event + $lainLain + $rbas + $bcabs01 + $bcabs02;
    $adaBiayaLain   = $totalBiayaLain > 0;
    $kelebihanSpp   = $spp > 0 ? ($spp - count($periodeTerpilih) * $sppPerBulan) : 0;

    // === VOUCHER ===
    $voucherTerpakai = [];
    $diskonPerVoucher = 50000;

    if ($request->has('voucher') && is_array($request->voucher)) {
        $voucherDipilih = array_filter($request->voucher);
        if (count($voucherDipilih) > count($periodeTerpilih)) {
            return back()->withErrors(['voucher' => 'Jumlah voucher melebihi jumlah bulan SPP'])->withInput();
        }

        foreach ($voucherDipilih as $noVoucher) {
            $v = VoucherLama::where('no_voucher', $noVoucher)
                ->where(function($q) use ($request) {
                    $q->whereNull('nim')->orWhere('nim', $request->nim);
                })
                ->first();

            if (!$v || $v->jumlah_voucher <= 0) {
                return back()->withErrors(['voucher' => "Voucher {$noVoucher} tidak valid / habis"])->withInput();
            }

            $v->decrement('jumlah_voucher', 1);

            VoucherHistori::create([
                'voucher_lama_id'   => $v->id,
                'nim'               => $request->nim,
                'nama_murid'        => $request->nama_murid,
                'tanggal'           => $request->tanggal,
                'tanggal_pemakaian' => $request->tanggal,
                'jumlah_voucher'    => 1,
                'voucher'           => $diskonPerVoucher,
                'status'            => 'digunakan',
            ]);

            $voucherTerpakai[] = $v->id;
        }
    }

    $buktiPath = $request->hasFile('bukti_transfer')
        ? $request->file('bukti_transfer')->store('bukti_transfer', 'public')
        : null;

    $tanggal = Carbon::parse($request->tanggal);

    // ==================================================================
    // === GENERATE FORMAT KWITANSI BARU ===
    // ==================================================================

    // 3 digit terakhir NIM (contoh: 051410001 → 001)
    $nimLast3 = str_pad(substr((string)$request->nim, -3), 3, '0', STR_PAD_LEFT);

    // Tahun 2 digit (2026 → 26)
    $tahun2 = str_pad($tanggal->year % 100, 2, '0', STR_PAD_LEFT);

    // Bulan & tanggal 2 digit
    $bulan2   = str_pad($tanggal->month, 2, '0', STR_PAD_LEFT);
    $tanggal2 = str_pad($tanggal->day, 2, '0', STR_PAD_LEFT);

    // Base kwitansi tanpa nomor urut
    $kwitansiBase = "KW{$nimLast3}{$tahun2}{$bulan2}{$tanggal2}";
    // Contoh: KW001260114

    // Nomor urut mulai dari 01 untuk murid ini
    $index = 1;

    DB::beginTransaction();
    try {
        $kwitansiList = [];
        $firstSppId = null;

        // === SIMPAN SPP PER BULAN ===
        foreach ($periodeTerpilih as $bt) {
            $diskon = isset($voucherTerpakai[$index - 1]) ? $diskonPerVoucher : 0;

            $kwitansi = $kwitansiBase . str_pad($index, 2, '0', STR_PAD_LEFT);
            // Contoh: KW00126011401, KW00126011402, ...

            $p = Penerimaan::create([
                'kwitansi'   => $kwitansi,
                'via'        => $request->via,
                'tanggal'    => $request->tanggal,
                'nim'        => $request->nim,
                'nama_murid' => $request->nama_murid,
                'kelas'      => $dataMurid['kelas'],
                'status'     => $dataMurid['status'],
                'guru'       => $dataMurid['guru'],
                'gol'        => $dataMurid['gol'] ?? null,
                'kd'         => $dataMurid['kd'] ?? null,
                'bulan'      => $bt['bulan'],
                'tahun'      => $bt['tahun'],
                'spp'        => $sppPerBulan,
                'voucher'    => $diskon,
                'total'      => $sppPerBulan - $diskon,
                'bimba_unit' => $dataMurid['bimba_unit'],
                'no_cabang'  => $dataMurid['no_cabang'],
                'RBAS'       => $rbas,
                'BCABS01'    => $bcabs01,
                'BCABS02'    => $bcabs02,
                'bukti_transfer_path' => $buktiPath,
            ]);

            if (!$firstSppId) $firstSppId = $p->id;
            $kwitansiList[] = $kwitansi;
            $index++;
        }

        // === DEPOSIT (pakai kwitansi pertama) ===
        if ($kelebihanSpp > 0) {
            $last = collect($periodeTerpilih)->last();
            $bulanDeposit = Carbon::create($last['tahun'], $this->bulanKeAngka($last['bulan']))->addMonth();

            DaftarMuridDeposit::create([
                'tanggal_transaksi'   => $tanggal->toDateString(),
                'nim'                 => $request->nim,
                'nama_murid'          => $request->nama_murid,
                'jumlah_deposit'      => $kelebihanSpp,
                'kategori_deposit'    => 'Deposit ' . $this->namaBulanIndonesia($bulanDeposit->month) . ' ' . $bulanDeposit->year,
                'status_deposit'      => 'Aktif',
                'keterangan_deposit'  => 'Kelebihan bayar SPP',
                'penerimaan_id'       => $firstSppId,
                'kwitansi'            => $kwitansiList[0] ?? null,
                'bimba_unit'          => $dataMurid['bimba_unit'],
                'no_cabang'           => $dataMurid['no_cabang'],
            ]);
        }

        // === BIAYA LAIN ===
        if ($adaBiayaLain) {
            $kwitansi = $kwitansiBase . str_pad($index, 2, '0', STR_PAD_LEFT);

            $p = Penerimaan::create([
                'kwitansi' => $kwitansi,
                'via'      => $request->via,
                'tanggal'  => $request->tanggal,
                'nim'      => $request->nim,
                'nama_murid' => $request->nama_murid,
                'kelas'      => $dataMurid['kelas'],
                'status'     => $dataMurid['status'],
                'guru'       => $dataMurid['guru'],
                'gol'        => $dataMurid['gol'] ?? null,
                'kd'         => $dataMurid['kd'] ?? null,
                'daftar'   => $daftar,
                'kaos'     => $kaosPendek,
                'kaos_lengan_panjang' => $kaosPanjang,
                'ukuran_kaos_pendek'  => $ukuranKaosPendekString,
                'ukuran_kaos_panjang' => $ukuranKaosPanjangString,
                'kaos_pendek_details' => $kaosPendekDetails,
                'kaos_panjang_details'=> $kaosPanjangDetails,
                'kpk' => $kpk, 'sertifikat'=>$sertifikat, 'stpb'=>$stpb, 'tas'=>$tas,
                'event'=>$event, 'lain_lain'=>$lainLain, 'total'=>$totalBiayaLain,
                'bimba_unit'=>$dataMurid['bimba_unit'],
                'no_cabang'=>$dataMurid['no_cabang'],
                'bukti_transfer_path'=> $buktiPath,
            ]);

            $kwitansiList[] = $kwitansi;
        }

        DB::commit();

        return redirect()->route('penerimaan.index')
            ->with('success', 'Pembayaran berhasil! Kwitansi: ' . implode(', ', $kwitansiList));

    } catch (\Exception $e) {
        DB::rollBack();
        if ($buktiPath) Storage::disk('public')->delete($buktiPath);
        return back()->withErrors(['error' => 'Gagal simpan: ' . $e->getMessage()])->withInput();
    }
}

    private function bulanKeAngka($bulanNama)
{
    $map = [
        'januari' => 1, 'februari' => 2, 'maret' => 3, 'april' => 4,
        'mei' => 5, 'juni' => 6, 'juli' => 7, 'agustus' => 8,
        'september' => 9, 'oktober' => 10, 'november' => 11, 'desember' => 12
    ];
    return $map[strtolower($bulanNama)] ?? 1;
}

    private function namaBulanIndonesia($bulan)
    {
        $nama = [1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April', 5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus', 9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'];
        return $nama[$bulan] ?? 'Bulan Tidak Valid';
    }



    public function edit(Penerimaan $penerimaan)
{
    // Murid aktif/baru + normalisasi SPP
    $murids = BukuInduk::whereIn(DB::raw('LOWER(status)'), ['aktif', 'baru'])
        ->get()
        ->map(function ($m) {
            $nominal = $this->cleanMoneyInput($m->spp);
            if ($nominal > 0 && $nominal < 1000) {
                $nominal *= 1000;
            }
            $m->spp = $nominal;
            return $m;
        });

    // Daftar voucher yang masih tersedia
    $vouchers = VoucherLama::where('jumlah_voucher', '>', 0)
        ->where(function ($q) use ($penerimaan) {
            $q->whereNull('nim')->orWhere('nim', $penerimaan->nim);
        })
        ->get();

    // Bulan-tahun SPP yang sudah dibayar (kecuali record ini)
    $sppLunas = Penerimaan::where('spp', '>', 0)
        ->where('nim', $penerimaan->nim)
        ->where('id', '<>', $penerimaan->id)
        ->select('nim', 'bulan', 'tahun')
        ->get()
        ->map(fn($r) => strtolower("{$r->nim}-{$r->bulan}-{$r->tahun}"))
        ->unique()
        ->toArray();

    // Daftar unit & cabang
    $units = BukuInduk::query()
        ->whereNotNull('bimba_unit')
        ->whereRaw("TRIM(bimba_unit) <> ''")
        ->select('bimba_unit', 'no_cabang')
        ->distinct()
        ->orderBy('bimba_unit')
        ->get();

    // Opsi RBAS, BCABS01, BCABS02
    $rbasOptions = Penerimaan::whereNotNull('RBAS')
        ->whereRaw("TRIM(COALESCE(RBAS,'')) <> ''")
        ->distinct()
        ->orderBy('RBAS')
        ->pluck('RBAS')
        ->toArray();

    $bcabs01Options = Penerimaan::whereNotNull('BCABS01')
        ->whereRaw("TRIM(COALESCE(BCABS01,'')) <> ''")
        ->distinct()
        ->orderBy('BCABS01')
        ->pluck('BCABS01')
        ->toArray();

    $bcabs02Options = Penerimaan::whereNotNull('BCABS02')
        ->whereRaw("TRIM(COALESCE(BCABS02,'')) <> ''")
        ->distinct()
        ->orderBy('BCABS02')
        ->pluck('BCABS02')
        ->toArray();

    // Cek hak akses admin
    $user = Auth::user();
    $isAdmin = $user && ($user->role === 'admin' || ($user->is_admin ?? false));

    return view('penerimaan.edit', compact(
        'penerimaan',
        'murids',
        'vouchers',
        'sppLunas',
        'units',
        'isAdmin',
        'rbasOptions',
        'bcabs01Options',
        'bcabs02Options'
    ));
}

public function update(Request $request, Penerimaan $penerimaan)
{
    // ================= 1. NORMALISASI NOMINAL =================
    $spp         = $this->cleanMoneyInput($request->input('spp'));
    $daftar      = $this->cleanMoneyInput($request->input('daftar'));
    $voucherNom  = $this->cleanMoneyInput($request->input('voucher'));
    $kaosPendek  = $this->cleanMoneyInput($request->input('kaos_pendek'));
    $kaosPanjang = $this->cleanMoneyInput($request->input('kaos_panjang'));
    $kpk         = $this->cleanMoneyInput($request->input('kpk'));
    $sertifikat  = $this->cleanMoneyInput($request->input('sertifikat'));
    $stpb        = $this->cleanMoneyInput($request->input('stpb'));
    $tas         = $this->cleanMoneyInput($request->input('tas'));
    $event       = $this->cleanMoneyInput($request->input('event'));
    $lainLain    = $this->cleanMoneyInput($request->input('lain_lain'));

    if ($spp > 0 && $spp < 1000) {
        $spp *= 1000;
    }

    // ================= 2. UKURAN KAOS =================
    $ukuranPendekInput = array_filter($request->input('ukuran_kaos_pendek', []));
    $ukuranKaosPendekString = $ukuranPendekInput ? implode(',', array_unique($ukuranPendekInput)) : null;

    $kaosPendekDetails = [];
    foreach ($ukuranPendekInput as $u) {
        $kaosPendekDetails[$u] = ($kaosPendekDetails[$u] ?? 0) + 1;
    }
    $kaosPendekDetails = $kaosPendekDetails ?: null;

    $ukuranPanjangInput = array_filter($request->input('ukuran_kaos_panjang', []));
    $ukuranKaosPanjangString = $ukuranPanjangInput ? implode(',', array_unique($ukuranPanjangInput)) : null;

    $kaosPanjangDetails = [];
    foreach ($ukuranPanjangInput as $u) {
        $kaosPanjangDetails[$u] = ($kaosPanjangDetails[$u] ?? 0) + 1;
    }
    $kaosPanjangDetails = $kaosPanjangDetails ?: null;

    // ================= 3. LOGIKA DASAR =================
    $tanggalPenyerahan = $request->filled('tanggal_penyerahan')
        ? $request->tanggal_penyerahan
        : $penerimaan->tanggal_penyerahan;

    $totalBiayaLainBaru = $daftar + $kaosPendek + $kaosPanjang + $kpk + $sertifikat + $stpb + $tas + $event + $lainLain;
    $adaBiayaLainBaru   = $totalBiayaLainBaru > 0;

    $isRecordSppMurni =
        $penerimaan->spp > 0 &&
        ($penerimaan->kaos ?? 0) == 0 &&
        ($penerimaan->kaos_lengan_panjang ?? 0) == 0 &&
        $penerimaan->daftar == 0 &&
        $penerimaan->kpk == 0 &&
        $penerimaan->tas == 0 &&
        $penerimaan->event == 0 &&
        $penerimaan->lain_lain == 0;

    $baseKwitansi = Str::before($penerimaan->kwitansi, '-');
    $tanggal      = Carbon::parse($request->tanggal);

    // ================= VALIDASI FILE =================
$rules = [
    'hapus_bukti_lama' => 'nullable|boolean',
    'catatan_bukti'    => 'nullable|string|max:500',
];

if ($request->hasFile('bukti_transfer')) {
    $rules['bukti_transfer'] = 'file|mimes:jpg,jpeg,png,pdf|max:5120';
}

$request->validate($rules);

// ================= UPLOAD BUKTI (SAMA DENGAN STORE) =================
$newBuktiPath = $penerimaan->bukti_transfer_path;

// upload ulang
if ($request->hasFile('bukti_transfer')) {

    // hapus file lama
    if ($penerimaan->bukti_transfer_path) {
        Storage::disk('public')->delete($penerimaan->bukti_transfer_path);
    }

    // SIMPAN DENGAN CARA YANG SAMA SEPERTI STORE
    $newBuktiPath = $request->file('bukti_transfer')
        ->store('bukti_transfer', 'public');

}

// hapus manual tanpa upload baru
if ($request->boolean('hapus_bukti_lama') && !$request->hasFile('bukti_transfer')) {
    if ($penerimaan->bukti_transfer_path) {
        Storage::disk('public')->delete($penerimaan->bukti_transfer_path);
    }
    $newBuktiPath = null;
}

    // ================= 6. TRANSAKSI DB =================
    DB::beginTransaction();
    try {
        $updateData = $request->except([
            'bukti_transfer',
            'hapus_bukti_lama',
            'ukuran_kaos_pendek',
            'ukuran_kaos_panjang'
        ]);

        $updateData['spp']                  = $spp;
        $updateData['kaos']                 = 0;
        $updateData['kaos_lengan_panjang']  = 0;
        $updateData['daftar']               = 0;
        $updateData['kpk']                  = 0;
        $updateData['tas']                  = 0;
        $updateData['event']                = 0;
        $updateData['lain_lain']            = 0;
        $updateData['total']                = $spp + ($voucherNom ?? 0);

        $updateData['ukuran_kaos_pendek']   = null;
        $updateData['ukuran_kaos_panjang']  = null;
        $updateData['kaos_pendek_details']  = null;
        $updateData['kaos_panjang_details'] = null;

        $updateData['bukti_transfer_path']  = $newBuktiPath;
        $updateData['catatan_bukti']        = $request->input('catatan_bukti', $penerimaan->catatan_bukti);
        $updateData['tanggal_penyerahan']   = $tanggalPenyerahan;

        $penerimaan->update($updateData);

        // ===== SPLIT BIAYA LAIN (TIDAK DIUBAH) =====
        if ($isRecordSppMurni && $adaBiayaLainBaru) {
            $last = Penerimaan::where('kwitansi', 'LIKE', $baseKwitansi . '%')
                ->whereDate('tanggal', $tanggal->toDateString())
                ->orderByRaw('CAST(SUBSTRING(kwitansi, LENGTH(kwitansi) - 1) AS UNSIGNED) DESC')
                ->first();

            $next = $last ? ((int) substr($last->kwitansi, -2)) + 1 : 2;
            $kwitansiBaru = $baseKwitansi . '-' . str_pad($next, 2, '0', STR_PAD_LEFT);

            Penerimaan::create([
                'kwitansi' => $kwitansiBaru,
                'via' => $penerimaan->via,
                'tanggal' => $penerimaan->tanggal,
                'tanggal_penyerahan' => $tanggalPenyerahan,
                'nim' => $penerimaan->nim,
                'nama_murid' => $penerimaan->nama_murid,
                'kelas' => $penerimaan->kelas,
                'gol' => $penerimaan->gol,
                'kd' => $penerimaan->kd,
                'status' => $penerimaan->status,
                'guru' => $penerimaan->guru,
                'daftar' => $daftar,
                'kaos' => $kaosPendek,
                'kaos_lengan_panjang' => $kaosPanjang,
                'ukuran_kaos_pendek' => $ukuranKaosPendekString,
                'ukuran_kaos_panjang' => $ukuranKaosPanjangString,
                'kaos_pendek_details' => $kaosPendekDetails,
                'kaos_panjang_details' => $kaosPanjangDetails,
                'kpk' => $kpk,
                'sertifikat' => $sertifikat,
                'stpb' => $stpb,
                'tas' => $tas,
                'event' => $event,
                'lain_lain' => $lainLain,
                'total' => $totalBiayaLainBaru,
                'bimba_unit' => $penerimaan->bimba_unit,
                'no_cabang' => $penerimaan->no_cabang,
                'RBAS' => $request->RBAS ?? $penerimaan->RBAS,
                'BCABS01' => $request->BCABS01 ?? $penerimaan->BCABS01,
                'BCABS02' => $request->BCABS02 ?? $penerimaan->BCABS02,
                'bukti_transfer_path' => $newBuktiPath,
                'catatan_bukti' => $request->input('catatan_bukti', $penerimaan->catatan_bukti),
            ]);
        }

        DB::commit();

        return redirect()->route('penerimaan.index')
            ->with('success', 'Data penerimaan berhasil diperbarui.');

    } catch (\Exception $e) {
        DB::rollBack();
        return back()->withErrors(['error' => $e->getMessage()])->withInput();
    }
}

    public function destroy(Penerimaan $penerimaan)
    {
        // Perluas logika destroy jika Anda ingin mengembalikan voucher yang sudah terpakai

        // 1. Cek apakah ada voucher yang digunakan pada transaksi ini
        $kodeVoucher = $penerimaan->voucher;
        $nim = $penerimaan->nim;

        DB::beginTransaction();
        try {
            // Hapus data Penerimaan
            $penerimaan->delete();

            // 2. Jika kode voucher ada dan transaksi ini mencakup nominal voucher > 0 (asumsi 50000)
            // Logika ini hanya relevan jika Anda menyimpan kode voucher yang digunakan di tabel Penerimaan
            // atau jika Anda ingin mengembalikan voucher yang terpakai di VoucherHistori

            // Asumsi: Kita mencari histori yang dibuat saat transaksi ini
            $voucherHistori = VoucherHistori::where('nim', $nim)
                ->where('voucher', 50000) // Nominal per voucher
                ->where('tanggal_pemakaian', $penerimaan->tanggal) // Mungkin perlu penyesuaian jika tanggal tidak unik
                ->first();

            if ($voucherHistori) {
                // 3. Kembalikan jumlah voucher di VoucherLama
                $voucherLama = VoucherLama::find($voucherHistori->voucher_lama_id);
                if ($voucherLama) {
                    $voucherLama->increment('jumlah_voucher', 1);
                    // Jika sebelumnya status 'Digunakan' dan sekarang jumlah > 0, ubah status
                    if ($voucherLama->status == 'Digunakan' && $voucherLama->jumlah_voucher > 0) {
                        $voucherLama->update(['status' => 'Aktif']);
                    }
                }
                // 4. Hapus histori penggunaan
                $voucherHistori->delete();
            }

            DB::commit();
            return redirect()->route('penerimaan.index')->with('success', 'Data penerimaan berhasil dihapus. Voucher (jika ada) telah dikembalikan.');
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->route('penerimaan.index')->with('error', 'Gagal menghapus data penerimaan: ' . $e->getMessage());
        }
    }

    public function import(Request $request)
    {
        $request->validate([
            'file' => 'required|mimes:xlsx,csv'
        ]);

        Excel::import(new PenerimaanImport, $request->file('file'));

        return redirect()->route('penerimaan.index')
            ->with('success', 'Data berhasil diimport!');
    }
    public function updateBulanTahun(Request $request, $id)
    {
        $request->validate([
            'bulan' => 'required|string',
            'tahun' => 'required|integer',
        ]);

        $penerimaan = Penerimaan::findOrFail($id);
        $penerimaan->bulan = $request->bulan;
        $penerimaan->tahun = $request->tahun;
        $penerimaan->save();

        return redirect()->route('penerimaan.index')->with('success', 'Bulan & Tahun berhasil diperbarui!');
    }
    public function updateTahun(Request $request, $id)
    {
        $request->validate([
            'tahun' => 'required|numeric',
        ]);

        $penerimaan = Penerimaan::findOrFail($id);
        $penerimaan->tahun = $request->tahun;
        $penerimaan->save();

        return back()->with('success', 'Tahun berhasil diperbarui');
    }
    public function spp(Request $request)
{
    $perPage       = (int) $request->input('per_page', 10);
    $search        = $request->input('search');                  // NIM atau nama murid (partial)
    $unitKode      = $request->input('unit');                     // Kode unit (misal '01045')
    $periodeDari   = $request->filled('periode_dari') ? $request->input('periode_dari') : null;
    $periodeSampai = $request->filled('periode_sampai') ? $request->input('periode_sampai') : null;

    // Dropdown Unit
    $unitList = Unit::withoutGlobalScopes()
        ->orderBy('biMBA_unit')
        ->get();

    // Dropdown Murid: dinamis sesuai unit yang dipilih
    $muridQuery = Penerimaan::query()
        ->select('nim', 'nama_murid')
        ->whereNotNull('nama_murid')
        ->whereRaw("TRIM(nama_murid) <> ''")
        ->whereNotNull('nim');

    if ($unitKode) {
        $muridQuery->where('bimba_unit', $unitKode);
    }

    $muridList = $muridQuery
        ->distinct()
        ->orderBy('nama_murid')
        ->get();

    // Query utama: hanya yang ada SPP
    $query = Penerimaan::query()->where('spp', '>', 0);

    // Filter Unit
    if ($unitKode) {
        $query->where('bimba_unit', $unitKode);
    }

    // Filter NIM atau Nama Murid (partial search)
    if (!empty($search)) {
        $query->where(function ($q) use ($search) {
            $q->where('nim', 'LIKE', '%' . trim($search) . '%')
              ->orWhere('nama_murid', 'LIKE', '%' . trim($search) . '%');
        });
    }

    // Filter Periode
    if ($periodeDari || $periodeSampai) {
        $from = $periodeDari
            ? Carbon::createFromFormat('Y-m', $periodeDari)->startOfMonth()->toDateString()
            : '1900-01-01';

        $to = $periodeSampai
            ? Carbon::createFromFormat('Y-m', $periodeSampai)->endOfMonth()->toDateString()
            : '2999-12-31';

        if (Schema::hasColumn('penerimaan', 'tanggal')) {
            $query->whereBetween('tanggal', [$from, $to]);
        } else {
            // Jika tidak ada kolom tanggal, gunakan bulan & tahun
            $query->where(function ($q) use ($from, $to) {
                $q->whereRaw("STR_TO_DATE(CONCAT(tahun, '-', LPAD(CASE
                    WHEN LOWER(TRIM(bulan))='januari' THEN 1 WHEN LOWER(TRIM(bulan))='februari' THEN 2 WHEN LOWER(TRIM(bulan))='maret' THEN 3
                    WHEN LOWER(TRIM(bulan))='april' THEN 4 WHEN LOWER(TRIM(bulan))='mei' THEN 5 WHEN LOWER(TRIM(bulan))='juni' THEN 6
                    WHEN LOWER(TRIM(bulan))='juli' THEN 7 WHEN LOWER(TRIM(bulan))='agustus' THEN 8 WHEN LOWER(TRIM(bulan))='september' THEN 9
                    WHEN LOWER(TRIM(bulan))='oktober' THEN 10 WHEN LOWER(TRIM(bulan))='november' THEN 11 WHEN LOWER(TRIM(bulan))='desember' THEN 12
                    END, 2, '0'), '-01'), '%Y-%m-%d') BETWEEN ? AND ?", [$from, $to]);
            });
        }
    }

    // Ordering
    $listQuery = (clone $query)->orderByDesc(
        Schema::hasColumn('penerimaan', 'tanggal') ? 'tanggal' : DB::raw("CONCAT(tahun, '-', LPAD(CASE
            WHEN LOWER(TRIM(bulan))='januari' THEN 1 WHEN LOWER(TRIM(bulan))='februari' THEN 2 WHEN LOWER(TRIM(bulan))='maret' THEN 3
            WHEN LOWER(TRIM(bulan))='april' THEN 4 WHEN LOWER(TRIM(bulan))='mei' THEN 5 WHEN LOWER(TRIM(bulan))='juni' THEN 6
            WHEN LOWER(TRIM(bulan))='juli' THEN 7 WHEN LOWER(TRIM(bulan))='agustus' THEN 8 WHEN LOWER(TRIM(bulan))='september' THEN 9
            WHEN LOWER(TRIM(bulan))='oktober' THEN 10 WHEN LOWER(TRIM(bulan))='november' THEN 11 WHEN LOWER(TRIM(bulan))='desember' THEN 12
            END, 2, '0'))")
    );

    $penerimaan = $listQuery->paginate($perPage)->appends($request->query());

    // Total
    $totalSPP     = (clone $query)->sum('spp');
    $totalVoucher = (clone $query)->sum('voucher');

    return view('penerimaan.spp', compact(
        'penerimaan',
        'unitList',
        'muridList',
        'totalSPP',
        'totalVoucher',
        'perPage',
        'search',
        'unitKode',
        'periodeDari',
        'periodeSampai'
    ));
}
public function produk(Request $request)
{
    $perPage = (int) $request->input('per_page', 10);

    // Input filter
    $search        = $request->input('search');                  // NIM murid yang dipilih
    $unitKode      = $request->input('unit');                     // Kode unit dari dropdown (misal: '01045')
    $periodeDari   = $request->filled('periode_dari') ? $request->input('periode_dari') : null;
    $periodeSampai = $request->filled('periode_sampai') ? $request->input('periode_sampai') : null;

    // === DROPDOWN BIMBA UNIT ===
    $unitList = Unit::withoutGlobalScopes()
        ->orderBy('biMBA_unit')
        ->get();

    // === DROPDOWN NAMA MURID — DINAMIS: IKUT FILTER UNIT ===
    $namaQuery = Penerimaan::query()
        ->select('nim', 'nama_murid')
        ->whereNotNull('nama_murid')
        ->whereRaw("TRIM(nama_murid) <> ''")
        ->whereNotNull('nim');

    // Jika unit sudah dipilih, hanya ambil murid dari unit tersebut
    if ($unitKode) {
        $namaQuery->where('bimba_unit', $unitKode);
    }

    $namaList = $namaQuery
        ->distinct()
        ->orderBy('nama_murid')
        ->get();

    // Query dasar: hanya transaksi yang ada produk
    $query = Penerimaan::query()
        ->where(function ($q) {
            $q->where('kaos', '>', 0)
                ->orWhere('kaos_lengan_panjang', '>', 0)
                ->orWhere('kpk', '>', 0)
                ->orWhere('tas', '>', 0)
                ->orWhere('sertifikat', '>', 0)
                ->orWhere('stpb', '>', 0)
                ->orWhere('event', '>', 0)
                ->orWhere('lain_lain', '>', 0)
                ->orWhere('RBAS', '>', 0)
                ->orWhere('BCABS01', '>', 0)
                ->orWhere('BCABS02', '>', 0);
        });

    // Filter pencarian murid (jika dipilih)
    if (!empty($search)) {
        $query->where('nim', $search); // karena dropdown pakai value = nim
    }

    // Filter unit
    if ($unitKode) {
        $query->where('bimba_unit', $unitKode);
    }

    // Filter periode
    if ($periodeDari || $periodeSampai) {
        $from = $periodeDari
            ? Carbon::createFromFormat('Y-m', $periodeDari)->startOfMonth()
            : Carbon::createFromDate(1900, 1, 1);

        $to = $periodeSampai
            ? Carbon::createFromFormat('Y-m', $periodeSampai)->endOfMonth()
            : Carbon::createFromDate(2999, 12, 31);

        if (Schema::hasColumn('penerimaan', 'tanggal')) {
            $query->whereBetween('tanggal', [$from, $to]);
        } else {
            $periodeExpr = DB::raw("STR_TO_DATE(CONCAT(tahun, '-', LPAD(CASE
                WHEN LOWER(TRIM(bulan))='januari' THEN 1 WHEN LOWER(TRIM(bulan))='februari' THEN 2 WHEN LOWER(TRIM(bulan))='maret' THEN 3
                WHEN LOWER(TRIM(bulan))='april' THEN 4 WHEN LOWER(TRIM(bulan))='mei' THEN 5 WHEN LOWER(TRIM(bulan))='juni' THEN 6
                WHEN LOWER(TRIM(bulan))='juli' THEN 7 WHEN LOWER(TRIM(bulan))='agustus' THEN 8 WHEN LOWER(TRIM(bulan))='september' THEN 9
                WHEN LOWER(TRIM(bulan))='oktober' THEN 10 WHEN LOWER(TRIM(bulan))='november' THEN 11 WHEN LOWER(TRIM(bulan))='desember' THEN 12
                END, 2, '0'), '-01'), '%Y-%m-%d')");

            $query->whereBetween($periodeExpr, [$from->format('Y-m-d'), $to->format('Y-m-d')]);
        }
    }

    // Ordering: terbaru dulu
    $listQuery = (clone $query)->orderByRaw(
        Schema::hasColumn('penerimaan', 'tanggal')
            ? 'tanggal DESC'
            : "CONCAT(tahun, '-', LPAD(CASE
                WHEN LOWER(TRIM(bulan))='januari' THEN 1 WHEN LOWER(TRIM(bulan))='februari' THEN 2 WHEN LOWER(TRIM(bulan))='maret' THEN 3
                WHEN LOWER(TRIM(bulan))='april' THEN 4 WHEN LOWER(TRIM(bulan))='mei' THEN 5 WHEN LOWER(TRIM(bulan))='juni' THEN 6
                WHEN LOWER(TRIM(bulan))='juli' THEN 7 WHEN LOWER(TRIM(bulan))='agustus' THEN 8 WHEN LOWER(TRIM(bulan))='september' THEN 9
                WHEN LOWER(TRIM(bulan))='oktober' THEN 10 WHEN LOWER(TRIM(bulan))='november' THEN 11 WHEN LOWER(TRIM(bulan))='desember' THEN 12
                END, 2, '0')) DESC"
    );

    // Harga satuan produk
    $hargaKaosPendek  = 70000;
    $hargaKaosPanjang = 85000;
    $hargaKpk         = 10000;
    $hargaTas         = 80000;
    $hargaSertifikat  = 15000;
    $hargaStpb        = 25000;
    $hargaEvent       = 100000;
    $hargaLainLain    = 30000;
    $hargaRbas        = 15000;
    $hargaBcabs01     = 15000;
    $hargaBcabs02     = 15000;

    // Data tabel + pagination
    $penerimaan = $listQuery
        ->selectRaw('penerimaan.*')
        ->selectRaw('FLOOR(COALESCE(kaos, 0) / ?) AS kaos_pendek_pcs', [$hargaKaosPendek])
        ->selectRaw('FLOOR(COALESCE(kaos_lengan_panjang, 0) / ?) AS kaos_panjang_pcs', [$hargaKaosPanjang])
        ->selectRaw('FLOOR(COALESCE(kpk, 0) / ?) AS kpk_pcs', [$hargaKpk])
        ->selectRaw('FLOOR(COALESCE(tas, 0) / ?) AS tas_pcs', [$hargaTas])
        ->selectRaw('FLOOR(COALESCE(sertifikat, 0) / ?) AS sertifikat_pcs', [$hargaSertifikat])
        ->selectRaw('FLOOR(COALESCE(stpb, 0) / ?) AS stpb_pcs', [$hargaStpb])
        ->selectRaw('FLOOR(COALESCE(event, 0) / ?) AS event_pcs', [$hargaEvent])
        ->selectRaw('FLOOR(COALESCE(lain_lain, 0) / ?) AS lainlain_pcs', [$hargaLainLain])
        ->selectRaw('FLOOR(COALESCE(RBAS, 0) / ?) AS rbas_pcs', [$hargaRbas])
        ->selectRaw('FLOOR(COALESCE(BCABS01, 0) / ?) AS bcabs01_pcs', [$hargaBcabs01])
        ->selectRaw('FLOOR(COALESCE(BCABS02, 0) / ?) AS bcabs02_pcs', [$hargaBcabs02])
        ->selectRaw("DATE_FORMAT(tanggal_penyerahan_kaos_pendek, '%d-%m-%Y') AS tgl_kaos_pendek_fmt")
        ->selectRaw("DATE_FORMAT(tanggal_penyerahan_kaos_panjang, '%d-%m-%Y') AS tgl_kaos_panjang_fmt")
        ->selectRaw("DATE_FORMAT(tanggal_penyerahan_kpk, '%d-%m-%Y') AS tgl_kpk_fmt")
        ->selectRaw("DATE_FORMAT(tanggal_penyerahan_tas, '%d-%m-%Y') AS tgl_tas_fmt")
        ->selectRaw("DATE_FORMAT(tanggal_penyerahan_rbas, '%d-%m-%Y') AS tgl_rbas_fmt")
        ->selectRaw("DATE_FORMAT(tanggal_penyerahan_bcabs01, '%d-%m-%Y') AS tgl_bcabs01_fmt")
        ->selectRaw("DATE_FORMAT(tanggal_penyerahan_bcabs02, '%d-%m-%Y') AS tgl_bcabs02_fmt")
        ->selectRaw("DATE_FORMAT(tanggal_penyerahan_sertifikat, '%d-%m-%Y') AS tgl_sertifikat_fmt")
        ->selectRaw("DATE_FORMAT(tanggal_penyerahan_stpb, '%d-%m-%Y') AS tgl_stpb_fmt")
        ->selectRaw("DATE_FORMAT(tanggal_penyerahan_event, '%d-%m-%Y') AS tgl_event_fmt")
        ->selectRaw("DATE_FORMAT(tanggal_penyerahan_lainlain, '%d-%m-%Y') AS tgl_lainlain_fmt")
        ->paginate($perPage)
        ->appends($request->query());

    // === TOTAL PCS (SUDAH DIBAYAR) ===
    $totalKaosPendek  = (clone $query)->sum(DB::raw("FLOOR(COALESCE(kaos, 0) / $hargaKaosPendek)"));
    $totalKaosPanjang = (clone $query)->sum(DB::raw("FLOOR(COALESCE(kaos_lengan_panjang, 0) / $hargaKaosPanjang)"));
    $totalKpk         = (clone $query)->sum(DB::raw("FLOOR(COALESCE(kpk, 0) / $hargaKpk)"));
    $totalTas         = (clone $query)->sum(DB::raw("FLOOR(COALESCE(tas, 0) / $hargaTas)"));
    $totalSertifikat  = (clone $query)->sum(DB::raw("FLOOR(COALESCE(sertifikat, 0) / $hargaSertifikat)"));
    $totalStpb        = (clone $query)->sum(DB::raw("FLOOR(COALESCE(stpb, 0) / $hargaStpb)"));
    $totalEvent       = (clone $query)->sum(DB::raw("FLOOR(COALESCE(event, 0) / $hargaEvent)"));
    $totalLainLain    = (clone $query)->sum(DB::raw("FLOOR(COALESCE(lain_lain, 0) / $hargaLainLain)"));
    $totalRbas        = (clone $query)->sum(DB::raw("FLOOR(COALESCE(RBAS, 0) / $hargaRbas)"));
    $totalBcabs01     = (clone $query)->sum(DB::raw("FLOOR(COALESCE(BCABS01, 0) / $hargaBcabs01)"));
    $totalBcabs02     = (clone $query)->sum(DB::raw("FLOOR(COALESCE(BCABS02, 0) / $hargaBcabs02)"));

    $totalSemuaProdukPcs = $totalKaosPendek + $totalKaosPanjang + $totalKpk + $totalTas + $totalSertifikat +
                           $totalStpb + $totalEvent + $totalLainLain + $totalRbas + $totalBcabs01 + $totalBcabs02;

    // === BELUM DISERAHKAN ===
    $belumKaosPendek  = (clone $query)->whereNull('tanggal_penyerahan_kaos_pendek')->sum(DB::raw("FLOOR(COALESCE(kaos, 0) / $hargaKaosPendek)"));
    $belumKaosPanjang = (clone $query)->whereNull('tanggal_penyerahan_kaos_panjang')->sum(DB::raw("FLOOR(COALESCE(kaos_lengan_panjang, 0) / $hargaKaosPanjang)"));
    $belumKpk         = (clone $query)->whereNull('tanggal_penyerahan_kpk')->sum(DB::raw("FLOOR(COALESCE(kpk, 0) / $hargaKpk)"));
    $belumTas         = (clone $query)->whereNull('tanggal_penyerahan_tas')->sum(DB::raw("FLOOR(COALESCE(tas, 0) / $hargaTas)"));
    $belumRbas        = (clone $query)->whereNull('tanggal_penyerahan_rbas')->sum(DB::raw("FLOOR(COALESCE(RBAS, 0) / $hargaRbas)"));
    $belumBcabs01     = (clone $query)->whereNull('tanggal_penyerahan_bcabs01')->sum(DB::raw("FLOOR(COALESCE(BCABS01, 0) / $hargaBcabs01)"));
    $belumBcabs02     = (clone $query)->whereNull('tanggal_penyerahan_bcabs02')->sum(DB::raw("FLOOR(COALESCE(BCABS02, 0) / $hargaBcabs02)"));
    $belumSertifikat  = (clone $query)->whereNull('tanggal_penyerahan_sertifikat')->sum(DB::raw("FLOOR(COALESCE(sertifikat, 0) / $hargaSertifikat)"));
    $belumStpb        = (clone $query)->whereNull('tanggal_penyerahan_stpb')->sum(DB::raw("FLOOR(COALESCE(stpb, 0) / $hargaStpb)"));
    $belumEvent       = (clone $query)->whereNull('tanggal_penyerahan_event')->sum(DB::raw("FLOOR(COALESCE(event, 0) / $hargaEvent)"));
    $belumLainlain    = (clone $query)->whereNull('tanggal_penyerahan_lainlain')->sum(DB::raw("FLOOR(COALESCE(lain_lain, 0) / $hargaLainLain)"));

    $totalBelumDiserahkan = $belumKaosPendek + $belumKaosPanjang + $belumKpk + $belumTas + $belumRbas +
                            $belumBcabs01 + $belumBcabs02 + $belumSertifikat + $belumStpb + $belumEvent + $belumLainlain;

        // === SUDAH DISERAHKAN (per produk) ===
    $sudahKaosPendek  = $totalKaosPendek - $belumKaosPendek;
    $sudahKaosPanjang = $totalKaosPanjang - $belumKaosPanjang;
    $sudahKpk         = $totalKpk - $belumKpk;
    $sudahTas         = $totalTas - $belumTas;
    $sudahRbas        = $totalRbas - $belumRbas;
    $sudahBcabs01     = $totalBcabs01 - $belumBcabs01;
    $sudahBcabs02     = $totalBcabs02 - $belumBcabs02;
    $sudahSertifikat  = $totalSertifikat - $belumSertifikat;
    $sudahStpb        = $totalStpb - $belumStpb;
    $sudahEvent       = $totalEvent - $belumEvent;
    $sudahLainlain    = $totalLainLain - $belumLainlain;

    $totalSudahDiserahkan = $totalSemuaProdukPcs - $totalBelumDiserahkan;

    // === RINGKASAN UKURAN KAOS ===
    $ukuranOptions = ['KAS', 'KAM', 'KAL', 'KAXL', 'KAXXL', 'KAXXXL', 'KAXXXLS'];

    $belumUkuranPendek  = array_fill_keys($ukuranOptions, 0);
    $sudahUkuranPendek  = array_fill_keys($ukuranOptions, 0);
    $belumUkuranPanjang = array_fill_keys($ukuranOptions, 0);
    $sudahUkuranPanjang = array_fill_keys($ukuranOptions, 0);

    $recordsWithKaos = (clone $query)
        ->where(function ($q) {
            $q->where('kaos', '>', 0)->orWhere('kaos_lengan_panjang', '>', 0);
        })
        ->select([
            'kaos', 'kaos_lengan_panjang',
            'ukuran_kaos_pendek', 'ukuran_kaos_panjang',
            'tanggal_penyerahan_kaos_pendek', 'tanggal_penyerahan_kaos_panjang'
        ])
        ->get();

    foreach ($recordsWithKaos as $row) {
        if ($row->kaos > 0 && $row->ukuran_kaos_pendek) {
            $sizes = array_filter(array_map('trim', explode(',', strtoupper($row->ukuran_kaos_pendek))));
            foreach ($sizes as $size) {
                if (in_array($size, $ukuranOptions)) {
                    is_null($row->tanggal_penyerahan_kaos_pendek)
                        ? $belumUkuranPendek[$size]++
                        : $sudahUkuranPendek[$size]++;
                }
            }
        }

        if ($row->kaos_lengan_panjang > 0 && $row->ukuran_kaos_panjang) {
            $sizes = array_filter(array_map('trim', explode(',', strtoupper($row->ukuran_kaos_panjang))));
            foreach ($sizes as $size) {
                if (in_array($size, $ukuranOptions)) {
                    is_null($row->tanggal_penyerahan_kaos_panjang)
                        ? $belumUkuranPanjang[$size]++
                        : $sudahUkuranPanjang[$size]++;
                }
            }
        }
    }

    return view('penerimaan.produk', compact(
        'penerimaan',
        'namaList',
        'unitList',

        // Total dibayar
        'totalKaosPendek', 'totalKaosPanjang', 'totalKpk', 'totalTas', 'totalSertifikat',
        'totalStpb', 'totalEvent', 'totalLainLain', 'totalRbas', 'totalBcabs01', 'totalBcabs02',
        'totalSemuaProdukPcs',

        // Belum diserahkan
        'belumKaosPendek', 'belumKaosPanjang', 'belumKpk', 'belumTas', 'belumRbas',
        'belumBcabs01', 'belumBcabs02', 'belumSertifikat', 'belumStpb', 'belumEvent', 'belumLainlain',
        'totalBelumDiserahkan',

        // Sudah diserahkan
        'sudahKaosPendek', 'sudahKaosPanjang', 'sudahKpk', 'sudahTas', 'sudahRbas',
        'sudahBcabs01', 'sudahBcabs02', 'sudahSertifikat', 'sudahStpb', 'sudahEvent', 'sudahLainlain',
        'totalSudahDiserahkan',

        // RINCIAN UKURAN KAOS — INI YANG SEBELUMNYA HILANG
        'belumUkuranPendek', 'sudahUkuranPendek',
        'belumUkuranPanjang', 'sudahUkuranPanjang',

        // Filter state
        'perPage', 'search', 'unitKode', 'periodeDari', 'periodeSampai'
    ));
}
public function updateTanggalPenyerahan(Request $request)
{
    // Isi method tetap sama persis
    try {
        $request->validate([
            'id'     => 'required|integer|exists:penerimaan,id',
            'field'  => 'required|string',
            'tanggal'=> 'nullable|date'
        ]);

        $fieldMap = [
            'kaos_pendek'   => 'tanggal_penyerahan_kaos_pendek',
            'kaos_panjang'  => 'tanggal_penyerahan_kaos_panjang',
            'kpk'           => 'tanggal_penyerahan_kpk',
            'tas'           => 'tanggal_penyerahan_tas',
            'rbas'          => 'tanggal_penyerahan_rbas',
            'bcabs01'       => 'tanggal_penyerahan_bcabs01',
            'bcabs02'       => 'tanggal_penyerahan_bcabs02',
            'sertifikat'    => 'tanggal_penyerahan_sertifikat',
            'stpb'          => 'tanggal_penyerahan_stpb',
            'event'         => 'tanggal_penyerahan_event',
            'lainlain'      => 'tanggal_penyerahan_lainlain',
        ];

        if (!isset($fieldMap[$request->field])) {
            return response()->json(['success' => false, 'message' => 'Field tidak valid'], 400);
        }

        $column = $fieldMap[$request->field];

        $penerimaan = Penerimaan::findOrFail($request->id);
        $penerimaan->update([$column => $request->tanggal ?: null]);

        return response()->json(['success' => true]);

    } catch (\Exception $e) {
        \Log::error('Update tanggal produk error: ' . $e->getMessage(), $request->all());

        return response()->json([
            'success' => false,
            'message' => 'Gagal: ' . $e->getMessage()
        ], 500);
    }
}
    protected function storeBuktiTransferFile(?\Illuminate\Http\UploadedFile $file): ?string
    {
        if (!$file)
            return null;
        // simpan ke storage/app/public/bukti_transfer/...
        return $file->store('bukti_transfer', 'public'); // returns e.g. "bukti_transfer/abc.jpg"
    }

    protected function deleteBuktiTransferFile(?string $path): void
    {
        if (!$path)
            return;
        try {
            Storage::disk('public')->delete($path);
        } catch (\Throwable $e) {
            \Log::warning('deleteBuktiTransferFile failed', ['path' => $path, 'err' => $e->getMessage()]);
        }
    }
    public function rbas(Request $request)
{
    $perPage    = (int) $request->input('per_page', 20);
    $search     = $request->input('search');
    $bulan      = $request->input('bulan');
    $tahun      = $request->input('tahun', now()->year);
    $unitFilter = $request->input('unit');

    // === DAFTAR UNIT UNTUK DROPDOWN ===
    $unitOptions = BukuInduk::whereIn(DB::raw('LOWER(status)'), ['aktif', 'baru'])
        ->whereNotNull('bimba_unit')
        ->where('bimba_unit', '!=', '')
        ->distinct()
        ->orderBy('bimba_unit')
        ->pluck('bimba_unit')
        ->toArray();

    // === QUERY MURID AKTIF / BARU ===
    $muridQuery = BukuInduk::whereIn(DB::raw('LOWER(status)'), ['aktif', 'baru'])
        ->select('nim', 'nama as nama_murid', 'bimba_unit', 'kelas', 'guru');

    if ($unitFilter) {
        $muridQuery->where('bimba_unit', $unitFilter);
    }

    $muridAktif = $muridQuery->get();

    // === Filter pencarian NIM / Nama ===
    if ($search) {
        $searchLower = strtolower(trim($search));
        $muridAktif = $muridAktif->filter(function ($m) use ($searchLower) {
            return str_contains(strtolower($m->nim), $searchLower) ||
                   str_contains(strtolower($m->nama_murid), $searchLower);
        });
    }

    // === Opsi bulan ===
    $bulanOptions = $this->getBulanOptions();

    // === Jika tidak ada murid setelah filter ===
    if ($muridAktif->isEmpty()) {
        return view('penerimaan.rbas', compact(
            'perPage', 'search', 'bulan', 'tahun', 'unitFilter',
            'unitOptions', 'bulanOptions', 'muridAktif'
        ) + [
            'sudahBayarList' => collect(),
            'belumBayarRbas' => collect(),
        ]);
    }

    // === Ambil record PENERIMAAN yang BENAR-BENAR pembayaran RBAS ===
    $penerimaanQuery = Penerimaan::where('tahun', $tahun)
        ->where('RBAS', '>', 0)  // Hanya yang nominal RBAS > 0 (pasti bayar RBAS)
        ->whereIn('nim', $muridAktif->pluck('nim'))
        ->select([
            'id',
            'nim',
            'kwitansi',
            'tanggal',
            'RBAS',
            'tanggal_penyerahan_rbas',
            'bulan', // untuk safety jika perlu debug
        ])
        ->orderBy('tanggal', 'desc'); // Urutkan terbaru dulu

    if ($bulan) {
        $penerimaanQuery->whereRaw('LOWER(TRIM(bulan)) = ?', [strtolower(trim($bulan))]);
    }

    // Ambil semua, lalu group by NIM dan ambil record terbaru per murid
    $penerimaanRbas = $penerimaanQuery->get()
        ->groupBy('nim')
        ->map(function ($group) {
            // Ambil record dengan tanggal terbaru (paling relevan)
            return $group->sortByDesc('tanggal')->first();
        });

    // === Suntikkan data penerimaan RBAS ke murid yang sudah bayar ===
    $sudahBayarList = $muridAktif
        ->whereIn('nim', $penerimaanRbas->keys())
        ->map(function ($murid) use ($penerimaanRbas) {
            $murid->penerimaan_rbas = $penerimaanRbas->get($murid->nim);
            return $murid;
        });

    // === Murid yang belum bayar RBAS ===
    $belumBayarRbas = $muridAktif->whereNotIn('nim', $penerimaanRbas->keys());

    return view('penerimaan.rbas', compact(
        'sudahBayarList',
        'belumBayarRbas',
        'perPage',
        'search',
        'bulan',
        'tahun',
        'unitFilter',
        'unitOptions',
        'bulanOptions',
        'muridAktif'
    ));
}

/**
 * Helper untuk daftar bulan Indonesia (bisa dipindah ke trait jika perlu)
 */
private function getBulanOptions(): array
{
    return [
        'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
        'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'
    ];
}
// Di PenerimaanController

public function getMuridByUnit(Request $request)
{
    $unit = $request->input('bimba_unit');

    $murid = BukuInduk::whereIn(DB::raw('LOWER(status)'), ['aktif', 'baru'])
        ->when($unit, function ($q) use ($unit) {
            return $q->where('bimba_unit', $unit);
        })
        ->orderBy('nama')
        ->select('nim', 'nama as nama_murid')
        ->get();

    return response()->json($murid);
}

public function export(Request $request)
{
    $query = Penerimaan::query();

    // Filter (sama seperti index)
    $search = trim($request->input('search', ''));
    if (!empty($search)) {
        if (str_contains($search, '|')) {
            [$nim] = array_map('trim', explode('|', $search));
            $query->where('nim', $nim);
        } else {
            $query->where(function ($q) use ($search) {
                $q->where('nim', 'like', "%{$search}%")
                  ->orWhere('nama_murid', 'like', "%{$search}%");
            });
        }
    }

    if ($request->bulan) $query->where('bulan', $request->bulan);
    if ($request->tahun) $query->where('tahun', $request->tahun);
    if ($request->bimba_unit) $query->where('bimba_unit', $request->bimba_unit);

    $penerimaan = $query->orderBy('tanggal', 'desc')->get();

    return Excel::download(
        new PenerimaanExport($penerimaan),
        'Penerimaan_SPP_' . now()->format('Y-m-d_His') . '.xlsx'
    );
}
}
