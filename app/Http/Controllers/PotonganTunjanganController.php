<?php

namespace App\Http\Controllers;

use App\Models\PotonganTunjangan;
use App\Models\PendapatanTunjangan;
use App\Models\AbsensiRelawan;
use App\Models\Profile;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class PotonganTunjanganController extends Controller
{
    /**
     * Helper: bersihkan dan normalisasi input currency/nominal
     */
    private function normalizeCurrency($value)
    {
        if ($value === null || $value === '') {
            return null;
        }

        $val = trim((string)$value);
        $val = preg_replace('/[^\d\.,-]/u', '', $val);

        if (strpos($val, ',') !== false && strpos($val, '.') !== false) {
            $val = str_replace('.', '', $val);
            $val = str_replace(',', '.', $val);
        } elseif (strpos($val, ',') !== false && strpos($val, '.') === false) {
            $val = str_replace(',', '.', $val);
        } else {
            $val = str_replace('.', '', $val);
        }

        return is_numeric($val) ? $val : null;
    }

    /**
     * Index
     */
    public function index(Request $request)
    {

        $this->runSyncFromAbsensi(); // AUTO SINKRON

    $query = PotonganTunjangan::with('pendapatan');
    
        $query = PotonganTunjangan::with('pendapatan');

        $monthFrom = $request->input('month_from');
        $monthTo   = $request->input('month_to');

        try {
            if ($monthFrom && !preg_match('/^\d{4}-\d{2}$/', $monthFrom)) {
                $monthFrom = Carbon::parse($monthFrom)->format('Y-m');
            }
        } catch (\Throwable $e) {
            $monthFrom = null;
        }
        try {
            if ($monthTo && !preg_match('/^\d{4}-\d{2}$/', $monthTo)) {
                $monthTo = Carbon::parse($monthTo)->format('Y-m');
            }
        } catch (\Throwable $e) {
            $monthTo = null;
        }

        if ($monthFrom && $monthTo) {
            $query->whereBetween('bulan', [$monthFrom, $monthTo]);
        } elseif ($monthFrom) {
            $query->where('bulan', '>=', $monthFrom);
        } elseif ($monthTo) {
            $query->where('bulan', '<=', $monthTo);
        }

        $potonganTunjangans = $query->orderBy('bulan', 'desc')->get();

        return view('potongan.index', [
            'potonganTunjangans' => $potonganTunjangans,
            'filter_month_from'  => $monthFrom,
            'filter_month_to'    => $monthTo,
        ]);
    }

    /**
     * Create form
     */
    public function create()
{
    $pendapatans = PendapatanTunjangan::query()
        ->select(
            'id',
            'nik',
            'nama',
            'jabatan',
            'status',
            'departemen',
            'masa_kerja',
            'bimba_unit',
            'no_cabang'
        )
        ->orderBy('nama', 'asc')
        ->get()
        ->unique('nik')        // ✅ kunci utama agar tidak dobel
        ->values();            // reset index collection

    return view('potongan.create', compact('pendapatans'));
}


    /**
     * Store
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'pendapatan_id'        => 'required|exists:pendapatan_tunjangan,id',
            'jabatan'              => 'nullable|string|max:100',
            'status'               => 'nullable|string|max:50',
            'departemen'           => 'nullable|string|max:100',
            'masa_kerja_display'   => 'nullable|string|max:50',
            'sakit'                => 'nullable|numeric',
            'izin'                 => 'nullable|numeric',
            'alpa'                 => 'nullable|numeric',
            'tidak_aktif'          => 'nullable|numeric',
            'kelebihan'            => 'nullable|numeric',
            'kelebihan_nominal'    => 'nullable|numeric',
            'kelebihan_bulan'      => 'nullable|string|max:7', // YYYY-MM
            'bulan'                => 'nullable|string|max:7',
            'lain_lain'            => 'nullable|numeric',
            'total'                => 'nullable|numeric',
            // cash advance
            'cash_advance_nominal' => 'nullable|numeric',
            'cash_advance_note'    => 'nullable|string|max:255',
        ]);

        $pendapatan = PendapatanTunjangan::findOrFail($validated['pendapatan_id']);

        $validated['nama']  = $pendapatan->nama;
        $validated['bulan'] = $validated['bulan'] ?? Carbon::now()->format('Y-m');

        $profile = Profile::where('nama', $pendapatan->nama)->first();

        if ($profile) {
            $validated['nik']        = $profile->nik ?? '-';
            $validated['masa_kerja'] = $profile->masa_kerja ?? 0;
            $validated['jabatan']    = $validated['jabatan'] ?? ($pendapatan->jabatan ?? $profile->jabatan ?? null);
            $validated['departemen'] = $validated['departemen'] ?? ($pendapatan->departemen ?? $profile->departemen ?? null);
            $validated['status']     = $validated['status'] ?? ($pendapatan->status ?? $profile->status_karyawan ?? null);

            // ✅ Tambahkan info unit & cabang (prioritas dari Pendapatan, fallback ke Profile)
            $validated['bimba_unit'] = $pendapatan->bimba_unit
                ?? $profile->bimba_unit
                ?? $profile->nama_unit
                ?? null;

            $validated['no_cabang']  = $pendapatan->no_cabang
                ?? $profile->no_cabang
                ?? $profile->kode_cabang
                ?? null;
        } else {
            $validated['nik']        = '-';
            $validated['masa_kerja'] = 0;
            // tetap boleh isi jabatan/status/departemen dari pendapatan kalau ada
            $validated['jabatan']    = $validated['jabatan'] ?? $pendapatan->jabatan;
            $validated['departemen'] = $validated['departemen'] ?? $pendapatan->departemen;
            $validated['status']     = $validated['status'] ?? $pendapatan->status;
            $validated['bimba_unit'] = $pendapatan->bimba_unit ?? null;
            $validated['no_cabang']  = $pendapatan->no_cabang ?? null;
        }

        // Normalize possible string numbers
        if ($request->filled('kelebihan_nominal')) {
            $normalized = $this->normalizeCurrency($request->input('kelebihan_nominal'));
            if ($normalized !== null) $validated['kelebihan_nominal'] = $normalized;
        }
        if ($request->filled('cash_advance_nominal')) {
            $normalized = $this->normalizeCurrency($request->input('cash_advance_nominal'));
            if ($normalized !== null) $validated['cash_advance_nominal'] = $normalized;
        }

        // calculate total (including cash advance by default)
        $sakit            = (float) ($validated['sakit'] ?? 0);
        $izin             = (float) ($validated['izin'] ?? 0);
        $alpa             = (float) ($validated['alpa'] ?? 0);
        $tidakAktif       = (float) ($validated['tidak_aktif'] ?? 0);
        $kelebihanNominal = (float) ($validated['kelebihan_nominal'] ?? $validated['kelebihan'] ?? 0);
        $lainLain         = (float) ($validated['lain_lain'] ?? 0);
        $cashAdvance      = (float) ($validated['cash_advance_nominal'] ?? 0);

        if (!isset($validated['total']) || $validated['total'] === null) {
            $validated['total'] = $sakit + $izin + $alpa + $tidakAktif + $kelebihanNominal + $lainLain + $cashAdvance;
        }

        PotonganTunjangan::create($validated);

        return redirect()->route('potongan.index')->with('success', 'Data berhasil ditambahkan');
    }

    /**
     * Edit form
     */
    public function edit(PotonganTunjangan $potongan)
    {
        $profile = Profile::where('nik', $potongan->nik)
                          ->orWhere('nama', $potongan->nama)
                          ->first();

        if ($profile) {
            if (empty($potongan->nik) || $potongan->nik === '-') {
                $potongan->nik = $profile->nik ?? $potongan->nik;
            }
            if (empty($potongan->masa_kerja) || $potongan->masa_kerja === '0') {
                $potongan->masa_kerja = $profile->masa_kerja ?? $potongan->masa_kerja;
            }

            // ✅ Isi tampilan unit & cabang kalau di record kosong
            if (empty($potongan->bimba_unit)) {
                $potongan->bimba_unit = $potongan->bimba_unit
                    ?? optional($potongan->pendapatan)->bimba_unit
                    ?? $profile->bimba_unit
                    ?? $profile->nama_unit;
            }
            if (empty($potongan->no_cabang)) {
                $potongan->no_cabang = $potongan->no_cabang
                    ?? optional($potongan->pendapatan)->no_cabang
                    ?? $profile->no_cabang
                    ?? $profile->kode_cabang;
            }
        }

        return view('potongan.edit', compact('potongan'));
    }

    /**
     * Update
     */
    public function update(Request $request, PotonganTunjangan $potongan)
    {
        $input = $request->all();

        // normalize numeric inputs
        if (isset($input['kelebihan_nominal'])) {
            $normalized = $this->normalizeCurrency($input['kelebihan_nominal']);
            $input['kelebihan_nominal'] = $normalized ?? 0;
        }

        if (isset($input['cash_advance_nominal'])) {
            $normalized = $this->normalizeCurrency($input['cash_advance_nominal']);
            $input['cash_advance_nominal'] = $normalized ?? 0;
        }

        // normalize months
        foreach (['kelebihan_bulan', 'bulan'] as $mb) {
            if (!empty($input[$mb])) {
                try {
                    if (!preg_match('/^\d{4}-\d{2}$/', $input[$mb])) {
                        $input[$mb] = Carbon::parse($input[$mb])->format('Y-m');
                    }
                } catch (\Throwable $e) {
                    // let validation handle it
                }
            }
        }

        $validator = Validator::make($input, [
            'nama'                => 'required|string|max:100',
            'jabatan'             => 'nullable|string|max:100',
            'status'              => 'nullable|string|max:50',
            'departemen'          => 'nullable|string|max:100',
            'masa_kerja'          => 'nullable|string|max:50',
            'sakit'               => 'nullable|numeric',
            'izin'                => 'nullable|numeric',
            'alpa'                => 'nullable|numeric',
            'tidak_aktif'         => 'nullable|numeric',
            'kelebihan_nominal'   => 'nullable|numeric',
            'kelebihan_bulan'     => 'nullable|date_format:Y-m',
            'bulan'               => 'required|string|max:7',
            'lain_lain'           => 'nullable|numeric',
            'total'               => 'nullable|numeric',
            // cash advance
            'cash_advance_nominal'=> 'nullable|numeric',
            'cash_advance_note'   => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        $validated = $validator->validated();

        // Map profile -> nik, masa_kerja, bimba_unit, no_cabang
        $profile = Profile::where('nama', $validated['nama'])->first();
        if ($profile) {
            $validated['nik']        = $profile->nik ?? $potongan->nik;
            $validated['masa_kerja'] = $validated['masa_kerja'] ?? $profile->masa_kerja ?? $potongan->masa_kerja;

            // ✅ sinkron juga unit & cabang
            $validated['bimba_unit'] = $potongan->bimba_unit
                ?? optional($potongan->pendapatan)->bimba_unit
                ?? $profile->bimba_unit
                ?? $profile->nama_unit
                ?? $potongan->bimba_unit;

            $validated['no_cabang']  = $potongan->no_cabang
                ?? optional($potongan->pendapatan)->no_cabang
                ?? $profile->no_cabang
                ?? $profile->kode_cabang
                ?? $potongan->no_cabang;
        } else {
            $validated['nik']        = $potongan->nik;
            $validated['masa_kerja'] = $validated['masa_kerja'] ?? $potongan->masa_kerja;
            // biarkan bimba_unit & no_cabang existing jika tidak ketemu profile
            $validated['bimba_unit'] = $potongan->bimba_unit;
            $validated['no_cabang']  = $potongan->no_cabang;
        }

        // fallback legacy 'kelebihan'
        if (isset($input['kelebihan']) && !isset($validated['kelebihan_nominal'])) {
            $validated['kelebihan_nominal'] = $input['kelebihan'];
        }

        // calculate total if not provided
        $sakit            = (float) ($validated['sakit'] ?? $potongan->sakit ?? 0);
        $izin             = (float) ($validated['izin'] ?? $potongan->izin ?? 0);
        $alpa             = (float) ($validated['alpa'] ?? $potongan->alpa ?? 0);
        $tidakAktif       = (float) ($validated['tidak_aktif'] ?? $potongan->tidak_aktif ?? 0);
        $kelebihanNominal = (float) ($validated['kelebihan_nominal'] ?? $potongan->kelebihan_nominal ?? $potongan->kelebihan ?? 0);
        $lainLain         = (float) ($validated['lain_lain'] ?? $potongan->lain_lain ?? 0);
        $cashAdvance      = (float) ($validated['cash_advance_nominal'] ?? $potongan->cash_advance_nominal ?? 0);

        if (!isset($validated['total']) || $validated['total'] === null) {
            $validated['total'] = $sakit + $izin + $alpa + $tidakAktif + $kelebihanNominal + $lainLain + $cashAdvance;
        }

        // IMPORTANT: Remove any key 'nim' if present to avoid DB errors
        if (array_key_exists('nim', $validated)) {
            unset($validated['nim']);
        }
        if (array_key_exists('nim', $input)) {
            unset($input['nim']);
        }

        $potongan->update($validated);

        return redirect()->route('potongan.index')->with('success', 'Data berhasil diperbarui');
    }

    /**
     * Destroy
     */
    public function destroy(PotonganTunjangan $potongan)
    {
        $potongan->delete();
        return redirect()->route('potongan.index')->with('success', 'Data berhasil dihapus');
    }

    /**
     * Sync from absensi
     */
    public function syncFromAbsensi()
{
    $this->runSyncFromAbsensi();

    return redirect()
        ->route('potongan.index')
        ->with('success','Potongan berhasil disinkronkan!');
}

    /**
     * Show detail
     */
    public function show(PotonganTunjangan $potongan)
    {
        // Cari profile hanya berdasarkan nik atau nama (tidak pakai nim)
        $profile = Profile::where('nik', $potongan->nik)
                         ->orWhere('nama', $potongan->nama)
                         ->first();

        $masaKerjaDisplay = $potongan->masa_kerja ?? ($profile->masa_kerja ?? '-');

        $year = null; $month = null;
        if (!empty($potongan->bulan) && preg_match('/^(\d{4})-(\d{2})$/', $potongan->bulan, $m)) {
            $year = $m[1]; $month = $m[2];
        }

        $totalAbsen = 0;
        $rekap = collect();

        if ($year && $month) {
            $absensiQuery = AbsensiRelawan::query()
                ->whereYear('tanggal', $year)
                ->whereMonth('tanggal', $month);

            $absensiQuery->where(function ($q) use ($potongan) {
                if (!empty($potongan->nik) && $potongan->nik !== '-') {
                    $q->where('nik', $potongan->nik);
                } else {
                    $q->where('nama_relawaan', $potongan->nama);
                }
            });

            $totalAbsen = (clone $absensiQuery)->count();

            $rekap = (clone $absensiQuery)
                ->selectRaw('status, COUNT(*) as jumlah')
                ->groupBy('status')
                ->pluck('jumlah', 'status');
        }

        $queryPerson = PotonganTunjangan::query();

        if (!empty($potongan->nik) && $potongan->nik !== '-') {
            $queryPerson->where('nik', $potongan->nik);
        } else {
            $queryPerson->where('nama', $potongan->nama);
        }

        $monthsWithKelebihan = $queryPerson
            ->where(function($q) {
                $q->where('kelebihan', '>', 0)
                  ->orWhere('kelebihan_nominal', '>', 0);
            })
            ->selectRaw('bulan, SUM(COALESCE(kelebihan, 0) + COALESCE(kelebihan_nominal, 0)) as total_kelebihan')
            ->groupBy('bulan')
            ->orderBy('bulan', 'desc')
            ->get()
            ->map(function ($row) {
                try {
                    $dt = Carbon::createFromFormat('Y-m', $row->bulan);
                    $row->bulan_label = $dt->translatedFormat('F Y');
                } catch (\Throwable $e) {
                    $row->bulan_label = $row->bulan;
                }
                return $row;
            });

        return view('potongan.show', compact(
            'potongan',
            'totalAbsen',
            'rekap',
            'monthsWithKelebihan',
            'masaKerjaDisplay'
        ));
    }
    
        public function runSyncFromAbsensi(?string $specificBulan = null)
    {
        $now = Carbon::now();

        // Hapus data lama
        if ($specificBulan) {
            PotonganTunjangan::where('bulan', $specificBulan)->delete();
        } else {
            $bulan1 = $now->format('Y-m');
            $bulan2 = $now->copy()->subMonth()->format('Y-m');
            $bulan3 = $now->copy()->subMonths(2)->format('Y-m');
            PotonganTunjangan::whereIn('bulan', [$bulan1, $bulan2, $bulan3])->delete();
        }

        // Ambil absensi dengan rentang yang benar untuk pay period
        $absensi = collect();

        if ($specificBulan) {
            $targetMonth = Carbon::createFromFormat('Y-m', $specificBulan);

            // Rentang pay period: 26 bulan sebelumnya sampai 25 bulan ini
            $startDate = $targetMonth->copy()->subMonth()->day(26);   // 26 Feb
            $endDate   = $targetMonth->copy()->day(25);               // 25 Mar

            $absensi = AbsensiRelawan::whereBetween('tanggal', [
                $startDate->format('Y-m-d'),
                $endDate->format('Y-m-d')
            ])->get();

            \Log::info("SYNC PAY PERIOD", [
                'bulan_target' => $specificBulan,
                'rentang'      => $startDate->format('d/m/Y') . ' s/d ' . $endDate->format('d/m/Y'),
                'jumlah_absen' => $absensi->count()
            ]);
        } else {
            // Default sync 4 bulan terakhir (tetap pakai logic lama)
            $absensi = AbsensiRelawan::where('tanggal', '>=', $now->copy()->subMonths(4)->startOfMonth())->get();
        }

        $dataPotongan = [];

        foreach ($absensi as $a) {
            if (empty($a->tanggal)) continue;

            $tanggal = Carbon::parse($a->tanggal);
            $bulanPotongan = $this->getPayPeriodMonth($tanggal);

            $key = ($a->nik ?? '-') . '|' . Str::slug($a->nama_relawaan ?? '') . '|' . $bulanPotongan;

            if (!isset($dataPotongan[$key])) {
                $dataPotongan[$key] = [
                    'nik'         => $a->nik ?? '-',
                    'nama'        => $a->nama_relawaan,
                    'jabatan'     => $a->posisi ?? null,
                    'departemen'  => $a->departemen ?? null,
                    'bulan'       => $bulanPotongan,
                    'sakit'       => 0,
                    'izin'        => 0,
                    'alpa'        => 0,
                    'tidak_aktif' => 0,
                    'kelebihan'   => 0,
                    'lain_lain'   => 0,
                    'total'       => 0,
                    'bimba_unit'  => $a->bimba_unit ?? null,
                    'no_cabang'   => $a->no_cabang ?? null,
                ];
            }

            $potonganPerHari = 24000;

            switch (strtoupper(trim($a->status ?? ''))) {
                case 'SAKIT':       $dataPotongan[$key]['sakit']       += $potonganPerHari; break;
                case 'IZIN':        $dataPotongan[$key]['izin']        += $potonganPerHari; break;
                case 'ALPA':        $dataPotongan[$key]['alpa']        += $potonganPerHari; break;
                case 'TIDAK AKTIF': $dataPotongan[$key]['tidak_aktif'] += $potonganPerHari; break;
                default:            $dataPotongan[$key]['lain_lain']   += $potonganPerHari; break;
            }
        }

        foreach ($dataPotongan as $data) {
            $profile = Profile::where('nik', $data['nik'])->orWhere('nama', $data['nama'])->first();
            $pendapatan = PendapatanTunjangan::where('nama', $data['nama'])->where('bulan', $data['bulan'])->first();

            $data['pendapatan_id'] = $pendapatan?->id;
            $data['masa_kerja']    = $profile?->masa_kerja ?? ($pendapatan?->masa_kerja ?? 0);
            $data['status']        = $pendapatan?->status ?? ($profile?->status_karyawan ?? '-');

            $data['bimba_unit'] = $data['bimba_unit'] ?? $pendapatan?->bimba_unit ?? $profile?->bimba_unit ?? $profile?->nama_unit;
            $data['no_cabang']  = $data['no_cabang']  ?? $pendapatan?->no_cabang  ?? $profile?->no_cabang ?? $profile?->kode_cabang;

            $data['total'] = $data['sakit'] + $data['izin'] + $data['alpa'] + $data['tidak_aktif'] + $data['lain_lain'];

            PotonganTunjangan::updateOrCreate(
                ['nama' => $data['nama'], 'bulan' => $data['bulan']],
                $data
            );
        }
    }

    /**
     * Menentukan bulan potongan berdasarkan Pay Period 26 s.d. 25
     * Berlaku untuk SEMUA bulan dan tahun
     */
        public function getPayPeriodMonth(Carbon $tanggal): string
    {
        $year  = (int) $tanggal->format('Y');
        $month = (int) $tanggal->format('m');
        $day   = (int) $tanggal->format('d');

        if ($day >= 26) {
            $nextMonth = $month + 1;
            $nextYear  = $year;
            if ($nextMonth > 12) {
                $nextMonth = 1;
                $nextYear++;
            }
            return sprintf('%04d-%02d', $nextYear, $nextMonth);
        }

        return sprintf('%04d-%02d', $year, $month);
    }
}
