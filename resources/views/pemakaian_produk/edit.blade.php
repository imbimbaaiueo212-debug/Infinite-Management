@extends('layouts.app')

@section('title', 'Edit Pemakaian Produk')

@section('content')
<div class="container-fluid py-4">
    <div class="row justify-content-center">
        <div class="col-md-11">
            <div class="card shadow-sm">
                <div class="card-header bg-warning text-dark">
                    <h4 class="mb-0 text-center">Edit Pemakaian Produk</h4>
                </div>
                <div class="card-body p-4">

                    <form action="{{ route('pemakaian_produk.update', $item->id) }}" method="POST">
                        @csrf
                        @method('PUT')

                        <div class="row g-3">
                            <!-- Tanggal Pemakaian -->
                            <div class="col-md-6">
                                <label class="form-label fw-bold">Tanggal Pemakaian <span class="text-danger">*</span></label>
                                <input type="date" name="tanggal" id="tanggal" class="form-control" 
                                       value="{{ old('tanggal', $item->tanggal ? \Illuminate\Support\Carbon::parse($item->tanggal)->format('Y-m-d') : '') }}" required>
                                @error('tanggal') <small class="text-danger">{{ $message }}</small> @enderror
                                <small class="text-muted d-block mt-2">
                                    <strong>Minggu Otomatis:</strong> 
                                    <span id="info_minggu" class="text-primary fw-bold">
                                        Minggu {{ $item->tanggal ? min(ceil(\Illuminate\Support\Carbon::parse($item->tanggal)->day / 7), 5) : '-' }}
                                    </span>
                                </small>
                            </div>

                            <!-- Unit biMBA -->
                            <div class="col-md-6">
                                <label class="form-label fw-bold">Unit biMBA <span class="text-danger">*</span></label>
                                <select name="unit_id" id="unit_id" class="form-select" required>
                                    <option value="">-- Pilih Unit --</option>
                                    @foreach($units as $unit)
                                        <option value="{{ $unit->id }}" {{ old('unit_id', $item->unit_id) == $unit->id ? 'selected' : '' }}>
                                            {{ $unit->label }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('unit_id') <small class="text-danger">{{ $message }}</small> @enderror
                            </div>

                            <!-- NIM & Nama Murid (Readonly) -->
                            <div class="col-md-6">
                                <label class="form-label fw-bold">NIM</label>
                                <input type="text" id="nim" class="form-control" readonly value="{{ old('nim', $item->nim) }}">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-bold">Nama Murid <span class="text-danger">*</span></label>
                                <input type="text" id="nama_murid" class="form-control" readonly value="{{ old('nama_murid', $item->nama_murid) }}">
                            </div>

                            <!-- Dropdown Murid -->
                            <div class="col-md-12">
                                <label class="form-label fw-bold">Pilih Murid dari Buku Induk <span class="text-danger">*</span></label>
                                <select name="murid_id" id="murid_dropdown" class="form-select" required>
                                    <option value="">-- Pilih Unit dulu --</option>
                                </select>
                                <small class="text-muted">Murid aktif dari unit terpilih</small>
                            </div>

                            <!-- Dropdown Produk -->
                            <div class="col-md-8">
                                <label class="form-label fw-bold">Produk <span class="text-danger">*</span></label>
                                <select name="label" id="produk_dropdown" class="form-select" required>
                                    <option value="">-- Pilih Unit dulu --</option>
                                </select>
                                <small class="text-muted">Produk tersedia di unit ini</small>
                            </div>

                            <!-- Jumlah -->
                            <div class="col-md-4">
                                <label class="form-label fw-bold">Jumlah <span class="text-danger">*</span></label>
                                <input type="number" name="jumlah" id="jumlah" class="form-control text-end" min="1" 
                                       value="{{ old('jumlah', $item->jumlah) }}" required>
                            </div>

                            <!-- Harga & Total -->
                            <div class="col-md-4">
                                <label class="form-label">Harga Satuan (Rp)</label>
                                <div class="input-group">
                                    <span class="input-group-text">Rp</span>
                                    <input type="number" name="harga" id="harga" class="form-control text-end" readonly 
                                           value="{{ old('harga', $item->harga) }}">
                                </div>
                            </div>

                            <div class="col-md-4">
                                <label class="form-label">Total (Rp)</label>
                                <div class="input-group">
                                    <span class="input-group-text">Rp</span>
                                    <input type="text" id="total" class="form-control text-end fw-bold bg-light" readonly 
                                           value="{{ old('total', $item->total ? number_format($item->total, 0, ',', '.') : '0') }}">
                                </div>
                            </div>

                            <!-- Field Otomatis -->
                            <div class="col-md-3">
                                <label class="form-label">Kategori</label>
                                <input type="text" id="kategori" class="form-control bg-light" readonly value="{{ old('kategori', $item->kategori) }}">
                                <input type="hidden" name="kategori" id="kategori_hidden" value="{{ old('kategori', $item->kategori) }}">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Jenis</label>
                                <input type="text" id="jenis" class="form-control bg-light" readonly value="{{ old('jenis', $item->jenis) }}">
                                <input type="hidden" name="jenis" id="jenis_hidden" value="{{ old('jenis', $item->jenis) }}">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Nama Produk</label>
                                <input type="text" id="nama_produk" class="form-control bg-light" readonly value="{{ old('nama_produk', $item->nama_produk) }}">
                                <input type="hidden" name="nama_produk" id="nama_produk_hidden" value="{{ old('nama_produk', $item->nama_produk) }}">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Satuan</label>
                                <input type="text" id="satuan" class="form-control bg-light" readonly value="{{ old('satuan', $item->satuan) }}">
                                <input type="hidden" name="satuan" id="satuan_hidden" value="{{ old('satuan', $item->satuan) }}">
                            </div>
                        </div>

                        <div class="mt-5 text-end">
                            <a href="{{ route('pemakaian_produk.index') }}" class="btn btn-secondary btn-lg px-4">
                                Kembali
                            </a>
                            <button type="submit" class="btn btn-warning btn-lg px-5 ms-3">
                                Update Pemakaian
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const unitSelect = document.getElementById('unit_id');
    const muridDropdown = document.getElementById('murid_dropdown');
    const produkDropdown = document.getElementById('produk_dropdown');
    const nimInput = document.getElementById('nim');
    const namaMuridInput = document.getElementById('nama_murid');
    const inputTanggal = document.getElementById('tanggal');
    const infoMinggu = document.getElementById('info_minggu');

    const inputs = {
        harga: document.getElementById('harga'),
        jumlah: document.getElementById('jumlah'),
        total: document.getElementById('total'),
        kategori: document.getElementById('kategori'),
        kategoriHidden: document.getElementById('kategori_hidden'),
        jenis: document.getElementById('jenis'),
        jenisHidden: document.getElementById('jenis_hidden'),
        namaProduk: document.getElementById('nama_produk'),
        namaProdukHidden: document.getElementById('nama_produk_hidden'),
        satuan: document.getElementById('satuan'),
        satuanHidden: document.getElementById('satuan_hidden')
    };

    // Data existing untuk preload
    const existingMuridId = '{{ $item->murid_id ?? "" }}';
    const existingLabel = '{{ $item->label ?? "" }}';
    const existingNim = '{{ $item->nim ?? "" }}';
    const existingNamaMurid = '{{ addslashes($item->nama_murid ?? "") }}';

    function formatRupiah(angka) {
        return new Intl.NumberFormat('id-ID').format(angka);
    }

    function hitungTotal() {
        const jumlah = parseFloat(inputs.jumlah.value) || 0;
        const harga = parseFloat(inputs.harga.value) || 0;
        inputs.total.value = jumlah && harga ? formatRupiah(jumlah * harga) : '0';
    }

    function hitungMinggu() {
        if (!inputTanggal.value) {
            infoMinggu.textContent = 'Minggu -';
            return;
        }
        const date = new Date(inputTanggal.value);
        const hari = date.getDate();
        const minggu = Math.min(Math.ceil(hari / 7), 5);
        infoMinggu.textContent = 'Minggu ' + minggu;
    }

    // Load murid & produk
    function loadData(unitId) {
        if (!unitId) {
            muridDropdown.innerHTML = '<option value="">-- Pilih Unit dulu --</option>';
            produkDropdown.innerHTML = '<option value="">-- Pilih Unit dulu --</option>';
            return;
        }

        const muridUrl = '{{ route('pemakaian_produk.murid_by_unit', ['unitId' => ':unitId']) }}'.replace(':unitId', unitId);
        const produkUrl = '{{ route('pemakaian_produk.produk_by_unit', ['unitId' => ':unitId']) }}'.replace(':unitId', unitId);

        // Load murid
        fetch(muridUrl)
            .then(r => r.json())
            .then(murids => {
                muridDropdown.innerHTML = '<option value="">-- Pilih Murid --</option>';
                let foundMurid = false;

                murids.forEach(murid => {
                    const option = document.createElement('option');
                    option.value = murid.id;
                    option.textContent = `${murid.nim || 'Tanpa NIM'} | ${murid.nama}`;
                    option.dataset.nim = murid.nim || '';
                    option.dataset.nama = murid.nama;

                    if (murid.id == existingMuridId) {
                        option.selected = true;
                        nimInput.value = murid.nim || '';
                        namaMuridInput.value = murid.nama;
                        foundMurid = true;
                    }

                    muridDropdown.appendChild(option);
                });

                // Jika murid existing tidak ditemukan (mungkin nonaktif), tetap tampilkan data lama
                if (!foundMurid && existingMuridId) {
                    const option = document.createElement('option');
                    option.value = existingMuridId;
                    option.textContent = `${existingNim || 'Tanpa NIM'} | ${existingNamaMurid} (Mungkin sudah tidak aktif)`;
                    option.selected = true;
                    option.disabled = true;
                    muridDropdown.appendChild(option);
                    nimInput.value = existingNim;
                    namaMuridInput.value = existingNamaMurid;
                }
            })
            .catch(() => {
                muridDropdown.innerHTML = '<option value="">Gagal memuat murid</option>';
            });

        // Load produk
        fetch(produkUrl)
            .then(r => r.json())
            .then(produks => {
                produkDropdown.innerHTML = '<option value="">-- Pilih Produk --</option>';

                produks.forEach(p => {
                    const option = document.createElement('option');
                    option.value = p.label;
                    option.textContent = `${p.label} - ${p.nama_produk || p.label} (${p.jenis})`;
                    option.dataset.kategori = p.kategori || '';
                    option.dataset.jenis = p.jenis || '';
                    option.dataset.namaProduk = p.nama_produk || p.label;
                    option.dataset.satuan = p.satuan || '';
                    option.dataset.harga = p.harga || 0;

                    if (p.label === existingLabel) {
                        option.selected = true;
                    }

                    produkDropdown.appendChild(option);
                });

                // Trigger change untuk isi field otomatis
                if (produkDropdown.value) {
                    produkDropdown.dispatchEvent(new Event('change'));
                }
            })
            .catch(() => {
                produkDropdown.innerHTML = '<option value="">Gagal memuat produk</option>';
            });
    }

    // Saat unit berubah
    unitSelect.addEventListener('change', function () {
        loadData(this.value);
    });

    // Saat pilih murid
    muridDropdown.addEventListener('change', function () {
        const option = this.options[this.selectedIndex];
        if (option && option.value && !option.disabled) {
            nimInput.value = option.dataset.nim;
            namaMuridInput.value = option.dataset.nama;
        }
    });

    // Saat pilih produk
    produkDropdown.addEventListener('change', function () {
        const option = this.options[this.selectedIndex];
        if (option && option.value) {
            inputs.kategori.value = option.dataset.kategori || '';
            inputs.kategoriHidden.value = option.dataset.kategori || '';
            inputs.jenis.value = option.dataset.jenis || '';
            inputs.jenisHidden.value = option.dataset.jenis || '';
            inputs.namaProduk.value = option.dataset.namaProduk || '';
            inputs.namaProdukHidden.value = option.dataset.namaProduk || '';
            inputs.satuan.value = option.dataset.satuan || '';
            inputs.satuanHidden.value = option.dataset.satuan || '';
            inputs.harga.value = option.dataset.harga || 0;
        } else {
            Object.keys(inputs).forEach(key => {
                if (inputs[key] && inputs[key].type !== 'hidden') inputs[key].value = '';
            });
            inputs.harga.value = 0;
        }
        hitungTotal();
    });

    // Event lain
    inputTanggal.addEventListener('change', hitungMinggu);
    inputs.jumlah.addEventListener('input', hitungTotal);

    // Inisialisasi
    hitungMinggu();
    hitungTotal();

    // Preload data existing saat halaman dimuat
    const currentUnitId = '{{ old('unit_id', $item->unit_id) }}';
    if (currentUnitId) {
        unitSelect.value = currentUnitId;
        loadData(currentUnitId);
    } else {
        // Jika unit tidak ada, tetap tampilkan data lama
        nimInput.value = '{{ $item->nim ?? "" }}';
        namaMuridInput.value = '{{ addslashes($item->nama_murid ?? "") }}';
    }
});
</script>
@endsection