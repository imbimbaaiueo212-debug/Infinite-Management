{{-- resources/views/komisi/index.blade.php --}}
@extends('layouts.app')

@section('title', 'Komisi')

@section('content')
<div class="container-fluid">
    <div class="card w-100 shadow-sm">
        <div class="card-body">
            <h3 class="mb-4 text-primary">Komisi</h3>

            {{-- Filter Periode + Unit --}}
            <form method="GET" class="row mb-4 g-3">

                <!-- Unit biMBA - paling kiri, lebar sedang -->
                <div class="col-md-3">
                    <label class="form-label fw-bold">Unit biMBA</label>
                    <select name="unit_id" class="form-select">
                        <option value="">-- Semua Unit --</option>
                        @foreach($unitOptions ?? [] as $opt)
                            <option value="{{ $opt['value'] }}"
                                    {{ request('unit_id') == $opt['value'] ? 'selected' : '' }}>
                                {{ $opt['label'] }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <!-- Tahun Awal -->
                <div class="col-md-2">
                    <label class="form-label fw-bold">Tahun Awal</label>
                    <input type="number" name="tahun_awal" value="{{ $tahunAwal }}" 
                           class="form-control" placeholder="2025" min="2020">
                </div>

                <!-- Bulan Awal -->
                <div class="col-md-2">
                    <label class="form-label fw-bold">Bulan Awal</label>
                    <select name="bulan_awal" class="form-select">
                        <option value="">-- Pilih --</option>
                        @foreach([
                            1=>'Januari',2=>'Februari',3=>'Maret',4=>'April',5=>'Mei',6=>'Juni',
                            7=>'Juli',8=>'Agustus',9=>'September',10=>'Oktober',11=>'November',12=>'Desember'
                        ] as $i => $nama)
                            <option value="{{ $i }}" {{ $i == $bulanAwal ? 'selected' : '' }}>{{ $nama }}</option>
                        @endforeach
                    </select>
                </div>

                <!-- Tahun Akhir -->
                <div class="col-md-2">
                    <label class="form-label fw-bold">Tahun Akhir</label>
                    <input type="number" name="tahun_akhir" value="{{ $tahunAkhir }}" 
                           class="form-control" placeholder="2025" min="2020">
                </div>

                <!-- Bulan Akhir -->
                <div class="col-md-2">
                    <label class="form-label fw-bold">Bulan Akhir</label>
                    <select name="bulan_akhir" class="form-select">
                        <option value="">-- Pilih --</option>
                        @foreach([
                            1=>'Januari',2=>'Februari',3=>'Maret',4=>'April',5=>'Mei',6=>'Juni',
                            7=>'Juli',8=>'Agustus',9=>'September',10=>'Oktober',11=>'November',12=>'Desember'
                        ] as $i => $nama)
                            <option value="{{ $i }}" {{ $i == $bulanAkhir ? 'selected' : '' }}>{{ $nama }}</option>
                        @endforeach
                    </select>
                </div>

                <!-- Tombol Filter & Reset -->
                <div class="col-md-1 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary w-100">Filter</button>
                </div>

                <div class="col-md-1 d-flex align-items-end">
                    <a href="{{ route('komisi.index') }}" class="btn btn-outline-secondary w-100">Reset</a>
                </div>

            </form>

            {{-- Tombol Sync --}}
            <form action="{{ route('komisi.sync') }}" method="POST" class="d-inline mb-4">
                @csrf
                <button type="submit" class="btn btn-primary btn-sm"
                    onclick="return confirm('Sync komisi dengan data penerimaan terbaru?\nData yang sudah ada akan diperbarui otomatis.')">
                    Sync Komisi Otomatis
                </button>
            </form>

            {{-- Info Periode --}}
            <div class="alert alert-info d-flex align-items-center mb-4">
                <strong>Periode:</strong>&nbsp;
                <span class="fw-bold text-primary">{{ $periodeText }}</span>
                &nbsp;&nbsp;|&nbsp;&nbsp;
                <strong>Jumlah Karyawan:</strong> {{ $data_komisi->count() }}
            </div>

            {{-- Tabel Komisi --}}
            <div class="table-responsive">
                <table class="table table-bordered table-sm text-center align-middle">
                    <thead class="table-primary">
                        <tr class="fw-bold">
                            <th rowspan="2">NIK</th>
                            <th rowspan="2">NAMA</th>
                            <th rowspan="2">JABATAN</th>
                            <th rowspan="2">STATUS</th>
                            <th rowspan="2">DEPARTEMEN</th>
                            <th rowspan="2">MASA KERJA</th>

                            {{-- Kolom khusus admin --}}
                            @if (auth()->check() && (auth()->user()->is_admin ?? false))
                                <th rowspan="2">UNIT</th>
                                <th rowspan="2">NO CABANG</th>
                            @endif

                            <th colspan="2" class="table-success">JUMLAH SPP</th>
                            <th colspan="5" class="table-warning">RINCIAN KOMISI</th>

                            <th colspan="9" class="table-purple text-white">DATA MURID biMBA AIUEO</th>
                            <th colspan="5" class="table-pink text-white">DATA MURID ENGLISH</th>
                            <th colspan="2" class="table-danger text-white">KOMISI MB KEPALA UNIT</th>
                        </tr>

                        <tr class="table-light">
                            <th>biMBA</th>
                            <th>ENGLISH</th>

                            <th>MB biMBA</th>
                            <th>MT biMBA</th>
                            <th>MB ENGLISH</th>
                            <th>MT ENGLISH</th>
                            <th class="bg-success text-white fw-bold">DIBAYARKAN</th>

                            <th>AM1</th><th>AM2</th><th>MGRS</th><th>MDF</th><th>BNF</th><th>BNF2</th>
                            <th>MB</th><th>MK</th><th>MT</th>

                            <th>AM1</th><th>AM2</th><th>MB</th><th>MK</th><th>MT</th>

                            <th>MB UMUM</th>
                            <th>INSENTIF</th>
                        </tr>
                    </thead>

                    <tbody>
                        @forelse($data_komisi as $k)
                        <tr>
                            <td>{{ $k->nik ?? '-' }}</td>
                            <td class="text-start fw-bold">{{ $k->nama }}</td>
                            <td>{{ $k->jabatan }}</td>
                            <td>{{ $k->status }}</td>
                            <td>{{ $k->departemen }}</td>
                            <td>{{ $k->profile?->masa_kerja_format ?? $k->masa_kerja . ' bulan' }}</td>

                            {{-- Kolom khusus admin --}}
                            @if (auth()->check() && (auth()->user()->is_admin ?? false))
                                <td>{{ $k->bimba_unit ?? '-' }}</td>
                                <td>{{ $k->no_cabang ?? '-' }}</td>
                            @endif

                            {{-- JUMLAH SPP --}}
                            <td>{{ number_format($k->spp_bimba ?? 0, 0, ',', '.') }}</td>
                            <td>{{ number_format($k->spp_english ?? 0, 0, ',', '.') }}</td>

                            {{-- RINCIAN KOMISI --}}
                            <td class="fw-bold">{{ number_format($k->komisi_mb_bimba ?? 0, 0, ',', '.') }}</td>
                            <td>{{ number_format($k->komisi_mt_bimba ?? 0, 0, ',', '.') }}</td>
                            <td>{{ number_format($k->komisi_mb_english ?? 0, 0, ',', '.') }}</td>
                            <td>{{ number_format($k->komisi_mt_english ?? 0, 0, ',', '.') }}</td>

                            <td class="text-white fw-bold text-center 
                                {{ ($k->sudah_dibayar ?? 0) >= (($k->komisi_mb_bimba ?? 0) + ($k->komisi_mt_bimba ?? 0)) ? 'bg-success' : 'bg-danger' }}">
                                {{ number_format($k->sudah_dibayar ?? 0, 0, ',', '.') }}
                            </td>

                            {{-- DATA MURID biMBA AIUEO --}}
                            <td>{{ $k->am1_bimba ?? '-' }}</td>
                            <td>{{ $k->am2_bimba ?? '-' }}</td>
                            <td>{{ $k->mgrs ?? '-' }}</td>
                            <td>{{ $k->mdf ?? '-' }}</td>
                            <td>{{ $k->bnf ?? '-' }}</td>
                            <td>{{ $k->bnf2 ?? '-' }}</td>
                            <td>{{ $k->murid_mb_bimba ?? '-' }}</td>
                            <td>{{ $k->mk_bimba ?? '-' }}</td>
                            <td>{{ $k->murid_mt_bimba ?? '-' }}</td>

                            {{-- DATA MURID ENGLISH --}}
                            <td>{{ $k->am1_english ?? '-' }}</td>
                            <td>{{ $k->am2_english ?? '-' }}</td>
                            <td>{{ $k->murid_mb_english ?? '-' }}</td>
                            <td>{{ $k->mk_english ?? '-' }}</td>
                            <td>{{ $k->murid_mt_english ?? '-' }}</td>

                            {{-- KOMISI MB KEPALA UNIT --}}
                            <td class="fw-bold">{{ number_format($k->mb_umum_ku ?? 0, 0, ',', '.') }}</td>
                            <td>{{ number_format($k->mb_insentif_ku ?? 0, 0, ',', '.') }}</td>
                        </tr>

                        @empty
                        <tr>
                            <td colspan="{{ auth()->check() && (auth()->user()->is_admin ?? false) ? 40 : 38 }}" 
                                class="text-center py-5 text-muted">
                                <h5>Belum ada data komisi untuk periode ini.</h5>
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection

@push('styles')
<style>
    .table-purple { background-color: #6f42c1 !important; }
    .table-pink   { background-color: #e83e8c !important; }
</style>
@endpush