@extends('layouts.app')

@section('title', 'Tambah Pendaftaran')

@section('content')
<div class="container">
    <h2 class="mb-3">Tambah Pendaftaran</h2>

    @if (session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    @if ($errors->any())
        <div class="alert alert-danger">
            <ul class="mb-0">
                @foreach ($errors->all() as $e)
                    <li>{{ $e }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @php
        $students           = $students           ?? collect();
        $selectedStudentId  = $selectedStudentId  ?? old('student_id');
        $prefilledNim       = $prefilledNim       ?? '';
        $prefilledNama      = $prefilledNama      ?? '';
        $prefilledUnit      = $prefilledUnit      ?? '';
        $prefilledCabang    = $prefilledCabang    ?? '';
        $prefilledTglLahir  = $prefilledTglLahir  ?? '';
        $prefilledTglMasuk  = $prefilledTglMasuk  ?? '';
        $tahapanOptions     = $tahapanOptions     ?? [];
        $kelasOptions       = $kelasOptions       ?? [];
        $hargaSaptataruna   = $hargaSaptataruna   ?? collect();
        $kdOptions          = $kdOptions          ?? [];
        $sppMapping         = $sppMapping         ?? [];
        $guruOptions        = $guruOptions        ?? [];
    @endphp

    <div class="card">
        <div class="card-body">
            <form method="POST" action="{{ route('registrations.store') }}" enctype="multipart/form-data">
                @csrf

                {{-- PILIH MURID --}}
                <div class="mb-3">
                    <label class="form-label fw-bold">Murid</label>

                    @if ($students->isEmpty())
                        <div class="alert alert-warning mb-2">
                            Belum ada data murid. Set status trial ke <b>LANJUT</b> dulu.
                        </div>
                    @endif

                    <select name="student_id" class="form-select" required
                            id="studentSelect" {{ $students->isEmpty() ? 'disabled' : '' }}>
                        <option value="">-- Pilih Murid --</option>
                        @foreach ($students as $s)
                            <option value="{{ $s->id }}"
                                data-unit="{{ $s->bimba_unit ?? '' }}"
                                data-cabang="{{ $s->no_cabang ?? '' }}"
                                data-nim="{{ $s->nim ?? '' }}"
                                data-nama="{{ $s->nama ?? '' }}"
                                data-tgllahir="{{ $s->tgl_lahir ?? $s->muridTrial->tgl_lahir ?? '' }}"
data-orangtua="{{ $s->orangtua ?? $s->muridTrial->orangtua ?? '' }}"
data-alamat="{{ $s->alamat ?? $s->muridTrial->alamat ?? '' }}"
data-info="{{ $s->muridTrial->info ?? '' }}"
                                {{ (int) $selectedStudentId === (int) $s->id ? 'selected' : '' }}>
                                {{ $s->nim ?: '—' }} — {{ $s->nama }}
                            </option>
                        @endforeach
                    </select>
                </div>

                {{-- UNIT & CABANG (OTOMATIS DARI MURID) --}}
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-bold text-primary">Unit biMBA</label>
                        <input type="text"
                               id="displayUnit"
                               class="form-control bg-light"
                               value="{{ old('bimba_unit', $prefilledUnit) }}"
                               readonly>
                        <input type="hidden"
                               name="bimba_unit"
                               value="{{ old('bimba_unit', $prefilledUnit) }}">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-bold text-primary">No Cabang</label>
                        <input type="text"
                               id="displayCabang"
                               class="form-control bg-light"
                               value="{{ old('no_cabang', $prefilledCabang) }}"
                               readonly>
                        <input type="hidden"
                               name="no_cabang"
                               value="{{ old('no_cabang', $prefilledCabang) }}">
                    </div>
                </div>

                {{-- STATUS & TANGGAL DAFTAR --}}
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-bold">Status</label>
                        @php
                            $st = old('status', 'pending');
                            $isAdmin = auth()->check() &&
                                       (auth()->user()->role === 'admin' || (auth()->user()->is_admin ?? false));
                        @endphp
                        <select name="status" class="form-select" required>
                            <option value="pending"  {{ $st === 'pending'  ? 'selected' : '' }}>Pending</option>
                            <option value="verified" {{ $st === 'verified' ? 'selected' : '' }}>Verified</option>
                            @if ($isAdmin)
                                <option value="accepted" {{ $st === 'accepted' ? 'selected' : '' }}>Accepted</option>
                            @else
                                <option value="accepted" disabled>Accepted (admin only)</option>
                            @endif
                            <option value="rejected" {{ $st === 'rejected' ? 'selected' : '' }}>Rejected</option>
                        </select>
                    </div>

                    <div class="col-md-6 mb-3">
    <label class="form-label fw-bold">Tanggal Daftar</label>
    <input type="date"
           name="tanggal_daftar"
           class="form-control"
           value="{{ old('tanggal_daftar', 
               $selectedStudent?->muridTrial?->tgl_mulai?->format('Y-m-d') 
               ?? now()->format('Y-m-d')
           ) }}">
    @error('tanggal_daftar')
        <div class="text-danger small mt-1">{{ $message }}</div>
    @enderror
</div>
                </div>

                {{-- IDENTITAS MURID (NIM, NAMA, TGL LAHIR, TGL MASUK) --}}
                <hr class="my-4">
                <h5 class="mb-3">Identitas Murid</h5>

                <div class="row mb-3">
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-bold">NIM</label>
                        <input type="text"
                               id="displayNim"
                               class="form-control bg-light"
                               value="{{ $prefilledNim }}"
                               readonly>
                        <input type="hidden" name="bi[nim]" value="{{ $prefilledNim }}">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-bold">Nama Lengkap</label>
                        <input type="text"
                               id="displayNama"
                               class="form-control bg-light"
                               value="{{ $prefilledNama }}"
                               readonly>
                        <input type="hidden" name="bi[nama]" value="{{ $prefilledNama }}">
                    </div>
                </div>

                <div class="row mb-4">
    <div class="col-md-6 mb-3">
        <label class="form-label fw-bold">Tanggal Penerimaan</label>
        <input type="date"
               name="tanggal_penerimaan"
               class="form-control"
               value="{{ old('tanggal_penerimaan', now()->format('Y-m-d')) }}">
        @error('tanggal_penerimaan')
            <div class="text-danger small mt-1">{{ $message }}</div>
        @enderror
    </div>
</div>

                {{-- DATA BUKU INDUK --}}
                <hr class="my-4">
                <h5 class="mb-3">Data Buku Induk</h5>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Tahapan</label>
                        <select class="form-control" name="bi[tahap]">
                            <option value="">-- Pilih Tahapan --</option>
                            @foreach ($tahapanOptions as $t)
                                <option value="{{ $t }}" {{ old('bi.tahap') === $t ? 'selected' : '' }}>
                                    {{ $t }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-md-6 mb-3">
                        <label class="form-label">Kelas</label>
                        <select class="form-control" name="bi[kelas]">
                            <option value="">-- Pilih Kelas --</option>
                            @foreach ($kelasOptions as $k)
                                <option value="{{ $k }}" {{ old('bi.kelas') === $k ? 'selected' : '' }}>
                                    {{ $k }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-md-6 mb-3">
                        <label class="form-label text-primary fw-bold">Guru Pengajar</label>
                        <select class="form-control" name="bi[guru]">
                            <option value="">-- Pilih Guru --</option>
                            @foreach ($guruOptions as $guru)
                                <option value="{{ $guru }}" {{ old('bi.guru') === $guru ? 'selected' : '' }}>
                                    {{ $guru }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-md-6 mb-3">
                        <label class="form-label">Gol</label>
                        <select class="form-control" name="bi[gol]" id="bi_gol">
                            <option value="">-- Pilih Gol --</option>
                            @foreach ($hargaSaptataruna->unique('kode') as $row)
                                @if ($row->kode)
                                    <option value="{{ $row->kode }}" {{ old('bi.gol') === $row->kode ? 'selected' : '' }}>
                                        {{ $row->kode }}
                                    </option>
                                @endif
                            @endforeach
                        </select>
                    </div>

                    <div class="col-md-6 mb-3">
                        <label class="form-label">KD</label>
                        <select class="form-control" name="bi[kd]" id="bi_kd">
                            <option value="">-- Pilih KD --</option>
                            @foreach ($kdOptions as $kd)
                                <option value="{{ $kd }}" {{ old('bi.kd') === $kd ? 'selected' : '' }}>
                                    {{ $kd }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-md-6 mb-3">
                        <label class="form-label">SPP</label>
                        <input type="text"
                               class="form-control"
                               id="bi_spp_display"
                               readonly
                               placeholder="Otomatis terisi">
                        <input type="hidden" name="bi[spp]" id="bi_spp" value="{{ old('bi.spp') }}">
                    </div>
                </div>
                <div class="row mb-4">

    <div class="col-md-6 mb-3">
        <label class="form-label fw-bold">Tempat Lahir</label>
        <input type="text"
               class="form-control"
               name="bi[tmpt_lahir]"
               value="{{ old('bi.tmpt_lahir', $prefilledTmptLahir) }}"
               placeholder="Contoh: Bandung">
    </div>

    <div class="col-md-6 mb-3">
        <label class="form-label fw-bold">Tanggal Lahir</label>
        <input type="date"
name="bi[tanggal_lahir]"
class="form-control"
value="{{ old('bi.tanggal_lahir', $prefilledTglLahir) }}">
    </div>

    <div class="col-md-6 mb-3">
        <label class="form-label fw-bold">Nama Orang Tua</label>
        <input type="text"
name="bi[orangtua]"
class="form-control"
value="{{ old('bi.orangtua', $prefilledOrangtua) }}">
    </div>

    <div class="col-md-6 mb-3">
        <label class="form-label fw-bold">Info / Sumber Informasi</label>
        <input type="text"
name="bi[info]"
class="form-control"
value="{{ old('bi.info', $prefilledInfo) }}">
    </div>

</div>

                {{-- ATTACHMENT --}}
                <div class="mb-3">
                    <label class="form-label">Upload Dokumen (PDF/JPG/PNG) - opsional</label>
                    <input type="file"
                           name="attachment"
                           class="form-control"
                           accept=".pdf,.jpg,.jpeg,.png,.webp">
                    <small class="text-muted">Maks 3MB</small>
                </div>

                {{-- TOMBOL AKSI --}}
                <div class="d-flex gap-2 mt-4">
                    <button type="submit"
                            class="btn btn-primary"
                            {{ $students->isEmpty() ? 'disabled' : '' }}>
                        Simpan Pendaftaran
                    </button>
                    <a href="{{ route('registrations.index') }}" class="btn btn-secondary">
                        Batal
                    </a>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const select      = document.getElementById('studentSelect');
    const unit        = document.getElementById('displayUnit');
    const cabang      = document.getElementById('displayCabang');
    const nim         = document.getElementById('displayNim');
    const nama        = document.getElementById('displayNama');
    const hiddenNim   = document.querySelector('input[name="bi[nim]"]');
    const hiddenNama  = document.querySelector('input[name="bi[nama]"]');
    const hiddenUnit  = document.querySelector('input[name="bimba_unit"]');
    const hiddenCabang= document.querySelector('input[name="no_cabang"]');

    function updateInfo() {
        const option = select.options[select.selectedIndex];

        if (option && option.value) {
            const vUnit   = option.dataset.unit   || '';
            const vCabang = option.dataset.cabang || '';
            const vNim    = option.dataset.nim    || '';
            const vNama   = option.dataset.nama   || '';

            unit.value   = vUnit;
            cabang.value = vCabang;
            nim.value    = vNim;
            nama.value   = vNama;

            hiddenUnit.value   = vUnit;
            hiddenCabang.value = vCabang;
            hiddenNim.value    = vNim;
            hiddenNama.value   = vNama;
        }
    }

    // Jalankan saat halaman dimuat (misal dari Trial langsung ke sini)
    if (select) {
        updateInfo();
        select.addEventListener('change', updateInfo);
    }

    // === SPP OTOMATIS ===
    const mapping = @json($sppMapping);
    const gol     = document.getElementById('bi_gol');
    const kd      = document.getElementById('bi_kd');
    const spp     = document.getElementById('bi_spp');
    const disp    = document.getElementById('bi_spp_display');

    function updateSPP() {
        const g = gol?.value || '';
        const k = kd?.value  || '';
        const val = mapping[g]?.[k] ?? '';

        if (spp)  spp.value  = val || '';
        if (disp) disp.value = val
            ? new Intl.NumberFormat('id-ID').format(val)
            : '';
    }

    gol?.addEventListener('change', updateSPP);
    kd?.addEventListener('change', updateSPP);
    updateSPP();
});
</script>
@endsection
