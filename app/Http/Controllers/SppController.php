<?php

namespace App\Http\Controllers;

use App\Models\BukuInduk;
use App\Models\Penerimaan;
use App\Models\Spp;
use App\Models\Unit;
use App\Services\GoogleFormService;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class SppController extends Controller
{
            public function index(Request $request, GoogleFormService $googleForm)
        {
            // Inisialisasi default semua variabel yang akan di-compact
            $sudahBayar    = collect();
            $belumBayar    = collect();
            $bayarDouble   = collect();
            $tahapMapping  = [];
            $guruMapping   = [];
            $units         = collect();
            $filterUnit    = $request->input('bimba_unit');

            try {
                // 1. Dropdown unit
                $unitsQuery = Unit::withoutGlobalScopes()->orderBy('biMBA_unit');

                $user = auth()->user();
                $isPrivileged = $user?->can('view-all-units') ||
                                in_array($user?->role ?? '', ['admin', 'super-admin', 'keuangan', 'keuangan-pusat']);

                if (!$isPrivileged) {
                    $unitsQuery->where('biMBA_unit', $user->bimba_unit ?? null);
                }

                $units = $unitsQuery->get(['id', 'biMBA_unit', 'no_cabang']);

                $allOption = collect([
                    (object) [
                        'biMBA_unit' => 'semua',
                        'no_cabang'  => '',
                        'label'      => 'Semua Unit',
                    ]
                ]);

                $units = $allOption->merge($units);

                // 2. Filter bulan & tahun
        $bulanAwalRaw  = $request->input('bulan_awal', '-- Semua --');
        $bulanAkhirRaw = $request->input('bulan_akhir', '-- Semua --');

        $bulanAwal   = strtolower(trim($bulanAwalRaw));
        $bulanAkhir  = strtolower(trim($bulanAkhirRaw));
        $tahun       = (int) $request->input('tahun', now()->year);

        $bulanList = [
            'januari', 'februari', 'maret', 'april', 'mei', 'juni',
            'juli', 'agustus', 'september', 'oktober', 'november', 'desember'
        ];

        $isAllMonths = in_array($bulanAwal, ['-- semua --', '', '--semua--'], true);

        $indexAwalTemp  = array_search($bulanAwal, $bulanList, true);
        $indexAkhirTemp = array_search($bulanAkhir, $bulanList, true);

        $indexAwal  = $isAllMonths ? 0 : ($indexAwalTemp !== false ? $indexAwalTemp : 0);
        $indexAkhir = $isAllMonths ? 11 : ($indexAkhirTemp !== false ? $indexAkhirTemp : $indexAwal);

        // Paksa satu bulan jika awal & akhir sama
        if (!$isAllMonths && $bulanAwal === $bulanAkhir && $indexAwal !== false) {
            $indexAkhir = $indexAwal;
        }

        // Tentukan rentang tahun (untuk kasus lintas tahun)
        $tahunAwal  = $tahun;
        $tahunAkhir = $tahun;

        // Jika bulan akhir < bulan awal → lintas tahun (Desember → Januari)
        if (!$isAllMonths && $indexAkhir < $indexAwal) {
            $tahunAkhir = $tahun + 1;
        }

        // Log untuk debug
        \Log::info('Filter Periode Final', [
            'bulan_filter' => $bulanAwal . ' - ' . $bulanAkhir,
            'tahun_filter' => $tahun,
            'tahunAwal'    => $tahunAwal,
            'tahunAkhir'   => $tahunAkhir,
            'indexAwal'    => $indexAwal,
            'indexAkhir'   => $indexAkhir,
            'isAllMonths'  => $isAllMonths,
        ]);

        // Tentukan akhir periode (untuk filter murid wajib)
        $endOfPeriod = null;
        if ($isAllMonths) {
            $endOfPeriod = \Carbon\Carbon::create($tahunAkhir, 12, 31)->toDateString();
        } else {
            $endMonth = $indexAkhir + 1;
            $endOfPeriod = \Carbon\Carbon::create($tahunAkhir, $endMonth, 1)
                ->endOfMonth()
                ->toDateString();
        }

                // 3. Murid yang AKTIF pada periode filter (tgl_masuk <= akhir periode DAN (tgl_keluar IS NULL atau tgl_keluar > akhir periode))
$endOfPeriodCarbon = \Carbon\Carbon::parse($endOfPeriod);

$allMuridQuery = BukuInduk::query()
    ->where('status', 'Aktif') // tetap pakai jika ada
    ->where(function ($q) use ($endOfPeriodCarbon) {
        $q->whereNull('tgl_keluar')
          ->orWhere('tgl_keluar', '>', $endOfPeriodCarbon);
    })
    ->where('tgl_masuk', '<=', $endOfPeriodCarbon);

if ($filterUnit && $filterUnit !== 'semua' && $filterUnit !== '') {
    $allMuridQuery->where('bimba_unit', $filterUnit);
}

$allMurid = $allMuridQuery->get()
    ->mapWithKeys(fn($item) => [str_pad($item->nim ?? '', 5, '0', STR_PAD_LEFT) => $item]);

                // 4. Murid yang WAJIB bayar di periode ini
$wajibBayar = $allMurid->filter(function ($m) use ($endOfPeriodCarbon, $indexAwal, $tahun, $tahunAwal, $tahunAkhir) {
    $tglMasuk = $m->tgl_masuk;
    $tglKeluar = $m->tgl_keluar;

    if (is_null($tglMasuk) || trim($tglMasuk) === '') {
        return false;
    }

    try {
        $tglMasukCarbon = \Carbon\Carbon::parse($tglMasuk);

        // Harus sudah masuk sebelum/sampai akhir periode
        if ($tglMasukCarbon->gt($endOfPeriodCarbon)) {
            return false;
        }

        // Jika sudah punya tgl_keluar, cek apakah keluar sebelum periode
        if ($tglKeluar) {
            $tglKeluarCarbon = \Carbon\Carbon::parse($tglKeluar);
            if ($tglKeluarCarbon->lte($endOfPeriodCarbon)) {
                return false; // sudah keluar sebelum/sampai akhir periode → tidak wajib
            }
        }

        // Cutoff tanggal masuk di bulan yang sama
        $cutoffTanggal = 15;
        $bulanMasuk = $tglMasukCarbon->month;
        $tahunMasuk = $tglMasukCarbon->year;

        if ($tahunMasuk == $tahun && $bulanMasuk == ($indexAwal + 1)) {
            if ($tglMasukCarbon->day > $cutoffTanggal) {
                return false;
            }
        }

        return true;
    } catch (\Exception $e) {
        return false;
    }
});

                // 5. Penerimaan & filter pembayaran sesuai periode
                $penerimaanQuery = Penerimaan::query();

                if ($filterUnit && $filterUnit !== 'semua' && $filterUnit !== '') {
                    $penerimaanQuery->where('bimba_unit', $filterUnit);
                }

                $penerimaan = $penerimaanQuery->get()->map(function ($item) {
                    $nimPad = str_pad($item->nim ?? '', 5, '0', STR_PAD_LEFT);
                    $item->nim_padded   = $nimPad;
                    $item->bulan_pakai  = strtolower(trim($item->bulan ?? ''));
                    $item->tahun_pakai  = (int) ($item->tahun ?? now()->year);
                    $item->nilai_bayar  = $this->getPaidAmount($item);
                    return $item;
                });

                $filtered = $penerimaan->filter(function ($item) use ($tahun, $indexAwal, $indexAkhir, $bulanList, $isAllMonths) {
                    if ((int) $item->tahun_pakai !== (int) $tahun) return false;

                    if ($isAllMonths) return true;

                    $bulanLower = $item->bulan_pakai;
                    if (empty($bulanLower)) return false;

                    $idx = array_search($bulanLower, $bulanList, true);
                    if ($idx === false) return false;

                    if ($indexAwal === $indexAkhir) {
                        return $idx === $indexAwal;
                    }

                    if ($indexAwal < $indexAkhir) {
                        return $idx >= $indexAwal && $idx <= $indexAkhir;
                    }

                    return $idx >= $indexAwal || $idx <= $indexAkhir;
                });

                $filteredPositive = $filtered->filter(fn($i) => (float) $i->nilai_bayar > 0);

                $paidByNim = $filteredPositive
                    ->groupBy('nim_padded')
                    ->map(fn($rows) => $rows->sum('nilai_bayar'))
                    ->filter(fn($sum) => $sum > 0);

                $sudahBayar = $filteredPositive->values();

                // 6. Mapping guru, tahap, spp
// Biarkan mapping ini ambil semua murid aktif (tanpa filter tgl_keluar ketat), supaya guru tetap muncul
$baseMuridQuery = BukuInduk::where('status', 'Aktif')
    ->when($filterUnit && $filterUnit !== 'semua' && $filterUnit !== '', fn($q) => $q->where('bimba_unit', $filterUnit));

$guruMapping = (clone $baseMuridQuery)->get()
    ->mapWithKeys(fn($i) => [str_pad($i->nim ?? '', 5, '0', STR_PAD_LEFT) => $i->guru ?? '-'])
    ->toArray();

$tahapMapping = (clone $baseMuridQuery)->get()
    ->mapWithKeys(fn($i) => [str_pad($i->nim ?? '', 5, '0', STR_PAD_LEFT) => $i->tahap ?? '-'])
    ->toArray();

$sppMapping = Spp::query()
    ->get()
    ->mapWithKeys(fn($i) => [str_pad($i->nim ?? '', 5, '0', STR_PAD_LEFT) => $i->keterangan ?? '-'])
    ->toArray();

// Map dulu guru, tahap, spp ke semua item sudahBayar
$sudahBayar = $sudahBayar->map(function ($p) use ($guruMapping, $sppMapping) {
    $nimPad = $p->nim_padded ?? '';
    $p->guru           = $guruMapping[$nimPad] ?? '-';
    $p->keterangan_spp = $sppMapping[$nimPad] ?? '-';
    $p->note           = $p->note ?? '-';
    return $p;
});

// Ambil semua deposit untuk periode ini
$sudahBayar = $sudahBayar->map(function ($p) {
    $nimPad = $p->nim_padded ?? $p->nim ?? '';

    $deposit_keterangan = '-';

    if ($nimPad && !empty($p->tanggal) && !empty($p->bulan_pakai)) {
        try {
            $tglBayar = \Carbon\Carbon::parse($p->tanggal);
            $bulanBayarNama = strtolower(trim($p->bulan_pakai));

            // Mapping bulan
            $bulanMap = [
                'januari'   => 1, 'februari'  => 2, 'maret'     => 3, 'april'     => 4,
                'mei'       => 5, 'juni'      => 6, 'juli'      => 7, 'agustus'   => 8,
                'september' => 9, 'oktober'   => 10,'november'  => 11,'desember'  => 12
            ];

            $bulanTarget = $bulanMap[$bulanBayarNama] ?? null;

            if ($bulanTarget === null) {
                // Bulan tidak dikenali → skip
                $p->deposit_keterangan = '-';
                return $p;
            }

            // Tahun target: prioritaskan $p->tahun_pakai jika ada
            $tahunTarget = (int) ($p->tahun_pakai ?? $tglBayar->year);

            // Jika bulan target = Januari tapi bayar di Desember tahun sebelumnya → lintas tahun
            if ($bulanTarget === 1 && $tglBayar->month === 12 && $tglBayar->year === $tahunTarget - 1) {
                $tahunTarget = $tglBayar->year + 1;
            }

            // Buat tanggal awal bulan target untuk perbandingan
            $awalBulanTarget = \Carbon\Carbon::create($tahunTarget, $bulanTarget, 1);

            // Jika tanggal bayar < awal bulan target → dianggap deposit untuk bulan target
            if ($tglBayar->lt($awalBulanTarget)) {
                $deposit_keterangan = 'Deposit bulan ' . ucfirst($bulanBayarNama) . ' ' . $tahunTarget;
            }
        } catch (\Exception $e) {
            // Parsing gagal → tetap default
        }
    }

    $p->deposit_keterangan = $deposit_keterangan;

    return $p;
});
// Filter ketat: buang murid yang sudah keluar (berdasarkan $allMurid yang sudah difilter tgl_keluar)
$sudahBayar = $sudahBayar->filter(function ($p) use ($allMurid, $endOfPeriodCarbon) {
    $nimPad = $p->nim_padded ?? '';

    // Jika NIM tidak ada di daftar murid aktif periode ini → buang
    if (!$allMurid->has($nimPad)) {
        return false;
    }

    // Cek tambahan tgl_keluar (pengaman ekstra)
    $murid = $allMurid[$nimPad];
    if ($murid->tgl_keluar) {
        $tglKeluar = \Carbon\Carbon::parse($murid->tgl_keluar);
        if ($tglKeluar->lte($endOfPeriodCarbon)) {
            return false;
        }
    }

    return true;
})->values();  // reset index array

                // 7. Belum bayar: hanya murid WAJIB yang belum bayar
                $nimSudahBayar = $paidByNim->keys();

                $belumBayar = $wajibBayar->reject(function ($m) use ($nimSudahBayar) {
                    $nimPad = str_pad($m->nim ?? '', 5, '0', STR_PAD_LEFT);
                    return $nimSudahBayar->contains($nimPad);
                });
                // Hitung total nominal SPP yang belum dibayar oleh murid-murid di $belumBayar
$totalBelumBayar = $belumBayar->sum(function ($m) {
    // Sesuaikan field/nilai SPP yang benar di model BukuInduk
    // Contoh kemungkinan nama field (pilih satu yang sesuai):
    return (float) ($m->spp ?? $m->nilai_spp ?? $m->spp_bulan_ini ?? $m->biaya_spp ?? 0);
});

               // 8. Ambil status pernyataan dari tabel spp (lebih stabil)
$sppStatusMapping = Spp::query()
    ->get()
    ->mapWithKeys(function ($row) {
        return [
            str_pad($row->nim ?? '', 5, '0', STR_PAD_LEFT) => $row
        ];
    });

$belumBayar = $belumBayar->map(function ($m) use ($sppStatusMapping) {

    $nimPad = str_pad($m->nim ?? '', 5, '0', STR_PAD_LEFT);

    $sppData = $sppStatusMapping[$nimPad] ?? null;

    if ($sppData) {
        $m->sudahIsiForm = $sppData->status_pernyataan === 'Sudah Mengisi Form'
            || $sppData->status_pernyataan === 'Sudah Membuat Pernyataan';

        $m->file_pernyataan = $sppData->file_pernyataan;
        $m->tanggalIsiForm = $sppData->updated_at;
    } else {
        $m->sudahIsiForm = false;
        $m->file_pernyataan = null;
        $m->tanggalIsiForm = null;
    }

    return $m;
});

                // 9. Data tambahan
                $bayarDouble = $sudahBayar->groupBy('nim_padded')->filter(fn($g) => $g->count() > 1);
                $tahapanOptions = ['Persiapan', 'Lanjutan'];

            } catch (\Exception $e) {
                \Log::error('Error di SppController@index', [
                    'message' => $e->getMessage(),
                    'trace'   => $e->getTraceAsString(),
                ]);

                return view('spp.index', compact(
                    'sudahBayar',
                    'belumBayar',
                    'bayarDouble',
                    'bulanAwal',
                    'bulanAkhir',
                    'tahun',
                    'tahapanOptions',
                    'tahapMapping',
                    'units',
                    'filterUnit',
                ))->with('error', 'Terjadi kesalahan saat memuat data. Silakan coba lagi.');
            }

        //Grouping untuk breakdown per bulan (hanya untuk tampilan ringkasan)
        $breakdownPerBulan = $sudahBayar
            ->groupBy('bulan_pakai')
            ->map(function ($group) {
                return [
                    'bulan' => ucfirst($group->first()->bulan_pakai ?? '-'),
                    'jumlah_murid' => $group->unique('nim_padded')->count(),
                    'total_spp' => $group->sum('nilai_bayar'),
                ];
            })
            ->sortBy(function ($item) use ($bulanList) {
                return array_search(strtolower($item['bulan']), $bulanList);
            });

        // Pass ke view
        return view('spp.index', compact(
            'sudahBayar',
            'belumBayar',
            'bayarDouble',
            'bulanAwal',
            'bulanAkhir',
            'tahun',
            'tahapanOptions',
            'tahapMapping',
            'units',
            'filterUnit',
            'breakdownPerBulan',   // tambahkan ini
            'totalBelumBayar'
        ));
        }

    public function create()
{
    // data penerimaan (dipakai sebagai sumber NIM dll)
    $penerimaan = Penerimaan::all();

    // data murid dari buku induk
    $bukuInduk = BukuInduk::all();

    // daftar tahap yang dipakai di dropdown
    $tahapanOptions = ['Persiapan', 'Lanjutan'];

    // mapping nim => tahap
    $tahapMapping = BukuInduk::pluck('tahap', 'nim')->toArray();

    return view('spp.create', compact('penerimaan', 'bukuInduk', 'tahapanOptions', 'tahapMapping'));
}
    public function store(Request $request)
    {
        $request->validate([
            'nim' => 'required',
            'nama_murid' => 'required',
            'kelas' => 'required',
        ]);

        Spp::create($request->all());

        return redirect()->route('spp.index')->with('success', 'Data SPP berhasil ditambahkan.');
    }

    public function edit(Spp $spp)
    {
        return view('spp.edit', compact('spp'));
    }

    public function update(Request $request, Spp $spp)
    {
        $spp->update($request->all());
        return redirect()->route('spp.index')->with('success', 'Data SPP berhasil diperbarui.');
    }

    public function destroy(Spp $spp)
    {
        $spp->delete();
        return redirect()->route('spp.index')->with('success', 'Data SPP berhasil dihapus.');
    }

    public function notifBelumBayar(Request $request = null)
{
    $today = now();

    // Ambil filter bulan & tahun (default ke bulan & tahun ini)
    $bulanAwal = strtolower($request->get('bulan_awal', $today->format('F')));
    $bulanAkhir = strtolower($request->get('bulan_akhir', $today->format('F')));
    $tahun = $request->get('tahun', $today->year);

    // Jika belum tanggal 5, kosongkan notif (opsional)
    if ($today->day < 5) {
        return collect();
    }

    $bulanRange = $this->getMonthRange($bulanAwal, $bulanAkhir);

    // Semua murid aktif
    $murid = BukuInduk::where('status', 'Aktif')->get();

    // Ambil semua penerimaan berdasarkan bulan & tahun
    $penerimaan = Penerimaan::where('tahun', $tahun)
        ->whereIn('bulan', $bulanRange)
        ->get()
        ->map(fn($item) => str_pad($item->nim, 5, '0', STR_PAD_LEFT));

    // Filter murid yang belum bayar
    $belumBayar = $murid->reject(fn($m) =>
        $penerimaan->contains(str_pad($m->nim, 5, '0', STR_PAD_LEFT))
    );

    // Ambil semua murid yang sudah membuat pernyataan dari tabel Spp
    $sudahPernyataan = \App\Models\Spp::where('status_pernyataan', 'Sudah Membuat Pernyataan')
        ->pluck('nim')
        ->map(fn($nim) => str_pad($nim, 5, '0', STR_PAD_LEFT));

    // Filter lagi: hanya tampilkan yang BELUM membuat pernyataan
    $belumBayarBelumPernyataan = $belumBayar->reject(fn($m) =>
        $sudahPernyataan->contains(str_pad($m->nim, 5, '0', STR_PAD_LEFT))
    );

    return $belumBayarBelumPernyataan->values();
}

 private function getMonthRange($bulanAwal, $bulanAkhir)
{
    $bulanList = [
        'januari', 'februari', 'maret', 'april', 'mei', 'juni',
        'juli', 'agustus', 'september', 'oktober', 'november', 'desember'
    ];

    $startIndex = array_search($bulanAwal, $bulanList);
    $endIndex   = array_search($bulanAkhir, $bulanList);

    if ($startIndex === false || $endIndex === false) {
        return [$bulanAwal]; // atau bisa throw exception / log error
    }

    if ($startIndex > $endIndex) {
        // kasus wrap-around: Desember → Januari
        return array_merge(
            array_slice($bulanList, $startIndex),
            array_slice($bulanList, 0, $endIndex + 1)
        );
    }

    return array_slice($bulanList, $startIndex, $endIndex - $startIndex + 1);
}


public function suratKeterlambatan($nim, Request $request, GoogleFormService $googleForm)
{
    // Hilangkan leading zero untuk matching yang lebih aman
    $nimClean = ltrim($nim, '0');

    // Ambil data murid dari database (pakai nim asli dari URL)
    $murid = BukuInduk::where('nim', $nim)->first();

    if (!$murid) {
        return redirect()->route('spp.index')->with('error', 'Data murid tidak ditemukan.');
    }

    // Ambil daftar murid belum bayar
    $belumBayar = $this->notifBelumBayar($request);

    // Cek apakah murid ini termasuk dalam daftar belum bayar
    $isBelumBayar = $belumBayar->contains(function ($m) use ($nimClean) {
        return ltrim($m->nim ?? '', '0') === $nimClean;
    });

    // Untuk test: comment dulu redirect ini agar blade selalu muncul
    // if (!$isBelumBayar) {
    //     return redirect()->route('spp.index')->with('error', 'Murid ini sudah membayar, surat tidak dapat ditampilkan.');
    // }

    // Ambil hasil Google Form
    $responses = $googleForm->getResponses();

    // Log sederhana untuk debug (lihat di storage/logs/laravel.log)
    \Log::info('suratKeterlambatan called', [
        'nim_input' => $nim,
        'nim_clean' => $nimClean,
        'responses_count' => count($responses),
    ]);

    // Cari entri yang cocok dengan NIM (case-insensitive key & trim)
    $dataForm = collect($responses)->first(function ($r) use ($nimClean) {
        $nimSheet = trim($r['NIM'] ?? $r['nim'] ?? '');
        return ltrim($nimSheet, '0') === $nimClean;
    });

    // Debug: log apakah dataForm ditemukan dan key apa saja
    if ($dataForm) {
        \Log::info('Data form ditemukan untuk NIM ' . $nimClean, [
            'keys' => array_keys($dataForm),
            'sample' => $dataForm['Golongan'] ?? $dataForm['golongan'] ?? 'tidak ada Golongan',
        ]);
    } else {
        \Log::warning('Data form TIDAK ditemukan untuk NIM ' . $nimClean, [
            'nim_sheet_sample' => collect($responses)->pluck('NIM')->take(3)->toArray(),
        ]);
    }

    // Jika tidak ditemukan → fallback ke default kosong
    if (!$dataForm) {
        $dataForm = [
            'Nama Orangtua' => '',
            'Nama Murid' => '',
            'Tanggal lahir' => '',
            'NIM' => $nim,
            'Golongan' => '',
            'SPP' => '',
            'Tanggal Bayar (tgl/bln/thn)' => '',
            'Unit' => '',
            'Alamat / Unit' => '',
            'Email Address' => '',
        ];
    }

    // Helper untuk ambil value case-insensitive + trim
    $get = function ($key) use ($dataForm) {
        if (!$dataForm) return null;
        $keyLower = strtolower(trim($key));
        foreach ($dataForm as $k => $v) {
            if (strtolower(trim($k)) === $keyLower) {
                return trim($v ?? '');
            }
        }
        return null;
    };

    // Buat object spp
    $spp = (object) [
        'tgl_bayar' => $get('Tanggal Bayar (tgl/bln/thn)') ?? '',
        'spp'       => $get('SPP') ?? '',
        'no_surat'  => 'SPKB-' . strtoupper(substr(md5($nim), 0, 5)),
    ];

    // Lengkapi data murid dari form (prioritas) atau dari DB (fallback)
    $murid->orangtua = $get('Nama Orangtua') ?? $murid->orangtua ?? '';
    $murid->nama     = $get('Nama Murid')    ?? $murid->nama     ?? '';
    $murid->gol      = $get('Golongan')      ?? $murid->gol      ?? '';
    $murid->unit     = $get('Unit')           ?? $murid->unit     ?? '';

    // Default kota
    $murid->kota = $murid->kota ?? 'Bekasi';

    // Pemohon (prioritas dari form)
    $pemohon = $get('Nama Orangtua') ?? '';
    if (empty($pemohon)) {
        $pemohon = $murid->orangtua 
                ?? $murid->nama_orangtua 
                ?? $murid->nama_ayah 
                ?? $murid->nama_ibu 
                ?? '-';
    }
    $pemohon = trim($pemohon) ?: '-';

    $waliKelas = $murid->guru ?? '-';

    $kepalaUnit = \App\Models\Profile::where('jabatan', 'Kepala Unit')
        ->where('bimba_unit', $murid->bimba_unit)
        ->value('nama') ?? '-';

    $unit = Unit::where('biMBA_unit', $murid->bimba_unit)->first();

    // Alamat lengkap unit
    $footerAlamat = '-';
    if ($unit) {
        $parts = array_filter([
            $unit->alamat_jalan ?? null,
            $unit->alamat_rt_rw ? 'RT/RW ' . $unit->alamat_rt_rw : null,
            $unit->alamat_kel_des,
            $unit->alamat_kecamatan ? 'Kec. ' . $unit->alamat_kecamatan : null,
            $unit->alamat_kota_kab ? ($unit->alamat_kota_kab === 'KOTA BEKASI' ? 'Kota Bekasi' : $unit->alamat_kota_kab) : null,
            $unit->alamat_provinsi ?? null,
        ]);
        $alamatStr = implode(', ', $parts);
        if ($unit->alamat_kode_pos) $alamatStr .= ' ' . $unit->alamat_kode_pos;
        $footerAlamat = $alamatStr ?: 'Alamat unit tidak lengkap';
    }

    $footerTelepon = $unit ? ($unit->TELP ?? $unit->telp ?? $unit->telepon ?? '-') : '-';
    $footerWebsite = 'www.bimba-aiueo.com';

    return view('spp.spkb', compact(
        'murid', 
        'spp', 
        'pemohon', 
        'waliKelas', 
        'kepalaUnit',
        'footerAlamat',
        'footerTelepon',
        'footerWebsite',
        'unit'
    ));
}



public function uploadPernyataan(Request $request, $nim)
{
    $request->validate([
        'file_pernyataan' => 'required|file|mimes:pdf,jpg,jpeg,png|max:2048',
    ]);

    $murid = \App\Models\BukuInduk::where('nim', $nim)->first();

    if (!$murid) {
        return back()->with('error', 'Data murid tidak ditemukan.');
    }

    // Simpan file
    $path = $request->file('file_pernyataan')->store('pernyataan', 'public');

    // Simpan ke tabel SPP
$spp = \App\Models\Spp::updateOrCreate(
    ['nim' => $nim],
    [
        'nama_murid' => $murid->nama,
        'kelas' => $murid->kelas,
        'file_pernyataan' => $path,
        'status_pernyataan' => 'Sudah Membuat Pernyataan',
    ]
);
    return back()->with('success', 'File pernyataan berhasil diupload.');
}

public function pollingStatus(Request $request, GoogleFormService $googleForm)
{
    $bulanAwal = $request->get('bulan_awal');
    $bulanAkhir = $request->get('bulan_akhir');
    $tahun = $request->get('tahun');

    // Ambil semua murid aktif
    $murid = BukuInduk::where('status', 'Aktif')->get();

    // Ambil data form dari Google Form
    $formResponses = collect($googleForm->getResponses());
    $formMapping = $formResponses->mapWithKeys(function ($r) {
        $nim = str_pad($r['NIM'] ?? '', 5, '0', STR_PAD_LEFT);
        return [$nim => [
            'sudahIsiForm' => true,
            'tanggalIsiForm' => $r['Timestamp'] ?? null
        ]];
    });

    // Gabungkan hanya status form ke daftar murid
    $data = $murid->map(function ($m) use ($formMapping) {
        $nim = str_pad($m->nim, 5, '0', STR_PAD_LEFT);
        return [
            'nim' => $nim,
            'sudahIsiForm' => $formMapping[$nim]['sudahIsiForm'] ?? false,
            'tanggalIsiForm' => $formMapping[$nim]['tanggalIsiForm'] ?? null,
        ];
    });

    return response()->json([
        'update' => $data
    ]);
}

public function show(Spp $spp)
{
    abort(404); // atau return view kosong
}
private function getPaidAmount($item): float
{
    // sesuaikan kemungkinan nama kolom di tabel penerimaan
    // urutan prioritas: spp, jumlah, nominal, total, bayar
    return (float)($item->spp
        ?? $item->jumlah
        ?? $item->nominal
        ?? $item->total
        ?? $item->bayar
        ?? 0);
}

public function syncGoogleForm(Request $request, GoogleFormService $googleForm)
{
    try {

        $filterUnit = $request->query('bimba_unit');
        $tahun      = $request->query('tahun', now()->year);

        $cacheKey = 'google_form_responses_' . md5($filterUnit ?? 'all_' . $tahun);
        cache()->forget($cacheKey); // 🔥 WAJIB

        $responses = collect($googleForm->getResponses());

        // ─── LOG PENTING #1 ───
        \Log::info('SYNC GOOGLE FORM - Mulai', [
            'total_responses' => $responses->count(),
            'sample_keys'     => $responses->isNotEmpty() ? array_keys($responses->first()) : 'kosong',
            'filter_unit'     => $filterUnit,
            'tahun'           => $tahun,
        ]);

        $updatedCount = 0;
        $skippedReasons = ['empty_nim' => 0, 'murid_not_found' => 0, 'no_change' => 0];

        foreach ($responses as $index => $row) {
            $nimRaw = trim($row['NIM'] ?? '');
            
            if (empty($nimRaw)) {
                $skippedReasons['empty_nim']++;
                continue;
            }

           $nim = trim($nimRaw);

            // ─── LOG PENTING #2 ─── (hanya log 5 pertama biar log tidak banjir)
            if ($index < 5) {
                \Log::info('Processing row #' . $index, [
                    'nim_raw' => $nimRaw,
                    'nim_padded' => $nim,
                    'nama' => $row['Nama Murid'] ?? '(kosong)',
                    'timestamp' => $row['Timestamp'] ?? '(kosong)',
                ]);
            }

            $murid = BukuInduk::where('nim', $nim)->first();
            if (!$murid) {
                $skippedReasons['murid_not_found']++;
                \Log::warning('Murid tidak ditemukan untuk NIM', ['nim' => $nim]);
                continue;
            }

            // Cek status sebelum update (untuk debug)
            $existingSpp = Spp::where('nim', $nim)->first();
$oldStatus = $existingSpp ? $existingSpp->status_pernyataan : 'belum ada record';

            $spp = Spp::updateOrCreate(
                ['nim' => $nim],
                [
                    'nama_murid'        => $row['Nama Murid'] ?? $murid->nama ?? '-',
                    'kelas'             => $murid->kelas ?? '-',
                    'status_pernyataan' => 'Sudah Mengisi Form',
                    'file_pernyataan'   => null,
                ]
            );

            $newStatus = $spp->status_pernyataan;

            if ($spp->wasRecentlyCreated) {
                $updatedCount++;
                \Log::info('Record BARU dibuat', ['nim' => $nim, 'status' => $newStatus]);
            } elseif ($spp->wasChanged() || $oldStatus !== $newStatus) {
                $updatedCount++;
                \Log::info('Record DI-UPDATE', ['nim' => $nim, 'old' => $oldStatus, 'new' => $newStatus]);
            } else {
                $skippedReasons['no_change']++;
            }
        }

        // ─── LOG RINGKASAN AKHIR ───
        \Log::info('SYNC GOOGLE FORM - Selesai', [
            'updated_count'     => $updatedCount,
            'skipped_empty_nim' => $skippedReasons['empty_nim'],
            'skipped_no_murid'  => $skippedReasons['murid_not_found'],
            'skipped_no_change' => $skippedReasons['no_change'],
        ]);

        cache()->forget('google_form_responses_' . md5($filterUnit ?? 'all_' . $tahun));

        return response()->json([
            'success' => true,
            'message' => "Berhasil menyinkronkan $updatedCount record dari Google Form.",
            'updated' => $updatedCount,
        ]);

    } catch (\Exception $e) {
        \Log::error('Gagal sync Google Form', [
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
        ]);

        return response()->json([
            'success' => false,
            'message' => 'Gagal menyinkronkan data: ' . $e->getMessage(),
        ], 500);
    }
}

}
