@extends('layouts.app')

@section('title', 'Rekap Progresif')

@section('content')
<div class="container-fluid py-4 card card-body">

    <h2 class="mb-4">Rekap Progresif</h2>

    <div class="d-flex justify-content-between align-items-center flex-wrap mb-4">

        {{-- Tombol Tambah hanya Admin --}}
        @if($isAdmin ?? false)
            <a href="{{ route('rekap-progresif.create') }}" class="btn btn-primary mb-3">
                Tambah Data Progresif
            </a>
        @endif

        <form method="GET" class="row g-2 align-items-end">
            <div class="col-auto">
                <label class="form-label small text-muted">Filter Periode</label>
                <input type="month" name="periode" class="form-control" value="{{ old('periode', $periode ?? '') }}">
            </div>

            <div class="col-auto">
                <button type="submit" class="btn btn-info text-white">
                    <i class="fas fa-filter me-1"></i> Filter
                </button>

                <a href="{{ route('rekap-progresif.index') }}" class="btn btn-secondary">
                    Reset
                </a>
            </div>
        </form>

    </div>

    <div class="table-responsive">

        @php
            $kolomRekapUtama = 12; // jumlah kolom tanpa unit, cabang, dan aksi
            $canEditDelete = $isAdmin ?? false;
            $kolomAksi = $canEditDelete ? 1 : 0;

            // Tambah kolom unit & cabang hanya jika admin
            $kolomUnitCabang = (auth()->check() && (auth()->user()->is_admin ?? false)) ? 2 : 0;

            $totalColspan = $kolomRekapUtama + $kolomUnitCabang + $kolomAksi;
        @endphp

        <table class="table table-bordered table-hover align-middle" style="min-width:900px;font-size:13px">

            <thead>
                <tr class="text-center" style="background:#cfe8ff;font-weight:600">
                    <th colspan="{{ $totalColspan }}">
                        REKAP PROGRESIF
                    </th>
                </tr>

                <tr class="table-light text-center">
                    <th rowspan="2">NO</th>
                    <th rowspan="2">NAMA</th>

                    {{-- KOLOM biMBA UNIT & NO CABANG – HANYA UNTUK ADMIN --}}
                    @if (auth()->check() && (auth()->user()->is_admin ?? false))
                        <th rowspan="2">biMBA UNIT</th>
                        <th rowspan="2">NO CABANG</th>
                    @endif

                    <th rowspan="2">JABATAN</th>
                    <th rowspan="2">STATUS</th>
                    <th rowspan="2">DEPARTEMEN</th>
                    <th rowspan="2">MASA KERJA</th>

                    <th colspan="2">SPP</th>

                    <th rowspan="2">TOTAL FM</th>
                    <th rowspan="2">PROGRESIF</th>
                    <th rowspan="2">KOMISI</th>
                    <th rowspan="2">DIBAYARKAN</th>

                    @if($canEditDelete)
                        <th rowspan="2">AKSI</th>
                    @endif
                </tr>

                <tr class="text-center">
                    <th>biMBA</th>
                    <th>ENGLISH</th>
                </tr>
            </thead>

            <tbody>
                @forelse($rekaps as $rekap)
                    <tr @if(strtolower($rekap->jabatan ?? '') === 'kepala unit') class="table-warning" @endif>
                        <td class="text-center">
                            {{ ($rekaps->currentPage() - 1) * $rekaps->perPage() + $loop->iteration }}
                        </td>

                        <td>{{ $rekap->nama ?? '-' }}</td>

                        {{-- Hanya admin yang melihat kolom ini --}}
                        @if (auth()->check() && (auth()->user()->is_admin ?? false))
                            <td class="text-center">{{ $rekap->bimba_unit ?? $rekap->biMBA_unit ?? '-' }}</td>
                            <td class="text-center">{{ $rekap->no_cabang ?? '-' }}</td>
                        @endif

                        <td class="text-center">{{ $rekap->jabatan ?? '-' }}</td>
                        <td class="text-center">{{ $rekap->status ?? '-' }}</td>
                        <td class="text-center">{{ $rekap->departemen ?? '-' }}</td>
                        <td class="text-center">{{ $rekap->masa_kerja ?? '-' }}</td>

                        <td class="text-end">{{ number_format($rekap->spp_bimba ?? 0, 0, ',', '.') }}</td>
                        <td class="text-end">{{ number_format($rekap->spp_english ?? 0, 0, ',', '.') }}</td>

                        <td class="text-end">{{ number_format($rekap->total_fm ?? 0, 2, ',', '.') }}</td>
                        <td class="text-end">{{ number_format($rekap->progresif ?? 0, 0, ',', '.') }}</td>
                        <td class="text-end">{{ number_format($rekap->komisi ?? 0, 0, ',', '.') }}</td>
                        <td class="text-end fw-bold text-success">
                            {{ number_format($rekap->dibayarkan ?? 0, 0, ',', '.') }}
                        </td>

                        @if($canEditDelete)
                            <td class="text-center">
                                <div class="btn-group">
                                    <a href="{{ route('rekap-progresif.edit', $rekap->id) }}"
                                       class="btn btn-sm btn-warning">
                                        <i class="fas fa-edit"></i>
                                    </a>

                                    <form action="{{ route('rekap-progresif.destroy', $rekap->id) }}"
                                          method="POST"
                                          onsubmit="return confirm('Yakin hapus data {{ $rekap->nama }} ?')">
                                        @csrf
                                        @method('DELETE')
                                        <button class="btn btn-sm btn-danger">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        @endif
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ $totalColspan }}" class="text-center py-4">
                            Tidak ada data rekap progresif
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if(isset($rekaps) && method_exists($rekaps,'links'))
        <div class="d-flex justify-content-center mt-4">
            {{ $rekaps->appends(request()->query())->links() }}
        </div>
    @endif

</div>
@endsection