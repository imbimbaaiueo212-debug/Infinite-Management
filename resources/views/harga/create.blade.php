@extends('layouts.app')

@section('content')
<div class="container">
    <h1>Tambah Harga Saptataruna</h1>
    <a href="{{ route('harga.index') }}" class="btn btn-secondary mb-3">Kembali</a>

    <form action="{{ route('harga.store') }}" method="POST">
        @csrf

        {{-- ================= KATEGORI ================= --}}
        <div class="mb-3">
            <label>Kategori</label>
            <select name="kategori" id="kategori" class="form-control" required>
                <option value="">Pilih Kategori</option>
                <option value="BIAYA PENDAFTARAN">BIAYA PENDAFTARAN</option>
                <option value="PENJUALAN">PENJUALAN</option>
                <option value="BIAYA SPP PER BULAN">BIAYA SPP PER BULAN</option>
            </select>
        </div>

        {{-- ================= SUB KATEGORI (hanya muncul jika BIAYA SPP PER BULAN) ================= --}}
        <div class="mb-3">
    <label>Sub Kategori</label>
    <select name="sub_kategori" class="form-control">
        <option value="">-</option>
        <option value="Kelas Standar (1 guru, 4 murid)">Kelas Standar (1 guru, 4 murid)</option>
        <option value="Kelas Khusus (1 guru, 2 murid)">Kelas Khusus (1 guru, 2 murid)</option>
        <option value="Kelas Beasiswa (1 guru, 4 murid)">Kelas Beasiswa (1 guru, 4 murid)</option>
        <option value="Kelas English (1 guru, 6 s/d 10 murid)">Kelas English (1 guru, 6 s/d 10 murid)</option>
    </select>
</div>

        {{-- ================= INPUTAN LAIN ================= --}}
        <div class="mb-3">
            <label>Kode</label>
            <input type="text" name="kode" class="form-control">
        </div>

        <div class="mb-3">
            <label>Nama</label>
            <input type="text" name="nama" class="form-control">
        </div>

        <div class="mb-3">
            <label>Duafa</label>
            <input type="number" step="0.01" name="duafa" class="form-control">
        </div>

        <div class="mb-3">
            <label>Promo 2019</label>
            <input type="number" step="0.01" name="promo_2019" class="form-control">
        </div>

        <div class="mb-3">
            <label>Daftar Ulang</label>
            <input type="number" step="0.01" name="daftar_ulang" class="form-control">
        </div>

        <div class="mb-3">
            <label>Spesial</label>
            <input type="number" step="0.01" name="spesial" class="form-control">
        </div>

        <div class="mb-3">
            <label>Umum1</label>
            <input type="number" step="0.01" name="umum1" class="form-control">
        </div>

        <div class="mb-3">
            <label>Umum2</label>
            <input type="number" step="0.01" name="umum2" class="form-control">
        </div>

        <div class="mb-3">
            <label>Harga</label>
            <input type="number" step="0.01" name="harga" class="form-control">
        </div>

        <div class="mb-3">
            <label>A-F</label>
            <input type="number" step="0.01" name="a" class="form-control" placeholder="A">
            <input type="number" step="0.01" name="b" class="form-control" placeholder="B">
            <input type="number" step="0.01" name="c" class="form-control" placeholder="C">
            <input type="number" step="0.01" name="d" class="form-control" placeholder="D">
            <input type="number" step="0.01" name="e" class="form-control" placeholder="E">
            <input type="number" step="0.01" name="f" class="form-control" placeholder="F">
        </div>

        <button type="submit" class="btn btn-success">Simpan</button>
    </form>
</div>

{{-- Script untuk toggle sub kategori --}}
<script>
document.getElementById('kategori').addEventListener('change', function() {
    let subKategoriDiv = document.getElementById('subKategoriDiv');
    if (this.value === 'BIAYA SPP PER BULAN') {
        subKategoriDiv.style.display = 'block';
    } else {
        subKategoriDiv.style.display = 'none';
    }
});
</script>
@endsection
