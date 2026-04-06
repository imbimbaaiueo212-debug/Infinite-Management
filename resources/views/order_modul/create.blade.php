@extends('layouts.app')

@section('title', 'Tambah Order Modul Baru')

@section('content')
<div class="container py-4">
    <div class="card shadow-sm">
        <div class="card-header bg-gradient-to-r from-indigo-600 to-purple-700 text-white py-4">
            <h2 class="mb-0 fw-bold text-center">Tambah Order Modul Baru</h2>
        </div>
        <div class="card-body p-4 p-lg-5">

            <div class="alert alert-info mb-5 rounded-3 shadow-sm" id="info-stok">
                <i class="fas fa-info-circle fa-2x me-3 float-start"></i>
                <strong>Status Stok & Harga Otomatis:</strong><br>
                Unit Anda sudah otomatis terdeteksi dari profile.<br>
                Pilih <strong>Tanggal Order</strong> untuk melihat stok dan produk sesuai unit.<br>
                Harga satuan diambil otomatis dari master produk.<br>
                Total harga = Jumlah × Harga Satuan → dihitung real-time!<br>
                <strong>Hanya minggu sesuai tanggal yang bisa diisi</strong> (minggu lain dimatikan otomatis).
            </div>

            <!-- Peringatan jika unit belum diatur (khusus non-admin) -->
            @php
                $isAdmin = auth()->check() && (auth()->user()->is_admin ?? false);
                $userUnit = auth()->user()->bimba_unit ?? null;
                $defaultUnitId = null;
                $unitDisplay = 'Unit belum diatur';

                if (!$isAdmin && $userUnit) {
                    $unit = \App\Models\Unit::where('biMBA_unit', $userUnit)->first();
                    if ($unit) {
                        $defaultUnitId = $unit->id;
                        $unitDisplay = $unit->no_cabang . ' | ' . strtoupper($unit->biMBA_unit);
                    }
                }
            @endphp

            @if (!$isAdmin && !$defaultUnitId)
                <div class="alert alert-danger mb-5 rounded-3 shadow-sm text-center">
                    <strong><i class="fas fa-exclamation-triangle me-2"></i>Unit Anda belum diatur!</strong><br>
                    Hubungi admin untuk mengatur unit di profile Anda agar bisa membuat order modul.
                </div>
            @endif

            <form action="{{ route('order_modul.store') }}" method="POST" id="orderForm">
                @csrf

                <!-- Hidden unit untuk non-admin (selalu ada untuk simpan ke DB) -->
                @if (!$isAdmin)
                    <input type="hidden" name="unit_id" id="unit_id" value="{{ old('unit_id', $defaultUnitId) }}" required>
                @endif

                <!-- Unit biMBA – HANYA UNTUK ADMIN -->
                @if ($isAdmin)
                    <div class="mb-5">
                        <label class="form-label fw-bold">Unit biMBA <span class="text-danger">*</span></label>
                        <select name="unit_id" id="unit_id" class="form-select form-select-lg" required>
                            <option value="">-- Pilih Unit --</option>
                            @foreach($units as $unit)
                                <option value="{{ $unit->id }}" {{ old('unit_id') == $unit->id ? 'selected' : '' }}>
                                    {{ $unit->no_cabang }} | {{ strtoupper($unit->biMBA_unit) }}
                                </option>
                            @endforeach
                        </select>
                        @error('unit_id')
                            <small class="text-danger">{{ $message }}</small>
                        @enderror
                    </div>
                @else
                    <!-- Non-admin: tampilkan unit sebagai info saja (tidak ada input) -->
                    @if ($defaultUnitId)
                        <div class="mb-5 text-center">
                            <label class="form-label fw-bold d-block">Unit biMBA Anda</label>
                            <div class="badge bg-primary fs-5 px-4 py-2">
                                {{ $unitDisplay }}
                            </div>
                            <small class="text-muted d-block mt-2">Unit otomatis dari profile Anda (tidak bisa diubah)</small>
                        </div>
                    @endif
                @endif

                <div class="row mb-4">
                    <div class="col-12 col-lg-6 offset-lg-3">
                        <label for="tanggal_order" class="form-label fw-bold text-primary fs-5">
                            Tanggal Order <span class="text-danger">*</span>
                        </label>
                        <input type="date"
                               name="tanggal_order"
                               id="tanggal_order"
                               class="form-control form-control-lg text-center"
                               value="{{ old('tanggal_order', now()->format('Y-m-d')) }}"
                               required>
                        <small class="text-muted d-block mt-2">
                            Tanggal menentukan bulan rekap stok & minggu yang aktif
                        </small>
                    </div>
                </div>

                <hr class="my-5 border-secondary">

                @for($i = 1; $i <= 5; $i++)
                    <div class="card mb-4 border-0 shadow-lg rounded-3 overflow-hidden minggu-card"
                         data-minggu="{{ $i }}">
                        <div class="card-header bg-primary text-white fw-bold py-3 text-center fs-4">
                            ORDER MODUL MINGGU KE-{{ $i }}
                        </div>
                        <div class="card-body bg-light minggu-body">
                            <div class="row g-4 align-items-end">
                                <!-- Produk -->
                                <div class="col-12 col-md-6 col-lg-4">
                                    <label class="form-label fw-bold">Produk</label>
                                    <select name="kode{{ $i }}"
                                            class="form-select form-select-lg kode-select"
                                            data-week="{{ $i }}"
                                            style="width: 100%;">
                                        <option value="">-- Pilih unit terlebih dahulu --</option>
                                    </select>
                                </div>

                                <!-- Jumlah -->
                                <div class="col-12 col-md-3 col-lg-2">
                                    <label class="form-label fw-bold">Jumlah</label>
                                    <input type="number"
                                           name="jml{{ $i }}"
                                           class="form-control form-control-lg jml-input text-center"
                                           data-week="{{ $i }}"
                                           min="0"
                                           step="1"
                                           placeholder="0"
                                           value="{{ old('jml' . $i, 0) }}">
                                </div>

                                <!-- Harga Satuan -->
                                <div class="col-12 col-md-4 col-lg-2">
                                    <label class="form-label fw-bold">Harga Satuan</label>
                                    <div class="input-group">
                                        <span class="input-group-text">Rp</span>
                                        <input type="text"
                                               id="harga_satuan{{ $i }}"
                                               class="form-control form-control-lg text-end fw-bold"
                                               readonly
                                               value="0">
                                    </div>
                                </div>

                                <!-- Total Harga -->
                                <div class="col-12 col-md-5 col-lg-2">
                                    <label class="form-label fw-bold text-success">Total Harga</label>
                                    <div class="input-group">
                                        <span class="input-group-text">Rp</span>
                                        <input type="text"
                                               id="total_harga{{ $i }}"
                                               class="form-control form-control-lg text-end fw-bold text-success fs-5 shadow-sm"
                                               readonly
                                               value="0">
                                    </div>
                                </div>

                                <!-- Status Stok -->
                                <div class="col-12 col-md-4 col-lg-2">
                                    <label class="form-label fw-bold">Status Stok</label>
                                    <div class="border rounded-3 p-3 text-center bg-white status-preview h-100 d-flex flex-column justify-content-center"
                                         data-week="{{ $i }}">
                                        <span class="text-muted fs-6">Pilih produk</span>
                                        <input type="hidden" name="sts{{ $i }}" value="">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                @endfor

                <div class="text-center mt-5">
                    <button type="submit" class="btn btn-success btn-lg px-5 shadow-lg rounded-pill" id="submitBtn"
                            {{ !$defaultUnitId && !$isAdmin ? 'disabled' : '' }}>
                        <i class="fas fa-save fa-lg me-3"></i> Simpan Order
                    </button>
                    <a href="{{ route('order_modul.index') }}" class="btn btn-outline-secondary btn-lg px-5 ms-3 rounded-pill">
                        <i class="fas fa-arrow-left fa-lg me-3"></i> Kembali
                    </a>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- jQuery & Select2 -->
<script src="https://code.jquery.com/jquery-3.7.1.min.js" integrity="sha256-/JqT3SQfawRcv/BIHPThkBvs0OEvtFFmqPF/lYI/Cxo=" crossorigin="anonymous"></script>
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet" />
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

<script>
$(document).ready(function() {

    let currentStatusMap = {};
    let currentPeriodeText = '';
    let currentMingguAktif = null;

    // Inisialisasi Select2
    $('.kode-select').select2({
        theme: 'bootstrap-5',
        placeholder: "Cari produk...",
        allowClear: true,
        width: '100%',
        templateResult: function(data) {
            if (!data.id) return data.text;
            const label = data.id;
            const status = currentStatusMap[label];
            let badge = '';
            if (status === 1) badge = '<span class="badge bg-success ms-2">AMAN</span>';
            else if (status === 0) badge = '<span class="badge bg-danger ms-2">KURANG</span>';
            return $(`<div class="d-flex justify-content-between align-items-center"><span>${data.text}</span>${badge}</div>`);
        },
        templateSelection: function(data) {
            if (!data.id) return data.text;
            const label = data.id;
            const status = currentStatusMap[label];
            let badge = '';
            if (status === 1) badge = ' ✅ AMAN';
            else if (status === 0) badge = ' ⚠️ KURANG';
            return data.text + badge;
        }
    });

    function formatRupiah(angka) {
        if (!angka || angka == 0) return '0';
        return angka.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ".");
    }

    function updateRow(week) {
        const $select = $(`.kode-select[data-week="${week}"]`);
        const selectedValue = $select.val();
        const $option = $select.find(`option[value="${selectedValue}"]`);

        const hargaSatuan = parseInt($option.data('harga')) || 0;
        const jml = parseInt($(`input[name="jml${week}"]`).val()) || 0;
        const total = jml * hargaSatuan;

        $(`#harga_satuan${week}`).val(formatRupiah(hargaSatuan));
        $(`#total_harga${week}`).val(formatRupiah(total));

        const $container = $(`.status-preview[data-week="${week}"]`);
        let html = '';
        let hiddenValue = '';

        const status = currentStatusMap[selectedValue];

        if (selectedValue && status !== undefined) {
            if (status === 1) {
                html = `<i class="fas fa-check-circle fa-4x text-success mb-2"></i><div class="fw-bold text-success fs-5">STOK AMAN</div>`;
                hiddenValue = '1';
            } else if (status === 0) {
                html = `<i class="fas fa-exclamation-triangle fa-4x text-danger mb-2"></i><div class="fw-bold text-danger fs-5">STOK KURANG</div>`;
                hiddenValue = '0';
            }
        } else if (selectedValue) {
            html = `<div class="text-muted fs-6">Status tidak tersedia</div>`;
        } else {
            html = `<span class="text-muted fs-6">Pilih produk</span>`;
        }

        $container.html(html + `<input type="hidden" name="sts${week}" value="${hiddenValue}">`);
    }

    function refreshAllPreviews() {
        for (let i = 1; i <= 5; i++) updateRow(i);
    }

    $('.kode-select').on('select2:select select2:clear', function() {
        updateRow($(this).data('week'));
    });

    $('.jml-input').on('input keyup change', function() {
        updateRow($(this).data('week'));
    });

    // Fungsi: Hitung minggu dalam bulan berdasarkan tanggal
    function getMingguFromTanggal(tanggalStr) {
        if (!tanggalStr) return null;

        const date = new Date(tanggalStr);
        if (isNaN(date.getTime())) return null;

        const year = date.getFullYear();
        const month = date.getMonth();
        const day = date.getDate();

        const firstDay = new Date(year, month, 1);
        const firstWeekday = firstDay.getDay(); // 0 = Minggu, 1 = Senin, ...

        // Offset agar minggu 1 dimulai dari tanggal 1
        const minggu = Math.ceil(day / 7);

        return minggu >= 1 && minggu <= 5 ? minggu : 5; // maksimal minggu 5
    }

    // Fungsi: Aktifkan hanya minggu rekomendasi, matikan yang lain
    function aktifkanHanyaMinggu(mingguAktif) {
        currentMingguAktif = mingguAktif;

        $('.minggu-card').each(function() {
            const $card = $(this);
            const mingguCard = parseInt($card.data('minggu'));

            if (mingguCard === mingguAktif) {
                // Aktifkan
                $card.removeClass('opacity-50');
                $card.find('.kode-select, .jml-input').prop('disabled', false).trigger('change');
                $card.find('.card-header')
                     .removeClass('bg-primary text-white')
                     .addClass('bg-warning text-dark')
                     .text(`ORDER MODUL MINGGU KE-${mingguCard} (Rekomendasi)`);
                $card.addClass('border-warning border-4 bg-warning-subtle');
            } else {
                // Matikan
                $card.addClass('opacity-50');
                $card.find('.kode-select, .jml-input').prop('disabled', true).val('').trigger('change');
                $card.find('.card-header')
                     .removeClass('bg-warning text-dark')
                     .addClass('bg-primary text-white')
                     .text(`ORDER MODUL MINGGU KE-${mingguCard}`);
                $card.removeClass('border-warning border-4 bg-warning-subtle');
            }
        });

        // Update preview setelah disable
        refreshAllPreviews();
    }

    // Fungsi utama: load data + aktifkan minggu
    function loadData() {
        const tanggal = $('#tanggal_order').val();
        const unitId = $('#unit_id').val();

        const mingguAktif = getMingguFromTanggal(tanggal);
        aktifkanHanyaMinggu(mingguAktif);

        if (!tanggal || !unitId) {
            currentStatusMap = {};
            currentPeriodeText = '';
            $('#info-stok').html(`
                <i class="fas fa-info-circle fa-2x me-3 float-start"></i>
                <strong>Status Stok & Harga Otomatis:</strong><br>
                Pilih <strong>Unit</strong> dan <strong>Tanggal Order</strong> untuk melihat data sesuai unit.<br>
                Hanya minggu sesuai tanggal yang bisa diisi.
            `);
            $('.status-preview').html('<div class="text-muted fs-6">Pilih unit & tanggal</div>');
            $('.kode-select').each(function() {
                $(this).empty().append('<option value="">-- Pilih unit terlebih dahulu --</option>').trigger('change');
            });
            refreshAllPreviews();
            return;
        }

        const periode = tanggal.substring(0, 7);

        $('.status-preview').html('<div class="text-muted fs-6">Memuat...</div>');

        $.ajax({
            url: '{{ route('order_modul.get_status_stok') }}',
            data: { periode_rekap: periode, unit_id: unitId },
            success: function(res) {
                currentStatusMap = res.status_stok || {};
                currentPeriodeText = res.periode_formatted || '';

                $('#info-stok').html(`
                    <i class="fas fa-info-circle fa-2x me-3 float-start"></i>
                    <strong>Status Stok & Harga Otomatis:</strong><br>
                    Status dari rekap bulan <strong>${currentPeriodeText}</strong> untuk unit terpilih.
                `);

                $.ajax({
                    url: '{{ route('order_modul.produks_by_unit') }}',
                    data: { unit_id: unitId },
                    success: function(prodRes) {
                        $('.kode-select').each(function() {
                            const $select = $(this);
                            const week = $select.data('week');
                            $select.empty();

                            // Jika minggu ini aktif, isi produk
                            if (week === currentMingguAktif) {
                                $select.append('<option value="">-- Pilih Produk --</option>');
                                prodRes.produks.forEach(function(p) {
                                    const option = new Option(
                                        `${p.label} (${p.jenis})`,
                                        p.label,
                                        false,
                                        false
                                    );
                                    $(option).data('harga', p.harga);
                                    $select.append(option);
                                });
                            } else {
                                $select.append('<option value="">Minggu tidak aktif</option>');
                            }

                            $select.trigger('change');
                        });

                        refreshAllPreviews();
                    },
                    error: function() {
                        alert('Gagal memuat daftar produk.');
                    }
                });
            },
            error: function() {
                alert('Gagal memuat status stok.');
            }
        });
    }

    $('#tanggal_order, #unit_id').on('change', loadData);

    // Trigger awal
    if ($('#tanggal_order').val()) {
        const mingguAwal = getMingguFromTanggal($('#tanggal_order').val());
        aktifkanHanyaMinggu(mingguAwal);
    }

    if ($('#tanggal_order').val() && $('#unit_id').val()) {
        loadData();
    }

    for (let i = 1; i <= 5; i++) {
        updateRow(i);
    }
});
</script>

<style>
    .bg-warning-subtle {
        background-color: #fff3cd !important;
    }
    .minggu-card.border-warning {
        transition: all 0.3s ease;
    }
    .opacity-50 {
        opacity: 0.5;
        pointer-events: none; /* agar tidak bisa diklik sama sekali */
    }
    .minggu-card .kode-select:disabled,
    .minggu-card .jml-input:disabled {
        background-color: #f8f9fa;
        cursor: not-allowed;
    }
</style>
@endsection