@extends('layouts.app')

@section('title', 'Edit Data Murid - ' . $bukuInduk->nama)

@section('content')
<div class="container-fluid py-4">
    <div class="row justify-content-center">
        <div class="col-12 col-xl-11">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-primary text-primary fw-bold d-flex justify-content-between align-items-center">
                    <h3 class="mb-0">Edit Murid: {{ $bukuInduk->nim }} | {{ $bukuInduk->nama }}</h3>
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
                                <h5 class="text-primary fw-bold border-bottom pb-0 mb-0">DETAIL MURID</h5>
                            </div>

                            @php
                                    $user = auth()->user();
                                @endphp

                                @if($user && empty($user->bimba_unit))
                                    {{-- ADMIN / belum punya unit → tampilkan select --}}
                                    <div class="col-lg-3">
                                        <label class="form-label fw-bold text-primary">
                                            Unit biMBA <span class="text-danger">*</span>
                                        </label>

                                        <select name="bimba_unit" id="bimba_unit_select"
                                            class="form-select @error('bimba_unit') is-invalid @enderror" required>

                                            <option value="">-- Pilih Unit biMBA --</option>

                                            @foreach($units as $namaUnit => $noCabang)
                                                <option value="{{ $namaUnit }}"
                                                    data-no-cabang="{{ $noCabang }}"
                                                    {{ old('bimba_unit', $bukuInduk->bimba_unit) === $namaUnit ? 'selected' : '' }}>
                                                    {{ $namaUnit }} ({{ $noCabang }})
                                                </option>
                                            @endforeach

                                        </select>

                                        @error('bimba_unit')
                                            <div class="text-danger small mt-1">{{ $message }}</div>
                                        @enderror
                                    </div>
                                @else
                                    {{-- USER → hidden (pakai unit dari login) --}}
                                    <input type="hidden" name="bimba_unit" value="{{ $user->bimba_unit }}">
                                @endif

                                 <div class="col-lg-3">
                                <label class="form-label">Nama Lengkap <span class="text-danger">*</span></label>
                                <input type="text" name="nama" class="form-control @error('nama') is-invalid @enderror"
                                       value="{{ old('nama', $bukuInduk->nama) }}" required>
                                @error('nama') <div class="text-danger small">{{ $message }}</div> @enderror
                            </div>

                            <div class="col-md-3">
                                <label class="form-label">Tempat Lahir</label>
                                <input type="text" name="tmpt_lahir" class="form-control"
                                       value="{{ old('tmpt_lahir', $bukuInduk->tmpt_lahir) }}">
                            </div>

                            <div class="col-md-3">
                                <label class="form-label">Tanggal Lahir</label>
                                <input type="date" name="tgl_lahir" class="form-control"
                                       value="{{ old('tgl_lahir', $bukuInduk->tgl_lahir?->format('Y-m-d')) }}">
                            </div>

                            <div class="col-md-3">
                                <label class="form-label">Usia</label>
                                <input type="text" class="form-control bg-light text-center" readonly
                                       value="{{ $bukuInduk->usia ?? '-' }} tahun">
                            </div>

                            <div class="col-md-3">
                                <label class="form-label">Tanggal Daftar</label>
                                <input type="date" name="tgl_daftar" class="form-control"
                                       value="{{ old('tgl_daftar', $bukuInduk->tgl_daftar?->format('Y-m-d')) }}">
                                <small class="text-muted">Jika kosong, akan otomatis diisi tanggal hari ini.</small>
                            </div>

                            <div class="col-md-3">
                                <label class="form-label">Tanggal Masuk <span class="text-danger">*</span></label>
                                <input type="date" name="tgl_masuk" class="form-control"
                                       value="{{ old('tgl_masuk', $bukuInduk->tgl_masuk?->format('Y-m-d')) }}" required>
                            </div>

                            <div class="col-md-3">
                                <label class="form-label">Tanggal Aktif</label>
                                <input type="date" name="tgl_aktif" class="form-control"
                                    value="{{ old('tgl_aktif', $bukuInduk->tgl_aktif?->format('Y-m-d')) }}">
                            </div>

                            <div class="col-md-3">
                                <label class="form-label">Lama Belajar</label>
                                <input type="text" class="form-control bg-light text-center" readonly
                                       value="{{ $bukuInduk->lama_bljr ?? '-' }}">
                            </div>

                            <!--NIM DI hidden -->
                            <div class="col-lg-3">
                                <input type="hidden" name="nim" value="{{ old('nim', $bukuInduk->nim) }}">
                                @error('nim') 
                                    <div class="text-danger small">{{ $message }}</div> 
                                @enderror
                            </div>

                                                  @php
                                    $user = auth()->user();
                                @endphp

                                @if($user && empty($user->no_cabang))
                                    {{-- ADMIN / belum punya no cabang → tampilkan --}}
                                    <div class="col-lg-3">
                                        <label class="form-label fw-bold">No Cabang</label>

                                        <input type="text"
                                            id="no_cabang_display"
                                            class="form-control bg-light text-center fw-bold fs-5 text-primary"
                                            readonly
                                            value="{{ old('no_cabang', $bukuInduk->no_cabang ?? '-') }}">

                                        <input type="hidden"
                                            name="no_cabang"
                                            id="no_cabang_hidden"
                                            value="{{ old('no_cabang', $bukuInduk->no_cabang ?? '') }}">
                                    </div>
                                @else
                                    {{-- USER → tidak tampil, pakai dari login --}}
                                    <input type="hidden" name="no_cabang" value="{{ $user->no_cabang }}">
                                @endif
                            
                            <!--- End -->
                            <div class="col-md-12">
                                <label class="form-label fw-bold">Status Saat Ini</label>
                                <div class="form-control text-center fs-5 fw-bold
                                    {{ $bukuInduk->status == 'Aktif' ? 'bg-success text-white' :
                                       ($bukuInduk->status == 'Keluar' ? 'bg-danger text-white' : 'bg-warning text-dark') }}">
                                    {{ $bukuInduk->status ?? 'Baru' }}
                                </div>
                            </div>

                            <div class="col-md-3">
                                <label class="form-label">Tanggal Keluar</label>
                                <input type="date" name="tgl_keluar" class="form-control"
                                       value="{{ old('tgl_keluar', $bukuInduk->tgl_keluar?->format('Y-m-d')) }}">
                            </div>

                            <div class="col-md-3">
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
                            <!-- Batas Akhir Murid Detail -->

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

                            <div class="col-md-3">
                                <label class="form-label">SPP <span class="text-danger">*</span></label>
                                <input type="text" id="spp_display" class="form-control bg-light text-success fw-bold text-center" readonly
                                       value="{{ $bukuInduk->spp ? 'Rp ' . number_format($bukuInduk->spp,0,',','.') : 'Belum ditentukan' }}">
                                <input type="hidden" name="spp" id="spp" value="{{ old('spp', $bukuInduk->spp) }}">
                            </div>

                            <!-- No Cabang tidak muncul untuk User -->

                            <!-- END -->
                            <div class="col-md-3">
                                <label class="form-label">Tahap</label>
                                <select name="tahap" id="tahap_select" class="form-select">
                                    <option value="">-- Pilih Tahap --</option>
                                    @foreach($tahapanOptions as $t)
                                        <option value="{{ $t }}"
                                            {{ old('tahap', $bukuInduk->tahap) == $t ? 'selected' : '' }}>
                                            {{ $t }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="col-md-3">
                                <label class="form-label">Tanggal Tahap</label>
                                <input type="date" name="tgl_tahapan" id="tgl_tahapan" class="form-control"
                                    value="{{ old('tgl_tahapan', $bukuInduk->tgl_tahapan?->format('Y-m-d')) }}">
                            </div>

                            <div class="w-100"></div> <!-- 🔥 ini bikin turun ke baris baru -->
                            

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

                            <div class="w-100"></div> <!-- 🔥 ini bikin turun ke baris baru -->

                             <div class="col-md-2">
                                <label class="form-label">Jenis KBM</label>
                                <select name="jenis_kbm" class="form-select">
                                    <option value="">-- Pilih Jenis --</option>
                                    @foreach($jenisKbmOptions as $jk)
                                        <option value="{{ $jk }}" {{ old('jenis_kbm', $bukuInduk->jenis_kbm) == $jk ? 'selected' : '' }}>{{ $jk }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="col-md-2">
                                <label class="form-label">Level</label>
                                <select name="level" id="level" class="form-select">
                                    <option value="">-- Pilih Level --</option>
                                    @foreach($levelOptions as $l)
                                        <option value="{{ $l }}" {{ old('level', $bukuInduk->level) == $l ? 'selected' : '' }}>
                                            {{ $l }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="col-md-3">
                                <label class="form-label">Tanggal Kenaikan Level</label>
                                <input type="date" name="tgl_level" id="tgl_level" class="form-control"
                                    value="{{ old('tgl_level', $bukuInduk->tgl_level?->format('Y-m-d')) }}">
                            </div>

                            <div class="col-md-4">
                                <label class="form-label">Keterangan Level</label>
                                <textarea name="keterangan_level" class="form-control">{{ old('keterangan_level', $bukuInduk->keterangan_level) }}</textarea>
                            </div>

                            <ul class="list-group mt-2">
                            @foreach($bukuInduk->levelHistories as $h)
                                <li class="list-group-item">
                                    <strong>{{ $h->level }}</strong> - 
                                    {{ \Carbon\Carbon::parse($h->tgl_level)->format('d M Y') }}

                                    @if($h->keterangan)
                                        <br>
                                        <small class="text-muted">
                                            {{ $h->keterangan }}
                                        </small>
                                    @endif
                                </li>
                            @endforeach
                            </ul>

                            

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

                            <div class="col-md-4">
                                <label class="form-label">Note</label>
                                <select name="note" class="form-select">
                                    <option value="">-- Tidak Ada --</option>
                                    @foreach($noteOptions as $n)
                                        <option value="{{ $n }}" {{ old('note', $bukuInduk->note) == $n ? 'selected' : '' }}>{{ $n }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="col-md-3">
                                    <label class="form-label">Info <span class="text-danger">*</span></label>
                                    <select name="info" id="info_select" class="form-select" required>
                                        <option value="">-- Pilih Info --</option>
                                        @foreach($infoOptions as $i)
                                            <option value="{{ $i }}"
                                                {{ old('info', $bukuInduk->info) == $i ? 'selected' : '' }}>
                                                {{ $i }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>

                                {{-- 🔥 KETERANGAN (AWALNYA DIHIDE) --}}
                                <div class="col-md-3 mt-2" id="keterangan_info_wrapper" style="display: none;">
                                    <label class="form-label">Keterangan Info</label>
                                    <textarea name="keterangan_info" class="form-control" rows="2">
                                {{ old('keterangan_info', $bukuInduk->keterangan_info ?? '') }}
                                    </textarea>
                            </div>

                            <div class="col-md-3">
                                <label class="form-label">No Cab/Marge</label>
                                <input name="no_pembayaran_murid" class="form-control" rows="2">{{ old('no_pembayaran_murid', $bukuInduk->no_pembayaran_murid) }}</input>
                            </div>

                            <div class="col-md-3">
                                <label class="form-label">No Pembayaran Murid</label>
                                <input name="no_cab_marge" class="form-control" rows="2">{{ old('no_cab_marge', $bukuInduk->no_cab_marge) }}</input>
                            </div>

                            <!-- 5. KBM & JADWAL -->
                            <div class="col-12"><hr class="my-4"></div>
                                <h4 class="col-12 mb-3">⏰ Jadwal biMBA</h4>

                            <div class="col-md-2">
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

                            <div class="col-md-4">
                                    <label class="form-label fw-bold text-info">Hari & Jam</label>
                                    <div class="form-control-plaintext border p-2 bg-light">
                                        @php
                                            $details = $bukuInduk->jadwal()
                                                ->select('hari', 'jam_ke', 'shift')
                                                ->distinct()
                                                ->orderByRaw("FIELD(hari, 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu')")
                                                ->orderBy('jam_ke')
                                                ->get();
                                        @endphp

                                        @if($details->isNotEmpty())
                                            @php
                                                // Ambil shift
                                                $shift = $details->first()->shift;

                                                // Ambil hari unik
                                                $hariList = $details->pluck('hari')->unique()->values()->toArray();

                                                // 🔥 Mapping jam_ke → jam asli
                                                $jamMap = [
                                                    1 => '08:00',
                                                    2 => '09:00',
                                                    3 => '10:00',
                                                    4 => '11:00',
                                                    5 => '12:00',
                                                    6 => '13:00',
                                                    7 => '14:00',
                                                    8 => '15:00',
                                                    9 => '16:00',
                                                ];

                                                // Ambil jam terkecil (biar konsisten)
                                                $jamKe = $details->min('jam_ke');

                                                $jam = $jamMap[$jamKe] ?? '-';
                                            @endphp

                                            <strong>{{ $shift }}</strong>
                                            <span class="text-muted">({{ implode(' | ', $hariList) }})</span>
                                            -
                                            <span class="fw-bold text-primary">{{ $jam }}</span>

                                        @else
                                            <span class="text-muted">Belum ada jadwal</span>
                                        @endif
                                    </div>
                                </div>

                            {{-- ================= BEASISWA ================= --}}
                             <div class="col-12"><hr class="my-4"></div>
                                <h4 class="col-12 mb-3">🗓️ Masa Aktif (Dhuafa & BNF)</h4>

                            <div class="col-md-4">
                                <label class="form-label fw-bold">Periode</label>

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
                                    Tanggal Mulai
                                </label>
                                <input type="date"
                                    name="tgl_mulai"
                                    class="form-control"
                                    value="{{ old('tgl_mulai', $bukuInduk->tgl_mulai?->format('Y-m-d')) }}">
                            </div>

                            <div class="col-md-4">
                                <label class="form-label fw-bold">
                                    Tanggal Akhir
                                </label>
                                <input type="date"
                                    name="tgl_akhir"
                                    class="form-control"
                                    value="{{ old('tgl_akhir', $bukuInduk->tgl_akhir?->format('Y-m-d')) }}">
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
                                    <label class="form-check-label fw-bold text-info" for="alert">
                                        Beasiswa Aktif
                                    </label>
                                </div>
                            </div>


                            <!-- Supply Modul -->
                            <div class="col-12"><hr class="my-4"></div>
                                <h4 class="col-12 mb-3">📚 Supply Modul</h4>
                                

                            <div class="col-md-6">
                                <label class="form-label">Asal Modul</label>
                                <input type="text" name="asal_modul" class="form-control"
                                       value="{{ old('asal_modul', $bukuInduk->asal_modul) }}">
                            </div>

                            <div class="col-md-12">
                                <label class="form-label text-primary fw-bold">KETERANGAN OPTIONAL</label>
                                <textarea name="keterangan_optional" class="form-control" rows="2">{{ old('keterangan_optional', $bukuInduk->keterangan_optional) }}</textarea>
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

document.addEventListener('DOMContentLoaded', function () {
    const infoSelect = document.getElementById('info_select');
    const keteranganWrapper = document.getElementById('keterangan_info_wrapper');

    function toggleKeterangan() {
        if (infoSelect.value === 'Lainnya') {
            keteranganWrapper.style.display = 'block';
        } else {
            keteranganWrapper.style.display = 'none';
        }
    }

    // saat berubah
    infoSelect.addEventListener('change', toggleKeterangan);

    // saat load (biar tetap muncul kalau edit)
    toggleKeterangan();
});
document.addEventListener('DOMContentLoaded', function () {
    const tahapSelect = document.getElementById('tahap_select');
    const tglTahapan = document.getElementById('tgl_tahapan');

    function handleTahapChange() {
        if (tahapSelect.value === 'Lanjutan') {

            // isi otomatis kalau kosong
            if (!tglTahapan.value) {
                const today = new Date();
                const yyyy = today.getFullYear();
                const mm = String(today.getMonth() + 1).padStart(2, '0');
                const dd = String(today.getDate()).padStart(2, '0');

                tglTahapan.value = `${yyyy}-${mm}-${dd}`;
            }

        } else if (tahapSelect.value === 'Persiapan') {

            // 🔥 kosongkan kalau pilih Persiapan
            tglTahapan.value = '';

        }
    }

    tahapSelect.addEventListener('change', handleTahapChange);

    // jalankan saat pertama load
    handleTahapChange();
});

document.getElementById('level').addEventListener('change', function () {
    let tglInput = document.getElementById('tgl_level');

    // kalau level dipilih & tanggal masih kosong → isi otomatis
    if (this.value && !tglInput.value) {
        let today = new Date().toISOString().split('T')[0];
        tglInput.value = today;
    }
});
</script>
@endpush