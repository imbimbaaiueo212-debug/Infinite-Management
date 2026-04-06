@extends('layouts.app')

@section('content')
<div class="card card-body">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
        <h1 class="fw-bold mb-0">Order Modul</h1>
        <div class="d-flex gap-2 flex-wrap">
            <a href="{{ route('order_modul.create') }}" class="btn btn-primary">
                <i class="fas fa-plus me-1"></i> Tambah Order
            </a>
            <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#importModal">
                <i class="fas fa-file-excel me-1"></i> Import Excel
            </button>
        </div>
    </div>

    {{-- Alert --}}
    @if (session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @elseif(session('error'))
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            {{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    {{-- Info Status Stok --}}
    <div class="alert alert-info small mb-4">
        <i class="fas fa-info-circle fa-2x me-3 float-start"></i>
        <strong>Status Stok (Real-Time):</strong><br>
        <i class="fas fa-caret-up text-success"></i> = Stok Aman<br>
        <i class="fas fa-caret-down text-danger"></i> = Stok Kurang<br>
        Diambil dari perhitungan stok bulan 
        <strong>{{ \Carbon\Carbon::createFromFormat('Y-m', $periodeRekap)->translatedFormat('F Y') }}</strong>
    </div>

    {{-- RINGKASAN TAHUNAN --}}
    <div class="card shadow-lg mb-4 border-0">
        <div class="card-header bg-gradient-to-r from-indigo-600 to-purple-700 text-white py-4">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
                <h4 class="mb-0 fw-bold text-black">Ringkasan Tahunan {{ $tahun ?? date('Y') }}</h4>
                
                <form method="GET" action="{{ route('order_modul.index') }}" class="d-inline">
                    <select name="tahun" class="form-select form-select-sm w-auto d-inline-block" onchange="this.form.submit()">
                        @for($y = date('Y'); $y >= date('Y')-5; $y--)
                            <option value="{{ $y }}" {{ ($tahun ?? date('Y')) == $y ? 'selected' : '' }}>
                                Tahun {{ $y }}
                            </option>
                        @endfor
                    </select>
                </form>
            </div>
        </div>
        <div class="card-body py-5">
            <div class="row text-center">
                <div class="col-md-6 mb-4 mb-md-0">
                    <div class="p-4 bg-gradient-to-br from-green-100 to-emerald-100 rounded-3 shadow">
                        <h3 class="fw-black text-success mb-2">
                            Rp {{ number_format($grandTotalTahun ?? 0, 0, ',', '.') }}
                        </h3>
                        <p class="mb-0 text-muted fw-semibold">Total Pendapatan Tahun Ini</p>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="p-4 bg-gradient-to-br from-blue-100 to-indigo-100 rounded-3 shadow">
                        <h3 class="fw-black text-primary mb-2">
                            {{ $totalOrderTahun ?? 0 }}
                        </h3>
                        <p class="mb-0 text-muted fw-semibold">Total Order Modul Tahun Ini</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- RINCIAN PER MINGGU --}}
    <div class="card shadow-sm mb-4">
        <div class="card-header bg-primary text-white">
            <h5 class="mb-0 fw-bold">Rincian Per Minggu (Tahun {{ $tahun ?? date('Y') }})</h5>
        </div>
        <div class="card-body">
            <div class="row text-center g-3">
                @php $colors = ['#e3f2fd', '#fce4ec', '#e8f5e9', '#fff3e0', '#f3e5f5']; @endphp
                @for($i = 1; $i <= 5; $i++)
                    <div class="col-6 col-md">
                        <div class="p-4 rounded-3 shadow-sm" style="background-color: {{ $colors[$i - 1] }}">
                            <strong class="d-block fs-5 mb-2">Minggu ke-{{ $i }}</strong>
                            <div class="fs-4 fw-bold text-primary mb-1">
                                Rp {{ number_format($totalHrgPerMinggu['hrg'.$i] ?? 0, 0, ',', '.') }}
                            </div>
                            <small class="text-muted">
                                {{ $totalOrderPerMinggu['minggu'.$i] ?? 0 }} order
                            </small>
                        </div>
                    </div>
                @endfor
            </div>
        </div>
    </div>

    {{-- Form Pencarian + Filter --}}
    <form method="GET" action="{{ route('order_modul.index') }}" class="card shadow-sm mb-4" id="filterForm">
        <div class="card-body">
            <div class="row g-3 align-items-end">

                {{-- Filter Unit biMBA – hanya untuk admin --}}
                <div class="col-12 col-md-4">
                    <label class="form-label fw-bold">Unit biMBA</label>

                    @if (auth()->check() && (auth()->user()->is_admin ?? false))
                        <select name="unit_id" class="form-select">
                            <option value="">-- Semua Unit --</option>
                            @foreach($units as $unit)
                                <option value="{{ $unit->id }}" {{ request('unit_id') == $unit->id ? 'selected' : '' }}>
                                    {{ $unit->no_cabang }} | {{ strtoupper($unit->biMBA_unit) }}
                                </option>
                            @endforeach
                        </select>
                    @else
                        @php
                            $userUnit = auth()->user()->bimba_unit ?? null;
                            $unitDisplay = 'Unit belum diatur';
                            $defaultUnitId = '';

                            if ($userUnit) {
                                $unit = \App\Models\Unit::where('biMBA_unit', $userUnit)->first();
                                if ($unit) {
                                    $unitDisplay = $unit->no_cabang . ' | ' . strtoupper($unit->biMBA_unit);
                                    $defaultUnitId = $unit->id;
                                }
                            }
                        @endphp

                        <input type="hidden" name="unit_id" value="{{ old('unit_id', $defaultUnitId) }}">

                        <input type="text" class="form-control bg-light text-center fw-bold" 
                               value="{{ $unitDisplay }}" readonly>

                        @if (!$defaultUnitId)
                            <small class="text-danger d-block mt-1">
                                Unit Anda belum diatur. Hubungi admin.
                            </small>
                        @endif
                    @endif
                </div>

                {{-- Cari Kode --}}
                <div class="col-12 col-md-3">
                    <label class="form-label fw-bold">Kode Modul</label>
                    <input type="text" name="kode" class="form-control" placeholder="Cari kode modul..." value="{{ $kode ?? '' }}">
                </div>

                {{-- Minggu --}}
                <div class="col-12 col-md-2">
                    <label class="form-label fw-bold">Minggu</label>
                    <select name="minggu" class="form-select">
                        <option value="">-- Semua --</option>
                        @for($i = 1; $i <= 5; $i++)
                            <option value="{{ $i }}" {{ ($minggu ?? '') == $i ? 'selected' : '' }}>Minggu {{ $i }}</option>
                        @endfor
                    </select>
                </div>

                {{-- Periode Rekap Stok --}}
                <div class="col-12 col-md-3">
                    <label class="form-label fw-bold">Periode Rekap Stok</label>
                    <input type="month" name="periode_rekap" class="form-control" value="{{ $periodeRekap }}">
                </div>

                {{-- Tombol Aksi --}}
                <div class="col-12 col-md-12 text-md-end mt-3 mt-md-0">
                    <button type="submit" class="btn btn-primary me-2">
                        <i class="fas fa-search me-1"></i> Filter
                    </button>
                    <a href="{{ route('order_modul.index') }}" class="btn btn-secondary">
                        <i class="fas fa-redo me-1"></i> Reset
                    </a>
                </div>
            </div>
        </div>
    </form>

    {{-- Tabel Order Modul --}}
    <div class="card shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-bordered table-striped text-center align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th rowspan="2" class="align-middle bg-info text-dark">UNIT & TANGGAL</th>
                            @for ($i = 1; $i <= 5; $i++)
                                <th colspan="4" class="bg-secondary text-dark">MINGGU KE-{{ $i }}</th>
                            @endfor
                            <th rowspan="2" class="bg-dark text-dark align-middle">AKSI</th>
                        </tr>
                        <tr>
                            @for ($i = 1; $i <= 5; $i++)
                                <th>KODE</th>
                                <th>JML</th>
                                <th>HARGA</th>
                                <th>STATUS STOK</th>
                            @endfor
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($orders as $order)
                            @php 
                                $colors = ['#e3f2fd', '#fce4ec', '#e8f5e9', '#fff3e0', '#f3e5f5']; 
                            @endphp
                            <tr>
                                <td class="align-middle text-start bg-light">
                                    <strong>
                                        @if($order->unit)
                                            {{ $order->unit->no_cabang }}<br>
                                            <small class="text-muted">{{ strtoupper($order->unit->biMBA_unit) }}</small>
                                        @else
                                            <span class="text-danger">Unit tidak diketahui</span>
                                        @endif
                                    </strong>
                                    <hr class="my-2">
                                    <small class="text-muted d-block">
                                        {{ \Carbon\Carbon::parse($order->tanggal_order)->format('d-m-Y') }}
                                    </small>
                                </td>

                                @for ($i = 1; $i <= 5; $i++)
                                    @php
                                        $kode = $order->{'kode' . $i} ?? null;
                                        $stsStok = null;

                                        if ($kode && $order->unit_id) {
                                            $rekap = \App\Models\DataProduk::where('periode', $periodeRekap)
                                                ->where('unit_id', $order->unit_id)
                                                ->where('label', $kode)
                                                ->select('sld_awal', 'terima', 'pakai', 'min_stok')
                                                ->first();

                                            if ($rekap) {
                                                $sld_akhir = $rekap->sld_awal + $rekap->terima - $rekap->pakai;
                                                $stsStok = $sld_akhir >= $rekap->min_stok ? 1 : 0;
                                            }
                                        }
                                    @endphp
                                    <td style="background-color: {{ $colors[$i - 1] }}">{{ $kode ?? '-' }}</td>
                                    <td style="background-color: {{ $colors[$i - 1] }}">{{ $order->{'jml' . $i} ?? 0 }}</td>
                                    <td style="background-color: {{ $colors[$i - 1] }}">{{ number_format($order->{'hrg' . $i} ?? 0, 0, ',', '.') }}</td>
                                    <td style="background-color: {{ $colors[$i - 1] }}; font-size: 2.5rem;" class="py-4">
                                        @if($stsStok === 1)
                                            <i class="fas fa-caret-up text-success" title="Stok Aman"></i>
                                        @elseif($stsStok === 0)
                                            <i class="fas fa-caret-down text-danger" title="Stok Kurang"></i>
                                        @else
                                            <span class="text-muted fs-4">-</span>
                                        @endif
                                    </td>
                                @endfor

                                <td class="align-middle py-2">
                                    <div class="d-flex flex-column flex-md-row gap-1 justify-content-center">
                                        <a href="{{ route('order_modul.edit', $order->id) }}" class="btn btn-sm btn-warning">
                                            <i class="fas fa-edit"></i> Edit
                                        </a>

                                        @if (auth()->user()?->role === 'admin')
                                            <form action="{{ route('order_modul.destroy', $order->id) }}" method="POST" class="d-inline" onsubmit="return confirm('Yakin hapus?')">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-sm btn-danger">
                                                    <i class="fas fa-trash"></i> Hapus
                                                </button>
                                            </form>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="22" class="text-center py-5 text-muted">
                                    <i class="fas fa-inbox fa-3x mb-3 opacity-50"></i><br>
                                    Belum ada data order modul untuk unit dan filter yang dipilih.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- Modal Import --}}
    <div class="modal fade" id="importModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content shadow-lg">
                <form action="{{ route('order_modul.import') }}" method="POST" enctype="multipart/form-data">
                    @csrf
                    <div class="modal-header bg-success text-white">
                        <h5 class="modal-title">Import Data Order Modul</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="alert alert-info small">
                            <strong>Format Excel:</strong><br>
                            Kolom: tanggal_order, unit_id (opsional), kode1, jml1, ..., kode5, jml5
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold">File Excel</label>
                            <input type="file" name="file" class="form-control" required accept=".xlsx,.xls,.csv">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-success">Import Data</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- SCRIPT AUTO FILTER -->
    <script>
    document.addEventListener('DOMContentLoaded', function () {
        const form = document.getElementById('filterForm');
        
        if (!form) return;

        const unitSelect   = form.querySelector('select[name="unit_id"]');
        const kodeInput    = form.querySelector('input[name="kode"]');
        const mingguSelect = form.querySelector('select[name="minggu"]');
        const periodeInput = form.querySelector('input[name="periode_rekap"]');

        function submitForm() {
            form.submit();
        }

        // Hanya tambahkan event jika elemen ada (aman untuk non-admin)
        if (unitSelect)   unitSelect.addEventListener('change', submitForm);
        if (mingguSelect) mingguSelect.addEventListener('change', submitForm);
        if (periodeInput) periodeInput.addEventListener('change', submitForm);

        if (kodeInput) {
            let timeout;
            kodeInput.addEventListener('input', function () {
                clearTimeout(timeout);
                timeout = setTimeout(submitForm, 800);
            });
        }
    });
    </script>
</div>
@endsection