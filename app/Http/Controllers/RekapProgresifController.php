<?php

namespace App\Http\Controllers;

use App\Models\RekapProgresif;
use App\Models\Profile;
use App\Models\Penerimaan;
use App\Models\HargaSaptataruna;
use App\Models\BukuInduk;
use App\Models\Unit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

class RekapProgresifController extends Controller
{

    private function isCurrentUserAdmin(): bool
    {
        if (!Auth::check()) return false;

        $role = strtoupper(Auth::user()->role ?? '');

        $allowedRoles = [
            'ADMIN',
            'ADMINISTRATOR',
            'OWNER',
            'DIREKTUR',
            'KEPALA UA'
        ];

        return in_array($role, $allowedRoles);
    }

    private function getIndonesianMonthName(string $monthNumber): ?string
    {
        $months = [
            '01'=>'Januari','02'=>'Februari','03'=>'Maret','04'=>'April',
            '05'=>'Mei','06'=>'Juni','07'=>'Juli','08'=>'Agustus',
            '09'=>'September','10'=>'Oktober','11'=>'November','12'=>'Desember',
        ];

        return $months[$monthNumber] ?? null;
    }

    /*
    |--------------------------------------------------------------------------
    | TARIF PROGRESIF
    |--------------------------------------------------------------------------
    */

    private $progresifTariffs = [

        'GURU'=>[
            [6,10,100000],[11,15,200000],[16,20,300000],
            [21,25,450000],[26,30,600000],[31,35,800000],
            [36,40,1000000],[41,45,1400000],[46,50,1500000],
            [51,55,1600000],[56,60,1800000],[61,65,2000000],
            [66,70,2100000],[71,75,2400000],[76,80,2600000]
        ],

        'KEPALA UNIT'=>[
            [10,29,210000],[30,49,420000],[50,69,630000],
            [70,89,840000],[90,109,1050000],[110,129,1260000],
            [130,149,1470000],[150,169,1680000],[170,189,1890000],
            [190,209,2100000],[210,229,2310000],[230,249,2520000]
        ],

        'KEPALA UA'=>[
            [250,269,2730000],[270,289,2940000],[290,309,3150000],
            [310,329,3360000],[330,349,3570000],[350,369,3780000],
            [370,389,3990000],[390,409,4200000],[410,429,4410000],
            [430,449,4620000],[450,469,4830000],[470,489,5040000]
        ]
    ];

    private function calculateProgresifTariff($totalFM,$jabatan)
    {
        $jabatan = strtoupper(trim($jabatan));

        if(!isset($this->progresifTariffs[$jabatan])){
            return 0;
        }

        $fm = floor($totalFM);

        foreach($this->progresifTariffs[$jabatan] as $range){

            [$min,$max,$tarif] = $range;

            if($fm >= $min && $fm <= $max){
                return $tarif;
            }
        }

        $last = end($this->progresifTariffs[$jabatan]);

        return $fm > $last[1] ? $last[2] : 0;
    }

    /*
    |--------------------------------------------------------------------------
    | INDEX
    |--------------------------------------------------------------------------
    */

public function index(Request $request)
{
    $periode = $request->get('periode');

    if (!$periode) {
        // Default: bulan SEBELUMNYA
        $defaultDate = Carbon::now()->subMonth()->startOfMonth();
        $periode = $defaultDate->format('Y-m');   // contoh: sekarang Maret → "2026-02"
    }

    $selectedTahun = null;
    $selectedBulanNama = null;

    if ($periode && preg_match('/^\d{4}-\d{2}$/', $periode)) {
        try {
            $date = Carbon::createFromFormat('Y-m', $periode);
            $selectedTahun = $date->year;
            $selectedBulanNama = $this->getIndonesianMonthName($date->format('m'));
        } catch (\Exception $e) {
            // fallback ke sekarang kalau format salah
            $date = Carbon::now();
            $selectedTahun = $date->year;
            $selectedBulanNama = $this->getIndonesianMonthName($date->format('m'));
        }
    }

    $query = RekapProgresif::query();

    // Filter utama: hanya Guru dan Kepala Unit (case-insensitive)
    $query->where(function ($q) {
        $q->whereRaw('LOWER(jabatan) LIKE ?', ['%guru%'])
          ->orWhereRaw('LOWER(jabatan) LIKE ?', ['%pengajar%'])
          ->orWhereRaw('LOWER(jabatan) LIKE ?', ['%tutor%'])
          ->orWhereRaw('LOWER(jabatan) = ?', ['kepala unit'])
          ->orWhereRaw('LOWER(jabatan) LIKE ?', ['%kepala bimba%'])
          ->orWhereRaw('LOWER(jabatan) LIKE ?', ['%kepala sekolah%'])
          ->orWhereRaw('LOWER(jabatan) = ?', ['ku']);
    });

    if ($selectedTahun) {
        $query->where('tahun', $selectedTahun);
    }

    if ($selectedBulanNama) {
        $query->where('bulan', $selectedBulanNama);
    }

    $rekaps = $query->orderByDesc('tahun')
                    ->orderByDesc('bulan')
                    ->paginate(25);

    $isAdmin = $this->isCurrentUserAdmin();

    return view('rekap-progresif.index', compact(
        'rekaps',
        'isAdmin',
        'periode',             // <-- kirimkan ini
        'selectedTahun',       // optional, kalau mau tampilkan di view
        'selectedBulanNama'    // optional
    ));
}

    /*
    |--------------------------------------------------------------------------
    | CREATE
    |--------------------------------------------------------------------------
    */

    public function create()
    {
        $profiles = Profile::orderBy('nama')->get();
        return view('rekap-progresif.create',compact('profiles'));
    }

    /*
    |--------------------------------------------------------------------------
    | STORE
    |--------------------------------------------------------------------------
    */

    public function store(Request $request)
    {

        $profile = Profile::findOrFail($request->profile_id);

        $bulan = strtolower($request->bulan);
        $tahun = $request->tahun;

        $bimbaUnit = $profile->biMBA_unit ?? $profile->bimba_unit ?? $profile->unit;

        $isKepalaUnit = strtolower($profile->jabatan) === 'kepala unit';

        $paidQuery = Penerimaan::whereRaw('LOWER(bulan)=?',[$bulan])
            ->where('tahun',$tahun)
            ->where('spp','>',0);

        if($isKepalaUnit){
    $paidQuery->where('bimba_unit',$bimbaUnit);
}else{
    $paidQuery->where('guru',$profile->nama);
}

        $paidNims = $paidQuery->distinct()->pluck('nim');

        $totalMuridBayar = $paidNims->count();

        $totalSPP = Penerimaan::whereIn('nim',$paidNims)
            ->whereRaw('LOWER(bulan)=?',[$bulan])
            ->where('tahun',$tahun)
            ->sum('spp');

        $totalMurid = $isKepalaUnit
    ? BukuInduk::where('bimba_unit', $bimbaUnit)->where('status','aktif')->count()
    : BukuInduk::where('guru',$profile->nama)->where('status','aktif')->count();

        $hargaS3 = HargaSaptataruna::whereRaw("LOWER(nama)='s3'")
            ->value('harga') ?? 300000;

        $totalFM = round(($totalSPP / $hargaS3) * 1.17,2);

        $progresif = $this->calculateProgresifTariff($totalFM,$profile->jabatan);

        $unit = Unit::whereRaw('LOWER(TRIM(biMBA_unit))=?',[strtolower(trim($bimbaUnit))])->first();

        $data = [

            'nama'=>$profile->nama,
            'jabatan'=>$profile->jabatan,
            'status'=>$profile->status_karyawan,
            'departemen'=>$profile->departemen,
            'masa_kerja'=>$this->formatMasaKerja($profile->masa_kerja),

            'bulan'=>$request->bulan,
            'tahun'=>$tahun,

            'spp_bimba'=>$totalSPP,
            'am1'=>$totalMurid,
            'am2'=>$totalMuridBayar,

            'total_fm'=>$totalFM,
            'progresif'=>$progresif,

            'spp_english'=>$request->spp_english ?? 0,
            'komisi'=>$request->komisi ?? 0,

            'dibayarkan'=>$progresif
                + ($request->spp_english ?? 0)
                + ($request->komisi ?? 0),

            'bimba_unit'=>$bimbaUnit,
            'no_cabang'=>$unit->no_cabang ?? null
        ];

        RekapProgresif::create($data);

        return redirect()->route('rekap-progresif.index')
        ->with('success','Data berhasil disimpan');
    }

    /*
    |--------------------------------------------------------------------------
    | EDIT
    |--------------------------------------------------------------------------
    */

public function edit($id)
{
    $rekap_progresif = RekapProgresif::findOrFail($id);

    return view('rekap-progresif.edit', compact('rekap_progresif'));
}

    /*
    |--------------------------------------------------------------------------
    | UPDATE
    |--------------------------------------------------------------------------
    */

    public function update(Request $request,$id)
    {

        $rekap = RekapProgresif::findOrFail($id);

        $rekap->update([

            'spp_english'=>$request->spp_english,
            'komisi'=>$request->komisi,

            'dibayarkan'=>
                $rekap->progresif
                + ($request->spp_english ?? 0)
                + ($request->komisi ?? 0)

        ]);

        return redirect()->route('rekap-progresif.index')
        ->with('success','Data berhasil diupdate');
    }

    /*
    |--------------------------------------------------------------------------
    | FORMAT MASA KERJA
    |--------------------------------------------------------------------------
    */

    /*
|--------------------------------------------------------------------------
| DELETE
|--------------------------------------------------------------------------
*/

public function destroy($id)
{
    $rekap = RekapProgresif::findOrFail($id);

    $rekap->delete();

    return redirect()
        ->route('rekap-progresif.index')
        ->with('success','Data berhasil dihapus');
}

    private function formatMasaKerja($masaKerjaRaw)
    {

        $masaKerjaRaw = (int)$masaKerjaRaw;

        $tahun = floor($masaKerjaRaw / 12);
        $bulan = $masaKerjaRaw % 12;

        return ($tahun ? "$tahun tahun " : '')
            .($bulan ? "$bulan bulan" : ($tahun ? '' : '-'));
    }

    public function calculate(Request $request)
{
    $profile = Profile::findOrFail($request->profile_id);

    $bulan = strtolower(trim($request->bulan));
    $tahun = $request->tahun;

    $bimbaUnit = $profile->biMBA_unit ?? $profile->bimba_unit ?? $profile->unit;

    $unit = Unit::whereRaw(
        'LOWER(TRIM(biMBA_unit)) = ?',
        [strtolower(trim($bimbaUnit))]
    )->first();

    $noCabang = $unit->no_cabang ?? null;

    $jabatan = strtolower(trim($profile->jabatan));

    $isKepalaUnit = $jabatan === 'kepala unit';
    $isRelawan = $jabatan === 'relawan';

    $paidQuery = Penerimaan::whereRaw('LOWER(bulan)=?', [$bulan])
        ->where('tahun', $tahun)
        ->where('spp', '>', 0);

    /*
    |--------------------------------------------------------------------------
    | FILTER DATA MURID
    |--------------------------------------------------------------------------
    */

    if ($isKepalaUnit || $isRelawan) {

        // Kepala unit ambil semua murid di unit
        $paidQuery->where('bimba_unit', 'LIKE', '%' . $bimbaUnit . '%');

    } else {

        // Guru hanya murid dia
        $paidQuery->where('guru', $profile->nama);

    }

    $paidNims = $paidQuery->distinct()->pluck('nim');

    $totalMuridBayar = $paidNims->count();

    $totalSPP = Penerimaan::whereIn('nim', $paidNims)
        ->whereRaw('LOWER(bulan)=?', [$bulan])
        ->where('tahun', $tahun)
        ->sum('spp');

    /*
    |--------------------------------------------------------------------------
    | TOTAL MURID AKTIF
    |--------------------------------------------------------------------------
    */

    if ($isKepalaUnit || $isRelawan) {

        $totalMurid = BukuInduk::where('bimba_unit', 'LIKE', '%' . $bimbaUnit . '%')
            ->where('status', 'aktif')
            ->count();

    } else {

        $totalMurid = BukuInduk::where('guru', $profile->nama)
            ->where('status', 'aktif')
            ->count();

    }

    $hargaS3 = HargaSaptataruna::whereRaw("LOWER(nama)='s3'")
        ->value('harga') ?? 300000;

    $totalFM = round(($totalSPP / $hargaS3) * 1.17, 2);

    $progresif = $this->calculateProgresifTariff($totalFM, $profile->jabatan);

    return response()->json([

        'jabatan' => $profile->jabatan,
        'status' => $profile->status_karyawan,
        'departemen' => $profile->departemen,
        'masa_kerja' => $this->formatMasaKerja($profile->masa_kerja),

        'bimba_unit' => $bimbaUnit,
        'no_cabang' => $noCabang,

        'spp_bimba' => $totalSPP,
        'total_fm' => $totalFM,
        'progresif' => $progresif,

        'am1' => $totalMurid,
        'am2' => $totalMuridBayar
    ]);
}


}