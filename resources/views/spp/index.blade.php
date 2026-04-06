@extends('layouts.app')

@section('title', 'Laporan Data SPP')

@section('content')
    <div class="container-fluid">
        <div class="card w-100">
            <div class="card-body">
                <h3 class="mb-4">Laporan Pembayaran SPP</h3>

                {{-- Filter Tanggal --}}
<form method="GET" 
      action="{{ route('spp.index') }}" 
      class="card card-body shadow-sm border border-light rounded-3 mb-4">

    <div class="row g-3 align-items-end">

        <!-- Unit / Cabang -->
         @if (auth()->check() && (auth()->user()->is_admin ?? false))
        <div class="col-12 col-md-3">
            <label class="form-label small text-muted mb-1">Unit / Cabang</label>
            <select name="bimba_unit" class="form-select form-select-sm">
                
                @foreach($units as $unit)
                    <option value="{{ $unit->biMBA_unit }}"
                            {{ $filterUnit === $unit->biMBA_unit ? 'selected' : '' }}>
                        {{ $unit->label ?? $unit->biMBA_unit . ' (' . ($unit->no_cabang ?? '-') . ')' }}
                    </option>
                @endforeach
            </select>
        </div>
        @endif

        <!-- Bulan Awal -->
        <div class="col-12 col-md-3">
            <label class="form-label small text-muted mb-1">Bulan Awal</label>
            <select name="bulan_awal" class="form-select form-select-sm" required>
                <option value="-- Semua --" {{ $bulanAwal === '-- semua --' ? 'selected' : '' }}>
                    -- Semua --
                </option>
                @foreach (['januari', 'februari', 'maret', 'april', 'mei', 'juni', 'juli', 'agustus', 'september', 'oktober', 'november', 'desember'] as $b)
                    <option value="{{ $b }}" {{ $bulanAwal === $b ? 'selected' : '' }}>
                        {{ ucfirst($b) }}
                    </option>
                @endforeach
            </select>
        </div>

        <!-- Bulan Akhir -->
        <div class="col-12 col-md-3">
            <label class="form-label small text-muted mb-1">Bulan Akhir</label>
            <select name="bulan_akhir" class="form-select form-select-sm" required>
                <option value="-- Semua --" {{ $bulanAkhir === '-- semua --' ? 'selected' : '' }}>
                    -- Semua --
                </option>
                @foreach (['januari', 'februari', 'maret', 'april', 'mei', 'juni', 'juli', 'agustus', 'september', 'oktober', 'november', 'desember'] as $b)
                    <option value="{{ $b }}" {{ $bulanAkhir === $b ? 'selected' : '' }}>
                        {{ ucfirst($b) }}
                    </option>
                @endforeach
            </select>
        </div>

        <!-- Tahun -->
        <div class="col-12 col-md-2">
            <label class="form-label small text-muted mb-1">Tahun</label>
            <input type="number" 
                   name="tahun" 
                   value="{{ old('tahun', $tahun) }}" 
                   min="2020" max="{{ now()->year + 5 }}" 
                   class="form-control form-control-sm" 
                   placeholder="Tahun" required>
        </div>

        <!-- Tombol -->
        <div class="col-12 col-md-1 d-flex align-items-end">
            <button type="submit" class="btn btn-primary btn-sm w-100">
                <i class="fas fa-filter me-1"></i> Filter
            </button>
        </div>

        <div class="col-12 col-md-1 d-flex align-items-end">
            <a href="{{ route('spp.index') }}" class="btn btn-outline-secondary btn-sm w-100">
                Reset
            </a>
        </div>

    </div>
</form>

                <div class="card card-body shadow-sm border border-light rounded-3 mb-4">
    <div class="d-flex flex-wrap align-items-center gap-3">
        <h4 class="mb-0 fw-semibold text-dark">Daftar SPP</h4>
        
        <button id="btnSync" 
                class="btn btn-success btn-sm px-3 py-2 d-flex align-items-center gap-1">
            <span>🔄</span> Update SPKB
        </button>
        
        <a href="{{ route('spp.create') }}" 
           class="btn btn-primary btn-sm px-3 py-2 d-flex align-items-center gap-1">
            <span>+</span> Tambah Pembayaran
        </a>
    </div>
</div>                
                {{-- Belum Bayar --}}
                <div class="mt-2 mb-4 card shadow-sm border-danger">
    <!-- Header yang bisa diklik untuk collapse -->
    <div class="card-header bg-danger text-primary d-flex justify-content-between align-items-center"
         data-bs-toggle="collapse" 
         data-bs-target="#belumBayarBody" 
         aria-expanded="false" 
         aria-controls="belumBayarBody"
         role="button"
         style="cursor: pointer;">
        
        <h5 class="mb-0 d-flex align-items-center flex-wrap gap-2">
    <i class="fas fa-exclamation-triangle me-1 text-danger"></i>
    Daftar Murid Belum Bayar SPP
    
    <span class="badge bg-white text-danger ms-1">
        {{ $belumBayar->count() }} murid
    </span>
    
    <span class="badge bg-danger text-white ms-1">
        Total: Rp {{ number_format($totalBelumBayar ?? 0, 0, ',', '.') }}
    </span>
</h5>

        <i class="fas fa-chevron-down collapse-icon ms-auto"></i>
    </div>

    <!-- Body collapse, default tertutup (tanpa class "show") -->
    <div id="belumBayarBody" class="collapse">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table id="belumBayarTable" class="table table-bordered table-sm table-hover align-middle text-center mb-0">
                    <thead class="table-light">
                    <tr>
                        <th class="text-center">NIM</th>
                        <th class="text-start">NAMA MURID</th>
                        <th class="text-start">INFO</th>
                        <th class="text-center">STATUS PERNYATAAN</th>
                        <th class="text-center">AKSI</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($belumBayar as $m)
                        <tr>
                            <td class="text-center fw-bold">
                                {{ str_pad($m->nim, 5, '0', STR_PAD_LEFT) }}
                            </td>
                            <td class="text-start fw-medium">{{ $m->nama }}</td>
                            <td class="text-start small">
                                <div>
                                    <strong>Kelas:</strong> {{ $m->kelas ?? '-' }} | 
                                    <strong>Tahap:</strong> {{ $m->tahap ?? '-' }}<br>
                                    <strong>Gol:</strong> {{ $m->gol ?? '-' }} | 
                                    <strong>KD:</strong> {{ $m->kd ?? '-' }} |
                                    <strong>SPP:</strong>  {{ $m->spp ?? '-' }} |
                                    <strong>Status:</strong> {{ $m->status ?? '-' }} |
                                    <strong>Guru:</strong> {{ $m->guru ?? '-' }} |
                                    <strong>Orang Tua:</strong> {{ $m->orangtua }}
                                </div>
                            </td>
                            <td class="text-center">
                                @if ($m->sudahIsiForm)
                                    <span class="badge bg-success d-block mb-1">
                                        Sudah Isi Form
                                        <br><small class="fw-normal">{{ $m->tanggalIsiForm }}</small>
                                    </span>
                                @elseif(!empty($m->file_pernyataan))
                                    <span class="badge bg-success d-block">Sudah Upload Pernyataan</span>
                                @else
                                    <span class="badge bg-danger d-block">Belum Membuat Pernyataan</span>
                                @endif
                            </td>
                            <td class="text-center">
                                <div class="d-flex flex-column gap-2 align-items-center">
                                    @if ($m->sudahIsiForm)
                                        <a href="{{ route('spp.surat-keterlambatan', ['nim' => str_pad($m->nim, 5, '0', STR_PAD_LEFT)]) }}"
   target="_blank"
   class="btn btn-sm btn-primary w-100">
    Lihat Surat Keterlambatan
</a>
                                    @else
                                        <a href="https://docs.google.com/forms/d/e/1FAIpQLSeaM6e-0kL0ks5eJ_hvSz5JJZDGVGyiq6cfRJa2JZV7Zezb7w/viewform?entry.123456={{ $m->nim }}"
                                           target="_blank"
                                           class="btn btn-sm btn-warning w-100">
                                            Isi Google Form
                                        </a>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="text-center py-4 text-muted fst-italic">
                                Tidak ada murid yang belum menyelesaikan kewajiban pernyataan pembayaran.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

                <h5>✅ Sudah Bayar</h5>
                <div class="card shadow-sm border border-light rounded-3 mb-4 overflow-hidden">
    <div class="card-header bg-light fw-semibold text-center py-2">
        Daftar Murid yang Sudah Bayar SPP
    </div>
    
    <div class="card-body p-0">
    <div class="table-responsive">
        <table id="sudahBayarTable" class="table table-bordered table-sm table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>NIM</th>
                    <th>NAMA MURID</th>
                    <th>INFO</th>
                    <th>KETERANGAN</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($sudahBayar as $p)
                    <tr @class([
                        'table-danger' => str_contains(strtolower($p->status ?? ''), 'keluar')
                                       || str_contains(strtolower($p->status ?? ''), 'lulus')
                                       || str_contains(strtolower($p->status ?? ''), 'dropout')
                                       || str_contains(strtolower($p->status ?? ''), 'pindah')
                                       || str_contains(strtolower($p->status ?? ''), 'mutasi keluar'),
                    ])>
                        <td class="text-center fw-bold">{{ $p->nim_padded ?? $p->nim }}</td>
                        <td class="text-start fw-medium">{{ $p->nama_murid ?? '-' }}</td>
                        <td class="text-start">
                            <div class="small">
                                <strong>Kelas:</strong> {{ $p->kelas ?? '-' }} | 
                                <strong>Tahap:</strong> {{ $tahapMapping[$p->nim_padded ?? $p->nim] ?? '-' }} | 
                                <strong>Gol:</strong> {{ $p->gol ?? '-' }} | 
                                <strong>KD:</strong> {{ $p->kd ?? '-' }}<br>
                                
                                <strong>SPP:</strong> Rp {{ number_format($p->nilai_bayar ?? $p->spp ?? 0, 0, ',', '.') }} | 
                                <strong>Tanggal Bayar:</strong> {{ $p->tanggal ?? '-' }} 
                                ({{ ucfirst($p->bulan_pakai ?? '-') }})<br>
                                
                                <strong>Status:</strong> {{ $p->status ?? '-' }} | 
                                <strong>Guru:</strong> {{ $p->guru ?? '-' }} | 
                            </div>
                        </td>
                        <td class="text-start small fw-medium">
    {{ $p->deposit_keterangan ?? '-' }}
</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="3" class="text-center py-4 text-muted fst-italic">
                            Tidak ada data pembayaran SPP yang ditemukan.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>  
</div>             

            </div>
        </div>
    </div>
    
@endsection

@push('scripts')
<script>
$(document).ready(function() {

    $('#btnSync').click(function() {
        const $btn = $(this);
        $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-2" role="status"></span> Sinkronisasi...');

        $.ajax({
            url: '{{ route("spp.sync-form") }}',
            method: 'GET',
            data: {
                bimba_unit: '{{ $filterUnit ?? "" }}',   // agar sesuai filter saat ini
                tahun: '{{ $tahun }}'
            },
            success: function(response) {
                if (response.success) {
                    alert(response.message);
                    location.reload(); // refresh supaya tabel ter-update
                } else {
                    alert('Gagal: ' + (response.message || 'Unknown error'));
                }
            },
            error: function(xhr) {
                let msg = 'Terjadi kesalahan server';
                if (xhr.responseJSON && xhr.responseJSON.message) {
                    msg = xhr.responseJSON.message;
                }
                alert(msg);
            },
            complete: function() {
                $btn.prop('disabled', false).html('🔄 Update SPKB');
            }
        });
    });

});
</script>
@endpush