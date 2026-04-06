@extends('layouts.app')

@section('title', 'Tambah Pemakaian Produk')

@section('content')
<div class="container-fluid py-4">
    <div class="row justify-content-center">
        <div class="col-md-11">
            <div class="card shadow-sm">
                <div class="card-header bg-primary text-white">
                    <h4 class="mb-0 text-center">Tambah Pemakaian Produk</h4>
                </div>
                <div class="card-body p-4">

                    @php
                        $isAdmin = auth()->check() && (auth()->user()->is_admin ?? false);
                        $userUnit = auth()->user()->bimba_unit ?? null;
                        $defaultUnitId = null;
                        $unitDisplay = 'Unit belum diatur';

                        if (!$isAdmin && $userUnit) {
                            $unit = \App\Models\Unit::where('biMBA_unit', $userUnit)->first();
                            if ($unit) {
                                $defaultUnitId = $unit->id;
                                $unitDisplay = $unit->no_cabang . ' | ' . strtoupper($unit->biMBA_unit);
                            }
                        }
                    @endphp

                    @if (!$isAdmin && !$defaultUnitId)
                        <div class="alert alert-danger mb-4 text-center">
                            <strong>Unit Anda belum diatur!</strong><br>
                            Hubungi admin untuk mengatur unit di profile agar bisa menambah pemakaian produk.
                        </div>
                    @endif

                    <form action="{{ route('pemakaian_produk.store') }}" method="POST" id="pemakaianForm">
                        @csrf

                        <!-- Hidden Unit (otomatis untuk non-admin) -->
                        @if (!$isAdmin)
                            <input type="hidden" name="unit_id" id="unit_id" value="{{ old('unit_id', $defaultUnitId) }}" required>
                        @endif

                        <!-- Unit biMBA – HANYA UNTUK ADMIN -->
                        @if ($isAdmin)
                            <div class="mb-4">
                                <label class="form-label fw-bold">Unit biMBA <span class="text-danger">*</span></label>
                                <select name="unit_id" id="unit_id" class="form-select" required>
                                    <option value="">-- Pilih Unit --</option>
                                    @foreach($units as $unit)
                                        <option value="{{ $unit->id }}" {{ old('unit_id') == $unit->id ? 'selected' : '' }}>
                                            {{ $unit->no_cabang }} | {{ strtoupper($unit->biMBA_unit) }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('unit_id') <small class="text-danger">{{ $message }}</small> @enderror
                            </div>
                        @else
                            <!-- Non-admin: tampilkan unit sebagai info saja -->
                            @if ($defaultUnitId)
                                <div class="mb-4 text-center">
                                    <label class="form-label fw-bold d-block">Unit biMBA Anda</label>
                                    <div class="badge bg-primary fs-5 px-4 py-2">
                                        {{ $unitDisplay }}
                                    </div>
                                    <small class="text-muted d-block mt-2">Unit otomatis dari profile (tidak bisa diubah)</small>
                                </div>
                            @endif
                        @endif

                        <div class="row g-3">
                            <!-- Tanggal -->
                            <div class="col-md-6">
                                <label class="form-label fw-bold">Tanggal Pemakaian <span class="text-danger">*</span></label>
                                <input type="date" name="tanggal" id="tanggal" class="form-control" 
                                       value="{{ old('tanggal', now()->format('Y-m-d')) }}" required>
                                @error('tanggal') <small class="text-danger">{{ $message }}</small> @enderror
                                <small class="text-muted d-block mt-2">
                                    <strong>Minggu Otomatis:</strong> 
                                    <span id="info_minggu" class="text-primary fw-bold">
                                        Minggu {{ min(ceil(now()->day / 7), 5) }}
                                    </span>
                                </small>
                            </div>

                            <!-- Data Murid -->
                            <div class="col-md-3">
                                <label class="form-label fw-bold">NIM</label>
                                <input type="text" id="nim" class="form-control" readonly placeholder="Otomatis terisi">
                            </div>
                            <div class="col-md-5">
                                <label class="form-label fw-bold">Nama Murid <span class="text-danger">*</span></label>
                                <input type="text" id="nama_murid" class="form-control" readonly placeholder="Otomatis terisi">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label fw-bold">Gol</label>
                                <input type="text" id="gol" class="form-control" readonly placeholder="-">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label fw-bold">Guru</label>
                                <input type="text" id="guru" class="form-control" readonly placeholder="-">
                            </div>

                            <!-- Dropdown Murid (otomatis filter sesuai unit user) -->
                            <div class="col-md-12">
                                <label class="form-label fw-bold">Pilih Murid dari Buku Induk <span class="text-danger">*</span></label>
                                <select name="murid_id" id="murid_dropdown" class="form-select" required>
                                    <option value="">-- Pilih Murid --</option>
                                </select>
                                <small class="text-muted">
                                    Murid aktif dari unit Anda ({{ $unitDisplay ?? 'tidak terdeteksi' }}) akan muncul di sini
                                </small>
                            </div>

                            <!-- Produk -->
                            <div class="col-md-8">
                                <label class="form-label fw-bold">Produk <span class="text-danger">*</span></label>
                                <select name="label" id="produk_dropdown" class="form-select" required>
                                    <option value="">-- Pilih Produk --</option>
                                </select>
                                <small class="text-muted">Produk tersedia di unit ini</small>
                            </div>

                            <div class="col-md-4">
                                <label class="form-label fw-bold">Jumlah <span class="text-danger">*</span></label>
                                <input type="number" name="jumlah" id="jumlah" class="form-control text-end" min="1" value="1" required>
                            </div>

                            <!-- Harga & Total -->
                            <div class="col-md-4">
                                <label class="form-label">Harga Satuan (Rp)</label>
                                <div class="input-group">
                                    <span class="input-group-text">Rp</span>
                                    <input type="number" name="harga" id="harga" class="form-control text-end" readonly value="0">
                                </div>
                            </div>

                            <div class="col-md-4">
                                <label class="form-label">Total (Rp)</label>
                                <div class="input-group">
                                    <span class="input-group-text">Rp</span>
                                    <input type="text" id="total" class="form-control text-end fw-bold bg-light" readonly value="0">
                                </div>
                            </div>

                            <!-- Field Otomatis dari Produk -->
                            <div class="col-md-3">
                                <label class="form-label">Kategori</label>
                                <input type="text" id="kategori" class="form-control bg-light" readonly>
                                <input type="hidden" name="kategori" id="kategori_hidden">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Jenis</label>
                                <input type="text" id="jenis" class="form-control bg-light" readonly>
                                <input type="hidden" name="jenis" id="jenis_hidden">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Nama Produk</label>
                                <input type="text" id="nama_produk" class="form-control bg-light" readonly>
                                <input type="hidden" name="nama_produk" id="nama_produk_hidden">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Satuan</label>
                                <input type="text" id="satuan" class="form-control bg-light" readonly>
                                <input type="hidden" name="satuan" id="satuan_hidden">
                            </div>
                        </div>

                        <div class="mt-5 text-end">
                            <a href="{{ route('pemakaian_produk.index') }}" class="btn btn-secondary btn-lg px-4">
                                Kembali
                            </a>
                            <button type="submit" class="btn btn-success btn-lg px-5 ms-3" id="submitBtn"
                                    {{ !$defaultUnitId && !$isAdmin ? 'disabled' : '' }}>
                                Simpan Pemakaian
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
    const unitIdInput     = document.getElementById('unit_id'); // hidden atau dropdown
    const muridDropdown   = document.getElementById('murid_dropdown');
    const produkDropdown  = document.getElementById('produk_dropdown');
    const nimInput        = document.getElementById('nim');
    const namaMuridInput  = document.getElementById('nama_murid');
    const golInput        = document.getElementById('gol');
    const guruInput       = document.getElementById('guru');
    const inputTanggal    = document.getElementById('tanggal');
    const infoMinggu      = document.getElementById('info_minggu');

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

    function formatRupiah(angka) {
        return new Intl.NumberFormat('id-ID').format(angka);
    }

    function hitungTotal() {
        const jumlah = parseFloat(inputs.jumlah.value) || 0;
        const harga = parseFloat(inputs.harga.value) || 0;
        inputs.total.value = jumlah && harga ? formatRupiah(jumlah * harga) : '0';
    }

    function hitungMinggu() {
        if (!inputTanggal.value) return;
        const date = new Date(inputTanggal.value);
        const hari = date.getDate();
        const minggu = Math.min(Math.ceil(hari / 7), 5);
        infoMinggu.textContent = 'Minggu ' + minggu;
    }

    // Load murid & produk saat halaman load (otomatis pakai unit user jika non-admin)
    function loadMuridDanProduk() {
        const unitId = unitIdInput ? unitIdInput.value : '{{ $defaultUnitId ?? '' }}';

        if (!unitId) {
            muridDropdown.innerHTML = '<option value="">-- Unit tidak terdeteksi --</option>';
            produkDropdown.innerHTML = '<option value="">-- Unit tidak terdeteksi --</option>';
            return;
        }

        // Load murid
        fetch(`/pemakaian-produk/murid/${unitId}`)
            .then(response => response.json())
            .then(murids => {
                muridDropdown.innerHTML = '<option value="">-- Pilih Murid --</option>';
                murids.forEach(murid => {
                    const option = document.createElement('option');
                    option.value = murid.id;
                    option.textContent = `${murid.nim || 'Tanpa NIM'} | ${murid.nama}`;
                    option.dataset.nim = murid.nim || '';
                    option.dataset.nama = murid.nama;
                    option.dataset.gol = murid.gol || '';
                    option.dataset.guru = murid.guru || '';
                    muridDropdown.appendChild(option);
                });
            })
            .catch(() => {
                muridDropdown.innerHTML = '<option value="">Gagal memuat murid</option>';
            });

        // Load produk
        fetch(`/pemakaian-produk/produk/${unitId}`)
            .then(response => response.json())
            .then(produks => {
                produkDropdown.innerHTML = '<option value="">-- Pilih Produk --</option>';
                produks.forEach(p => {
                    const option = document.createElement('option');
                    option.value = p.label;
                    option.textContent = `${p.label} (${p.nama_produk || p.label})`;
                    option.dataset.kategori = p.kategori || '';
                    option.dataset.jenis = p.jenis || '';
                    option.dataset.namaProduk = p.nama_produk || p.label;
                    option.dataset.satuan = p.satuan || '';
                    option.dataset.harga = p.harga || 0;
                    produkDropdown.appendChild(option);
                });
            })
            .catch(() => {
                produkDropdown.innerHTML = '<option value="">Gagal memuat produk</option>';
            });
    }

    // Saat pilih murid → isi field
    muridDropdown.addEventListener('change', function () {
        const option = this.options[this.selectedIndex];
        if (option && option.value) {
            nimInput.value = option.dataset.nim;
            namaMuridInput.value = option.dataset.nama;
            golInput.value = option.dataset.gol || '-';
            guruInput.value = option.dataset.guru || '-';
        } else {
            nimInput.value = '';
            namaMuridInput.value = '';
            golInput.value = '';
            guruInput.value = '';
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
            Object.values(inputs).forEach(input => {
                if (input && input.type !== 'hidden') input.value = '';
            });
            inputs.harga.value = 0;
        }
        hitungTotal();
    });

    // Event listener
    inputTanggal.addEventListener('change', hitungMinggu);
    inputs.jumlah.addEventListener('input', hitungTotal);

    // Inisialisasi awal
    hitungMinggu();
    hitungTotal();

    // Load murid & produk otomatis saat halaman dibuka (khusus non-admin pakai unit profile)
    loadMuridDanProduk();
});
</script>
@endsection