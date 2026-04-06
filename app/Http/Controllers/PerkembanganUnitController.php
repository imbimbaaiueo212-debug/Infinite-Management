<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use App\Models\BukuInduk;
use App\Models\Unit;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class PerkembanganUnitController extends Controller
{
    public function index(Request $request)
    {
        Carbon::setLocale('id');

        $user = Auth::user();
        $isAdmin = in_array($user->role ?? '', ['admin', 'superadmin', 'pusat']);

        $tahunMulai = (int) $request->input('tahun_mulai', date('Y'));
        $bulan = $request->filled('bulan') ? (int) $request->input('bulan') : null;
        if ($bulan !== null && ($bulan < 1 || $bulan > 12)) {
            $bulan = null;
        }

        // Hak akses unit
        if (!$isAdmin) {
            $bimba_unit_input = $user->bimba_unit ?? '';
            $no_cabang        = $user->no_cabang ?? '';

            if (empty($bimba_unit_input) || empty($no_cabang)) {
                return redirect()->route('dashboard')
                    ->with('error', 'Unit Anda belum terdaftar di sistem.');
            }
        } else {
            $bimba_unit_input = $request->input('bimba_unit');
            $no_cabang        = $request->input('no_cabang');
        }

        $bimba_unit_norm = mb_strtoupper(trim((string) $bimba_unit_input));

        $unitTerpilih = null;
        if ($bimba_unit_norm !== '') {
            $unitTerpilih = Unit::whereRaw('TRIM(UPPER(bimba_unit)) = ?', [$bimba_unit_norm])->first();
            if ($unitTerpilih && empty($no_cabang)) {
                $no_cabang = $unitTerpilih->no_cabang;
            }
        }

        if ($bimba_unit_norm === '' || empty($no_cabang) || !$unitTerpilih) {
            return view('perkembangan_units.index', [
                'unitTerpilih'      => null,
                'bimba_unit'        => $bimba_unit_input,
                'no_cabang'         => $no_cabang,
                'tahunMulai'        => $tahunMulai,
                'bulan'             => $bulan,
                'mb'                => array_fill(0, 12, 0),
                'mk'                => array_fill(0, 12, 0),
                'ma'                => array_fill(0, 12, 0),
                'bnf'               => array_fill(0, 12, 0),
                'd'                 => array_fill(0, 12, 0),
                'aktifDesemberLalu' => 0,
            ]);
        }

        $base = BukuInduk::query();

        if (!$isAdmin) {
            $base->where('bimba_unit', $user->bimba_unit)
                 ->where('no_cabang', $user->no_cabang);
        } else {
            $base->whereRaw('TRIM(UPPER(bimba_unit)) = ?', [$bimba_unit_norm])
                 ->where('no_cabang', $no_cabang);
        }

        $mb  = array_fill(0, 12, 0);
        $mk  = array_fill(0, 12, 0);
        $ma  = array_fill(0, 12, 0); // default 0
        $bnf = array_fill(0, 12, 0);
        $d   = array_fill(0, 12, 0);

        // 1. MURID BARU (MB) - berdasarkan tgl_masuk saja
        $queryBaru = $base->clone()
            ->whereYear('tgl_masuk', $tahunMulai);

        if ($bulan !== null) {
            $queryBaru->whereMonth('tgl_masuk', $bulan);
        }

        $baru = $queryBaru->selectRaw('MONTH(tgl_masuk) as bulan, COUNT(*) as jumlah')
                          ->groupBy('bulan')
                          ->pluck('jumlah', 'bulan');

        foreach ($baru as $bln => $jumlah) {
            $mb[$bln - 1] = (int) $jumlah;
        }

        // 2. MURID KELUAR
        $keluarQuery = $base->clone()
            ->whereNotNull('tgl_keluar')
            ->whereYear('tgl_keluar', $tahunMulai);

        if ($bulan !== null) {
            $keluarQuery->whereMonth('tgl_keluar', $bulan);
        }

        $keluar = $keluarQuery->selectRaw('MONTH(tgl_keluar) as bulan, COUNT(*) as jumlah')
                              ->groupBy('bulan')
                              ->pluck('jumlah', 'bulan');

        foreach ($keluar as $bln => $jumlah) {
            $mk[$bln - 1] = (int) $jumlah;
        }

        // 3. BEASISWA BNF & D
        if (\Schema::hasColumn('buku_induk', 'status_beasiswa')) {
            $beasiswaQuery = $base->clone()
                ->whereYear('tgl_masuk', $tahunMulai)
                ->whereIn(DB::raw('UPPER(status_beasiswa)'), ['BNF', 'D']);

            if ($bulan !== null) {
                $beasiswaQuery->whereMonth('tgl_masuk', $bulan);
            }

            $beasiswa = $beasiswaQuery->selectRaw('UPPER(status_beasiswa) as jenis, MONTH(tgl_masuk) as bulan, COUNT(*) as jumlah')
                                      ->groupBy('jenis', 'bulan')
                                      ->get();

            foreach ($beasiswa as $row) {
                $i = $row->bulan - 1;
                if ($row->jenis === 'BNF') $bnf[$i] = (int) $row->jumlah;
                if ($row->jenis === 'D')   $d[$i]   = (int) $row->jumlah;
            }
        }

       // MA = saldo aktif per akhir bulan
$ma = array_fill(0, 12, 0);

$bulanLoop = $bulan !== null ? [$bulan] : range(1, 12);

foreach ($bulanLoop as $m) {
    $cutoff = Carbon::create($tahunMulai, $m, 1)
        ->endOfMonth()
        ->endOfDay();

    $ma[$m - 1] = $base->clone()
        ->where('status', 'aktif')
        ->where('tgl_masuk', '<=', $cutoff)
        ->where(function ($q) use ($cutoff) {
            $q->whereNull('tgl_keluar')
              ->orWhere('tgl_keluar', '>', $cutoff);
        })
        ->count();
}
// --- Tambahan: Breakdown jumlah murid bayar SPP & total nominal per bulan ---

$sppPerBulan = [];

if ($unitTerpilih) {  // hanya jika unit sudah dipilih
    $tahun = $tahunMulai;

    // Tentukan akhir periode untuk cek status aktif
    $endOfPeriod = null;
    if ($bulan !== null) {
        // Jika filter per bulan, gunakan akhir bulan tersebut
        $endOfPeriod = Carbon::create($tahunMulai, $bulan, 1)->endOfMonth()->endOfDay();
    } else {
        // Jika tahun penuh, gunakan akhir tahun
        $endOfPeriod = Carbon::create($tahunMulai, 12, 31)->endOfDay();
    }

    $penerimaan = \App\Models\Penerimaan::query()
    ->where('penerimaan.tahun', $tahun)  // spesifik tabel
    ->when($bulan !== null, function ($q) use ($bulan) {
        $bulanNama = strtolower(Carbon::create()->month($bulan)->translatedFormat('F'));
        $q->whereRaw('LOWER(TRIM(penerimaan.bulan)) = ?', [$bulanNama]); // spesifik tabel
    })
    ->when($bimba_unit_norm, function ($q) use ($bimba_unit_norm) {
        $q->whereRaw('TRIM(UPPER(penerimaan.bimba_unit)) = ?', [$bimba_unit_norm]); // tambahkan penerimaan.
    })
    // JOIN ke buku_induk
    ->join('buku_induk', 'penerimaan.nim', '=', 'buku_induk.nim')
    ->where('buku_induk.status', 'Aktif')
    ->where(function ($q) use ($endOfPeriod) {
        $q->whereNull('buku_induk.tgl_keluar')
          ->orWhere('buku_induk.tgl_keluar', '>', $endOfPeriod);
    })
    ->where('buku_induk.tgl_masuk', '<=', $endOfPeriod)
    ->select('penerimaan.*') // pastikan hanya ambil kolom dari penerimaan
    ->get();

    // Grup per bulan (lowercase → ucfirst untuk tampilan)
    $grouped = $penerimaan
        ->groupBy(function ($item) {
            $bln = strtolower(trim($item->bulan ?? ''));
            return $bln;
        })
        ->map(function ($group, $bulanLower) use ($tahun) {
            // Jumlah murid unik (sudah pasti aktif karena difilter join)
            $jumlahMurid = $group->unique('nim')->count();

            $totalNominal = $group->sum(function ($item) {
                return (float) ($item->spp ?? $item->jumlah ?? $item->nominal ?? $item->total ?? $item->bayar ?? 0);
            });

            return [
                'bulan'        => ucfirst($bulanLower),
                'jumlah_murid' => $jumlahMurid,
                'total_spp'    => $totalNominal,
            ];
        });

    // Ubah ke array ber-index 0–11 (sesuai urutan bulan)
    $bulanList = [
        'januari', 'februari', 'maret', 'april', 'mei', 'juni',
        'juli', 'agustus', 'september', 'oktober', 'november', 'desember'
    ];

    foreach ($bulanList as $idx => $bln) {
        $data = $grouped->firstWhere('bulan', ucfirst($bln));
        $sppPerBulan[$idx] = $data ? $data : ['jumlah_murid' => 0, 'total_spp' => 0];
    }
} else {
    $sppPerBulan = array_fill(0, 12, ['jumlah_murid' => 0, 'total_spp' => 0]);
}


        return view('perkembangan_units.index', [
            'unitTerpilih'      => $unitTerpilih,
            'bimba_unit'        => $bimba_unit_input,
            'no_cabang'         => $no_cabang,
            'tahunMulai'        => $tahunMulai,
            'bulan'             => $bulan,
            'mb'                => $mb,
            'mk'                => $mk,
            'ma'                => $ma,
            'bnf'               => $bnf,
            'd'                 => $d,
            'sppPerBulan'       => $sppPerBulan,   // <-- tambahkan ini
        ]);
    }
}