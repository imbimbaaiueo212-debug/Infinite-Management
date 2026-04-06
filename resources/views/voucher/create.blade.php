@extends('layouts.app')

@section('title', 'Tambah Data Voucher')

@section('content')
<div class="card card-body">
    <h2>Voucher Old Version</h2>

    @if ($errors->any())
        <div class="alert alert-danger">
            <ul>
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- PESAN JIKA UNIT BELUM DIATUR (NON-ADMIN) --}}
    @if (!auth()->user()->isAdminUser() && empty(auth()->user()->bimba_unit))
        <div class="alert alert-warning">
            <strong>Unit Anda belum diatur!</strong><br>
            Hubungi admin untuk mengisi bimba_unit di profil Anda agar bisa membuat voucher.
        </div>
    @endif

    <form action="{{ route('voucher.store') }}" method="POST">
        @csrf

        {{-- HIDDEN BIMBA_UNIT & NO_CABANG untuk NON-ADMIN --}}
        @if (!auth()->user()->isAdminUser() && auth()->user()->bimba_unit)
            <input type="hidden" name="bimba_unit" value="{{ auth()->user()->bimba_unit }}">
            <input type="hidden" name="no_cabang" value="{{ auth()->user()->no_cabang ?? '' }}">
        @endif

        <!-- DATA VOUCHER -->
        <div class="mb-3">
            <label>1 Lembar Voucher 1 Bulan SPP</label>
            <input type="number" name="jumlah_voucher" id="jumlah_voucher" class="form-control" value="1" min="1">
        </div>

        <div class="mb-3">
            <label>Tanggal</label>
            <input type="date" name="tanggal" class="form-control" value="{{ old('tanggal') }}">
        </div>

        <div class="mb-3">
            <label>Status</label>
            <select name="status" class="form-control">
                <option value="">-- Pilih Status --</option>
                <option value="penyerahan" {{ old('status') == 'penyerahan' ? 'selected' : '' }}>Penyerahan</option>
                <option value="pemakaian" {{ old('status') == 'pemakaian' ? 'selected' : '' }}>Pemakaian</option>
            </select>
        </div>

        <div class="mb-3">
            <label>Nomor Voucher</label>
            <div id="voucher-container">
                <input type="text" name="no_voucher[]" class="form-control mb-2" placeholder="Nomor Voucher" required>
            </div>
            <button type="button" id="add-voucher" class="btn btn-secondary btn-sm">Tambah Voucher</button>
        </div>

        <!-- DETAIL HUMAS -->
        <h5>Detail Humas</h5>

        <!-- Dropdown Unit HANYA untuk Admin -->
        @if (auth()->user()->isAdminUser())
            <div class="mb-3">
                <label>biMBA Unit <span class="text-danger">*</span></label>
                <select name="bimba_unit" id="bimba_unit" class="form-control select2" required>
                    <option value="">-- Pilih biMBA Unit --</option>
                    @foreach ($units as $unit)
                        <option value="{{ $unit->biMBA_unit }}" data-no-cabang="{{ $unit->no_cabang ?? '' }}">
                            {{ $unit->biMBA_unit }} ({{ $unit->no_cabang ?? '-' }})
                        </option>
                    @endforeach
                </select>
                @error('bimba_unit') <div class="text-danger small">{{ $message }}</div> @enderror
            </div>

            <div class="mb-3">
                <label>No Cabang</label>
                <input type="text" name="no_cabang" id="no_cabang" class="form-control bg-light" readonly>
            </div>
        @endif

        <!-- NIM / Nama Murid (dari AJAX) -->
        <div class="mb-3">
            <label>NIM / Nama Murid <span class="text-danger">*</span></label>
            <select name="nim" id="nim" class="form-control select2" required>
                <option value="">-- Pilih NIM / Nama Murid --</option>
            </select>
            @error('nim') <div class="text-danger small">{{ $message }}</div> @enderror
        </div>

        <!-- Auto-fill fields -->
        <div class="mb-3">
            <label>Nama Murid</label>
            <input type="text" name="nama_murid" id="nama_murid" class="form-control" readonly>
        </div>

        <div class="mb-3">
            <label>Orangtua</label>
            <input type="text" name="orangtua" id="orangtua" class="form-control" readonly>
        </div>

        <div class="mb-3">
            <label>Telp/HP</label>
            <input type="text" name="telp_hp" id="telp_hp" class="form-control" readonly>
        </div>

        <!-- DETAIL MURID BARU (tetap manual seperti versi lama) -->
        <h5>Detail Murid Baru</h5>
        <div class="mb-3">
            <label>NIM Murid Baru</label>
            <input type="text" name="nim_murid_baru" id="nim_murid_baru" class="form-control">
        </div>
        <div class="mb-3">
            <label>Nama Murid Baru</label>
            <input type="text" name="nama_murid_baru" id="nama_murid_baru" class="form-control">
        </div>
        <div class="mb-3">
            <label>Orangtua Murid Baru</label>
            <input type="text" name="orangtua_murid_baru" id="orangtua_murid_baru" class="form-control">
        </div>
        <div class="mb-3">
            <label>Telp/HP Murid Baru</label>
            <input type="text" name="telp_hp_murid_baru" id="telp_hp_murid_baru" class="form-control">
        </div>

        <button type="submit" class="btn btn-success">Simpan</button>
        <a href="{{ route('voucher.index') }}" class="btn btn-secondary">Kembali</a>
    </form>
</div>
@endsection

<!-- Select2 & jQuery -->
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

<script>
$(function() {
    // Inisialisasi Select2
    $('.select2').select2({
        placeholder: "-- Pilih --",
        allowClear: true
    });

    // Event untuk admin: pilih unit → load murid
    $('#bimba_unit').on('change', function() {
        var unit = $(this).val();
        var cabang = $(this).find('option:selected').data('no-cabang') || '';
        $('#no_cabang').val(cabang);

        loadMurid(unit);
    });

    // Fungsi load murid via AJAX
    function loadMurid(unit) {
        $('#nim').empty().append('<option value="">-- Pilih NIM / Nama Murid --</option>');

        if (!unit) {
            clearMuridFields();
            $('#nim').trigger('change');
            return;
        }

        $.ajax({
            url: '{{ route("get.murid.by.unit") }}',
            type: 'GET',
            data: { bimba_unit: unit },
            dataType: 'json',
            success: function(data) {
                if (data.length === 0) {
                    $('#nim').append('<option disabled>Tidak ada murid di unit ini</option>');
                } else {
                    $.each(data, function(i, murid) {
                        $('#nim').append(
                            $('<option>', {
                                value: murid.id,
                                text: murid.text,
                                'data-nama': murid.nama,
                                'data-orangtua': murid.orangtua,
                                'data-telp': murid.telp_hp
                            })
                        );
                    });
                }
                $('#nim').trigger('change');
            },
            error: function(xhr, status, error) {
                console.error('AJAX Error:', {
                    status: status,
                    error: error,
                    response: xhr.responseText,
                    url: xhr.responseURL || '{{ route("get.murid.by.unit") }}'
                });
                alert('Gagal memuat data murid.\nStatus: ' + xhr.status + ' (' + xhr.statusText + ')\nCek console browser untuk detail.');
            }
        });
    }

    // Auto-fill field saat pilih NIM
    $('#nim').on('change', function() {
        var selected = $(this).find('option:selected');
        if (selected.val()) {
            $('#nama_murid').val(selected.data('nama') || '');
            $('#orangtua').val(selected.data('orangtua') || '');
            $('#telp_hp').val(selected.data('telp') || '');
        } else {
            clearMuridFields();
        }
    });

    function clearMuridFields() {
        $('#nama_murid, #orangtua, #telp_hp').val('');
    }

    // Load awal untuk non-admin (unit sudah dari profil)
    @if (!auth()->user()->isAdminUser() && auth()->user()->bimba_unit)
        loadMurid('{{ auth()->user()->bimba_unit }}');
    @endif

    // Tambah input nomor voucher
    $('#add-voucher').on('click', function() {
        $('#voucher-container').append(
            '<input type="text" name="no_voucher[]" class="form-control mb-2" placeholder="Nomor Voucher" required>'
        );
    });
});
</script>
