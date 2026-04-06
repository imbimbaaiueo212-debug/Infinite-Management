@extends('layouts.app')

@section('title', 'Edit Data Penerimaan')

@section('content')
<div class="container">
    <h4 class="mb-4 fw-bold">Edit Data Penerimaan</h4>

    @if ($errors->any())
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <ul class="mb-0">
                @foreach ($errors->all() as $err)
                    <li>{{ $err }}</li>
                @endforeach
            </ul>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    <form action="{{ route('penerimaan.update', $penerimaan->id) }}"
          method="POST"
          enctype="multipart/form-data"
          id="form-penerimaan">
        @csrf
        @method('PUT')

        <div class="row g-3">
            <!-- Kwitansi -->
            <div class="col-md-4">
                <label class="form-label fw-semibold">Kwitansi</label>
                <input type="text" name="kwitansi" class="form-control" 
                       value="{{ old('kwitansi', $penerimaan->kwitansi) }}" required>
            </div>

            <!-- Via -->
            <div class="col-md-4">
                <label class="form-label fw-semibold">Via</label>
                <select name="via" class="form-select" required>
                    <option value="" disabled>-- Pilih Via --</option>
                    <option value="cash"    {{ old('via', $penerimaan->via) == 'cash'    ? 'selected' : '' }}>Cash</option>
                    <option value="transfer" {{ old('via', $penerimaan->via) == 'transfer' ? 'selected' : '' }}>Transfer</option>
                    <option value="edc"     {{ old('via', $penerimaan->via) == 'edc'     ? 'selected' : '' }}>EDC</option>
                </select>
            </div>

            <!-- Tanggal -->
            <div class="col-md-4">
                <label class="form-label fw-semibold">Tanggal</label>
                <input type="date" name="tanggal" class="form-control" 
                       value="{{ old('tanggal', $penerimaan->tanggal) }}" required>
            </div>

            <!-- Bulan -->
            <div class="col-md-4">
                <label class="form-label fw-semibold">Bulan</label>
                <select name="bulan" class="form-select" required>
                    <option value="" disabled>-- Pilih Bulan --</option>
                    @php
                        $months = ['Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];
                        $bulanSelected = old('bulan', $penerimaan->bulan);
                    @endphp
                    @foreach ($months as $m)
                        <option value="{{ $m }}" {{ $bulanSelected == $m ? 'selected' : '' }}>{{ $m }}</option>
                    @endforeach
                </select>
            </div>

            <!-- Tahun -->
            <div class="col-md-4">
                <label class="form-label fw-semibold">Tahun</label>
                <select name="tahun" class="form-select" required>
                    <option value="" disabled>-- Pilih Tahun --</option>
                    @php
                        $currentYear = date('Y');
                        $startYear = $currentYear - 5;
                        $endYear = $currentYear + 2;
                        $tahunSelected = old('tahun', $penerimaan->tahun);
                    @endphp
                    @for ($y = $endYear; $y >= $startYear; $y--)
                        <option value="{{ $y }}" {{ $tahunSelected == $y ? 'selected' : '' }}>{{ $y }}</option>
                    @endfor
                </select>
            </div>

            <!-- NIM -->
            <div class="col-md-3">
                <label class="form-label fw-semibold">NIM</label>
                <select name="nim" id="nimSelect" class="form-select" required>
                    <option value="" disabled>-- Pilih NIM --</option>
                    @foreach ($murids as $murid)
                        @php
                            $nimWithZero = str_pad($murid->nim, 4, '0', STR_PAD_LEFT);
                        @endphp
                        <option value="{{ $nimWithZero }}"
                            data-nama="{{ $murid->nama }}"
                            data-kelas="{{ $murid->kelas }}"
                            data-gol="{{ $murid->gol }}"
                            data-kd="{{ $murid->kd }}"
                            data-status="{{ $murid->status }}"
                            data-guru="{{ $murid->guru }}"
                            data-spp="{{ $murid->spp }}"
                            data-bimba_unit="{{ $murid->bimba_unit ?? '' }}"
                            data-no_cabang="{{ $murid->no_cabang ?? '' }}"
                            {{ old('nim', $penerimaan->nim) == $nimWithZero ? 'selected' : '' }}>
                            {{ $nimWithZero }}
                        </option>
                    @endforeach
                </select>
            </div>

            <!-- Nama Murid -->
            <div class="col-md-5">
                <label class="form-label fw-semibold">Nama Murid</label>
                <input type="text" id="namaMuridInput" name="nama_murid" class="form-control" 
                       value="{{ old('nama_murid', $penerimaan->nama_murid) }}" readonly>
            </div>

            <!-- Kelas, Gol, KD -->
            <div class="col-md-2">
                <label class="form-label fw-semibold">Kelas</label>
                <input type="text" id="kelasInput" name="kelas" class="form-control" 
                       value="{{ old('kelas', $penerimaan->kelas) }}" readonly>
            </div>
            <div class="col-md-1">
                <label class="form-label fw-semibold">Gol</label>
                <input type="text" id="golInput" name="gol" class="form-control" 
                       value="{{ old('gol', $penerimaan->gol) }}" readonly>
            </div>
            <div class="col-md-1">
                <label class="form-label fw-semibold">KD</label>
                <input type="text" id="kdInput" name="kd" class="form-control" 
                       value="{{ old('kd', $penerimaan->kd) }}" readonly>
            </div>

            <!-- Status & Guru -->
            <div class="col-md-4">
                <label class="form-label fw-semibold">Status</label>
                <input type="text" id="statusInput" name="status" class="form-control" 
                       value="{{ old('status', $penerimaan->status) }}" readonly>
            </div>
            <div class="col-md-4">
                <label class="form-label fw-semibold">Guru</label>
                <input type="text" id="guruInput" name="guru" class="form-control" 
                       value="{{ old('guru', $penerimaan->guru) }}" readonly>
            </div>

            <!-- Unit biMBA -->
            <div class="col-md-4">
                <label class="form-label fw-semibold">Unit (biMBA)</label>
                @if(!empty($isAdmin) && $isAdmin)
                    <select name="bimba_unit" id="bimba_unit" class="form-select">
                        <option value="">-- Pilih Unit --</option>
                        @foreach($units as $u)
                            <option value="{{ $u->bimba_unit }}"
                                data-cabang="{{ $u->no_cabang }}"
                                {{ old('bimba_unit', $penerimaan->bimba_unit) == $u->bimba_unit ? 'selected' : '' }}>
                                {{ $u->bimba_unit }}
                            </option>
                        @endforeach
                    </select>
                @else
                    <input type="text" class="form-control" value="{{ old('bimba_unit', $penerimaan->bimba_unit ?? auth()->user()->bimba_unit ?? '-') }}" readonly>
                    <input type="hidden" name="bimba_unit" value="{{ old('bimba_unit', $penerimaan->bimba_unit ?? auth()->user()->bimba_unit ?? '') }}">
                @endif
            </div>

            <!-- No Cabang -->
            <div class="col-md-2">
                <label class="form-label fw-semibold">No Cabang</label>
                <input type="text" name="no_cabang" id="no_cabang" class="form-control"
                       value="{{ old('no_cabang', $penerimaan->no_cabang ?? '') }}">
            </div>
        </div>

        <hr class="my-4">

        <h5 class="mb-3 fw-bold">Komponen Biaya</h5>

        <div class="row g-3">
            @php
                $components = [
                    'daftar', 'voucher', 'spp', 'kpk', 'sertifikat', 'stpb', 'tas', 'event', 'lain_lain',
                    'RBAS', 'BCABS01', 'BCABS02'
                ];
            @endphp

            @foreach ($components as $field)
                <div class="col-md-3 col-sm-6">
                    <label class="form-label fw-semibold">
                        {{ strtoupper(str_replace('_', ' ', $field)) }}
                    </label>
                    <input type="number" min="0" step="1"
                           name="{{ $field }}"
                           class="form-control biaya-field"
                           value="{{ old($field, $penerimaan->$field ?? 0) }}"
                           {{ in_array($field, ['spp']) ? 'readonly' : '' }}>
                </div>
            @endforeach

            <!-- Kaos Pendek -->
            <div class="col-md-3 col-sm-6">
                <label class="form-label fw-semibold">Kaos Lengan Pendek (Rp)</label>
                <input type="text" name="kaos_pendek" id="kaos_pendek" class="form-control biaya-lain"
                       value="{{ number_format(old('kaos_pendek', $penerimaan->kaos ?? 0), 0, ',', '.') }}">
                <div id="ukuran-pendek-container" class="mt-2"></div>
            </div>

            <!-- Kaos Panjang -->
            <div class="col-md-3 col-sm-6">
                <label class="form-label fw-semibold">Kaos Lengan Panjang (Rp)</label>
                <input type="text" name="kaos_panjang" id="kaos_panjang" class="form-control biaya-lain"
                       value="{{ number_format(old('kaos_panjang', $penerimaan->kaos_lengan_panjang ?? 0), 0, ',', '.') }}">
                <div id="ukuran-panjang-container" class="mt-2"></div>
            </div>

            <!-- TOTAL -->
            <div class="col-md-3 col-sm-6">
                <label class="form-label fw-bold text-primary">TOTAL</label>
                <input type="text" id="total" class="form-control bg-warning-subtle fw-bold fs-5 text-center"
                       value="{{ number_format(old('total', $penerimaan->total ?? 0), 0, ',', '.') }}" readonly>
            </div>
        </div>

        <hr class="my-4">

        <!-- Bukti Transfer -->
        <div class="row">
            <div class="col-md-8 col-lg-6">
                <label class="form-label fw-bold">Bukti Penyerahan / Bukti Transfer</label>

                @if($penerimaan->bukti_transfer_path)
                    <div class="mb-3">
                        <a href="{{ asset('storage/' . $penerimaan->bukti_transfer_path) }}"
                           target="_blank" class="btn btn-outline-primary btn-sm">
                            <i class="bi bi-eye"></i> Lihat Bukti Saat Ini
                        </a>
                    </div>
                @else
                    <p class="text-muted mb-3">Belum ada bukti pembayaran diupload.</p>
                @endif

                <input type="file" name="bukti_transfer"
                       class="form-control @error('bukti_transfer') is-invalid @enderror"
                       accept=".jpg,.jpeg,.png,.pdf">

                @error('bukti_transfer')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror

                <div class="form-text text-muted mt-1">
                    Format: jpg, jpeg, png, pdf • Maksimal 5 MB • Kosongkan jika tidak ingin mengganti
                </div>

                @if($penerimaan->bukti_transfer_path)
                    <div class="form-check mt-3">
                        <input class="form-check-input" type="checkbox" name="hapus_bukti_lama" value="1" id="hapus_bukti_lama">
                        <label class="form-check-label text-danger" for="hapus_bukti_lama">
                            Hapus bukti transfer lama (centang jika ingin menghapus)
                        </label>
                    </div>
                @endif
            </div>
        </div>

        <div class="mt-5 d-flex gap-3">
            <button type="submit" class="btn btn-success btn-lg px-5">
                <i class="bi bi-save"></i> Update Data
            </button>
            <a href="{{ route('penerimaan.index') }}" class="btn btn-outline-secondary btn-lg px-5">
                <i class="bi bi-arrow-left"></i> Kembali
            </a>
        </div>
    </form>
</div>

<!-- Dependencies -->
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

<script>
$(document).ready(function() {
    // Select2 untuk NIM
    $('#nimSelect').select2({
        placeholder: "-- Cari atau pilih NIM --",
        allowClear: true,
        width: '100%'
    }).on('select2:select', function(e) {
        const selected = $(e.params.data.element);
        $('#namaMuridInput').val(selected.data('nama') || '');
        $('#kelasInput').val(selected.data('kelas') || '');
        $('#golInput').val(selected.data('gol') || '');
        $('#kdInput').val(selected.data('kd') || '');
        $('#statusInput').val(selected.data('status') || '');
        $('#guruInput').val(selected.data('guru') || '');

        // Optional: update unit & cabang jika admin
        @if(!empty($isAdmin) && $isAdmin)
            $('#bimba_unit').val(selected.data('bimba_unit') || '');
            $('#no_cabang').val(selected.data('no_cabang') || '');
        @endif

        hitungTotal();
    });

    // Format Rupiah
    function formatRupiah(angka) {
        return angka.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ".");
    }

    function unformatRupiah(str) {
        return parseInt((str || '0').replace(/\./g, '')) || 0;
    }

    // Hitung total
    function hitungTotal() {
        let sum = 0;
        $('.biaya-field, .biaya-lain').each(function() {
            sum += unformatRupiah($(this).val());
        });
        $('#total').val(formatRupiah(sum));
    }

    // Format input biaya (kecuali kaos yang punya handler sendiri)
    $('.biaya-field').on('input', function() {
        let val = this.value.replace(/\D/g, '');
        this.value = formatRupiah(val);
        hitungTotal();
    });

    // === Fitur Ukuran Kaos ===
    const ukuranOptions = ['KAS', 'KAM', 'KAL', 'KAXL', 'KAXXL', 'KAXXXL', 'KAXXXLS'];
    const hargaKaos = 70000;

    function generateUkuranDropdowns(containerId, jumlah, type, existing = []) {
        const container = $(`#${containerId}`);
        container.empty();

        if (jumlah <= 0) {
            container.hide();
            return;
        }

        container.show();
        let html = `<small class="fw-bold text-primary d-block mb-2">Ukuran untuk ${jumlah} pcs kaos ${type === 'pendek' ? 'lengan pendek' : 'lengan panjang'}:</small>`;

        for (let i = 0; i < jumlah; i++) {
            const selected = existing[i] || '';
            html += `
                <div class="mb-2">
                    <label class="small">Ukuran #${i+1}</label>
                    <select name="ukuran_kaos_${type}[]" class="form-select form-select-sm">
                        <option value="">-- Pilih --</option>
                        ${ukuranOptions.map(opt => `<option value="${opt}" ${opt === selected ? 'selected' : ''}>${opt}</option>`).join('')}
                    </select>
                </div>`;
        }
        container.html(html);
    }

    function updateUkuranFields() {
        const pendek = unformatRupiah($('#kaos_pendek').val());
        const panjang = unformatRupiah($('#kaos_panjang').val());

        const jmlPendek  = Math.floor(pendek  / hargaKaos);
        const jmlPanjang = Math.floor(panjang / hargaKaos);

        const existingPendek  = "{{ $penerimaan->ukuran_kaos_pendek  ?? '' }}".split(',').map(s => s.trim()).filter(Boolean);
        const existingPanjang = "{{ $penerimaan->ukuran_kaos_panjang ?? '' }}".split(',').map(s => s.trim()).filter(Boolean);

        generateUkuranDropdowns('ukuran-pendek-container',  jmlPendek,  'pendek',  existingPendek);
        generateUkuranDropdowns('ukuran-panjang-container', jmlPanjang, 'panjang', existingPanjang);
    }

    // Handler khusus kaos
    $('#kaos_pendek, #kaos_panjang').on('input', function() {
        let val = this.value.replace(/\D/g, '');
        this.value = formatRupiah(val);
        updateUkuranFields();
        hitungTotal();
    });

    // Init saat load
    updateUkuranFields();
    hitungTotal();
});
</script>
@endsection