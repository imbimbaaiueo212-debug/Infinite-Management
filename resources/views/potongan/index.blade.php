@extends('layouts.app')

@section('title', 'Potongan Tunjangan')

@section('content')
<div class="card card-body shadow-sm">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="mb-0">Data Potongan Tunjangan</h2>
        <div>
            <a href="{{ route('potongan.create') }}" class="btn btn-primary">
                <i class="bi bi-plus-lg me-2"></i>Tambah Data
            </a>
        </div>
    </div>

    <!-- Form Filter Bulan -->
    <div class="card border-0 shadow-sm rounded-4 mb-4">
        <div class="card-body rounded-2">
            <form method="GET" action="{{ route('potongan.index') }}" class="row g-3 align-items-end">
                <!-- Bulan Mulai -->
                <div class="col-lg-3 col-md-4">
                    <label class="form-label fw-semibold small">Bulan Mulai</label>
                    <input type="month" name="month_from" class="form-control"
                           value="{{ old('month_from', $filter_month_from ?? request('month_from')) }}">
                </div>

                <!-- Bulan Selesai -->
                <div class="col-lg-3 col-md-4">
                    <label class="form-label fw-semibold small">Bulan Selesai</label>
                    <input type="month" name="month_to" class="form-control"
                           value="{{ old('month_to', $filter_month_to ?? request('month_to')) }}">
                </div>

                <!-- Tombol -->
                <div class="col-lg-3 col-md-4 d-flex gap-2">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="fas fa-filter me-1"></i> Filter
                    </button>
                    <a href="{{ route('potongan.index') }}" class="btn btn-outline-secondary w-100">
                        Reset
                    </a>
                </div>
            </form>
        </div>
    </div>

    <!-- Tampilan Filter Aktif -->
    @if(!empty($filter_month_from) || !empty($filter_month_to))
        @php
            function _monthLabel($m) {
                if (!$m) return '-';
                try {
                    return \Carbon\Carbon::createFromFormat('Y-m', $m)->translatedFormat('M Y');
                } catch (\Throwable $e) {
                    try {
                        return \Carbon\Carbon::parse($m)->translatedFormat('M Y');
                    } catch (\Throwable $e2) {
                        return $m;
                    }
                }
            }
        @endphp
        <div class="mb-3">
            <strong>Filter aktif:</strong>
            Bulan {{ _monthLabel($filter_month_from) }} s/d {{ _monthLabel($filter_month_to) }}
        </div>
    @endif

    <!-- Notifikasi -->
    @if (session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <!-- Tabel Data Potongan Tunjangan -->
    <div class="table-responsive">
        <table class="table table-sm table-bordered text-center align-middle">
            <thead class="table-light">
                <tr>
                    <th>NO</th>
                    <th>NIK</th>
                    <th>NAMA</th>
                    <th>JABATAN</th>
                    <th>STATUS</th>
                    <th>DEPARTEMEN</th>

                    {{-- KOLOM UNIT biMBA & NO. CABANG – HANYA UNTUK ADMIN --}}
                    @if (auth()->check() && (auth()->user()->is_admin ?? false))
                        <th>UNIT biMBA</th>
                        <th>NO. CABANG</th>
                    @endif

                    <th>SAKIT</th>
                    <th>IZIN</th>
                    <th>ALPA</th>
                    <th>TIDAK AKTIF</th>
                    <th>KELEBIHAN (Rp)</th>
                    <th>ASAL KELEBIHAN</th>
                    <th>LAIN-LAIN</th>
                    <th>CASH ADVANCE (Rp)</th>
                    <th>TOTAL</th>
                    <th>AKSI</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($potonganTunjangans as $p)
                    <tr>
                        <td>{{ $loop->iteration }}</td>
                        <td>{{ $p->nik ?? '-' }}</td>
                        <td class="text-start">{{ $p->nama }}</td>
                        <td>{{ $p->jabatan }}</td>
                        <td>{{ $p->status }}</td>
                        <td>{{ $p->departemen }}</td>

                        {{-- Hanya admin yang melihat kolom ini --}}
                        @if (auth()->check() && (auth()->user()->is_admin ?? false))
                            <td>{{ $p->bimba_unit ?? '-' }}</td>
                            <td>{{ $p->no_cabang ?? '-' }}</td>
                        @endif

                        <td>{{ number_format($p->sakit ?? 0, 0, ',', '.') }}</td>
                        <td>{{ number_format($p->izin ?? 0, 0, ',', '.') }}</td>
                        <td>{{ number_format($p->alpa ?? 0, 0, ',', '.') }}</td>
                        <td>{{ number_format($p->tidak_aktif ?? 0, 0, ',', '.') }}</td>

                        <td>{{ number_format($p->kelebihan_nominal ?? $p->kelebihan ?? 0, 0, ',', '.') }}</td>

                        <td>
                            @if(!empty($p->kelebihan_bulan) && preg_match('/^\d{4}-\d{2}$/', $p->kelebihan_bulan))
                                {{ \Carbon\Carbon::parse($p->kelebihan_bulan)->translatedFormat('M Y') }}
                            @else
                                {{ $p->kelebihan_bulan ?? '-' }}
                            @endif
                        </td>

                        <td>{{ number_format($p->lain_lain ?? 0, 0, ',', '.') }}</td>
                        <td>{{ number_format($p->cash_advance_nominal ?? 0, 0, ',', '.') }}</td>

                        <!-- TOTAL (sudah termasuk cash_advance) -->
                        <td>
                            <strong>
                                {{ number_format(
                                    ($p->sakit ?? 0) +
                                    ($p->izin ?? 0) +
                                    ($p->alpa ?? 0) +
                                    ($p->tidak_aktif ?? 0) +
                                    ($p->kelebihan_nominal ?? $p->kelebihan ?? 0) +
                                    ($p->lain_lain ?? 0) +
                                    ($p->cash_advance_nominal ?? 0),
                                    0,
                                    ',',
                                    '.'
                                ) }}
                            </strong>
                        </td>

                        <td class="text-nowrap">
                            <a href="{{ route('potongan.show', $p->id) }}" class="btn btn-info btn-sm">Detail</a>
                            <a href="{{ route('potongan.edit', $p->id) }}" class="btn btn-warning btn-sm">Edit</a>

                            @if (auth()->user()?->role === 'admin')
                                <form action="{{ route('potongan.destroy', $p->id) }}" method="POST" style="display:inline;">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" onclick="return confirm('Yakin hapus data?')"
                                            class="btn btn-danger btn-sm">Hapus</button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        {{-- Jumlah kolom disesuaikan dinamis --}}
                        <td colspan="{{ auth()->check() && (auth()->user()->is_admin ?? false) ? '18' : '16' }}" class="text-center py-4">
                            Belum ada data
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <style>
        .table td, .table th {
            vertical-align: middle;
        }
    </style>
</div>
@endsection