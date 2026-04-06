@extends('layouts.app')

@section('title', 'Pembayaran Tunjangan')

@section('content')
<div class="card card-body shadow-sm">

    <div class="mx-auto" style="max-width: 1400px;">

        <h2 class="text-center mb-4 px-3 px-md-0">Daftar Pembayaran Tunjangan</h2>

        @if(session('success'))
            <div class="alert alert-success alert-dismissible fade show mx-3 mx-md-0" role="alert">
                {{ session('success') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        @endif

        {{-- Form Filter --}}
        <div class="bg-white border-bottom shadow-sm mb-3 card card-body">
            <div class="p-3 px-3 px-md-0 card-body">
                <form action="{{ route('pembayaran.index') }}" method="GET" class="row g-2">
                    <div class="col-12 col-md-5">
                        <label class="form-label fw-medium small">Nama Relawan</label>
                        <select name="nama" id="nama" class="form-select form-select-sm" onchange="this.form.submit()">
                            <option value="">-- Semua Relawan --</option>
                            @foreach($allNames as $n)
                                <option value="{{ $n }}" {{ ($nama ?? '') == $n ? 'selected' : '' }}>{{ $n }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-12 col-md-5">
                        <label class="form-label fw-medium small">Bulan</label>
                        <select name="bulan" id="bulan" class="form-select form-select-sm" onchange="this.form.submit()">
                            <option value="">-- Semua Bulan --</option>
                            @foreach($allMonths as $b)
                                <option value="{{ $b }}" {{ ($bulan ?? '') == $b ? 'selected' : '' }}>
                                    {{ \Carbon\Carbon::parse($b)->translatedFormat('F Y') }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-12 col-md-2 d-grid mt-3 mt-md-auto">
                        <button type="button" class="btn btn-outline-secondary btn-sm" 
                                onclick="window.location.href='{{ route('pembayaran.index') }}'">
                            Reset
                        </button>
                    </div>
                </form>
            </div>
        </div>

        {{-- Desktop: Tabel Normal --}}
        <div class="d-none d-md-block table-responsive px-3 px-md-0">
            <table class="table table-bordered table-hover align-middle card-body">
                <thead class="table-light text-center card-body">
                    <tr>
                        <th>NO</th>
                        <th>NIK</th>
                        <th>NAMA</th>

                        {{-- KOLOM UNIT biMBA & NO. CABANG – HANYA UNTUK ADMIN --}}
                        @if (auth()->check() && (auth()->user()->is_admin ?? false))
                            <th>UNIT biMBA</th>
                            <th>NO. CABANG</th>
                        @endif

                        <th>JABATAN</th>
                        <th>STATUS</th>
                        <th>DEPT</th>
                        <th>MASA KERJA</th>
                        <th>NO REK</th>
                        <th>BANK</th>
                        <th>ATAS NAMA</th>
                        <th>PENDAPATAN</th>
                        <th>SAKIT</th>
                        <th>IZIN</th>
                        <th>ALPA</th>
                        <th>TIDAK AKTIF</th>
                        <th>KELEBIHAN</th>
                        <th>LAIN-LAIN</th>
                        <th>POTONGAN</th>
                        <th>DIBAYARKAN</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($pembayaranTunjangans as $key => $p)
                        <tr>
                            <td class="text-center">{{ $key + 1 }}</td>
                            <td class="text-center">{{ $p->nik ?? '-' }}</td>
                            <td class="fw-medium">{{ $p->nama }}</td>

                            {{-- Hanya admin yang melihat kolom ini --}}
                            @if (auth()->check() && (auth()->user()->is_admin ?? false))
                                <td>{{ $p->bimba_unit ?? '-' }}</td>
                                <td>{{ $p->no_cabang ?? '-' }}</td>
                            @endif

                            <td>{{ $p->jabatan ?? '-' }}</td>
                            <td>{{ $p->status ?? '-' }}</td>
                            <td>{{ $p->departemen ?? '-' }}</td>
                            <td class="text-center">{{ filled($p->masa_kerja) ? $p->masa_kerja : '0 bln' }}</td>
                            <td>{{ $p->no_rekening ?? '-' }}</td>
                            <td>{{ $p->bank ?? '-' }}</td>
                            <td>{{ $p->atas_nama ?? '-' }}</td>
                            <td class="text-end fw-bold">{{ number_format($p->pendapatan, 0, ',', '.') }}</td>
                            <td class="text-end">{{ number_format($p->sakit ?? 0, 0, ',', '.') }}</td>
                            <td class="text-end">{{ number_format($p->izin ?? 0, 0, ',', '.') }}</td>
                            <td class="text-end">{{ number_format($p->alpa ?? 0, 0, ',', '.') }}</td>
                            <td class="text-end">{{ number_format($p->tidak_aktif ?? 0, 0, ',', '.') }}</td>
                            <td class="text-end">{{ number_format($p->kelebihan ?? 0, 0, ',', '.') }}</td>
                            <td class="text-end">{{ number_format($p->lain_lain ?? 0, 0, ',', '.') }}</td>
                            <td class="text-end fw-bold text-danger">
                                {{ number_format(
                                    ($p->sakit ?? 0) + ($p->izin ?? 0) + ($p->alpa ?? 0) +
                                    ($p->tidak_aktif ?? 0) + ($p->kelebihan ?? 0) + ($p->lain_lain ?? 0),
                                    0, ',', '.'
                                ) }}
                            </td>
                            <td class="text-end fw-bold text-success">{{ number_format($p->dibayarkan, 0, ',', '.') }}</td>
                        </tr>
                    @empty
                        <tr>
                            {{-- Jumlah kolom disesuaikan dinamis --}}
                            <td colspan="{{ auth()->check() && (auth()->user()->is_admin ?? false) ? '21' : '19' }}" class="text-center py-5 text-muted">
                                Belum ada data
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Mobile: Card Stacked --}}
        <div class="d-block d-md-none">
            @forelse($pembayaranTunjangans as $key => $p)
                <div class="bg-white border-bottom shadow-sm mb-3">
                    <div class="p-3">
                        <!-- Ringkasan di atas -->
                        <div class="bg-light rounded-3 p-3 mb-3 text-center">
                            <div class="small text-muted">Pendapatan</div>
                            <div class="fs-5 fw-bold">{{ number_format($p->pendapatan, 0, ',', '.') }}</div>
                            
                            <div class="small text-danger mt-2">Total Potongan</div>
                            <div class="fw-bold text-danger">
                                {{ number_format(
                                    ($p->sakit ?? 0) + ($p->izin ?? 0) + ($p->alpa ?? 0) +
                                    ($p->tidak_aktif ?? 0) + ($p->kelebihan ?? 0) + ($p->lain_lain ?? 0),
                                    0, ',', '.'
                                ) }}
                            </div>
                            
                            <div class="small text-success mt-2">Dibayarkan</div>
                            <div class="fs-4 fw-bold text-success">{{ number_format($p->dibayarkan, 0, ',', '.') }}</div>
                        </div>

                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <h6 class="fw-bold mb-0">{{ $p->nama }}</h6>
                            <small class="text-muted">No. {{ $key + 1 }}</small>
                        </div>
                        
                        <div class="small text-muted mb-3">
                            {{ $p->nik ?? '-' }} • {{ $p->jabatan ?? '-' }}
                            @if (auth()->check() && (auth()->user()->is_admin ?? false))
                                • {{ $p->bimba_unit ?? '-' }} ({{ $p->no_cabang ?? '-' }})
                            @endif
                        </div>

                        <div class="row g-2 small text-muted">
                            <div class="col-6"><strong>Status:</strong> {{ $p->status ?? '-' }}</div>
                            <div class="col-6"><strong>Dept:</strong> {{ $p->departemen ?? '-' }}</div>
                            <div class="col-6"><strong>Masa Kerja:</strong> {{ filled($p->masa_kerja) ? $p->masa_kerja : '0 bln' }}</div>
                            <div class="col-6"><strong>Bank:</strong> {{ $p->bank ?? '-' }}</div>
                            <div class="col-12"><strong>Rekening:</strong> {{ $p->no_rekening ?? '-' }} a.n. {{ $p->atas_nama ?? '-' }}</div>
                        </div>
                    </div>
                </div>
            @empty
                <div class="text-center py-5 text-muted px-3">
                    <em>Belum ada data pembayaran tunjangan</em>
                </div>
            @endforelse
        </div>

    </div>
</div>
@endsection