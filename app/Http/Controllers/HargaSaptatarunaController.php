<?php

namespace App\Http\Controllers;

use App\Models\HargaSaptataruna;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use App\Imports\HargaSaptatarunaImport;

class HargaSaptatarunaController extends Controller
{
    // Menampilkan semua data
public function index()
{
    $items = HargaSaptataruna::all(); // jangan salah model
    return view('harga.index', compact('items'));
}

    // Menampilkan form untuk membuat data baru
    public function create()
    {
        return view('harga.create');
    }

    // Menyimpan data baru
    public function store(Request $request)
    {
        $data = $request->validate([
            'kategori' => 'nullable|string|max:255',
            'sub_kategori' => 'nullable|string|max:255',
            'kode' => 'nullable|string|max:50',
            'nama' => 'nullable|string|max:255',
            'duafa' => 'nullable|numeric',
            'promo_2019' => 'nullable|numeric',
            'daftar_ulang' => 'nullable|numeric',
            'spesial' => 'nullable|numeric',
            'umum1' => 'nullable|numeric',
            'umum2' => 'nullable|numeric',
            'harga' => 'nullable|numeric',
            'a' => 'nullable|numeric',
            'b' => 'nullable|numeric',
            'c' => 'nullable|numeric',
            'd' => 'nullable|numeric',
            'e' => 'nullable|numeric',
            'f' => 'nullable|numeric',
        ]);

        HargaSaptataruna::create($data);

        return redirect()->route('harga.index')->with('success', 'Data berhasil ditambahkan.');
    }

    // Menampilkan detail data
    public function show(HargaSaptataruna $hargaSaptataruna)
    {
        return view('harga.show', compact('hargaSaptataruna'));
    }

    // Menampilkan form edit
public function edit(HargaSaptataruna $harga)
{
    return view('harga.edit', ['hargaSaptataruna' => $harga]);
}

    // Update data
    public function update(Request $request, HargaSaptataruna $harga)
{
    $data = $request->validate([
        'kategori' => 'nullable|string|max:255',
        'sub_kategori' => 'nullable|string|max:255',
        'kode' => 'nullable|string|max:50',
        'nama' => 'nullable|string|max:255',
        'duafa' => 'nullable|numeric',
        'promo_2019' => 'nullable|numeric',
        'daftar_ulang' => 'nullable|numeric',
        'spesial' => 'nullable|numeric',
        'umum1' => 'nullable|numeric',
        'umum2' => 'nullable|numeric',
        'harga' => 'nullable|numeric',
        'a' => 'nullable|numeric',
        'b' => 'nullable|numeric',
        'c' => 'nullable|numeric',
        'd' => 'nullable|numeric',
        'e' => 'nullable|numeric',
        'f' => 'nullable|numeric',
    ]);

    $harga->update($data);

    return redirect()->route('harga.index')->with('success', 'Data berhasil diupdate.');
}


    // Hapus data
    public function destroy($id)
{
    $item = HargaSaptataruna::findOrFail($id);
    $item->delete();

    return redirect()->route('harga.index')->with('success', 'Data berhasil dihapus');
}

    // Import Excel
    public function import(Request $request)
    {
        $request->validate([
            'file' => 'required|mimes:xlsx,xls,csv',
        ]);

        Excel::import(new HargaSaptatarunaImport, $request->file('file'));

        return redirect()->route('harga.index')->with('success', 'Data berhasil diimport dari Excel.');
    }
}
