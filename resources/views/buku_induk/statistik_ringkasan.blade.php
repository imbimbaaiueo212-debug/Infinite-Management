@extends('layouts.app')

@section('content')
<style>
    .stat-container {
        background-color: #e7f1ff; 
        padding: 20px;
        border-radius: 8px;
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    }
    /* Style Filter */
    .filter-section {
        background: #f8f9fa;
        padding: 15px;
        border-radius: 8px;
        border: 1px solid #dee2e6;
        margin-bottom: 20px;
    }
    .stat-title-main {
        background: linear-gradient(to bottom, #ffffff, #cce0ff);
        border: 1px solid #b3ccff;
        text-align: center;
        font-weight: bold;
        padding: 8px;
        margin-bottom: 20px;
        font-size: 1rem;
        color: #333;
        border-radius: 4px;
    }
    .unit-badge {
        background-color: #ffffff;
        border: 1px solid #b3ccff;
        padding: 5px 20px;
        display: inline-block;
        margin-bottom: 20px;
        color: #0d6efd;
        font-weight: bold;
        border-radius: 4px;
    }
    .column-title {
        text-align: center;
        font-weight: bold;
        margin-bottom: 15px;
        border-bottom: 2px solid #b3ccff;
        padding-bottom: 8px;
        font-size: 0.95rem;
        color: #444;
    }
    .stat-row {
        display: flex;
        align-items: center;
        margin-bottom: 10px;
        gap: 8px;
    }
    .stat-label {
        flex: 1;
        font-size: 0.85rem;
        color: #444;
    }
    .stat-box {
        background: white;
        border: 1px solid #b3ccff;
        width: 65px;
        text-align: center;
        padding: 5px;
        font-size: 0.9rem;
        font-weight: 500;
        border-radius: 4px;
        color: #333;
    }
    .stat-percent {
        background: #f0f5ff;
        border: 1px solid #b3ccff;
        width: 50px;
        text-align: center;
        padding: 5px;
        font-size: 0.8rem;
        color: #333;
        border-radius: 4px;
    }
</style>

<div class="container-fluid py-4">
    <div class="filter-section shadow-sm">
    <form method="GET" action="" class="row g-3 align-items-end">
        @if($isAdmin)
            <!-- HANYA ADMIN YANG LIHAT DROPDOWN UNIT -->
            <div class="col-12 col-md-4">
                <label class="form-label small fw-bold">Pilih Unit</label>
                <select name="unit_id" class="form-select form-select-sm">
                    @foreach($unitOptions as $value => $nama)
                        <option value="{{ $value }}" {{ $selectedUnit == $value ? 'selected' : '' }}>
                            {{ $nama }}
                        </option>
                    @endforeach
                </select>
            </div>
        @else
            <!-- USER BIASA: HILANGKAN DROPDOWN, PAKSA UNIT SENDIRI -->
            <input type="hidden" name="unit_id" value="{{ $userUnit }}">

            <div class="col-12">
                <div class="alert alert-info small py-2 mb-0 rounded">
                    <i class="fas fa-building me-2"></i>
                    Statistik untuk Unit: <strong>{{ $userUnit ?? 'Tidak Terdefinisi' }}</strong>
                </div>
            </div>
        @endif

        <!-- Bulan & Tahun tetap muncul untuk semua user -->
        <div class="col-6 col-md-{{ $isAdmin ? '3' : '4' }}">
            <label class="form-label small fw-bold">Bulan</label>
            <select name="bulan" class="form-select form-select-sm">
                @foreach($bulanOptions as $key => $nama)
                    <option value="{{ $key }}" {{ $bulan == $key ? 'selected' : '' }}>{{ $nama }}</option>
                @endforeach
            </select>
        </div>

        <div class="col-6 col-md-{{ $isAdmin ? '3' : '4' }}">
            <label class="form-label small fw-bold">Tahun</label>
            <select name="tahun" class="form-select form-select-sm">
                @foreach($tahunOptions as $thn)
                    <option value="{{ $thn }}" {{ $tahun == $thn ? 'selected' : '' }}>{{ $thn }}</option>
                @endforeach
            </select>
        </div>

        <!-- Info Auto Update -->
        <div class="col-12 col-md-{{ $isAdmin ? '2' : '4' }} d-flex align-items-end justify-content-end">
            <small class="text-muted fst-italic">Auto update</small>
        </div>
    </form>
</div>

    <div class="stat-container shadow-sm">
        <div class="stat-title-main">STATISTIK MURID</div>
        
        <div class="unit-badge">
            biMBA-AIUEO {{ $namaUnitTerpilih }}
        </div>

        <div class="row g-4">
            <div class="col-12 col-sm-6 col-lg-3">
                <div class="column-title">Realisasi Murid</div>
                <div class="stat-row">
                    <div class="stat-label">Murid Trial Baru</div>
                    <div class="stat-box">{{ $trialBaru }}</div>
                </div>
                <div class="stat-row">
                    <div class="stat-label">Murid Baru</div>
                    <div class="stat-box">{{ $muridBaruCount }}</div>
                </div>
                <div class="stat-row">
                    <div class="stat-label">Murid Keluar</div>
                    <div class="stat-box">{{ $muridKeluarCount }}</div>
                </div>
                <div class="stat-row">
                    <div class="stat-label">Murid Aktif</div>
                    <div class="stat-box">{{ $muridAktifCount }}</div>
                </div>
                <div class="stat-row">
                    <div class="stat-label">Murid Dhuafa</div>
                    <div class="stat-box">{{ $muridDhuafa }}</div>
                </div>
                <div class="stat-row">
                    <div class="stat-label">Murid BNF</div>
                    <div class="stat-box">{{ $muridBNF }}</div>
                </div>
            </div>

            <div class="col-12 col-sm-6 col-lg-3">
                <div class="column-title">Berdasarkan Usia</div>
                @foreach($dataUsia as $item)
                <div class="stat-row">
                    <div class="stat-label">{{ $item['label'] }}</div>
                    <div class="stat-box">{{ $item['jumlah'] }}</div>
                    <div class="stat-percent">{{ $item['persen'] }}%</div>
                </div>
                @endforeach
            </div>

            <div class="col-12 col-sm-6 col-lg-3">
                <div class="column-title">Lama Belajar</div>
                @foreach($dataLama as $item)
                <div class="stat-row">
                    <div class="stat-label">{{ $item['label'] }}</div>
                    <div class="stat-box">{{ $item['jumlah'] }}</div>
                    <div class="stat-percent">{{ $item['persen'] }}%</div>
                </div>
                @endforeach
            </div>

            <div class="col-12 col-sm-6 col-lg-3">
                <div class="column-title">Lain-lain</div>
                <div class="stat-row"><div class="stat-label">Tahap Persiapan</div><div class="stat-box">{{ $tahapPersiapan }}</div></div>
                <div class="stat-row"><div class="stat-label">Tahap Lanjutan</div><div class="stat-box">{{ $tahapLanjutan }}</div></div>
                <div class="stat-row"><div class="stat-label">Murid Aktif Kembali</div><div class="stat-box">{{ $aktifKembali }}</div></div>
                <div class="stat-row"><div class="stat-label">Murid Cuti</div><div class="stat-box">{{ $cuti }}</div></div>
                <div class="stat-row"><div class="stat-label">Murid Garansi</div><div class="stat-box">{{ $garansi }}</div></div>
                <div class="stat-row"><div class="stat-label">Murid Pindahan</div><div class="stat-box">{{ $pindahan }}</div></div>
            </div>
        </div>
    </div>
</div>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const form = document.querySelector('.filter-section form');
        const selects = form.querySelectorAll('select');

        selects.forEach(select => {
            select.addEventListener('change', function () {
                form.submit(); // Otomatis submit form saat ada perubahan
            });
        });
    });
</script>
@endsection