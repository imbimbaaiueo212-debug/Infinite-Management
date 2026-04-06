@extends('layouts.app')

@section('content')
    <div class="container">
        <h1>Edit Harga Saptataruna</h1>
        <a href="{{ route('harga.index') }}" class="btn btn-secondary mb-3">Kembali</a>

        <form action="{{ route('harga.update', $hargaSaptataruna->id) }}" method="POST">
            @csrf
            @method('PUT')

            <div class="mb-3">
                <label>Kategori</label>
                <select name="kategori" class="form-control" required>
                    <option value="BIAYA PENDAFTARAN"
                        {{ $hargaSaptataruna->kategori == 'BIAYA PENDAFTARAN' ? 'selected' : '' }}>BIAYA PENDAFTARAN
                    </option>
                    <option value="PENJUALAN" {{ $hargaSaptataruna->kategori == 'PENJUALAN' ? 'selected' : '' }}>PENJUALAN
                    </option>
                    <option value="BIAYA SPP PER BULAN"
                        {{ $hargaSaptataruna->kategori == 'BIAYA SPP PER BULAN' ? 'selected' : '' }}>BIAYA SPP PER BULAN
                    </option>
                </select>
            </div>

            <div class="mb-3">
                <label>Sub Kategori</label>
                <select name="sub_kategori" class="form-control">
                    <option value="">-</option>
                    <option value="Kelas Standar (1 guru, 4 murid)"
                        {{ $hargaSaptataruna->sub_kategori == 'Kelas Standar (1 guru, 4 murid)' ? 'selected' : '' }}>Kelas
                        Standar (1 guru, 4 murid)</option>
                    <option value="Kelas Khusus (1 guru, 2 murid)"
                        {{ $hargaSaptataruna->sub_kategori == 'Kelas Khusus (1 guru, 2 murid)' ? 'selected' : '' }}>Kelas
                        Khusus (1 guru, 2 murid)</option>
                    <option value="Kelas Beasiswa (1 guru, 4 murid)"
                        {{ $hargaSaptataruna->sub_kategori == 'Kelas Beasiswa (1 guru, 4 murid)' ? 'selected' : '' }}>Kelas
                        Beasiswa (1 guru, 4 murid)</option>
                    <option value="Kelas English (1 guru, 6 s/d 10 murid)"
                        {{ $hargaSaptataruna->sub_kategori == 'Kelas English (1 guru, 6 s/d 10 murid)' ? 'selected' : '' }}>
                        Kelas English (1 guru, 6 s/d 10 murid)</option>
                </select>
            </div>

            <div class="mb-3">
                <label>Kode</label>
                <input type="text" name="kode" class="form-control" value="{{ $hargaSaptataruna->kode }}">
            </div>

            <div class="mb-3">
                <label>Nama</label>
                <input type="text" name="nama" class="form-control" value="{{ $hargaSaptataruna->nama }}">
            </div>

            <div class="mb-3">
                <label>Duafa</label>
                <input type="number" step="0.01" name="duafa" class="form-control"
                    value="{{ $hargaSaptataruna->duafa }}">
            </div>

            <div class="mb-3">
                <label>Promo 2019</label>
                <input type="number" step="0.01" name="promo_2019" class="form-control"
                    value="{{ $hargaSaptataruna->promo_2019 }}">
            </div>

            <div class="mb-3">
                <label>Daftar Ulang</label>
                <input type="number" step="0.01" name="daftar_ulang" class="form-control"
                    value="{{ $hargaSaptataruna->daftar_ulang }}">
            </div>

            <div class="mb-3">
                <label>Spesial</label>
                <input type="number" step="0.01" name="spesial" class="form-control"
                    value="{{ $hargaSaptataruna->spesial }}">
            </div>

            <div class="mb-3">
                <label>Umum1</label>
                <input type="number" step="0.01" name="umum1" class="form-control"
                    value="{{ $hargaSaptataruna->umum1 }}">
            </div>

            <div class="mb-3">
                <label>Umum2</label>
                <input type="number" step="0.01" name="umum2" class="form-control"
                    value="{{ $hargaSaptataruna->umum2 }}">
            </div>

            <div class="mb-3">
                <label>Harga</label>
                <input type="number" step="0.01" name="harga" class="form-control"
                    value="{{ $hargaSaptataruna->harga }}">
            </div>

            <div class="mb-3">
                <label>A-F</label>
                <input type="number" step="0.01" name="a" class="form-control"
                    value="{{ $hargaSaptataruna->a }}">
                <input type="number" step="0.01" name="b" class="form-control"
                    value="{{ $hargaSaptataruna->b }}">
                <input type="number" step="0.01" name="c" class="form-control"
                    value="{{ $hargaSaptataruna->c }}">
                <input type="number" step="0.01" name="d" class="form-control"
                    value="{{ $hargaSaptataruna->d }}">
                <input type="number" step="0.01" name="e" class="form-control"
                    value="{{ $hargaSaptataruna->e }}">
                <input type="number" step="0.01" name="f" class="form-control"
                    value="{{ $hargaSaptataruna->f }}">
            </div>

            <button type="submit" class="btn btn-success">Update</button>
        </form>
    </div>
@endsection
