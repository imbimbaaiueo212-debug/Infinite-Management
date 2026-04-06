@extends('layouts.app')

@section('title', 'Daftar Penerimaan Produk')

@section('content')
<div class="card card-body">
    <h1 class="mb-4">Daftar Penerimaan Produk dan Atribut</h1>

    <a href="{{ route('penerimaan_produk.create_multi') }}" class="btn btn-primary mb-3 shadow-sm">
        <i class="fas fa-plus-circle me-1"></i> Tambah Multi Item (Satu Faktur)
    </a>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    {{-- CARD FILTER --}}
    <div class="card card-body p-4 mb-4 shadow-sm">
        <h6 class="card-title mb-3">Filter Data (Otomatis)</h6>
        <div class="row g-3 align-items-end">
            {{-- Unit biMBA – HANYA UNTUK ADMIN --}}
            @if (auth()->check() && (auth()->user()->is_admin ?? false))
                <div class="col-md-3 col-12">
                    <label for="filter_unit" class="form-label small text-muted">Unit biMBA</label>
                    <select id="filter_unit" class="form-select">
                        <option value="">-- Semua Unit --</option>
                        @foreach($units as $unit)
                            <option value="{{ $unit->id }}">
                                {{ $unit->no_cabang }} | {{ strtoupper($unit->biMBA_unit) }}
                            </option>
                        @endforeach
                    </select>
                </div>
            @endif

            {{-- Label Produk --}}
            <div class="col-md-3 col-12">
                <label for="filter_label" class="form-label small text-muted">Label Produk</label>
                <select id="filter_label" class="form-select">
                    <option value="">-- Semua Label --</option>
                    @foreach($labels as $lbl)
                        <option value="{{ $lbl }}">{{ $lbl }}</option>
                    @endforeach
                </select>
            </div>

            {{-- Tanggal Awal --}}
            <div class="col-md-3 col-12">
                <label for="filter_tanggal_awal" class="form-label small text-muted">Tanggal Awal</label>
                <input type="date" id="filter_tanggal_awal" class="form-control">
            </div>

            {{-- Tanggal Akhir --}}
            <div class="col-md-3 col-12">
                <label for="filter_tanggal_akhir" class="form-label small text-muted">Tanggal Akhir</label>
                <input type="date" id="filter_tanggal_akhir" class="form-control">
            </div>
        </div>

        <div class="mt-3 text-end">
            <button id="reset-filter" class="btn btn-secondary btn-sm">Reset Filter</button>
        </div>
    </div>

    {{-- TABEL --}}
    <div class="table-responsive">
        <table class="table table-bordered table-striped table-hover align-middle text-center" id="tabel-penerimaan" style="min-width: 1500px;">
            <thead class="table-light">
                <tr>
                    <th>NO</th>
                    <th>FAKTUR</th>

                    {{-- KOLOM UNIT – HANYA UNTUK ADMIN --}}
                    @if (auth()->check() && (auth()->user()->is_admin ?? false))
                        <th>UNIT</th>
                    @endif

                    <th>TANGGAL</th>
                    <th>MINGGU</th>
                    <th>LABEL</th>
                    <th>JUMLAH PENERIMAAN</th>
                    <th>JUMLAH ORDERAN</th> <!-- KOLOM BARU YANG DIMINTA -->
                    <th>KATEGORI</th>
                    <th>JENIS</th>
                    <th>NAMA PRODUK</th>
                    <th>SATUAN</th>
                    <th>HARGA (Rp)</th>
                    <th>STATUS</th>
                    <th>ISI</th>
                    <th>TOTAL (Rp)</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody>
                @forelse($items as $item)
                <tr data-unit="{{ $item->unit_id ?? '' }}"
                    data-label="{{ $item->label ?? '' }}"
                    data-tanggal="{{ \Carbon\Carbon::parse($item->tanggal)->format('Y-m-d') }}">
                    <td>{{ $loop->iteration }}</td>
                    <td>{{ $item->faktur }}</td>

                    {{-- Hanya admin yang melihat kolom ini --}}
                    @if (auth()->check() && (auth()->user()->is_admin ?? false))
                        <td>
                            @if($item->unit)
                                <strong>{{ $item->unit->no_cabang }}</strong><br>
                                <small>{{ strtoupper($item->unit->biMBA_unit) }}</small>
                            @else
                                <span class="text-muted">-</span>
                            @endif
                        </td>
                    @endif

                    <td>{{ \Carbon\Carbon::parse($item->tanggal)->format('d-m-Y') }}</td>
                    <td>{{ $item->minggu }}</td>
                    <td>{{ $item->label ?? '-' }}</td>
                    <td class="text-end">{{ number_format($item->jumlah ?? 0, 0, ',', '.') }}</td>
                    <td class="text-end fw-bold text-info">
                        {{ number_format($item->jumlah_order ?? 0, 0, ',', '.') }} <!-- Nilai dari controller -->
                    </td>
                    <td>{{ $item->kategori ?? '-' }}</td>
                    <td>{{ $item->jenis ?? '-' }}</td>
                    <td class="text-start">{{ $item->nama_produk }}</td>
                    <td>{{ $item->satuan ?? '-' }}</td>
                    <td class="text-end">{{ number_format($item->harga ?? 0, 0, ',', '.') }}</td>
                    <td>{{ $item->status ?? '-' }}</td>
                    <td>{{ $item->isi ?? '-' }}</td>
                    <td class="text-end fw-bold">{{ number_format($item->total ?? 0, 0, ',', '.') }}</td>
                    <td>
                        <a href="{{ route('penerimaan_produk.edit', $item->id) }}" class="btn btn-sm btn-warning me-1">
                            <i class="fas fa-edit"></i>
                        </a>

                        @if (strtolower(trim(auth()->user()?->role ?? '')) === 'admin')
                            <form action="{{ route('penerimaan_produk.destroy', $item->id) }}" method="POST" class="d-inline">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-sm btn-danger" 
                                        onclick="return confirm('Yakin hapus faktur {{ $item->faktur }}?')">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </form>
                        @endif
                    </td>
                </tr>
                @empty
                    <tr>
                        <td colspan="{{ auth()->check() && (auth()->user()->is_admin ?? false) ? '16' : '15' }}" class="text-center py-8 text-muted">Tidak ada data.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="d-flex justify-content-center mt-4">
        {{ $items->links() }}
    </div>
</div>

<script>
// Script filter tetap sama seperti asli kamu
document.addEventListener('DOMContentLoaded', function () {
    const rows          = document.querySelectorAll('#tabel-penerimaan tbody tr');
    const filterUnit    = document.getElementById('filter_unit');
    const filterLabel   = document.getElementById('filter_label');
    const filterTglAwal = document.getElementById('filter_tanggal_awal');
    const filterTglAkhir = document.getElementById('filter_tanggal_akhir');
    const resetBtn      = document.getElementById('reset-filter');

    function applyFilter() {
        const selectedUnit  = filterUnit ? filterUnit.value : '';
        const selectedLabel = filterLabel.value.trim().toUpperCase();
        const tglAwal       = filterTglAwal.value;
        const tglAkhir      = filterTglAkhir.value;

        rows.forEach(row => {
            const unit   = row.dataset.unit;
            const label  = (row.dataset.label || '').toUpperCase();
            const tanggal = row.dataset.tanggal;

            let visible = true;

            if (selectedUnit && unit !== selectedUnit) visible = false;
            if (selectedLabel && !label.includes(selectedLabel)) visible = false;
            if (tglAwal && tanggal < tglAwal) visible = false;
            if (tglAkhir && tanggal > tglAkhir) visible = false;

            row.style.display = visible ? '' : 'none';
        });
    }

    if (filterUnit) filterUnit.addEventListener('change', applyFilter);
    filterLabel.addEventListener('change', applyFilter);
    filterTglAwal.addEventListener('change', applyFilter);
    filterTglAkhir.addEventListener('change', applyFilter);

    resetBtn.addEventListener('click', () => {
        if (filterUnit) filterUnit.value = '';
        filterLabel.value = '';
        filterTglAwal.value = '';
        filterTglAkhir.value = '';
        applyFilter();
    });

    applyFilter();
});
</script>
@endsection