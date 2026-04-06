@extends('layouts.app')

@section('content')
    <h2>Edit Voucher Lama</h2>
    <a href="{{ route('voucher.index') }}" class="btn btn-secondary mb-3">Kembali</a>

    @if ($errors->any())
        <div class="alert alert-danger">
            <ul>
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form action="{{ route('voucher.update', $voucher->id) }}" method="POST" enctype="multipart/form-data">
        @csrf
        @method('PUT')

        <!-- ===================== -->
        <!-- DATA VOUCHER -->
        <!-- ===================== -->

        <div class="mb-3">
            <label>Voucher</label>
            <input type="text" name="voucher" class="form-control" value="{{ old('voucher', $voucher->voucher) }}">
        </div>

        <div class="form-group mb-3">
            <label for="jumlah_voucher">Jumlah Voucher</label>
            <input type="number" name="jumlah_voucher" id="jumlah_voucher" class="form-control"
                value="{{ old('jumlah_voucher', $voucher->jumlah_voucher) }}" min="0">
        </div>

        <div class="mb-3">
            <label>Tanggal</label>
            <input type="date" name="tanggal" class="form-control" value="{{ old('tanggal', $voucher->tanggal) }}">
        </div>

        <div class="mb-3">
            <label>Status</label>
            <select name="status" id="status" class="form-control">
                <option value="">-- Pilih Status --</option>
                <option value="penyerahan" {{ old('status', $voucher->status) == 'penyerahan' ? 'selected' : '' }}>
                    Penyerahan</option>
                <option value="pemakaian" {{ old('status', $voucher->status) == 'pemakaian' ? 'selected' : '' }}>
                    Pemakaian
                </option>
            </select>
        </div>

        <div class="mb-3">
            <label>Tanggal Pemakaian</label>
            <input type="date" name="tanggal_pemakaian" id="tanggal_pemakaian" class="form-control"
                value="{{ old('tanggal_pemakaian', $voucher->tanggal_pemakaian) }}"
                {{ old('status', $voucher->status) == 'penyerahan' ? 'readonly' : '' }}>
        </div>

        <hr>

        <!-- ===================== -->
        <!-- DETAIL HUMAS -->
        <!-- ===================== -->
        <h5>Detail Humas</h5>

        <div class="mb-3">
            <label>NIM</label>
            <select name="nim" id="nim" class="form-control select2">
                <option value="">-- Pilih NIM --</option>
                @foreach ($bukuInduk as $bi)
                    <option value="{{ $bi->nim }}" {{ old('nim', $voucher->nim) == $bi->nim ? 'selected' : '' }}>
                        {{ $bi->nim }} - {{ $bi->nama }}
                    </option>
                @endforeach
            </select>
        </div>

        {{-- ✅ NEW: BIMBA UNIT --}}
        <div class="mb-3">
            <label>biMBA Unit</label>
            <input type="text" name="bimba_unit" id="bimba_unit" class="form-control"
                value="{{ old('bimba_unit', $voucher->bimba_unit) }}" readonly>
        </div>

        {{-- ✅ NEW: NO CABANG --}}
        <div class="mb-3">
            <label>No Cabang</label>
            <input type="text" name="no_cabang" id="no_cabang" class="form-control"
                value="{{ old('no_cabang', $voucher->no_cabang) }}" readonly>
        </div>

        <div class="mb-3">
            <label>Nama Murid</label>
            <input type="text" name="nama_murid" id="nama_murid" class="form-control"
                value="{{ old('nama_murid', $voucher->nama_murid) }}" readonly>
        </div>

        <div class="mb-3">
            <label>Orangtua</label>
            <input type="text" name="orangtua" id="orangtua" class="form-control"
                value="{{ old('orangtua', $voucher->orangtua) }}" readonly>
        </div>

        <div class="mb-3">
            <label>Telp/HP</label>
            <input type="text" name="telp_hp" id="telp_hp" class="form-control"
                value="{{ old('telp_hp', $voucher->telp_hp) }}" readonly>
        </div>

        <hr>

        <!-- ===================== -->
        <!-- DETAIL MURID BARU -->
        <!-- ===================== -->
        <h5>Detail Murid Baru</h5>

        <div class="mb-3">
            <label>NIM Murid Baru</label>
            <input type="text" name="nim_murid_baru" class="form-control"
                value="{{ old('nim_murid_baru', $voucher->nim_murid_baru) }}">
        </div>

        <div class="mb-3">
            <label>Nama Murid Baru</label>
            <input type="text" name="nama_murid_baru" class="form-control"
                value="{{ old('nama_murid_baru', $voucher->nama_murid_baru) }}">
        </div>

        <div class="mb-3">
            <label>Orangtua Murid Baru</label>
            <input type="text" name="orangtua_murid_baru" class="form-control"
                value="{{ old('orangtua_murid_baru', $voucher->orangtua_murid_baru) }}">
        </div>

        <div class="mb-3">
            <label>Telp/HP Murid Baru</label>
            <input type="text" name="telp_hp_murid_baru" class="form-control"
                value="{{ old('telp_hp_murid_baru', $voucher->telp_hp_murid_baru) }}">
        </div>

        <button type="submit" class="btn btn-success">Simpan</button>
    </form>

    <!-- ===================== -->
    <!-- SELECT2 + JS AUTO FILL -->
    <!-- ===================== -->

    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

    <script>
        $(document).ready(function() {

            $('#nim').select2({
                placeholder: "-- Pilih NIM --",
                allowClear: true
            });

            // ✅ AUTO FILL + BIMBA UNIT & NO CABANG
            $('#nim').change(function() {
                var nim = $(this).val();
                if (nim) {
                    $.get('/get-buku-induk/' + nim, function(data) {
                        if (data) {
                            $('#nama_murid').val(data.nama_murid);
                            $('#orangtua').val(data.orangtua);
                            $('#telp_hp').val(data.no_telp_hp);

                            // ✅ NEW
                            $('#bimba_unit').val(data.biMBA_unit ?? data.bimba_unit ?? '');
                            $('#no_cabang').val(data.no_cabang ?? '');
                        } else {
                            $('#nama_murid').val('');
                            $('#orangtua').val('');
                            $('#telp_hp').val('');
                            $('#bimba_unit').val('');
                            $('#no_cabang').val('');
                        }
                    });
                } else {
                    $('#nama_murid').val('');
                    $('#orangtua').val('');
                    $('#telp_hp').val('');
                    $('#bimba_unit').val('');
                    $('#no_cabang').val('');
                }
            });

            // trigger saat edit load
            if ($('#nim').val()) {
                $('#nim').trigger('change');
            }

            // ✅ Status logic
            $('#status').change(function() {
                var status = $(this).val();
                if (status === 'pemakaian') {
                    $('#tanggal_pemakaian').prop('readonly', false);
                } else {
                    $('#tanggal_pemakaian').prop('readonly', true).val('');
                }
            });

            var currentStatus = $('#status').val();
            if (currentStatus === 'penyerahan') {
                $('#tanggal_pemakaian').prop('readonly', true);
            }
        });
    </script>
@endsection
