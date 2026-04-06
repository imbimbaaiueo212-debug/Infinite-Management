@extends('layouts.app')

@section('title', 'Pendapatan Tunjangan')

@section('content')
    <div class="card card-body shadow-sm">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1 class="mb-0">Pendapatan Tunjangan</h1>
            <div>
                <!-- Generate Bulan Baru -->
                <form action="{{ route('pendapatan-tunjangan.generate') }}" method="POST" class="d-inline"
                      onsubmit="return confirm('Generate data bulan baru?')">
                    @csrf
                    <button type="submit" class="btn btn-success btn-sm">
                        <i class="bi bi-plus-lg me-1"></i>Generate Bulan Baru
                    </button>
                </form>

                <!-- Filter Bulan -->
                <form method="GET" action="{{ route('pendapatan-tunjangan.index') }}" class="d-inline ms-2">
                    <select name="bulan" onchange="this.form.submit()" class="form-select form-select-sm d-inline-block" style="width: auto;">
                        <option value="">-- Semua Bulan --</option>
                        @foreach($allMonths as $b)
                            <option value="{{ $b }}" {{ ($bulan ?? '') == $b ? 'selected' : '' }}>
                                {{ \Carbon\Carbon::parse($b . '-01')->translatedFormat('F Y') }}
                            </option>
                        @endforeach
                    </select>
                </form>
            </div>
        </div>

        @if (session('success'))
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                {{ session('success') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        @endif
        @if (session('error'))
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                {{ session('error') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        @endif

        <div class="table-responsive">
            <table class="table table-bordered table-hover table-sm text-center align-middle">
                <thead class="table-primary">
                    <tr>
                        <th>NIK</th>
                        <th>NAMA</th>
                        <th>JABATAN</th>
                        <th>STATUS</th>
                        <th>DEPARTEMEN</th>

                        {{-- KOLOM BIMBA UNIT & NO CABANG – HANYA UNTUK ADMIN --}}
                        @if (auth()->check() && (auth()->user()->is_admin ?? false))
                            <th>UNIT biMBA</th>
                            <th>NO. CABANG</th>
                        @endif

                        <th>MASA KERJA</th>
                        <th>THP</th>
                        <th>KERAJINAN</th>
                        <th>ENGLISH</th>
                        <th>MENTOR</th>
                        <th>KEKURANGAN</th>
                        <th>BULAN KEKURANGAN</th>
                        <th>TJ KELUARGA</th>
                        <th>LAIN-LAIN</th>
                        <th>TOTAL</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($pendapatans as $p)
                        <tr>
                            <td>{{ $p['nik'] ?? '-' }}</td>
                            <td class="text-start">{{ $p['nama'] ?? '-' }}</td>
                            <td>{{ $p['jabatan'] ?? '-' }}</td>
                            <td>{{ $p['status'] ?? '-' }}</td>
                            <td>{{ $p['departemen'] ?? '-' }}</td>

                            {{-- Hanya admin yang melihat kolom ini --}}
                            @if (auth()->check() && (auth()->user()->is_admin ?? false))
                                <td>{{ $p['bimba_unit'] ?? '-' }}</td>
                                <td>{{ $p['no_cabang'] ?? '-' }}</td>
                            @endif

                            <td>
                                @if(isset($p['masa_kerja_format']))
                                    {{ $p['masa_kerja_format'] }}
                                @else
                                    @php
                                        $mk = (int) ($p['masa_kerja'] ?? 0);
                                        $years  = floor($mk / 12);
                                        $months = $mk % 12;
                                        $format = ($years > 0 ? $years . ' tahun ' : '') . $months . ' bulan';
                                        echo $format ?: '0 bulan';
                                    @endphp
                                @endif
                            </td>
                            <td>Rp. {{ number_format($p['thp'] ?? 0, 0, ',', '.') }}</td>
                            <td>Rp. {{ number_format($p['kerajinan'] ?? 0, 0, ',', '.') }}</td>
                            <td>Rp. {{ number_format($p['english'] ?? 0, 0, ',', '.') }}</td>
                            <td>Rp. {{ number_format($p['mentor'] ?? 0, 0, ',', '.') }}</td>

                            <td>
                                @if(($p['kekurangan'] ?? 0) > 0)
                                    <span class="text-danger fw-bold" title="Kekurangan yang dibayarkan di bulan ini">
                                        Rp. {{ number_format($p['kekurangan'] ?? 0, 0, ',', '.') }}
                                    </span>
                                @else
                                    Rp. {{ number_format($p['kekurangan'] ?? 0, 0, ',', '.') }}
                                @endif
                            </td>

                            <td>
                                @if($p['bulan_kekurangan'] ?? false)
                                    <span class="badge bg-secondary">
                                        {{ $p['bulan_kekurangan'] }}
                                    </span>
                                @else
                                    -
                                @endif
                            </td>

                            <td>Rp. {{ number_format($p['tj_keluarga'] ?? 0, 0, ',', '.') }}</td>
                            <td>Rp. {{ number_format($p['lain_lain'] ?? 0, 0, ',', '.') }}</td>
                            <td class="fw-bold">Rp. {{ number_format($p['total'] ?? 0, 0, ',', '.') }}</td>

                            <td>
                                @if(!empty($p['id']))
                                    <a href="{{ route('pendapatan-tunjangan.edit', $p['id']) }}"
                                       class="btn btn-sm btn-warning">Edit</a>

                                    @if (auth()->user()?->role === 'admin')
                                        <form action="{{ route('pendapatan-tunjangan.destroy', $p['id']) }}" method="POST"
                                              style="display:inline-block">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-danger"
                                                    onclick="return confirm('Apakah yakin ingin dihapus?')">Hapus</button>
                                        </form>
                                    @endif
                                @else
                                    <span class="badge bg-info text-dark">Otomatis (belum disimpan)</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ auth()->check() && (auth()->user()->is_admin ?? false) ? '19' : '17' }}" class="text-center py-4">
                                Belum ada data untuk bulan ini
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection