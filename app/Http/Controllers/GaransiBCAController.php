<?php

namespace App\Http\Controllers;

use App\Models\GaransiBCA;
use App\Models\BukuInduk;
use App\Models\Unit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;
use Barryvdh\DomPDF\Facade\Pdf;



class GaransiBCAController extends Controller
{
    /* =====================================================
     * INDEX
     * ===================================================== */
    public function index()
{
    $query = GaransiBCA::query()->orderBy('id', 'desc');

    // Jika bukan admin → jangan pakai filter bimba_unit sama sekali
    if (auth()->check() && (auth()->user()->is_admin ?? false)) {
        // Hanya admin yang boleh filter unit
        // (kalau ada filter request unit, tambahkan di sini)
    } else {
        // Non-admin: tidak ada filter bimba_unit
        // Bisa kosongkan atau abaikan saja
    }

    $data = $query->get();

    return view('garansi_bca.index', compact('data'));
}

    /* =====================================================
     * CREATE
     * ===================================================== */
    public function create(Request $request)
    {
        $user = Auth::user();

        /* ================= UNIT ================= */
        if ($user->isAdminUser()) {
            $listUnit = Unit::orderBy('biMBA_unit')
                ->pluck('biMBA_unit', 'biMBA_unit');
        } else {
            $listUnit = collect([
                $user->unit?->biMBA_unit => $user->unit?->biMBA_unit
            ]);
        }

        /* ================= MURID (BUKU INDUK) ================= */
        $muridQuery = BukuInduk::query()
            ->select(
                'nim',
                'nama',
                'tmpt_lahir',
                'tgl_lahir',
                'tgl_masuk',
                'orangtua',
                'bimba_unit'
            )
            ->whereNotNull('nim')
            ->where('nim', '!=', '');

        // Admin wajib pilih unit dulu
        if ($user->isAdminUser()) {
            if ($request->filled('bimba_unit')) {
                $muridQuery->where('bimba_unit', $request->bimba_unit);
            } else {
                $muridQuery->whereRaw('1 = 0');
            }
        }

        $listMurid = $muridQuery
            ->orderBy('nama')
            ->get();

        return view('garansi_bca.create', compact(
            'listUnit',
            'listMurid'
        ));
    }

    /* =====================================================
     * STORE
     * ===================================================== */
    public function store(Request $request)
    {
        $request->validate([
            'nim'             => 'required|exists:buku_induk,nim',
            'virtual_account' => 'nullable|string',
        ]);

        /* ================= AMBIL DATA BUKU INDUK ================= */
        $murid = BukuInduk::where('nim', trim($request->nim))->firstOrFail();

        /* ================= SIMPAN ================= */
        GaransiBCA::create([
            'virtual_account' => $request->virtual_account,

            // 🔑 DATA OTOMATIS DARI BUKU INDUK
            'nama_murid' => $murid->nama,

            'tempat_tanggal_lahir' => trim(
                ($murid->tmpt_lahir ?: '-') .
                ', ' .
                ($murid->tgl_lahir
                    ? $murid->tgl_lahir->format('d-m-Y')
                    : '-'
                )
            ),

            'tanggal_masuk'       => $murid->tgl_masuk,
            'nama_orang_tua_wali' => $murid->orangtua,
            'bimba_unit'          => $murid->bimba_unit,

            // ✅ TANGGAL DIBERIKAN OTOMATIS
            'tanggal_diberikan'   => Carbon::today(),
        ]);

        return redirect()
            ->route('garansi-bca.index')
            ->with('success', 'Data Garansi BCA berhasil ditambahkan');
    }

    /* =====================================================
     * EDIT
     * ===================================================== */
    public function edit($id)
    {
        $data = GaransiBCA::findOrFail($id);

        return view('garansi_bca.edit', compact('data'));
    }

    /* =====================================================
     * UPDATE
     * ===================================================== */
    public function update(Request $request, $id)
    {
        $data = GaransiBCA::findOrFail($id);

        $validated = $request->validate([
            'virtual_account'        => 'nullable|string',
            'tempat_tanggal_lahir'   => 'nullable|string',
            'tanggal_masuk'          => 'nullable|date',
            'nama_orang_tua_wali'    => 'nullable|string',
            'tanggal_diberikan'      => 'nullable|date', // ← jika mau bisa diedit admin
        ]);

        $data->update($validated);

        return redirect()
            ->route('garansi-bca.index')
            ->with('success', 'Data Garansi BCA berhasil diupdate');
    }

    /* =====================================================
     * DESTROY
     * ===================================================== */
    public function destroy($id)
    {
        GaransiBCA::findOrFail($id)->delete();

        return redirect()
            ->route('garansi-bca.index')
            ->with('success', 'Data Garansi BCA berhasil dihapus');
    }
    /* =====================================================
 * PDF GARANSI BCA
 * ===================================================== */
public function pdf($id)
{
    $user = auth()->user();

    $data = GaransiBCA::findOrFail($id);

    // 🔐 Security: user non-admin hanya boleh lihat unit sendiri
    if (
        !$user->isAdminUser() &&
        $data->bimba_unit !== $user->unit?->biMBA_unit
    ) {
        abort(403);
    }

    $pdf = Pdf::loadView(
    'garansi_bca.pdf',
    compact('data')
)->setPaper('A5', 'landscape');

    // tampil di browser
    return $pdf->stream(
        'garansi-bca-' . str_replace(' ', '-', strtolower($data->nama_murid)) . '.pdf'
    );
}
}
