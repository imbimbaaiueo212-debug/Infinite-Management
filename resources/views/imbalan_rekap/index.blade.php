@extends('layouts.app')

@section('content')
<div class="container-fluid">
    <h3 class="mb-4 text-primary fw-bold">Rekapitulasi Imbalan Relawan</h3>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show shadow-sm" role="alert">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show shadow-sm" role="alert">
            {{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    @php
        $start_bulan = request('start_bulan', request('bulan', now()->format('m')));
        $start_tahun = request('start_tahun', request('tahun', now()->format('Y')));
        $end_bulan   = request('end_bulan', $start_bulan);
        $end_tahun   = request('end_tahun', $start_tahun);

        try {
            $startDate = \Carbon\Carbon::createFromFormat('Y-m', "$start_tahun-$start_bulan")->startOfMonth();
            $endDate   = \Carbon\Carbon::createFromFormat('Y-m', "$end_tahun-$end_bulan")->endOfMonth();
        } catch (\Exception $e) {
            $startDate = now()->startOfMonth();
            $endDate   = now()->endOfMonth();
        }
        $periodeText = $startDate->locale('id')->translatedFormat('F Y') . 
                       ($startDate->isSameMonth($endDate) ? '' : ' — ' . $endDate->locale('id')->translatedFormat('F Y'));
    @endphp

    <!-- HEADER + FILTER -->
    <div class="card shadow-sm mb-4">
        <div class="card-body">
            <div class="row align-items-center">

                <!-- INFO PERIODE -->
                <div class="col-lg-5 mb-3 mb-lg-0">
                    <h5 class="mb-0 text-primary">
                        Periode Tampil:
                        <strong class="fs-5">{{ $periodeText }}</strong>
                    </h5>
                </div>

                <!-- FORM FILTER -->
                <div class="col-lg-7">
                    <form method="GET" class="row g-3 align-items-end">

                        {{-- DARI BULAN --}}
                        <div class="col-lg-2 col-md-4 col-6">
                            <label class="small text-muted mb-1">Dari Bulan</label>
                            <select name="start_bulan" class="form-select form-select-sm" onchange="this.form.submit()">
                                @foreach([
                                    '01'=>'Januari','02'=>'Februari','03'=>'Maret','04'=>'April',
                                    '05'=>'Mei','06'=>'Juni','07'=>'Juli','08'=>'Agustus',
                                    '09'=>'September','10'=>'Oktober','11'=>'November','12'=>'Desember'
                                ] as $k => $v)
                                    <option value="{{ $k }}" {{ $start_bulan == $k ? 'selected' : '' }}>{{ $v }}</option>
                                @endforeach
                            </select>
                        </div>

                        {{-- DARI TAHUN --}}
                        <div class="col-lg-1 col-md-2 col-6">
                            <label class="small text-muted mb-1">Tahun</label>
                            <select name="start_tahun" class="form-select form-select-sm" onchange="this.form.submit()">
                                @for($y = now()->year - 3; $y <= now()->year + 1; $y++)
                                    <option value="{{ $y }}" {{ $start_tahun == $y ? 'selected' : '' }}>{{ $y }}</option>
                                @endfor
                            </select>
                        </div>

                        {{-- SAMPAI BULAN --}}
                        <div class="col-lg-2 col-md-4 col-6">
                            <label class="small text-muted mb-1">Sampai Bulan</label>
                            <select name="end_bulan" class="form-select form-select-sm" onchange="this.form.submit()">
                                @foreach([
                                    '01'=>'Januari','02'=>'Februari','03'=>'Maret','04'=>'April',
                                    '05'=>'Mei','06'=>'Juni','07'=>'Juli','08'=>'Agustus',
                                    '09'=>'September','10'=>'Oktober','11'=>'November','12'=>'Desember'
                                ] as $k => $v)
                                    <option value="{{ $k }}" {{ $end_bulan == $k ? 'selected' : '' }}>{{ $v }}</option>
                                @endforeach
                            </select>
                        </div>

                        {{-- SAMPAI TAHUN --}}
                        <div class="col-lg-1 col-md-2 col-6">
                            <label class="small text-muted mb-1">Tahun</label>
                            <select name="end_tahun" class="form-select form-select-sm" onchange="this.form.submit()">
                                @for($y = now()->year - 3; $y <= now()->year + 1; $y++)
                                    <option value="{{ $y }}" {{ $end_tahun == $y ? 'selected' : '' }}>{{ $y }}</option>
                                @endfor
                            </select>
                        </div>

                        {{-- FILTER UNIT – HANYA UNTUK ADMIN --}}
                        @if (auth()->check() && (auth()->user()->is_admin ?? false))
                            <div class="col-lg-3 col-md-6">
                                <label class="small text-muted mb-1">Unit biMBA</label>
                                <select name="bimba_unit" class="form-select form-select-sm" onchange="this.form.submit()">
                                    <option value="">-- Semua Unit --</option>
                                    @foreach($unitOptions as $unit)
                                        <option value="{{ $unit }}" {{ request('bimba_unit') == $unit ? 'selected' : '' }}>
                                            {{ $unit }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        @endif

                        {{-- FILTER NAMA --}}
                        <div class="col-lg-3 col-md-6">
                            <label class="small text-muted mb-1">Nama Relawan</label>
                            <select name="nama" class="form-select form-select-sm" onchange="this.form.submit()">
                                <option value="">-- Semua Nama --</option>
                                @foreach($namaOptions as $nama)
                                    <option value="{{ $nama }}" {{ request('nama') == $nama ? 'selected' : '' }}>
                                        {{ $nama }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        {{-- AKSI FILTER --}}
                        <div class="col-12 text-end">
                            <button type="submit" class="btn btn-primary btn-sm">
                                <i class="bi bi-funnel"></i> Filter
                            </button>
                            <a href="{{ route('imbalan_rekap.index') }}" class="btn btn-outline-secondary btn-sm">
                                <i class="bi bi-arrow-clockwise"></i> Reset
                            </a>
                        </div>

                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- TOMBOL AKSI UTAMA -->
    @php
        $current = \Carbon\Carbon::createFromFormat('Y-m', "$start_tahun-$start_bulan");
        $prev    = $current->copy()->subMonth();
    @endphp

    <div class="d-flex align-items-center gap-2 mb-3 flex-wrap">

        <!-- Generate Bulan Sebelumnya -->
        <form action="{{ route('imbalan_rekap.generate') }}" method="POST" class="d-inline">
            @csrf
            <input type="hidden" name="start_bulan" value="{{ $prev->format('m') }}">
            <input type="hidden" name="start_tahun" value="{{ $prev->format('Y') }}">
            <button class="btn btn-warning btn-sm">
                <i class="bi bi-clock-history"></i> Generate Bulan Sebelumnya
            </button>
        </form>

        <!-- Generate Bulan Ini -->
        <form action="{{ route('imbalan_rekap.generate') }}" method="POST" class="d-inline">
            @csrf
            <input type="hidden" name="start_bulan" value="{{ $start_bulan }}">
            <input type="hidden" name="start_tahun" value="{{ $start_tahun }}">
            <button class="btn btn-primary btn-sm">
                <i class="bi bi-arrow-repeat"></i> Generate Periode Ini
            </button>
        </form>

        <!-- Bayar Semua Periode Ini -->
        <form method="POST" action="{{ route('imbalan_rekap.bayar_periode') }}" class="d-inline ms-auto">
            @csrf
            <input type="hidden" name="start_bulan" value="{{ $start_bulan }}">
            <input type="hidden" name="start_tahun" value="{{ $start_tahun }}">
            <button class="btn btn-success btn-sm" onclick="return confirm('Yakin ingin tandai SEMUA relawan periode ini sebagai SUDAH DIBAYAR?')">
                <i class="bi bi-cash-stack"></i> Bayar Semua Periode Ini
            </button>
        </form>
    </div>

    <!-- INFO CICILAN -->
    <div class="alert alert-info small mb-4">
        <i class="bi bi-info-circle"></i>
        <strong>Cicilan Cash Advance bersifat manual.</strong> 
        Pilih angsuran di dropdown jika ingin dipotong bulan ini. Jika tidak dipilih, tidak ada potongan cicilan.
    </div>

    <!-- TABEL -->
    <div class="card shadow">
        <div class="card-body p-0">
            <div class="table-responsive" style="max-height: 70vh; overflow-y: auto;">
                <table class="table table-sm table-bordered table-hover align-middle mb-0 text-nowrap" style="font-size: 0.8rem; min-width: 3200px;">
                    <thead>
                        <tr class="text-white fw-bold text-center">
                            <th colspan="9" class="bg-primary text-dark">PROFIL RELAWAN</th>
                            <th colspan="8" class="bg-success text-dark">IMBALAN POKOK</th>
                            <th colspan="2" class="bg-warning text-dark">INSENTIF MENTOR</th>
                            <th colspan="2" class="bg-warning text-dark">TRANSPORT</th>
                            <th colspan="3" class="bg-danger text-dark">KEKURANGAN IMBALAN</th>
                            <th colspan="6" class="bg-success text-dark">KELEBIHAN IMBALAN</th>
                            <th colspan="6" class="bg-info text-dark">BAGI HASIL SPP</th>
                            <th colspan="2" class="bg-secondary text-dark">TOTAL DIBAYARKAN</th>
                            <th colspan="2" class="bg-dark">AKSI</th>
                        </tr>
                        <tr class="table-light text-center fw-bold">
                            <th>NIK</th>
                            <th>NAMA</th>
                            <th>bulan</th>

                            @if (auth()->check() && (auth()->user()->is_admin ?? false))
                                <th>UNIT</th>
                                <th>CABANG</th>
                            @endif

                            <th>POSISI</th>
                            <th>STATUS</th>
                            <th>DEPT</th>
                            <th>MASA KERJA</th>
                            <th>WAKTU/MGG</th>
                            <th>WAKTU/BLN</th>
                            <th>DURASI</th>
                            <th>%</th>
                            <th>KTR</th>
                            <th>POKOK</th>
                            <th>LAINNYA</th>
                            
                            <th>TOTAL</th>
                            <th>INSENTIF</th>
                            <th>KET. INSENTIF</th>
                            <th>TRANSPORT</th>
                            <th>@HARI</th>
                            <th>KEKURANGAN</th>
                            <th>BULAN</th>
                            <th>KET. KEKURANGAN</th>
                            <th>KELEBIHAN</th>
                            <th>BULAN</th>
                            <th>KET. KELEBIHAN</th>
                            <th>CICILAN</th>
                            <th>KET. CICILAN</th>
                            <th>TOTAL KELEBIHAN</th>
                            <th>MURID</th>
                            <th>SPP</th>
                            <th>KEKURANGAN</th>
                            <th>KELEBIHAN</th>
                            <th>BAGI HASIL</th>
                            <th>KET. BAGI HASIL</th>
                            <th>YANG DIBAYARKAN</th>
                            <th>CATATAN</th>
                            <th>STATUS BAYAR</th>
                        </tr>
                    </thead>
                    <tbody>
                        @php
                            $showAll = request()->has('all');
                            $rows = $showAll ? \App\Models\ImbalanRekap::orderBy('nama')->get() : $rekaps;
                        @endphp

                        @forelse($rows as $index => $r)
                            @php
                                $hasId = !is_null($r->id);
                                $formatRupiah = function($value) {
                                    if (!$value || $value == 0) return '-';
                                    $abs = abs($value);
                                    $formatted = 'Rp ' . number_format($abs, 0, ',', '.');
                                    return $value < 0 ? "<span class='text-danger'>($formatted)</span>" : $formatted;
                                };
                                $isDibayar = $r->status_pembayaran === 'dibayar';
                            @endphp
                            <tr data-id="{{ $hasId ? $r->id : '' }}" class="{{ $isDibayar ? 'table-success' : '' }}">
                                <td class="text-center fw-bold">
                                    {{ $r->profile?->nik ?? ($showAll ? $index + 1 : $rekaps->firstItem() + $index) }}
                                </td>
                                <td><strong>{{ $r->nama }}</strong></td>
                                <td class="text-center">{{ $r->bulan ?? '-' }}</td>

                                @if (auth()->check() && (auth()->user()->is_admin ?? false))
                                    <td class="text-center">{{ $r->bimba_unit ?? '-' }}</td>
                                    <td class="text-center">{{ $r->no_cabang ?? '-' }}</td>
                                @endif

                                <td class="text-center">{{ $r->posisi ?? '-' }}</td>
                                <td class="text-center">{{ $r->status ?? '-' }}</td>
                                <td>{{ $r->departemen ?? '-' }}</td>
                                <td class="text-center">{{ $r->masa_kerja_formatted ?? '-' }}</td>
                                <td class="text-center">{{ $r->waktu_mgg ?? '-' }}</td>
                                <td class="text-center">{{ $r->waktu_bln ?? '-' }}</td>
                                <td class="text-end">{{ $r->durasi_kerja ? number_format($r->durasi_kerja, 0, ',', '.') . ' Jam' : '-' }}</td>
                                <td class="text-center">{{ $r->persen ? number_format($r->persen, 2) . '%' : '-' }}</td>
                                <td class="text-center">{{ $r->ktr ?? '-' }}</td>
                                <td class="text-end text-success fw-bold">{!! $formatRupiah($r->imbalan_pokok) !!}</td>

                                <!-- LAINNYA -->
                                <td class="text-end position-relative">
                                    <div class="d-flex align-items-center justify-content-end gap-2">
                                        <input type="text" class="form-control form-control-sm text-end inline-edit-input"
                                            value="{{ ($r->imbalan_lainnya ?? 0) > 0 ? number_format($r->imbalan_lainnya, 0, ',', '.') : '' }}"
                                            data-field="imbalan_lainnya" data-id="{{ $r->id }}"
                                            style="width:100px;" placeholder="0">
                                        <i class="bi bi-pencil text-primary edit-icon" title="Edit"></i>
                                        <span class="saving text-primary" style="display:none; font-size:0.7rem;">Saving...</span>
                                    </div>
                                </td>

                            

                                <td class="text-end text-primary fw-bold bg-secondary-subtle" id="total_imbalan_{{ $r->id }}">
                                    {!! $formatRupiah($r->total_imbalan) !!}
                                </td>

                                <!-- sisanya tetap sama seperti kode asli kamu -->
                                <!-- Insentif Mentor -->
                                <td class="text-end position-relative">
                                    <div class="d-flex align-items-center justify-content-end gap-2">
                                        <input type="text" class="form-control form-control-sm text-end inline-edit-input"
                                            value="{{ ($r->insentif_mentor ?? 0) > 0 ? number_format($r->insentif_mentor, 0, ',', '.') : '' }}"
                                            data-field="insentif_mentor" data-id="{{ $r->id }}"
                                            style="width:100px;" placeholder="0">
                                        <i class="bi bi-pencil text-primary edit-icon" title="Edit"></i>
                                        <span class="saving text-primary" style="display:none; font-size:0.7rem;">Saving...</span>
                                    </div>
                                </td>
                                <td class="position-relative">
                                    <div class="d-flex align-items-center gap-2">
                                        <input type="text" class="form-control form-control-sm inline-edit-input"
                                            value="{{ $r->keterangan_insentif ?? '' }}" data-field="keterangan_insentif" data-id="{{ $r->id }}"
                                            style="width:130px;">
                                        <i class="bi bi-pencil text-primary edit-icon" title="Edit"></i>
                                        <span class="saving text-primary" style="display:none; font-size:0.7rem;">Saving...</span>
                                    </div>
                                </td>

                                <td class="text-end text-warning"><strong>{!! $formatRupiah($r->tambahan_transport) !!}</strong></td>

                                <td class="text-center position-relative">
                                    <div class="d-flex align-items-center justify-content-center gap-2">
                                        <input type="text" class="form-control form-control-sm text-center inline-edit-input"
                                            value="{{ $r->at_hari ?? '' }}" data-field="at_hari" data-id="{{ $r->id }}"
                                            style="width:70px;">
                                        <i class="bi bi-pencil text-primary edit-icon" title="Edit"></i>
                                        <span class="saving text-primary" style="display:none; font-size:0.7rem;">Saving...</span>
                                    </div>
                                </td>

                                <!-- Kekurangan Imbalan -->
                                <td class="text-end text-danger position-relative">
                                    <div class="d-flex align-items-center justify-content-end gap-2">
                                        <input type="text" class="form-control form-control-sm text-end inline-edit-input"
                                            value="{{ ($r->kekurangan ?? 0) > 0 ? number_format($r->kekurangan, 0, ',', '.') : '' }}"
                                            data-field="kekurangan" data-id="{{ $r->id }}"
                                            style="width:100px;" placeholder="0">
                                        <i class="bi bi-pencil text-primary edit-icon" title="Edit"></i>
                                        <span class="saving text-primary" style="display:none; font-size:0.7rem;">Saving...</span>
                                    </div>
                                </td>
                                <td class="text-center">
                                    {{ $r->bulan_kekurangan_full ?? '-' }}
                                </td>
                                <td class="position-relative">
                                    <div class="d-flex align-items-center gap-1" title="{{ $r->keterangan_kekurangan ?? 'Tidak ada keterangan' }}" data-bs-toggle="tooltip">
                                        <i class="bi bi-info-circle text-primary small"></i>
                                        <span class="text-muted small text-truncate" style="max-width: 110px;">
                                            {{ $r->keterangan_kekurangan ? Str::limit($r->keterangan_kekurangan, 18) : '-' }}
                                        </span>
                                    </div>
                                </td>

                                <!-- Kelebihan Imbalan -->
                                <td class="text-end text-success position-relative">
                                    <div class="d-flex align-items-center justify-content-end gap-2">
                                        <input type="text" class="form-control form-control-sm text-end inline-edit-input"
                                            value="{{ ($r->kelebihan ?? 0) > 0 ? number_format($r->kelebihan, 0, ',', '.') : '' }}"
                                            data-field="kelebihan" data-id="{{ $r->id }}"
                                            style="width:100px;" placeholder="0">
                                        <i class="bi bi-pencil text-primary edit-icon" title="Edit"></i>
                                        <span class="saving text-primary" style="display:none; font-size:0.7rem;">Saving...</span>
                                    </div>
                                </td>
                                <td class="text-center">
                                    {{ $r->bulan_kelebihan_full ?? '-' }}
                                </td>
                                <td class="position-relative">
                                    <div class="d-flex align-items-center gap-1" title="{{ $r->keterangan_kelebihan ?? 'Tidak ada keterangan' }}" data-bs-toggle="tooltip">
                                        <i class="bi bi-info-circle text-primary small"></i>
                                        <span class="text-muted small text-truncate" style="max-width: 110px;">
                                            {{ $r->keterangan_kelebihan ? Str::limit($r->keterangan_kelebihan, 18) : '-' }}
                                        </span>
                                    </div>
                                </td>

                                <!-- Cicilan -->
                                <td class="text-end text-warning position-relative">
                                    <div class="d-flex align-items-center justify-content-end gap-2">
                                        @php
                                            $allInstallments = collect();
                                            $currentInstallmentId = $r->installment_id;

                                            if ($r->profile && $r->profile->nama) {
                                                $allInstallments = \App\Models\CashAdvanceInstallment::join('cash_advances as ca', 'cash_advance_installments.cash_advance_id', '=', 'ca.id')
                                                    ->whereRaw('TRIM(UPPER(ca.nama)) = ?', [strtoupper(trim($r->profile->nama))])
                                                    ->select('cash_advance_installments.*')
                                                    ->orderBy('cash_advance_installments.cicilan_ke')
                                                    ->get();
                                            }
                                        @endphp

                                        @if($allInstallments->isNotEmpty() || $r->cicilan > 0)
                                            <select class="form-select form-select-sm inline-edit-cicilan"
                                                    data-id="{{ $r->id }}"
                                                    style="width:220px;">
                                                <option value="">-- Pilih Cicilan --</option>
                                                @foreach($allInstallments as $inst)
                                            @php
                                                $nominalMurni = (int) $inst->nominal_angsuran;
                                                $ket = $inst->cicilan_ke;
                                                $optionValue = $inst->id . '|' . $nominalMurni . '|' . $ket;

                                                $isSelected = $currentInstallmentId && $currentInstallmentId == $inst->id;
                                                $isLunas    = $inst->sudah_dibayar == 1;

                                                // Perbaikan: gunakan $isSelected (bukan $isCurrentlySelected)
                                                $disabled = ($isLunas && !$isSelected) ? 'disabled' : '';
                                                $lunasText = $isLunas ? ' (Lunas)' : '';
                                                $optionStyle = ($isLunas && !$isSelected) ? 'color: #6c757d; font-style: italic;' : '';
                                            @endphp
                                            <option value="{{ $optionValue }}"
                                                    {{ $isSelected ? 'selected' : '' }}
                                                    {{ $disabled }}
                                                    style="{{ $optionStyle }}">
                                                Rp {{ number_format($nominalMurni, 0, ',', '.') }} → Ke-{{ $ket }}{{ $lunasText }}
                                            </option>
                                        @endforeach
                                            </select>
                                        @else
                                            <span class="text-muted small">-</span>
                                        @endif

                                        <i class="bi bi-pencil text-primary edit-icon" title="Pilih cicilan"></i>
                                        <span class="saving text-primary" style="display:none; font-size:0.7rem;">Saving...</span>
                                    </div>
                                </td>

                                <td class="position-relative">
                                    <div class="d-flex align-items-center gap-2">
                                        <input type="text" class="form-control form-control-sm" 
                                               value="{{ $r->keterangan_cicilan ?? '' }}"
                                               readonly 
                                               style="width:180px; background:#f8f9fa; font-size:0.8rem;">
                                        <span class="text-muted small">Manual</span>
                                    </div>
                                </td>

                                <td class="text-end text-success fw-bold bg-secondary-subtle">
                                    {!! $formatRupiah($r->total_kelebihan) !!}
                                </td>

                                <!-- Bagi Hasil SPP -->
                                <td class="text-center">{{ $r->jumlah_murid ?? '-' }}</td>
                                <td class="text-end">{!! $formatRupiah($r->jumlah_spp) !!}</td>
                                <td class="text-end text-danger">{!! $formatRupiah($r->kekurangan_spp) !!}</td>
                                <td class="text-end text-success">{!! $formatRupiah($r->kelebihan_spp) !!}</td>
                                <td class="text-end">{!! $formatRupiah($r->jumlah_bagi_hasil) !!}</td>
                                <td>{{ $r->keterangan_bagi_hasil ?? '-' }}</td>

                                <!-- Yang Dibayarkan -->
                                <td class="text-end fw-bold bg-info-subtle {{ ($r->yang_dibayarkan ?? 0) < 0 ? 'text-danger' : 'text-success' }}"
                                    id="yang_dibayarkan_{{ $r->id }}">
                                    {!! $formatRupiah($r->yang_dibayarkan) !!}
                                </td>

                                <td class="position-relative">
                                    @php
                                        $filtered = null;

                                        if ($r->catatan) {
                                            $parts = explode(' | ', $r->catatan);

                                            $allowed = collect($parts)->filter(function ($p) {
                                                $p = strtolower(trim($p));

                                                return str_contains($p, 'izin') ||
                                                    str_contains($p, 'sakit') ||
                                                    str_contains($p, 'alpa') ||
                                                    str_contains($p, 'tidak aktif') ||
                                                    str_contains($p, 'pulang cepat');
                                            });

                                            $filtered = $allowed->implode(' | ');
                                        }
                                    @endphp

                                    @if($filtered)
                                        <div class="d-flex align-items-center gap-1"
                                            title="{{ $filtered }}"
                                            data-bs-toggle="tooltip">

                                            <i class="bi bi-chat-text text-primary small"></i>

                                            <span class="text-muted small text-truncate" style="max-width:200px;">
                                                {{ Str::limit($filtered, 50) }}
                                            </span>

                                        </div>
                                    @else
                                        <span class="text-muted">-</span>
                                    @endif
                                </td>

                                <!-- AKSI -->
                                <td class="text-center">
                                    @if($isDibayar)
                                        <span class="badge bg-success px-3 py-2">
                                            <i class="bi bi-check-circle-fill me-1"></i> Sudah Dibayar
                                        </span>
                                        @if($r->tanggal_dibayar)
                                            <div class="small text-muted mt-1">
                                                {{ $r->tanggal_dibayar->format('d/m/y H:i') }}
                                            </div>
                                        @endif
                                    @else
                                        <button class="btn btn-sm btn-outline-success btn-bayar-inline"
                                                data-id="{{ $r->id }}"
                                                data-nama="{{ addslashes($r->nama) }}"
                                                title="Tandai sudah dibayar periode ini">
                                            <i class="bi bi-cash-coin"></i> Bayar
                                        </button>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ auth()->check() && (auth()->user()->is_admin ?? false) ? 38 : 36 }}" 
                                    class="text-center text-muted py-5">
                                    @if(request('all'))
                                        Belum ada data rekap sama sekali.
                                    @else
                                        Tidak ada data untuk periode <strong>{{ $periodeText }}</strong><br>
                                        <small>Klik <strong>Refresh</strong> untuk membuat data.</small>
                                    @endif
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    @unless(request('all'))
        <div class="mt-3 d-flex justify-content-center">
            {{ $rekaps->appends(request()->query())->links() }}
        </div>
    @endunless
</div>

<style>
    .inline-edit-input {
        border: 1px solid #ced4da;
        background: #fff;
        transition: all 0.2s;
    }
    .inline-edit-input:focus {
        border-color: #0d6efd;
        box-shadow: 0 0 0 3px rgba(13,110,253,0.25);
    }
    .edit-icon:hover { opacity: 0.7; cursor: pointer; }
    .saving { font-weight: bold; }
    .btn-bayar-inline:hover { background-color: #198754; color: white !important; }
</style>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const tooltipTriggerList = document.querySelectorAll('[data-bs-toggle="tooltip"]');
    tooltipTriggerList.forEach(el => new bootstrap.Tooltip(el));

    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    if (!csrfToken) {
        console.error('CSRF token tidak ditemukan!');
        return;
    }

    const numericFields = ['imbalan_lainnya', 'insentif_mentor', 'kekurangan', 'kelebihan', 'cicilan', 'at_hari', 'potongan_tunjangan'];

    const formatRupiah = (value) => {
        if (!value || value == 0) return '-';
        const abs = Math.abs(value);
        const formatted = 'Rp ' + abs.toLocaleString('id-ID');
        return value < 0 ? `<span class="text-danger">(${formatted})</span>` : formatted;
    };

    // Inline edit input biasa (sudah ada, tidak diubah)
    document.querySelectorAll('.inline-edit-input').forEach(input => {
        const container = input.parentElement.parentElement;
        const editIcon = container.querySelector('.edit-icon');
        const saving = container.querySelector('.saving');

        input.addEventListener('focus', () => editIcon && (editIcon.style.display = 'none'));

        const save = () => {
            const recordId = input.dataset.id;
            if (!recordId) return;

            let rawValue = input.value.trim();
            if (rawValue === '' || rawValue === '-') rawValue = null;

            let value = rawValue;
            if (numericFields.includes(input.dataset.field) && value !== null) {
                value = parseInt(value.replace(/\./g, ''), 10) || 0;
            }

            saving && (saving.style.display = 'inline');
            editIcon && (editIcon.style.display = 'none');

            const formData = new FormData();
            formData.append('id', recordId);
            formData.append(input.dataset.field, value ?? '');
            formData.append('_token', csrfToken);

            fetch("{{ route('imbalan_rekap.update-inline') }}", {
                method: 'POST',
                headers: { 'Accept': 'application/json' },
                body: formData
            })
            .then(res => res.ok ? res.json() : res.text().then(text => { throw new Error(text) }))
            .then(data => {
                if (data.success) {
                    if (data.total_imbalan !== undefined) {
                        const el = document.getElementById('total_imbalan_' + recordId);
                        if (el) el.innerHTML = formatRupiah(data.total_imbalan);
                    }
                    if (data.yang_dibayarkan !== undefined) {
                        const el = document.getElementById('yang_dibayarkan_' + recordId);
                        if (el) {
                            el.innerHTML = formatRupiah(data.yang_dibayarkan);
                            el.className = `text-end fw-bold bg-info-subtle ${data.yang_dibayarkan < 0 ? 'text-danger' : 'text-success'}`;
                        }
                    }
                    if (data.tambahan_transport !== undefined) {
                        const cell = input.closest('tr').querySelector('td.text-warning strong');
                        if (cell) cell.innerHTML = formatRupiah(data.tambahan_transport);
                    }
                    if (input.dataset.field === 'at_hari' && data.at_hari !== undefined) {
                        input.value = data.at_hari || '';
                    }
                }
            })
            .catch(err => console.error('Error:', err))
            .finally(() => {
                saving && (saving.style.display = 'none');
                editIcon && (editIcon.style.display = 'inline');
            });
        };

        input.addEventListener('blur', save);
        input.addEventListener('keypress', e => e.key === 'Enter' && (e.preventDefault(), input.blur()));
    });

    // Handler Dropdown Cicilan (sudah ada, tidak diubah)
    document.querySelectorAll('.inline-edit-cicilan').forEach(select => {
        const container = select.parentElement;
        const editIcon  = container.querySelector('.edit-icon');
        const saving    = container.querySelector('.saving');
        const tr        = select.closest('tr');
        let previousValue = select.value || '';

        select.addEventListener('change', function () {
            const recordId = this.dataset.id;
            const newVal   = this.value;

            if (!recordId) return;

            if (saving) saving.style.display = 'inline';
            if (editIcon) editIcon.style.display = 'none';

            const formData = new FormData();
            formData.append('id', recordId);
            formData.append('_token', csrfToken);

            if (newVal === "") {
                formData.append('installment_id', '');
                formData.append('cicilan', 0);
                formData.append('keterangan_cicilan', '');
            } else {
                const parts = newVal.split('|');
                formData.append('installment_id', parts[0]);
                formData.append('cicilan', parts[1] || 0);
                formData.append('keterangan_cicilan', 'Cicilan Cash Advance | ' + (parts[2] || ''));
            }

            fetch("{{ route('imbalan_rekap.update-inline') }}", {
                method: 'POST',
                headers: { 'Accept': 'application/json' },
                body: formData
            })
            .then(res => {
                if (!res.ok) throw new Error('Network response was not ok');
                return res.json();
            })
            .then(data => {
                if (data.success) {
                    const elBayar = document.getElementById('yang_dibayarkan_' + recordId);
                    if (elBayar) {
                        elBayar.innerHTML = formatRupiah(data.yang_dibayarkan);
                        elBayar.className = `text-end fw-bold bg-info-subtle ${data.yang_dibayarkan < 0 ? 'text-danger' : 'text-success'}`;
                    }

                    const inputKet = tr.querySelector('td input[readonly]');
                    if (inputKet) inputKet.value = data.keterangan_cicilan || '';

                    if (previousValue !== '' && previousValue !== newVal) {
                        const prevParts = previousValue.split('|');
                        const prevId = prevParts[0];
                        Array.from(this.options).forEach(opt => {
                            if (opt.value.startsWith(prevId + '|')) {
                                opt.text = opt.text.replace(' (Lunas)', '');
                                opt.disabled = false;
                                opt.style.color = '';
                                opt.style.fontStyle = '';
                            }
                        });
                    }

                    if (newVal !== '') {
                        const selectedOption = this.options[this.selectedIndex];
                        if (selectedOption && !selectedOption.text.includes('(Lunas)')) {
                            selectedOption.text += ' (Lunas)';
                        }
                        if (selectedOption) {
                            selectedOption.disabled = true;
                            selectedOption.style.color = '#6c757d';
                            selectedOption.style.fontStyle = 'italic';
                        }
                    }

                    previousValue = newVal;
                } else {
                    alert('Gagal: ' + (data.message || 'Terjadi kesalahan'));
                    this.value = previousValue;
                }
            })
            .catch(err => {
                console.error('Error:', err);
                alert('Gagal menyimpan cicilan.');
                this.value = previousValue;
            })
            .finally(() => {
                if (saving) saving.style.display = 'none';
                if (editIcon) editIcon.style.display = 'inline';
            });
        });

        if (select.value) previousValue = select.value;
    });

    // ==================== BARU: Handler Tombol Bayar Inline ====================
    document.querySelectorAll('.btn-bayar-inline').forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            const id   = this.dataset.id;
            const nama = this.dataset.nama;

            if (!confirm(`Tandai **${nama}** sebagai SUDAH DIBAYAR untuk periode ini?`)) return;

            const originalText = this.innerHTML;
            this.innerHTML = '<i class="bi bi-hourglass-split"></i> Proses...';
            this.disabled = true;

            fetch("{{ route('imbalan_rekap.bayar-single') }}", {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    'Accept': 'application/json'
                },
                body: JSON.stringify({ id: id })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const td = this.closest('td');
                    td.innerHTML = `
                        <span class="badge bg-success px-3 py-2">
                            <i class="bi bi-check-circle-fill me-1"></i> Sudah Dibayar
                        </span>
                        <div class="small text-muted mt-1">
                            ${new Date().toLocaleString('id-ID', {
                                day: '2-digit', month: '2-digit', year: 'numeric', 
                                hour: '2-digit', minute: '2-digit'
                            })}
                        </div>
                    `;
                    // Optional: beri efek highlight sebentar
                    td.closest('tr').classList.add('table-success');
                } else {
                    alert(data.message || 'Gagal menandai pembayaran');
                    this.innerHTML = originalText;
                    this.disabled = false;
                }
            })
            .catch(err => {
                console.error(err);
                alert('Terjadi kesalahan saat menyimpan status pembayaran');
                this.innerHTML = originalText;
                this.disabled = false;
            });
        });
    });

    // Refresh dengan F5 tetap periode
    document.addEventListener('keydown', function(e) {
        if (e.key === 'F5' || e.keyCode === 116) {
            e.preventDefault();
            const url = new URL(window.location.href);
            const refreshUrl = "{{ route('imbalan_rekap.refresh') }}" + 
                '?start_bulan=' + '{{ $start_bulan }}' +
                '&start_tahun=' + '{{ $start_tahun }}' +
                '&end_bulan='   + '{{ $end_bulan }}' +
                '&end_tahun='   + '{{ $end_tahun }}' +
                (url.searchParams.has('bimba_unit') ? '&bimba_unit=' + url.searchParams.get('bimba_unit') : '') +
                (url.searchParams.has('nama')       ? '&nama='       + url.searchParams.get('nama')       : '');
            window.location.href = refreshUrl;
        }
    });
});
</script>
@endsection