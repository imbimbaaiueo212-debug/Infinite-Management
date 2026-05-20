<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\ImbalanRekap;
use App\Models\AbsensiVolunteer;
use App\Models\Adjustment;
use App\Models\CashAdvanceInstallment;
use App\Models\CashAdvance;
use App\Models\DurasiKegiatan;
use App\Models\Ktr;
use App\Models\Profile;
use App\Models\PotonganTunjangan;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use App\Events\ProfileUpdated; // Tambahkan ini di atas class
use Illuminate\Support\Facades\Cache;
use Throwable;

class ImbalanRekapController extends Controller
{
    public function index(Request $request)
{
    /* ======================================================
     * 1. FILTER PARAM
     * ====================================================== */
    $start_bulan = $request->get('start_bulan', now()->format('m'));
    $start_tahun = $request->get('start_tahun', now()->format('Y'));
    $end_bulan   = $request->get('end_bulan', $start_bulan);
    $end_tahun   = $request->get('end_tahun', $start_tahun);
    $bimba_unit  = $request->get('bimba_unit');
    $nama        = $request->get('nama');
    $showAll     = $request->has('all');

    /* ======================================================
     * 2. RANGE TANGGAL
     * ====================================================== */
    try {
        $startDate = Carbon::createFromFormat('Y-m', "$start_tahun-$start_bulan")->startOfMonth();
        $endDate   = Carbon::createFromFormat('Y-m', "$end_tahun-$end_bulan")->endOfMonth();
    } catch (\Exception $e) {
        $startDate = now()->startOfMonth();
        $endDate   = now()->endOfMonth();
    }

    /* ======================================================
     * 3. LIST BULAN
     * ====================================================== */
    $bulanLabels = [];
    $tmp = $startDate->copy();

    while ($tmp <= $endDate) {
        $bulanLabels[] = $tmp->locale('id')->translatedFormat('F Y');
        $tmp->addMonth();
    }

    /* ======================================================
     * 4. LABEL PERIODE
     * ====================================================== */
    $periodeLabel = $startDate->locale('id')->translatedFormat('F Y');
    if (!$startDate->isSameMonth($endDate)) {
        $periodeLabel .= ' — ' . $endDate->locale('id')->translatedFormat('F Y');
    }

    /* ======================================================
     * 5. CEK ADMIN
     * ====================================================== */
    $user = Auth::user();

    $isAdmin = $user && (
        $user->role === 'admin' ||
        ($user->is_admin ?? false)
    );

    /* ======================================================
     * 6. UNIT OPTIONS
     * ====================================================== */
    $unitOptions = $isAdmin
        ? \App\Models\Unit::orderBy('biMBA_unit')->pluck('biMBA_unit')
        : collect();

    /* ======================================================
     * 7. NAMA OPTIONS
     * ====================================================== */
    $namaOptionsQuery = \App\Models\Profile::query()
        ->select('profiles.nama')
        ->join('imbalan_rekaps', 'profiles.nama', '=', 'imbalan_rekaps.nama')
        ->distinct()
        ->orderBy('profiles.nama');

    if (!$showAll) {
        $namaOptionsQuery->whereIn('imbalan_rekaps.bulan', $bulanLabels);
    }

    if ($bimba_unit) {
        $namaOptionsQuery->where('profiles.biMBA_unit', $bimba_unit);
    }

    $namaOptions = $namaOptionsQuery->pluck('nama');

    /* ======================================================
     * 8. QUERY UTAMA
     * ====================================================== */
    $query = \App\Models\ImbalanRekap::query()
        ->with('profile') // relasi penting
        ->join('profiles', 'imbalan_rekaps.nama', '=', 'profiles.nama')
        ->select(
            'imbalan_rekaps.*',
            'profiles.status_karyawan as profile_status',
            'profiles.tgl_masuk as profile_tgl_masuk',
            'profiles.masa_kerja as profile_masa_kerja'
        )
        ->orderByRaw("CAST(profiles.nik AS UNSIGNED) ASC")
        ->orderBy('imbalan_rekaps.nama', 'ASC');

    if (!$showAll) {
        $query->whereIn('imbalan_rekaps.bulan', $bulanLabels);
    }

    if ($bimba_unit) {
        $query->where('profiles.biMBA_unit', $bimba_unit);
    }

    if ($nama) {
        $query->where('imbalan_rekaps.nama', $nama);
    }

    /* ======================================================
     * 9. AMBIL DATA
     * ====================================================== */
    $rekaps = $showAll ? $query->get() : $query->paginate(50);

    /* ======================================================
     * 10. FIX DATA (SUPER PENTING)
     * ====================================================== */
    foreach ($rekaps as $r) {

        // ✅ FIX STATUS (AMBIL DARI PROFILE)
        $r->status = $r->profile_status ?? $r->status;

        // ✅ FIX MASA KERJA
        if (!empty($r->masa_kerja)) {
            $r->masa_kerja_formatted = $this->formatMasaKerja((int)$r->masa_kerja);
        } elseif (!empty($r->profile_masa_kerja)) {
            $r->masa_kerja_formatted = $this->formatMasaKerja((int)$r->profile_masa_kerja);
        } elseif (!empty($r->profile_tgl_masuk)) {
            $r->masa_kerja_formatted = $this->hitungMasaKerja($r->profile_tgl_masuk);
        } else {
            $r->masa_kerja_formatted = '-';
        }

        // ✅ OPTIONAL: FIX MAGANG AUTO ZERO (biar konsisten)
        if (strtolower(trim($r->status)) === 'magang') {
            $r->imbalan_pokok = 0;
            $r->total_imbalan = 0;
            $r->yang_dibayarkan = 0;
        }
    }

    if (!$showAll) {
        $rekaps->appends($request->all());
    }

    /* ======================================================
     * 11. RETURN
     * ====================================================== */
    return view('imbalan_rekap.index', compact(
        'rekaps',
        'periodeLabel',
        'start_bulan',
        'start_tahun',
        'end_bulan',
        'end_tahun',
        'bimba_unit',
        'nama',
        'namaOptions',
        'unitOptions',
        'startDate',
        'endDate',
        'showAll'
    ));
}

    private function hitungMasaKerja($tanggalMasuk)
    {
        if (!$tanggalMasuk)
            return null;
        try {
            $mulai = Carbon::parse($tanggalMasuk);
            $sekarang = Carbon::now();
            $tahun = $sekarang->diffInYears($mulai);
            $bulan = $sekarang->copy()->subYears($tahun)->diffInMonths($mulai);

            if ($tahun > 0 && $bulan > 0)
                return "$tahun tahun $bulan bulan";
            if ($tahun > 0)
                return "$tahun tahun";
            if ($bulan > 0)
                return "$bulan bulan";
            return "0 bulan";
        } catch (\Exception $e) {
            return null;
        }
    }

    public function truncate()
    {
        ImbalanRekap::truncate();
        return redirect()->route('imbalan_rekap.index')->with('success', 'Semua data ImbalanRekap dihapus.');
    }

    private function formatMasaKerja($bulan)
    {
        if ($bulan === null || $bulan < 0)
            return '-';
        $tahun = intdiv($bulan, 12);
        $sisaBulan = $bulan % 12;
        $parts = [];
        if ($tahun > 0)
            $parts[] = "$tahun tahun";
        if ($sisaBulan > 0)
            $parts[] = "$sisaBulan bulan";
        return $parts ? implode(' ', $parts) : '0 bulan';
    }

    public function refresh(Request $request)
{
    $start_bulan = $request->get('start_bulan', now()->format('m'));
    $start_tahun = $request->get('start_tahun', now()->format('Y'));

    try {
        $carbon = Carbon::createFromFormat('Y-m', "$start_tahun-$start_bulan");
        $periodeLabel = $carbon->locale('id')->translatedFormat('F Y');
    } catch (\Exception $e) {
        $periodeLabel = now()->locale('id')->translatedFormat('F Y');
    }

    DB::beginTransaction();

    try {

        // ❌ JANGAN DELETE DATA
        // ImbalanRekap::where('bulan', $periodeLabel)->delete();

        // regenerate (akan update jika sudah ada)
        $result = $this->createRekapsForPeriode($periodeLabel);

        DB::commit();

        return redirect()->route('imbalan_rekap.index', $request->query())
            ->with('success', "Refresh berhasil untuk periode {$periodeLabel}");

    } catch (\Throwable $e) {

        DB::rollBack();

        return redirect()->route('imbalan_rekap.index')
            ->with('error', 'Gagal refresh: ' . $e->getMessage());
    }
}
    public function updateInline(Request $request)
{
    $request->validate([
        'id' => 'required|exists:imbalan_rekaps,id',
    ]);

    $rekap = \App\Models\ImbalanRekap::findOrFail($request->id);

    // ======================================================
    // 1. Ambil SEMUA field yang dikirim (bisa > 1 field)
    // ======================================================
    $fields = $request->except(['id', '_token']);

    if (empty($fields)) {
        return response()->json([
            'success' => false,
            'message' => 'Tidak ada field yang dikirim'
        ], 422);
    }

    // ======================================================
    // 2. Set semua field ke model
    // ======================================================
    foreach ($fields as $field => $value) {
        // Normalisasi nilai kosong
        if ($value === '') {
            $value = null;
        }
        $rekap->$field = $value;
    }

    // ======================================================
    // 3. Logika khusus field tertentu
    // ======================================================

    // AT HARI → hitung tambahan transport
    if (array_key_exists('at_hari', $fields)) {
        $hari = (int) ($rekap->at_hari ?? 0);
        $rekap->tambahan_transport = $hari * 24000;
    }

    // ======================================================
    // 4. Hitung ulang TOTAL IMBALAN
    // ======================================================
    $rekap->total_imbalan =
        ($rekap->imbalan_pokok ?? 0) +
        ($rekap->imbalan_lainnya ?? 0) +
        ($rekap->insentif_mentor ?? 0) +
        ($rekap->tambahan_transport ?? 0) +
        ($rekap->kekurangan ?? 0);

    // ======================================================
    // 5. Hitung ulang YANG DIBAYARKAN (FINAL)
    // ======================================================
    $rekap->yang_dibayarkan =
        $rekap->total_imbalan +
        ($rekap->jumlah_bagi_hasil ?? 0) -
        ($rekap->kelebihan ?? 0) -
        ($rekap->cicilan ?? 0);

    // ======================================================
    // 6. Bersihkan keterangan cicilan jika cicilan = 0
    // ======================================================
    if (
        array_key_exists('cicilan', $fields) ||
        array_key_exists('keterangan_cicilan', $fields)
    ) {
        if (($rekap->cicilan ?? 0) == 0) {
            $rekap->keterangan_cicilan = null;

            if ($rekap->catatan) {
                $parts = explode(' | ', $rekap->catatan);

                $parts = array_filter($parts, function ($part) {
                    $part = strtolower(trim($part));
                    return !str_contains($part, 'cicilan cash advance')
                        && !str_contains($part, 'angsuran ke');
                });

                $rekap->catatan = $parts
                    ? implode(' | ', $parts)
                    : null;
            }
        }
    }

    // ======================================================
    // 7. Simpan perubahan ke imbalan_rekaps
    // ======================================================
    $rekap->save();

    // ======================================================
// 8. UPDATE STATUS CICILAN – VERSI AMAN & SESUAI DATABASE
// ======================================================
if (array_key_exists('installment_id', $fields)) {
    $newInstallmentId = $rekap->installment_id;

    // Ambil nama relawan
    $namaRelawan = trim($rekap->nama ?? $rekap->profile?->nama ?? '');

    if ($namaRelawan) {
        // 1. Reset semua cicilan relawan ini yang sebelumnya lunas via potong gaji
        \App\Models\CashAdvanceInstallment::whereHas('cashAdvance', function ($q) use ($namaRelawan) {
            $q->whereRaw('TRIM(UPPER(nama)) = ?', [strtoupper($namaRelawan)]);
        })
        ->where('keterangan', 'like', '%Dipotong via Imbalan Relawan%')
        ->update([
            'status'         => 'belum',   // <-- SESUAIKAN DENGAN NILAI ASLI
            'sudah_dibayar'  => 0,
            'tanggal_bayar'  => null,
            'keterangan'     => null,
        ]);
    }

    // 2. Jika ada cicilan baru dipilih → tandai lunas
    if ($newInstallmentId) {
        $installment = \App\Models\CashAdvanceInstallment::find($newInstallmentId);

        if ($installment) {
            $periode = \Carbon\Carbon::now()->locale('id')->translatedFormat('F Y');
            if (request()->has('start_bulan') && request()->has('start_tahun')) {
                $periode = \Carbon\Carbon::createFromFormat('m-Y', request('start_bulan') . '-' . request('start_tahun'))
                    ->locale('id')
                    ->translatedFormat('F Y');
            }

            $installment->update([
                'status'         => 'lunas',   // <-- SESUAIKAN DENGAN NILAI ASLI
                'sudah_dibayar'  => 1,
                'tanggal_bayar'  => \Carbon\Carbon::now(),
                'keterangan'     => 'Dipotong via Imbalan Relawan periode ' . $periode,
            ]);
        }
    }
}
    // ======================================================
    // 9. Response untuk frontend (real-time update)
    // ======================================================
    return response()->json([
        'success'             => true,
        'message'             => 'Data berhasil diperbarui',
        'total_imbalan'       => $rekap->total_imbalan,
        'yang_dibayarkan'     => $rekap->yang_dibayarkan,
        'tambahan_transport'  => $rekap->tambahan_transport ?? 0,
        'at_hari'             => $rekap->at_hari ?? '',
        'cicilan'             => $rekap->cicilan ?? 0,
        'keterangan_cicilan'  => $rekap->keterangan_cicilan ?? '',
        'catatan'             => $rekap->catatan ?? '',
    ]);
}




    public function slip(Request $request, $id)
{
    $rekap = ImbalanRekap::findOrFail($id);
    $profile = Profile::where('nama', $rekap->nama)->first();

    // === MASA KERJA ===
    $masaKerja = '-';
    if ($profile?->tgl_masuk) {
        $masaKerja = $this->hitungMasaKerja($profile->tgl_masuk);
    } elseif ($rekap->masa_kerja && is_numeric($rekap->masa_kerja)) {
        $masaKerja = $this->formatMasaKerja((int) $rekap->masa_kerja);
    }

    // === PERIODE ===
    $now = Carbon::now()->startOfMonth();
    $months = [];
    for ($i = 0; $i < 12; $i++) {
        $m = $now->copy()->subMonths($i);
        $months[] = [
            'value' => $m->format('Y-m'),
            'label' => $m->locale('id')->translatedFormat('F Y'),
        ];
    }

    $defaultPeriode = $rekap->bulan
        ? Carbon::createFromFormat('F Y', $rekap->bulan, 'id')->format('Y-m')
        : Carbon::now()->format('Y-m');

    $selectedPeriode = $request->query('periode') ?? $defaultPeriode;

    $periodeLabel = Carbon::createFromFormat('Y-m', $selectedPeriode)
        ->locale('id')
        ->translatedFormat('F Y');

    // === AMBIL POTONGAN (hanya untuk ditampilkan) ===
    $potongan = PotonganTunjangan::where('nama', $rekap->nama)
        ->where('bulan', $selectedPeriode)
        ->first();

    $totalPotongan = ($potongan->sakit ?? 0) +
        ($potongan->izin ?? 0) +
        ($potongan->alpa ?? 0) +
        ($potongan->tidak_aktif ?? 0) +
        ($potongan->cash_advance_nominal ?? 0) +
        ($potongan->lainnya ?? 0);

    // === HITUNG TOTAL PENDAPATAN ===
    $imbalanPokok      = $rekap->imbalan_pokok ?? 0;
    $imbalanLainnya    = $rekap->imbalan_lainnya ?? 0;
    $insentifMentor    = $rekap->insentif_mentor ?? 0;
    $tambahanTransport = $rekap->tambahan_transport ?? 0;

    // Total Pendapatan = Jumlah penuh (potongan absensi sudah diakomodasi di transport)
    $totalPendapatan = $imbalanPokok + $imbalanLainnya + $insentifMentor + $tambahanTransport;

    // Yang Dibayarkan = Total Pendapatan - hanya cicilan & kelebihan
    $yangDibayarkan = $totalPendapatan 
                      + ($rekap->jumlah_bagi_hasil ?? 0) 
                      - ($rekap->kelebihan ?? 0) 
                      - ($rekap->cicilan ?? 0);

    // === TANGGAL MASUK ===
    $tglMasuk = $profile?->tgl_masuk
        ? Carbon::parse($profile->tgl_masuk)->locale('id')->translatedFormat('d F Y')
        : '-';

    // === ADMIN CHECK ===
    $isAdmin = Auth::check() && (
        Auth::user()->role === 'admin' || 
        Auth::user()->is_admin || 
        Auth::user()->hasRole('admin')
    );

    $units = $isAdmin ? \App\Models\Unit::orderBy('biMBA_unit')->get() : collect();

    $allRekaps = ImbalanRekap::orderBy('nama')->get(['id', 'nama', 'unit_id']);

    return view('imbalan_rekap.slip', [
        'rekap'             => $rekap,
        'profile'           => $profile,
        'masaKerja'         => $masaKerja,
        'periode'           => $periodeLabel,
        'periodeValue'      => $selectedPeriode,
        'periodeOptions'    => $months,
        'tanggalMasuk'      => $tglMasuk,

        'unit'              => $rekap->biMBA_unit ?? $rekap->unit ?? 'biMBA AIUEO',
        'no_cabang'         => $rekap->no_cabang ?? '-',

        'noRekening'        => $profile?->no_rekening ?? '-',
        'bank'              => $profile?->bank ?? '-',
        'atasNama'          => $profile?->nama_rekening ?? $rekap->nama,

        // DATA HITUNGAN - WAJIB DIKIRIM
        'potongan'          => $potongan,
        'totalPotongan'     => $totalPotongan,
        'totalPendapatan'   => $totalPendapatan,     // ← PENTING!
        'yangDibayarkan'    => $yangDibayarkan,

        'allRekaps'         => $allRekaps,
        'isAdmin'           => $isAdmin,
        'units'             => $units,
    ]);
}

    public function pdf(Request $request, $id)
{
    $rekap = ImbalanRekap::findOrFail($id);
    $profile = Profile::where('nama', $rekap->nama)->first();

    /* ======================================================
     * 🔥 FIX STATUS / KATEGORI (ANTI NYANGKUT MAGANG)
     * ====================================================== */
    $statusAktual   = strtolower(trim($profile->status_karyawan ?? ''));
    $kategoriAktual = strtolower(trim($profile->kategori ?? ''));

    // 🔥 LOGIC FINAL
    if ($kategoriAktual && !str_contains($kategoriAktual, 'magang')) {
        $statusFinal = $kategoriAktual;
    } else {
        $statusFinal = $statusAktual;
    }

    // inject ke rekap supaya view pakai data terbaru
    $rekap->status_karyawan = $statusFinal;
    $rekap->kategori        = $statusFinal;

    $isMagang = str_contains($statusFinal, 'magang');

    /* ======================================================
     * PERIODE
     * ====================================================== */
    $periodeValue = $request->query('periode');

    $bulan = $periodeValue
        ? Carbon::createFromFormat('Y-m', $periodeValue)->format('Y-m')
        : ($rekap->bulan
            ? Carbon::createFromFormat('F Y', $rekap->bulan, 'id')->format('Y-m')
            : Carbon::now()->subMonth()->format('Y-m'));

    /* ======================================================
     * POTONGAN
     * ====================================================== */
    $potongan = null;

    if ($rekap->pendapatan_id) {
        $potongan = PotonganTunjangan::where('pendapatan_id', $rekap->pendapatan_id)
            ->where('bulan', $bulan)
            ->first();
    }

    if (!$potongan) {
        $potongan = PotonganTunjangan::where('nama', $rekap->nama)
            ->where('bulan', $bulan)
            ->first();
    }

    if (!$potongan && $profile?->nik) {
        $potongan = PotonganTunjangan::where('nik', $profile->nik)
            ->where('bulan', $bulan)
            ->first();
    }

    /* ======================================================
     * ADJUSTMENT
     * ====================================================== */
    $totalKekuranganAdj = 0;
    $totalKelebihanAdj = 0;
    $keteranganKekuranganAdj = null;
    $keteranganKelebihanAdj = null;

    try {
        $carbonPeriode = Carbon::createFromFormat('Y-m', $bulan);
        $month = $carbonPeriode->month;
        $year = $carbonPeriode->year;

        $adjustments = Adjustment::whereRaw(
                'TRIM(UPPER(nama)) = ?',
                [strtoupper(trim($rekap->nama))]
            )
            ->where('month', $month)
            ->where('year', $year)
            ->get();

        foreach ($adjustments as $adj) {

            $nominal = (float) $adj->nominal;
            $type = strtolower(trim($adj->type ?? ''));

            if (str_contains($type, 'tambah') || $type === 'tambahan') {

                $totalKekuranganAdj += $nominal;

                $keteranganKekuranganAdj = $keteranganKekuranganAdj
                    ? $keteranganKekuranganAdj . ' | ' . trim($adj->keterangan ?? '')
                    : trim($adj->keterangan ?? '');

            } elseif (str_contains($type, 'potong') || $type === 'potongan') {

                $totalKelebihanAdj += $nominal;

                $keteranganKelebihanAdj = $keteranganKelebihanAdj
                    ? $keteranganKelebihanAdj . ' | ' . trim($adj->keterangan ?? '')
                    : trim($adj->keterangan ?? '');
            }
        }

    } catch (\Exception $e) {
        Log::warning("Gagal ambil adjustment PDF: " . $e->getMessage());
    }

    /* ======================================================
     * HITUNG IMBALAN
     * ====================================================== */
    $pokok      = $rekap->imbalan_pokok ?? 0;
    $lainnya    = $rekap->imbalan_lainnya ?? 0;
    $insentif   = $rekap->insentif_mentor ?? 0;
    $transport  = $rekap->tambahan_transport ?? 0;

    $totalPendapatan =
        $pokok +
        $lainnya +
        $insentif +
        $transport +
        $totalKekuranganAdj;

    /* ======================================================
     * POTONGAN (DISPLAY ONLY)
     * ====================================================== */
    $totalPotonganTetap = 0;

    if ($potongan) {
        $totalPotonganTetap =
            ($potongan->sakit ?? 0) +
            ($potongan->izin ?? 0) +
            ($potongan->alpa ?? 0) +
            ($potongan->tidak_aktif ?? 0) +
            ($potongan->cash_advance_nominal ?? 0) +
            ($potongan->lainnya ?? 0);
    }

    $cicilanNilai = $rekap->cicilan ?? 0;
    $cicilanKeterangan = $rekap->keterangan_cicilan ?? 'Cicilan Cash Advance';

    $totalPotongan =
        $totalPotonganTetap +
        $cicilanNilai +
        $totalKelebihanAdj;

    /* ======================================================
     * YANG DIBAYARKAN
     * ====================================================== */
    $yangDibayarkan =
        $totalPendapatan +
        ($rekap->jumlah_bagi_hasil ?? 0) -
        ($rekap->kelebihan ?? 0) -
        $cicilanNilai;

    /* ======================================================
     * 🔥 HANDLE MAGANG
     * ====================================================== */
    if ($isMagang) {
        $rekap->imbalan_pokok = 0;
        $totalPendapatan = 0;
        $yangDibayarkan = 0;
    }

    /* ======================================================
     * UNIT
     * ====================================================== */
    $unitName = $rekap->bimba_unit
        ?? $rekap->biMBA_unit
        ?? $profile?->bimba_unit
        ?? $profile?->unit
        ?? '-';

    $noCabang = $rekap->no_cabang ?? $profile?->no_cabang ?? null;

    $unitDisplay = ($noCabang && $unitName !== '-')
        ? $noCabang . ' - ' . strtoupper($unitName)
        : strtoupper($unitName);

    /* ======================================================
     * FORMAT
     * ====================================================== */
    $periodeLabel = $periodeValue
        ? Carbon::createFromFormat('Y-m', $periodeValue)
            ->locale('id')
            ->translatedFormat('F Y')
        : ($rekap->bulan
            ?? Carbon::now()->subMonth()->locale('id')->translatedFormat('F Y'));

    Carbon::setLocale('id');

    $tanggalMasukFormatted = $profile?->tgl_masuk
        ? Carbon::parse($profile->tgl_masuk)->translatedFormat('d F Y')
        : '-';

    /* ======================================================
     * DATA VIEW
     * ====================================================== */
    $data = [
        'rekap'                   => $rekap,
        'profile'                 => $profile,
        'masaKerja'               => $this->formatMasaKerja(
                                        $profile?->masa_kerja
                                        ?? $rekap->masa_kerja
                                        ?? 0
                                   ),
        'periode'                 => $periodeLabel,
        'periodeValue'            => $periodeValue,
        'tanggalSekarang'         => Carbon::now()->translatedFormat('d F Y'),
        'tanggalMasukFormatted'   => $tanggalMasukFormatted,
        'biMBA_unit'              => $unitDisplay,
        'no_cabang'               => $noCabang ?? '-',
        'noRekening'              => $profile?->no_rekening ?? '-',
        'bank'                    => $profile?->bank ?? '-',
        'atasNama'                => $profile?->nama_rekening ?? $rekap->nama ?? '-',
        'potongan'                => $potongan,
        'cicilanNilai'            => $cicilanNilai,
        'cicilanKeterangan'       => $cicilanKeterangan,
        'totalPendapatan'         => $totalPendapatan,
        'totalPotongan'           => $totalPotongan,
        'yangDibayarkan'          => $yangDibayarkan,
        'totalKekuranganAdj'      => $totalKekuranganAdj,
        'totalKelebihanAdj'       => $totalKelebihanAdj,
        'keteranganKekuranganAdj' => $keteranganKekuranganAdj,
        'keteranganKelebihanAdj'  => $keteranganKelebihanAdj,
        'isPdf'                   => true,
    ];

    $pdf = Pdf::loadView('imbalan_rekap.slip_pdf', $data)
        ->setPaper('a5', 'landscape');

    $fileName = 'slip-imbalan-' .
        preg_replace('/[^A-Za-z0-9\-]/', '-', strtolower($rekap->nama ?? 'rekap')) .
        '-' . $periodeLabel . '.pdf';

    return $pdf->stream($fileName);
}


    public function generateMonth(Request $request)
{
    // Ambil periode dari query string (GET), sama seperti refresh
    $start_bulan = $request->get('start_bulan', now()->format('m'));
    $start_tahun = $request->get('start_tahun', now()->format('Y'));

    try {
        $carbon = Carbon::createFromFormat('Y-m', "$start_tahun-$start_bulan");
        $labelBulan = $carbon->locale('id')->translatedFormat('F Y');
    } catch (\Exception $e) {
        $labelBulan = now()->locale('id')->translatedFormat('F Y');
    }

    $result = $this->createRekapsForPeriode($labelBulan);

    return redirect()
        ->route('imbalan_rekap.index', $request->query())
        ->with('success', "Berhasil generate ulang {$result['created']} data untuk periode {$labelBulan}");
}

      public function createRekapsForPeriode(string $labelBulan): array
{
    $created = 0;
    $updated = 0;
    $errors  = [];

    $labelBulan = trim(preg_replace('/\s+/', ' ', $labelBulan));

    $normalize = fn($str) => strtoupper(preg_replace('/\s+/', '', $str ?? ''));

    $extractRbNumber = function ($val, $default = 30) {
        if (!empty($val) && preg_match('/(\d+)/', $val, $m)) {
            return (int)$m[1];
        }
        return $default;
    };

    // ================= PARSING BULAN =================
    $monthMap = [
        'Januari'=>'01','Februari'=>'02','Maret'=>'03','April'=>'04',
        'Mei'=>'05','Juni'=>'06','Juli'=>'07','Agustus'=>'08',
        'September'=>'09','Oktober'=>'10','November'=>'11','Desember'=>'12'
    ];

    $split = explode(' ', $labelBulan);
    $bulanFormatYm = ($split[1] ?? date('Y')) . '-' . ($monthMap[$split[0]] ?? date('m'));

    // Sync Potongan
    try {
        app(\App\Http\Controllers\PotonganTunjanganController::class)
            ->runSyncFromAbsensi($bulanFormatYm);
    } catch (\Throwable $e) {
        Log::warning("Gagal sync potongan: " . $e->getMessage());
    }

    // ==================== AMBIL PROFILE ====================
    $profiles = Profile::where(function ($q) {
            $q->whereNull('status_karyawan')
              ->orWhere('status_karyawan', '')
              ->orWhere('status_karyawan', 'Aktif')
              ->orWhere('status_karyawan', 'aktif')
              ->orWhere('status_karyawan', 'Magang')
              ->orWhere('status_karyawan', 'magang')
              ->orWhere('status_karyawan', 'Kepala Unit')
              ->orWhere('status_karyawan', 'Kepala');
        })
        ->orderBy('nama')
        ->get();

    Log::info("Generate Rekap {$labelBulan} - Jumlah Profile: " . $profiles->count());

    if ($profiles->isEmpty()) {
        return [
            'created' => 0,
            'updated' => 0,
            'total'   => 0,
            'errors'  => ['Tidak ada profile aktif ditemukan.']
        ];
    }

    $ktrList = Ktr::all();

    $rbConfig = [
        60 => ['min_jam' => 220, 'jam_label' => 240],
        55 => ['min_jam' => 200, 'jam_label' => 220],
        50 => ['min_jam' => 180, 'jam_label' => 200],
        45 => ['min_jam' => 160, 'jam_label' => 180],
        40 => ['min_jam' => 140, 'jam_label' => 160],
        35 => ['min_jam' => 130, 'jam_label' => 140],
        30 => ['min_jam' => 110, 'jam_label' => 120],
        25 => ['min_jam' =>  90, 'jam_label' => 100],
        20 => ['min_jam' =>  70, 'jam_label' =>  80],
        15 => ['min_jam' =>  50, 'jam_label' =>  60],
        10 => ['min_jam' =>  35, 'jam_label' =>  40],
        8  => ['min_jam' =>  25, 'jam_label' =>  32],
        5  => ['min_jam' =>   0, 'jam_label' =>  20],
    ];

    foreach ($profiles as $p) {
        try {
            // === firstOrNew dengan profile_id + bulan ===
            $rekap = ImbalanRekap::firstOrNew([
                'profile_id' => $p->id,
                'bulan'      => $labelBulan
            ]);

            $isNew = !$rekap->exists;
            $isMagang = strtolower(trim($p->status_karyawan ?? '')) === 'magang';

            // RB Awal
            $rbAwal = (str_contains(strtolower($p->jabatan ?? ''), 'kepala')) ? 40 : 30;
            $rbAwal = $extractRbNumber($p->rb ?? $p->rb_tambahan ?? '', $rbAwal);

            $ktrInput = trim($p->ktr ?? $p->ktr_tambahan ?? '');

            $durasiFull = $rbConfig[$rbAwal]['jam_label'] ?? 140;

            // Potongan Absensi
            $hariDipotong = 0;
            $potongan = PotonganTunjangan::where('nama', $p->nama)
                ->where('bulan', $bulanFormatYm)
                ->first();

            if ($potongan) {
                $totalPotong = ($potongan->sakit ?? 0) + ($potongan->izin ?? 0)
                             + ($potongan->alpa ?? 0) + ($potongan->tidak_aktif ?? 0)
                             + ($potongan->lain_lain ?? 0);
                $hariDipotong = (int) floor($totalPotong / 24000);
            }

            $jamEfektif = max(0, $durasiFull - ($hariDipotong * 7));

            $rbFinal = $rbAwal;
            $durasiFinal = $durasiFull;

            if ($jamEfektif < $durasiFull) {
                foreach ($rbConfig as $rb => $cfg) {
                    if ($jamEfektif >= $cfg['min_jam']) {
                        $rbFinal = $rb;
                        $durasiFinal = $cfg['jam_label'];
                        break;
                    }
                }
            }

            // Imbalan Pokok
            $imbalanPokokFull = 1050000;
            if ($ktrInput && $rbFinal) {
                $targetRb = 'RB' . $rbFinal;
                $ktrClean = $normalize($ktrInput);

                $ktr = $ktrList->first(function ($item) use ($ktrClean, $targetRb, $normalize) {
                    $kategori = $normalize($item->kategori ?? '');
                    $waktu    = $normalize($item->waktu ?? '');
                    return (str_contains($kategori, $ktrClean) || str_contains($ktrClean, $kategori)) &&
                           str_contains($waktu, $targetRb);
                });

                if ($ktr && $ktr->jumlah > 0) {
                    $imbalanPokokFull = (int) $ktr->jumlah;
                }
            }

            // Fill Data
            $rekap->fill([
                'nama'                => $p->nama,
                'bulan'               => $labelBulan,
                'profile_id'          => $p->id,
                'posisi'              => $p->jabatan,
                'status'              => $p->status_karyawan,
                'departemen'          => $p->departemen,
                'bimba_unit'          => $p->bimba_unit ?? $p->biMBA_unit,
                'no_cabang'           => $p->no_cabang,
                'masa_kerja'          => $p->masa_kerja,
                'nik'                 => $p->nik,
                'jabatan'             => $p->jabatan,
                'kategori'            => $p->kategori,
                'status_karyawan'     => $p->status_karyawan,

                'waktu_mgg'           => 'RB ' . $rbFinal,
                'waktu_bln'           => $durasiFinal . ' Jam',
                'durasi_kerja'        => $jamEfektif,
                'persen'              => $durasiFull > 0 ? round(($jamEfektif / $durasiFull) * 100, 2) : 0,
                'ktr'                 => $ktrInput ?: null,
                'imbalan_pokok'       => round($imbalanPokokFull),

                'tambahan_transport'  => max(0, 25 - $hariDipotong) * 24000,
                'at_hari'             => max(0, 25 - $hariDipotong),
                'imbalan_lainnya'     => $p->imbalan_lainnya_default ?? 0,
                'insentif_mentor'     => $p->insentif_mentor ?? 0,
                'cicilan'             => $p->cicilan_default ?? 0,
            ]);

            $rekap->total_imbalan = 
                ($rekap->imbalan_pokok ?? 0) + 
                ($rekap->imbalan_lainnya ?? 0) + 
                ($rekap->insentif_mentor ?? 0) + 
                ($rekap->tambahan_transport ?? 0);

            $rekap->yang_dibayarkan = $rekap->total_imbalan - ($rekap->cicilan ?? 0);

            if ($isMagang) {
                $rekap->imbalan_pokok = 0;
                $rekap->total_imbalan = 0;
                $rekap->yang_dibayarkan = 0;
            }

            $rekap->save();

            $isNew ? $created++ : $updated++;

        } catch (\Throwable $e) {
            $errors[] = $p->nama . ' => ' . $e->getMessage();
            Log::error("Gagal generate rekap {$p->nama} untuk {$labelBulan}", ['error' => $e->getMessage()]);
        }
    }

    return [
        'created' => $created,
        'updated' => $updated,
        'total'   => $created + $updated,
        'errors'  => $errors
    ];
}


    private function parseRbDurasi(?string $durasi): array
    {
        $result = ['rb' => null, 'hours' => null];
        if (empty($durasi) || !is_string($durasi))
            return $result;

        $s = trim(preg_replace('/[[:punct:]]+/', ' ', $durasi));
        $s = preg_replace('/\s+/', ' ', $s);

        if (preg_match('/\bR\s*B\s*0*([0-9]{1,4})\b/i', $s, $m)) {
            $result['rb'] = 'RB' . $m[1];
        } elseif (preg_match('/\bRB0*([0-9]{1,4})\b/i', $s, $m)) {
            $result['rb'] = 'RB' . $m[1];
        }

        preg_match_all('/\b([0-9]{1,5})\b/', $s, $allNums);
        if (!empty($allNums[1])) {
            $nums = array_map('intval', $allNums[1]);
            $result['hours'] = max($nums);
        }

        return $result;
    }

  public function indexSlip(Request $request)
{
    $user = Auth::user();

    /* ======================================================
     * PARAMETER
     * ====================================================== */
    $rekapId  = $request->get('rekap_id');
    $unitId   = $request->get('unit_id');
    $periode  = $request->get('periode');

    /* ======================================================
     * CEK ADMIN
     * ====================================================== */
    $isAdmin = $user && (
        $user->role === 'admin' ||
        ($user->is_admin ?? false)
    );

    /* ======================================================
     * UNIT USER
     * ====================================================== */
    $userUnit = $user->biMBA_unit ?? $user->bimba_unit ?? $user->unit ?? null;

    /* ======================================================
     * NORMALISASI PERIODE
     * ====================================================== */
    $normalizedPeriode = null;
    $bulanLabel = null;

    if ($periode) {
        try {
            $normalizedPeriode = Carbon::createFromFormat('Y-m', $periode)->format('Y-m');
        } catch (\Exception $e) {}
    }

    if (!$normalizedPeriode) {
        $latest = ImbalanRekap::latest()->first();
        if ($latest?->bulan) {
            try {
                $normalizedPeriode = Carbon::createFromFormat('F Y', $latest->bulan, 'id')->format('Y-m');
            } catch (\Exception $e) {}
        }
    }

    if ($normalizedPeriode) {
        $bulanLabel = Carbon::createFromFormat('Y-m', $normalizedPeriode)
            ->locale('id')
            ->translatedFormat('F Y');
    }

    /* ======================================================
     * RESOLVE UNIT
     * ====================================================== */
    $displayUnit = null;

    if ($isAdmin && $unitId) {
        $displayUnit = optional(\App\Models\Unit::find($unitId))->biMBA_unit;
    }

    if (!$isAdmin && $userUnit) {
        $displayUnit = $userUnit;
    }

    if (!$displayUnit && $rekapId) {
        $rekapTmp = ImbalanRekap::find($rekapId);
        $displayUnit = $rekapTmp->biMBA_unit ?? null;
    }

    $displayUnit = $displayUnit ?: 'biMBA AIUEO';

    /* ======================================================
     * DROPDOWN
     * ====================================================== */
    $units = $isAdmin ? \App\Models\Unit::orderBy('biMBA_unit')->get() : collect();

    $allRekaps = ImbalanRekap::where('biMBA_unit', $displayUnit)
        ->orderBy('nama')
        ->get(['id', 'nama']);

    /* ======================================================
     * DATA UTAMA
     * ====================================================== */
    $rekap   = $rekapId ? ImbalanRekap::find($rekapId) : null;
    $profile = $rekap ? Profile::where('nama', $rekap->nama)->first() : null;

    /* ======================================================
     * 🔥 FIX STATUS & KATEGORI (PALING PENTING)
     * ====================================================== */
    $statusFinal = $profile->status_karyawan 
        ?? $profile->status 
        ?? $rekap->status 
        ?? 'Aktif';

    $kategoriFinal = strtolower(trim($statusFinal)) === 'magang'
        ? 'Magang'
        : 'Aktif';

    // override biar blade lama tetap aman
    if ($rekap) {
        $rekap->status   = $statusFinal;
        $rekap->kategori = $kategoriFinal;
    }

    /* ======================================================
     * POTONGAN
     * ====================================================== */
    $potongan = $rekap
        ? PotonganTunjangan::where('nama', $rekap->nama)
            ->where('bulan', $normalizedPeriode)
            ->first()
        : null;

    /* ======================================================
     * CICILAN
     * ====================================================== */
    $cicilan = collect();
    $totalCicilan = (int) ($rekap->cicilan ?? 0);

    if ($totalCicilan > 0) {
        $cicilan->push((object)[
            'keterangan' => $rekap->keterangan_cicilan ?? 'Cicilan Cash Advance',
            'jumlah'     => $totalCicilan,
        ]);
    }

    /* ======================================================
     * MASA KERJA
     * ====================================================== */
    $masaKerja = '-';
    $tanggalMasuk = '-';

    if ($profile?->tgl_masuk) {
        $masaKerja = $this->hitungMasaKerja($profile->tgl_masuk);
        $tanggalMasuk = Carbon::parse($profile->tgl_masuk)->translatedFormat('d F Y');
    }

    /* ======================================================
     * ADJUSTMENT (ANTI ERROR)
     * ====================================================== */
    $adjustments = collect();
    $totalKekuranganAdj = 0;
    $totalKelebihanAdj  = 0;
    $keteranganKekuranganAdj = '';
    $keteranganKelebihanAdj  = '';

    if ($rekap && $normalizedPeriode) {
        try {
            $carbon = Carbon::createFromFormat('Y-m', $normalizedPeriode);

            $adjustments = Adjustment::whereRaw(
                    'TRIM(UPPER(nama)) = ?',
                    [strtoupper(trim($rekap->nama))]
                )
                ->where('month', $carbon->month)
                ->where('year', $carbon->year)
                ->get();

            foreach ($adjustments as $adj) {
                $nominal = (float) $adj->nominal;
                $type = strtolower(trim($adj->type ?? ''));

                if (str_contains($type, 'tambah')) {
                    $totalKekuranganAdj += $nominal;

                    if (!empty($adj->keterangan)) {
                        $keteranganKekuranganAdj .=
                            ($keteranganKekuranganAdj ? ' | ' : '') . $adj->keterangan;
                    }

                } elseif (str_contains($type, 'potong')) {
                    $totalKelebihanAdj += $nominal;

                    if (!empty($adj->keterangan)) {
                        $keteranganKelebihanAdj .=
                            ($keteranganKelebihanAdj ? ' | ' : '') . $adj->keterangan;
                    }
                }
            }

        } catch (\Exception $e) {
            Log::warning('Adjustment error: ' . $e->getMessage());
        }
    }

    $keteranganKekuranganAdj = $keteranganKekuranganAdj ?: null;
    $keteranganKelebihanAdj  = $keteranganKelebihanAdj ?: null;

    /* ======================================================
     * HITUNG TOTAL
     * ====================================================== */
    $imbalanPokok      = $rekap->imbalan_pokok ?? 0;
    $imbalanLainnya    = $rekap->imbalan_lainnya ?? 0;
    $insentifMentor    = $rekap->insentif_mentor ?? 0;
    $transport         = $rekap->tambahan_transport ?? 0;

    $totalPendapatan = $imbalanPokok + $imbalanLainnya + $insentifMentor + $transport + $totalKelebihanAdj;

    $yangDibayarkan = $totalPendapatan
        + ($rekap->jumlah_bagi_hasil ?? 0)
        - ($rekap->kelebihan ?? 0)
        - ($rekap->cicilan ?? 0);

    $totalPotongan = ($potongan->total ?? 0) + $totalCicilan + $totalKekuranganAdj;

    /* ======================================================
     * RETURN VIEW
     * ====================================================== */
    return view('imbalan_rekap.slip_index', [
        'rekap'                   => $rekap,
        'profile'                 => $profile,
        'potongan'                => $potongan,
        'cicilan'                 => $cicilan,
        'totalCicilan'            => $totalCicilan,
        'allRekaps'               => $allRekaps,
        'periodeOptions'          => $this->getPeriodeOptions(),
        'periodeValue'            => $normalizedPeriode,
        'periode'                 => $bulanLabel,
        'masaKerja'               => $masaKerja,
        'tanggalMasuk'            => $tanggalMasuk,
        'noRekening'              => $profile?->no_rekening ?? '-',
        'bank'                    => $profile?->bank ?? '-',
        'atasNama'                => $profile?->nama_rekening ?? $rekap?->nama,
        'unit'                    => $displayUnit,
        'isAdmin'                 => $isAdmin,
        'units'                   => $units,

        'adjustments'             => $adjustments,
        'totalKekuranganAdj'      => $totalKekuranganAdj,
        'totalKelebihanAdj'       => $totalKelebihanAdj,
        'keteranganKekuranganAdj' => $keteranganKekuranganAdj,
        'keteranganKelebihanAdj'  => $keteranganKelebihanAdj,

        'totalPotongan'           => $totalPotongan,
        'totalPendapatan'         => $totalPendapatan,
        'yangDibayarkan'          => $yangDibayarkan,

        // 🔥 tambahan biar fleksibel di blade
        'statusFinal'             => $statusFinal,
        'kategoriFinal'           => $kategoriFinal,
    ]);
}

/* ======================================================
 * OPSI PERIODE (DROPDOWN BULAN)
 * ====================================================== */
private function getPeriodeOptions(): array
{
    $out = [];

    for ($i = 0; $i < 12; $i++) {
        $d = now()->subMonths($i);
        $out[] = [
            'value' => $d->format('Y-m'),
            'label' => $d->locale('id')->translatedFormat('F Y'),
        ];
    }

    return $out;
}

public function getRelawansByFilter(Request $request)
{
    $periode  = $request->query('periode');     // format Y-m
    $unit_id  = $request->query('unit_id');

    $query = ImbalanRekap::query()
        ->select('id', 'nama')
        ->distinct()
        ->orderBy('nama');

    // Filter periode (wajib)
    if ($periode) {
        // Karena kolom 'bulan' di tabel simpan format "Januari 2025", bukan Y-m
        // Kita perlu konversi dulu
        try {
            $carbonPeriode = Carbon::createFromFormat('Y-m', $periode);
            $bulanLabel = $carbonPeriode->locale('id')->translatedFormat('F Y');
            $query->where('bulan', $bulanLabel);
        } catch (\Exception $e) {
            // Jika format salah → return kosong atau fallback
            return response()->json([]);
        }
    } else {
        return response()->json([]);
    }

    // Filter unit (opsional, hanya jika admin & dipilih)
    if ($unit_id) {
        $query->where('biMBA_unit', function ($sub) use ($unit_id) {
            $sub->select('biMBA_unit')
                ->from('units')
                ->where('id', $unit_id)
                ->limit(1);
        });
        // Atau jika unit disimpan langsung sebagai string di imbalan_rekaps:
        // $query->where('biMBA_unit', $unitName);  // sesuaikan
    }

    $relawans = $query->get();

    return response()->json(
        $relawans->map(fn($r) => [
            'id'   => $r->id,
            'nama' => $r->nama,
        ])
    );
}
public function bayarPeriode(Request $request)
{

    $start_bulan = $request->start_bulan;
    $start_tahun = $request->start_tahun;

    $periode = Carbon::createFromFormat('Y-m', "$start_tahun-$start_bulan")
        ->locale('id')
        ->translatedFormat('F Y');

    $updated = ImbalanRekap::where('bulan', $periode)
        ->update([
            'status_pembayaran' => 'dibayar',
            'tanggal_dibayar' => now(),
            'dibayar_oleh' => Auth::user()->name
        ]);

    return back()->with(
        'success',
        "Berhasil membayar {$updated} relawan untuk periode {$periode}"
    );

}

public function bayarSingle(Request $request)
{
    $request->validate([
        'id' => 'required|exists:imbalan_rekaps,id'
    ]);

    $rekap = ImbalanRekap::findOrFail($request->id);

    if ($rekap->status_pembayaran === 'dibayar') {
        return response()->json([
            'success' => false,
            'message' => 'Sudah ditandai dibayar sebelumnya'
        ], 422);
    }

    $rekap->update([
        'status_pembayaran' => 'dibayar',
        'tanggal_dibayar'   => now(),
        'dibayar_oleh'      => Auth::user()->name ?? 'System'
    ]);

    // Refresh model agar accessor bekerja (opsional, tapi aman)
    $rekap->refresh();

    $tanggalFormatted = $rekap->tanggal_dibayar 
        ? $rekap->tanggal_dibayar->format('d/m/Y H:i') 
        : now()->format('d/m/Y H:i');  // fallback jika entah kenapa null

    return response()->json([
        'success' => true,
        'message' => 'Berhasil ditandai sudah dibayar',
        'tanggal' => $tanggalFormatted
    ]);
}
}