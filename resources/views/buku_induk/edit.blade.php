@extends('layouts.app')

@section('title', 'Edit Data Murid - {{ $bukuInduk->nama }}')

@section('content')
<div class="container-fluid py-4">
    <div class="row justify-content-center">
        <div class="col-12 col-xl-11">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                    <h3 class="mb-0">Edit Murid: {{ $bukuInduk->nama }} (NIM: {{ $bukuInduk->nim }})</h3>
                    <a href="{{ route('buku_induk.index') }}" class="btn btn-light btn-sm">Kembali ke Daftar</a>
                </div>

                <div class="card-body p-4">
                    @if($errors->any())
                        <div class="alert alert-danger alert-dismissible fade show">
                            <ul class="mb-0">
                                @foreach($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    @endif

                    <form action="{{ route('buku_induk.update', $bukuInduk->id) }}" method="POST">
                        @csrf
                        @method('PUT')

                        <div class="row g-3">

                            <!-- 1. IDENTITAS UTAMA -->
                            <div class="col-12">
                                <h5 class="text-primary border-bottom pb-2 mb-3">Identitas Murid</h5>
                            </div>

                            <div class="col-lg-6">
                                <label class="form-label fw-bold">NIM <span class="text-danger"> *</span></label>
                                <input type="text" name="nim" class="form-control @error('nim') is-invalid @enderror"
                                       value="{{ old('nim', $bukuInduk->nim) }}" required>
                                @error('nim') <div class="text-danger small">{{ $message }}</div> @enderror
                            </div>

                            <div class="col-lg-6">
                                <label class="form-label fw-bold">Nama Lengkap <span class="text-danger">*</span></label>
                                <input type="text" name="nama" class="form-control @error('nama') is-invalid @enderror"
                                       value="{{ old('nama', $bukuInduk->nama) }}" required>
                                @error('nama') <div class="text-danger small">{{ $message }}</div> @enderror
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">Tempat Lahir</label>
                                <input type="text" name="tmpt_lahir" class="form-control"
                                       value="{{ old('tmpt_lahir', $bukuInduk->tmpt_lahir) }}">
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">Tanggal Lahir</label>
                                <input type="date" name="tgl_lahir" class="form-control"
                                       value="{{ old('tgl_lahir', $bukuInduk->tgl_lahir?->format('Y-m-d')) }}">
                            </div>

                            <!-- 2. UNIT & CABANG -->
                            <div class="col-12 mt-4">
                                <h5 class="text-primary border-bottom pb-2 mb-3">Unit & Cabang</h5>
                            </div>

                            <div class="col-lg-8">
                                <label class="form-label fw-bold text-primary">Unit biMBA <span class="text-danger">*</span></label>
                                <select name="bimba_unit" id="bimba_unit_select" class="form-select @error('bimba_unit') is-invalid @enderror" required>
                                    <option value="">-- Pilih Unit biMBA --</option>
                                    @foreach($units as $namaUnit => $noCabang)
                                        <option value="{{ $namaUnit }}"
                                            data-no-cabang="{{ $noCabang }}"
                                            {{ old('bimba_unit', $bukuInduk->bimba_unit) === $namaUnit ? 'selected' : '' }}>
                                            {{ $namaUnit }} ({{ $noCabang }})
                                        </option>
                                    @endforeach
                                </select>
                                @error('bimba_unit') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                            </div>

                            <div class="col-lg-4">
                                <label class="form-label fw-bold">No Cabang</label>
                                <input type="text" id="no_cabang_display" class="form-control bg-light text-center fw-bold fs-5 text-primary" readonly
                                       value="{{ old('no_cabang', $bukuInduk->no_cabang ?? '-') }}">
                                <input type="hidden" name="no_cabang" id="no_cabang_hidden"
                                       value="{{ old('no_cabang', $bukuInduk->no_cabang ?? '') }}">
                            </div>

                            <!-- 3. TANGGAL & STATUS -->
                            <div class="col-12 mt-4">
                                <h5 class="text-primary border-bottom pb-2 mb-3">Tanggal & Status</h5>
                            </div>

                            <div class="col-md-4">
                                <label class="form-label">Tanggal Masuk <span class="text-danger">*</span></label>
                                <input type="date" name="tgl_masuk" class="form-control"
                                       value="{{ old('tgl_masuk', $bukuInduk->tgl_masuk?->format('Y-m-d')) }}" required>
                            </div>

                            <div class="col-md-4">
                                <label class="form-label">Usia</label>
                                <input type="text" class="form-control bg-light text-center" readonly
                                       value="{{ $bukuInduk->usia ?? '-' }} tahun">
                            </div>

                            <div class="col-md-4">
                                <label class="form-label">Lama Belajar</label>
                                <input type="text" class="form-control bg-light text-center" readonly
                                       value="{{ $bukuInduk->lama_bljr ?? '-' }}">
                            </div>

                            <div class="col-md-6">
                                <label class="form-label fw-bold">Status Saat Ini</label>
                                <div class="form-control text-center fs-4 fw-bold
                                    {{ $bukuInduk->status == 'Aktif' ? 'bg-success text-white' :
                                       ($bukuInduk->status == 'Keluar' ? 'bg-danger text-white' : 'bg-warning text-dark') }}">
                                    {{ $bukuInduk->status ?? 'Baru' }}
                                </div>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">Tanggal Keluar</label>
                                <input type="date" name="tgl_keluar" class="form-control"
                                       value="{{ old('tgl_keluar', $bukuInduk->tgl_keluar?->format('Y-m-d')) }}">
                            </div>

                            <!-- 4. AKADEMIK & SPP -->
                            <div class="col-12 mt-4">
                                <h5 class="text-primary border-bottom pb-2 mb-3">Akademik & SPP</h5>
                            </div>

                            <div class="col-md-3">
                                <label class="form-label">Kelas <span class="text-danger">*</span></label>
                                <select name="kelas" class="form-select" required>
                                    <option value="">-- Pilih Kelas --</option>
                                    @foreach($kelasOptions as $k)
                                        <option value="{{ $k }}" {{ old('kelas', $bukuInduk->kelas) == $k ? 'selected' : '' }}>{{ $k }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="col-md-3">
                                <label class="form-label">Tahap</label>
                                <select name="tahap" class="form-select">
                                    <option value="">-- Pilih Tahap --</option>
                                    @foreach($tahapanOptions as $t)
                                        <option value="{{ $t }}" {{ old('tahap', $bukuInduk->tahap) == $t ? 'selected' : '' }}>{{ $t }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="col-md-3">
                                <label class="form-label">Gol <span class="text-danger">*</span></label>
                                <select name="gol" id="gol" class="form-select" required>
                                    <option value="">-- Pilih Gol --</option>
                                    @foreach($HargaSaptataruna as $item)
                                        <option value="{{ $item->kode }}" {{ old('gol', $bukuInduk->gol) == $item->kode ? 'selected' : '' }}>
                                            {{ $item->kode }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="col-md-3">
                                <label class="form-label">KD <span class="text-danger">*</span></label>
                                <select name="kd" id="kd" class="form-select" required>
                                    <option value="">-- Pilih KD --</option>
                                    @foreach($kdOptions as $kd)
                                        <option value="{{ $kd }}" {{ old('kd', $bukuInduk->kd) == $kd ? 'selected' : '' }}>
                                            {{ $kd }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="col-md-4">
                                <label class="form-label">SPP <span class="text-danger">*</span></label>
                                <input type="text" id="spp_display" class="form-control bg-light text-success fw-bold text-center" readonly
                                       value="{{ $bukuInduk->spp ? 'Rp ' . number_format($bukuInduk->spp,0,',','.') : 'Belum ditentukan' }}">
                                <input type="hidden" name="spp" id="spp" value="{{ old('spp', $bukuInduk->spp) }}">
                            </div>

                            <div class="col-md-4">
                                <label class="form-label">Guru / Motivator</label>
                                <select name="guru" class="form-select">
                                    <option value="">-- Pilih Guru --</option>
                                    @foreach($profil as $p)
                                        <option value="{{ $p->nama }}"
                                            {{ old('guru', $bukuInduk->guru) == $p->nama ? 'selected' : '' }}>
                                            {{ $p->nama }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="col-md-4">
                                <label class="form-label">Petugas Trial</label>
                                <select name="petugas_trial" class="form-select">
                                    <option value="">-- Tidak Ada --</option>
                                    @foreach($profil as $p)
                                        <option value="{{ $p->nama }}"
                                            {{ old('petugas_trial', $bukuInduk->petugas_trial) == $p->nama ? 'selected' : '' }}>
                                            {{ $p->nama }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <!-- 5. KBM & JADWAL -->
                            <div class="col-12 mt-4">
                                <h5 class="text-primary border-bottom pb-2 mb-3">Jadwal & KBM</h5>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label fw-bold text-info">Kode Jadwal <span class="text-danger">*</span></label>
                                <select name="kode_jadwal" class="form-select" required>
                                    <option value="">-- Pilih Kode Jadwal --</option>
                                    @foreach($kodeJadwalOptions as $kode)
                                        <option value="{{ $kode }}"
                                            {{ old('kode_jadwal', $bukuInduk->kode_jadwal) == $kode ? 'selected' : '' }}>
                                            {{ $kode }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="col-md-6">
    <label class="form-label fw-bold text-info">Hari & Jam</label>
    <div class="form-control-plaintext border p-2 bg-light">
        @php
            $details = $bukuInduk->jadwal()
                ->select('hari', 'jam_ke', 'shift')
                ->distinct()
                ->orderByRaw("FIELD(hari, 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu')")
                ->get();
        @endphp

        @if($details->isNotEmpty())
            @foreach($details->groupBy('hari') as $hari => $group)
                <div>
                    <strong>{{ $hari }}</strong>                
                </div>
            @endforeach
        @else
            <span class="text-muted">Belum ada jadwal</span>
        @endif
    </div>
    <!-- Opsional: tambah info kecil -->
    <small class="form-text text-muted">
        Jadwal diambil dari sinkronisasi. Edit melalui tombol "Generate Jadwal" jika perlu perubahan.
    </small>
</div>

                            <div class="col-md-4">
                                <label class="form-label">Level</label>
                                <select name="level" class="form-select">
                                    <option value="">-- Pilih Level --</option>
                                    @foreach($levelOptions as $l)
                                        <option value="{{ $l }}" {{ old('level', $bukuInduk->level) == $l ? 'selected' : '' }}>{{ $l }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="col-md-4">
                                <label class="form-label">Jenis KBM</label>
                                <select name="jenis_kbm" class="form-select">
                                    <option value="">-- Pilih Jenis --</option>
                                    @foreach($jenisKbmOptions as $jk)
                                        <option value="{{ $jk }}" {{ old('jenis_kbm', $bukuInduk->jenis_kbm) == $jk ? 'selected' : '' }}>{{ $jk }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <!-- 6. KONTAK & ORANG TUA -->
                            <div class="col-12 mt-4">
                                <h5 class="text-primary border-bottom pb-2 mb-3">Kontak & Orang Tua</h5>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">Nama Orang Tua <span class="text-danger">*</span></label>
                                <input type="text" name="orangtua" class="form-control"
                                       value="{{ old('orangtua', $bukuInduk->orangtua) }}" required>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">No. Telp / HP <span class="text-danger">*</span></label>
                                <input type="text" name="no_telp_hp" class="form-control"
                                       value="{{ old('no_telp_hp', $bukuInduk->no_telp_hp) }}" required>
                            </div>

                            <div class="col-md-12">
                                <label class="form-label">Alamat Murid</label>
                                <textarea name="alamat_murid" class="form-control" rows="2">{{ old('alamat_murid', $bukuInduk->alamat_murid) }}</textarea>
                            </div>

                            {{-- ================= BEASISWA ================= --}}
                            <div class="col-12 mt-4">
                                <h5 class="text-danger border-bottom pb-2 mb-3">
                                    Beasiswa
                                </h5>
                            </div>

                            <div class="col-md-4">
                                <label class="form-label fw-bold">Periode Beasiswa</label>

                                {{-- TAMPILAN SAJA --}}
                                <input type="text"
                                    class="form-control bg-light fw-bold text-danger"
                                    value="{{ $bukuInduk->periode ?? '-' }}"
                                    readonly>

                                {{-- NILAI ASLI UNTUK SUBMIT --}}
                                <input type="hidden"
                                    name="periode"
                                    value="{{ $bukuInduk->periode }}">
                            </div>


                            <div class="col-md-4">
                                <label class="form-label fw-bold">
                                    Tanggal Mulai Beasiswa
                                </label>
                                <input type="date"
                                    name="tgl_mulai"
                                    class="form-control"
                                    value="{{ old('tgl_mulai', $bukuInduk->tgl_mulai?->format('Y-m-d')) }}">
                            </div>

                            <div class="col-md-4">
                                <label class="form-label fw-bold">
                                    Tanggal Akhir Beasiswa
                                </label>
                                <input type="date"
                                    name="tgl_akhir"
                                    class="form-control"
                                    value="{{ old('tgl_akhir', $bukuInduk->tgl_akhir?->format('Y-m-d')) }}">
                            </div>

                            <div class="col-md-4">
    <label class="form-label fw-bold">Note Garansi</label>
    <select name="note_garansi" class="form-select">
        <option value="">-- Tidak Ada --</option>
        @foreach($noteGaransiOptions as $opt)
            <option value="{{ $opt }}"
                {{ old('note_garansi', $bukuInduk->note_garansi) === $opt ? 'selected' : '' }}>
                {{ $opt }}
            </option>
        @endforeach
    </select>
</div>


                            <div class="col-md-6 mt-3">
                                <div class="form-check">
                                    <input
                                        class="form-check-input"
                                        type="checkbox"
                                        name="alert"
                                        id="alert"
                                        value="aktif"
                                        {{ old('alert', $bukuInduk->alert) === 'aktif' ? 'checked' : '' }}
                                    >
                                    <label class="form-check-label fw-bold text-danger" for="alert">
                                        Beasiswa Aktif
                                    </label>
                                </div>
                                <small class="text-muted">
                                    Akan otomatis aktif jika tanggal masih berlaku
                                </small>
                            </div>


                            <!-- 7. NOTE & LAIN-LAIN -->
                            <div class="col-12 mt-4">
                                <h5 class="text-primary border-bottom pb-2 mb-3">Note & Keterangan Lain</h5>
                            </div>

                            <div class="col-md-4">
                                <label class="form-label">Note</label>
                                <select name="note" class="form-select">
                                    <option value="">-- Tidak Ada --</option>
                                    @foreach($noteOptions as $n)
                                        <option value="{{ $n }}" {{ old('note', $bukuInduk->note) == $n ? 'selected' : '' }}>{{ $n }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">Kategori Keluar</label>
                                <select name="kategori_keluar" class="form-select">
                                    <option value="">-- Pilih Kategori --</option>
                                    @foreach($kategoriKeluarOptions as $kk)
                                        <option value="{{ $kk }}" {{ old('kategori_keluar', $bukuInduk->kategori_keluar) == $kk ? 'selected' : '' }}>{{ $kk }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">Alasan Keluar</label>
                                <input type="text" name="alasan" class="form-control"
                                       value="{{ old('alasan', $bukuInduk->alasan) }}">
                            </div>

                            <div class="col-md-12">
                                <label class="form-label">Keterangan Optional</label>
                                <textarea name="keterangan_optional" class="form-control" rows="2">{{ old('keterangan_optional', $bukuInduk->keterangan_optional) }}</textarea>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">Asal Modul</label>
                                <input type="text" name="asal_modul" class="form-control"
                                       value="{{ old('asal_modul', $bukuInduk->asal_modul) }}">
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">Info <span class="text-danger">*</span></label>
                                <select name="info" class="form-select" required>
                                    <option value="">-- Pilih Info --</option>
                                    @foreach($infoOptions as $i)
                                        <option value="{{ $i }}" {{ old('info', $bukuInduk->info) == $i ? 'selected' : '' }}>{{ $i }}</option>
                                    @endforeach
                                </select>
                            </div>

                        </div>

                        <hr class="my-5">

                        <div class="text-end">
                            <a href="{{ route('buku_induk.index') }}" class="btn btn-secondary btn-lg px-5 me-3">Batal</a>
                            <button type="submit" class="btn btn-success btn-lg px-5">
                                <i class="fas fa-save"></i> Update Data Murid
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
// UPDATE NO CABANG OTOMATIS
const unitSelect = document.getElementById('bimba_unit_select');
const displayCabang = document.getElementById('no_cabang_display');
const hiddenCabang = document.getElementById('no_cabang_hidden');

unitSelect?.addEventListener('change', function() {
    const option = this.options[this.selectedIndex];
    const cabang = option.getAttribute('data-no-cabang') || '-';
    displayCabang.value = cabang;
    hiddenCabang.value = cabang;
});

// Jalankan saat load
document.addEventListener('DOMContentLoaded', function() {
    if (unitSelect && unitSelect.value) {
        const option = unitSelect.options[unitSelect.selectedIndex];
        const cabang = option.getAttribute('data-no-cabang') || '-';
        displayCabang.value = cabang;
        hiddenCabang.value = cabang;
    }

    // UPDATE SPP OTOMATIS
    const sppMapping = @json($sppMapping);
    const golSelect = document.getElementById('gol');
    const kdSelect = document.getElementById('kd');
    const sppHidden = document.getElementById('spp');
    const sppDisplay = document.getElementById('spp_display');

    function updateSPP() {
        const g = golSelect?.value;
        const k = kdSelect?.value;
        if (g && k && sppMapping[g] && sppMapping[g][k] !== undefined) {
            const harga = parseInt(sppMapping[g][k]) || 0;
            sppHidden.value = harga;
            sppDisplay.value = harga ? 'Rp ' + new Intl.NumberFormat('id-ID').format(harga) : 'Belum ditentukan';
        } else {
            sppHidden.value = '';
            sppDisplay.value = 'Belum ditentukan';
        }
    }

    golSelect?.addEventListener('change', updateSPP);
    kdSelect?.addEventListener('change', updateSPP);
    updateSPP(); // jalankan awal
});

document.addEventListener('DOMContentLoaded', function () {
    const start = document.querySelector('[name="tgl_mulai"]');
    const end   = document.querySelector('[name="tgl_akhir"]');
    const alert = document.getElementById('alert');

    function cekBeasiswa() {
        if (!start.value || !end.value) return;

        const today = new Date();
        const mulai = new Date(start.value);
        const akhir = new Date(end.value);

        if (today >= mulai && today <= akhir) {
            alert.checked = true;
        }
    }

    start?.addEventListener('change', cekBeasiswa);
    end?.addEventListener('change', cekBeasiswa);

    cekBeasiswa();
});
</script>
@endpush