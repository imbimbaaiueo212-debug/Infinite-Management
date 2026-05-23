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
        $ma  = array_fill(0, 12, 0);
        $bnf = array_fill(0, 12, 0);
        $d   = array_fill(0, 12, 0);

        // 1. MURID BARU (MB)
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

        // 3. BNF & DHUAFA (menggunakan GOL dari Buku Induk)
        $beasiswaQuery = $base->clone()
            ->whereYear('tgl_masuk', $tahunMulai)
            ->whereNotNull('gol');

        if ($bulan !== null) {
            $beasiswaQuery->whereMonth('tgl_masuk', $bulan);
        }

        $beasiswa = $beasiswaQuery->selectRaw(
                "TRIM(UPPER(gol)) as jenis, 
                 MONTH(tgl_masuk) as bulan, 
                 COUNT(*) as jumlah"
            )
            ->groupBy('jenis', 'bulan')
            ->get();

        foreach ($beasiswa as $row) {
            $i = $row->bulan - 1;
            if (in_array($row->jenis, ['S3B1', 'S3B2', 'S3B3'])) {
                $bnf[$i] = (int) $row->jumlah;
            }
            if ($row->jenis === 'D') {
                $d[$i] = (int) $row->jumlah;
            }
        }

        // 4. MA = Murid Aktif per akhir bulan
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

        // 5. SPP PER BULAN dari tabel penerimaan
        $sppPerBulan = $this->getSppPerBulan($tahunMulai, $bulan, $bimba_unit_norm);

        // 6. TOTAL BNF & DHUAFA SEMUA TAHUN
        $totalBnfAllTime = $base->clone()
            ->whereNotNull('gol')
            ->whereIn(DB::raw('TRIM(UPPER(gol))'), ['S3B1', 'S3B2', 'S3B3'])
            ->count();

        $totalDhuafaAllTime = $base->clone()
            ->whereNotNull('gol')
            ->where(DB::raw('TRIM(UPPER(gol))'), 'D')
            ->count();

        return view('perkembangan_units.index', [
            'unitTerpilih'          => $unitTerpilih,
            'bimba_unit'            => $bimba_unit_input,
            'no_cabang'             => $no_cabang,
            'tahunMulai'            => $tahunMulai,
            'bulan'                 => $bulan,
            'mb'                    => $mb,
            'mk'                    => $mk,
            'ma'                    => $ma,
            'bnf'                   => $bnf,
            'd'                     => $d,
            'sppPerBulan'           => $sppPerBulan,
            'totalBnfAllTime'       => $totalBnfAllTime,
            'totalDhuafaAllTime'    => $totalDhuafaAllTime,
        ]);
    }

    /**
     * Ambil data SPP per bulan dari tabel penerimaan
     */
    private function getSppPerBulan($tahun, $bulanFilter = null, $bimba_unit_norm = null)
    {
        $query = \App\Models\Penerimaan::query()
            ->where('tahun', $tahun)
            ->when($bimba_unit_norm, function ($q) use ($bimba_unit_norm) {
                $q->whereRaw('TRIM(UPPER(bimba_unit)) = ?', [$bimba_unit_norm]);
            });

        if ($bulanFilter !== null) {
            $bulanNama = strtolower(Carbon::create()->month($bulanFilter)->translatedFormat('F'));
            $query->whereRaw('LOWER(TRIM(bulan)) = ?', [$bulanNama]);
        }

        $penerimaan = $query->get();

        $grouped = $penerimaan
            ->groupBy(function ($item) {
                return strtolower(trim($item->bulan ?? ''));
            })
            ->map(function ($group) {
                return [
                    'bulan'        => ucfirst(strtolower(trim($group->first()->bulan ?? ''))),
                    'jumlah_murid' => $group->unique('nim')->count(),
                    'total_spp'    => $group->sum(function ($item) {
                        return (float) ($item->spp ?? $item->jumlah ?? $item->nominal ?? $item->total ?? 0);
                    }),
                ];
            });

        $bulanList = ['januari','februari','maret','april','mei','juni','juli','agustus','september','oktober','november','desember'];
        $result = [];

        foreach ($bulanList as $idx => $bln) {
            $data = $grouped->firstWhere('bulan', ucfirst($bln));
            $result[$idx] = $data ?? ['jumlah_murid' => 0, 'total_spp' => 0];
        }

        return $result;
    }
}