@extends('layouts.app')

@section('title', 'Tambah Data Buku Induk')

@section('content')
<div class="card card-body">
    <h2 class="mb-4">Tambah Data Buku Induk</h2>

    @if (session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    @if (session('error'))
        <div class="alert alert-danger">{{ session('error') }}</div>
    @endif

    <form action="{{ route('buku_induk.store') }}" method="POST">
        @csrf

        <div class="row">

            {{-- NIM – OTOMATIS TERISI --}}
            <div class="col-md-6 mb-3">
                <label class="form-label">NIM <span class="text-danger">*</span></label>
                <div class="input-group">
                    <span class="input-group-text" id="nim-display">
                        {{-- Tampilan NIM yang otomatis --}}
                        <span id="nim-preview">
                            @if (!$isAdmin && $autoNim)
                                {{ $autoNim }}
                            @else
                                ----- (akan terisi otomatis)
                            @endif
                        </span>
                    </span>
                    <input type="hidden" name="nim" id="nim" value="{{ old('nim', $autoNim ?? '') }}">
                    <input type="hidden" name="nim_suffix" id="nim_suffix" value="{{ old('nim_suffix', $autoNimSuffix ?? '') }}">
                </div>
                <small class="text-muted">
                    @if ($isAdmin)
                        Pilih unit → NIM otomatis terisi (urut terakhir + 1).
                    @else
                        NIM otomatis dari unit Anda (urut terakhir + 1).
                    @endif
                </small>
                @error('nim') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
            </div>

            {{-- UNIT biMBA & NO CABANG – Hanya untuk ADMIN --}}
            @if ($isAdmin)
                <div class="col-md-6">
                    <label for="bimba_unit">Unit biMBA <span class="text-danger">*</span></label>
                    <select name="bimba_unit" id="bimba_unit" class="form-control @error('bimba_unit') is-invalid @enderror" required>
                        <option value="">-- Pilih Unit --</option>
                        @foreach($units as $namaUnit => $cabang)
                            <option value="{{ $namaUnit }}" {{ old('bimba_unit') == $namaUnit ? 'selected' : '' }}>
                                {{ $namaUnit }}
                            </option>
                        @endforeach
                    </select>
                    @error('bimba_unit') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>

                <div class="col-md-6">
                    <label for="no_cabang">No. Cabang</label>
                    <input type="text" name="no_cabang" id="no_cabang" class="form-control" readonly>
                </div>
            @else
                {{-- Non-admin: hidden input saja --}}
                @if ($userUnit)
                    <input type="hidden" name="bimba_unit" value="{{ $userUnit }}">
                    <input type="hidden" name="no_cabang" value="{{ $userNoCabang }}">
                @else
                    <div class="col-12 mb-3">
                        <div class="alert alert-warning">
                            Unit biMBA Anda belum diatur. Hubungi admin.
                        </div>
                        <input type="hidden" name="bimba_unit" value="">
                        <input type="hidden" name="no_cabang" value="">
                    </div>
                @endif
            @endif

            {{-- Nama --}}
            <div class="col-md-6 mb-3">
                <label for="nama" class="form-label">Nama <span class="text-danger">*</span></label>
                <input type="text" name="nama" id="nama" class="form-control" value="{{ old('nama') }}" required>
                @error('nama') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>

            {{-- Tempat Lahir --}}
            <div class="col-md-6 mb-3">
                <label for="tmpt_lahir">Tempat Lahir</label>
                <input type="text" name="tmpt_lahir" id="tmpt_lahir" class="form-control" value="{{ old('tmpt_lahir') }}">
            </div>

            {{-- Tgl Lahir --}}
            <div class="col-md-6 mb-3">
                <label for="tgl_lahir">Tanggal Lahir</label>
                <input type="date" name="tgl_lahir" id="tgl_lahir" class="form-control" value="{{ old('tgl_lahir') }}">
            </div>

            {{-- Tgl Masuk --}}
            <div class="col-md-6 mb-3">
                <label for="tgl_masuk">Tanggal Masuk <span class="text-danger">*</span></label>
                <input type="date" name="tgl_masuk" id="tgl_masuk" class="form-control" value="{{ old('tgl_masuk') }}" required>
                @error('tgl_masuk') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>

            {{-- Usia --}}
            <div class="col-md-6 mb-3">
                <label for="usia">Usia</label>
                <input type="number" name="usia" id="usia" class="form-control" readonly>
            </div>

            {{-- Lama Belajar --}}
            <div class="col-md-6 mb-3">
                <label for="lama_bljr">Lama Belajar</label>
                <input type="text" name="lama_bljr" id="lama_bljr" class="form-control" readonly>
            </div>

            {{-- Tahapan --}}
            <div class="col-md-6 mb-3">
                <label for="tahap">Tahapan</label>
                <select name="tahap" id="tahap" class="form-control">
                    <option value="">-- Pilih --</option>
                    @foreach($tahapanOptions as $t)
                        <option value="{{ $t }}" {{ old('tahap') == $t ? 'selected' : '' }}>{{ $t }}</option>
                    @endforeach
                </select>
            </div>

            {{-- Sumber Informasi --}}
            <div class="col-md-6 mb-3">
                <label for="info">Sumber Informasi <span class="text-danger">*</span></label>
                <select name="info" id="info" class="form-control @error('info') is-invalid @enderror" required>
                    <option value="">-- Pilih --</option>
                    @foreach($infoOptions as $opt)
                        <option value="{{ $opt }}" {{ old('info') == $opt ? 'selected' : '' }}>{{ $opt }}</option>
                    @endforeach
                </select>
                @error('info') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>

            <!-- Kelas -->
            <div class="col-md-6 mb-3">
                <label for="kelas">Kelas <span class="text-danger">*</span></label>
                <select name="kelas" id="kelas" class="form-control" required>
                    <option value="">-- Pilih Kelas --</option>
                    @foreach($kelasOptions as $k)
                        <option value="{{ $k }}" {{ old('kelas') == $k ? 'selected' : '' }}>{{ $k }}</option>
                    @endforeach
                </select>
                @error('kelas') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>

            <!-- Gol -->
            <div class="col-md-6 mb-3">
                <label for="gol">Gol <span class="text-danger">*</span></label>
                <select name="gol" id="gol" class="form-control" required>
                    <option value="">-- Pilih Gol --</option>
                    @foreach($HargaSaptataruna->unique('kode') as $item)
                        @if($item->kode)
                            <option value="{{ $item->kode }}" {{ old('gol') == $item->kode ? 'selected' : '' }}>
                                {{ $item->kode }}
                            </option>
                        @endif
                    @endforeach
                </select>
                @error('gol') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>

            <!-- KD -->
            <div class="col-md-6 mb-3">
                <label for="kd">KD <span class="text-danger">*</span></label>
                <select name="kd" id="kd" class="form-control" required>
                    <option value="">-- Pilih KD --</option>
                    @foreach($kdOptions as $k)
                        <option value="{{ $k }}" {{ old('kd') == $k ? 'selected' : '' }}>{{ $k }}</option>
                    @endforeach
                </select>
                @error('kd') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>

            <!-- Petugas Trial -->
            <div class="col-md-6 mb-3">
                <label for="petugas_trial">Petugas Trial</label>
                <select name="petugas_trial" id="petugas_trial" class="form-control">
                    <option value="">-- Pilih --</option>
                    @foreach($profil as $p)
                        <option value="{{ $p->nama }}">{{ $p->nama }}</option>
                    @endforeach
                </select>
            </div>

            <!-- Guru -->
            <div class="col-md-6 mb-3">
                <label for="guru">Guru <span class="text-danger">*</span></label>
                <select name="guru" id="guru" class="form-control" required>
                    <option value="">-- Pilih Guru --</option>
                    @foreach($profil as $g)
                        <option value="{{ $g->nama }}">{{ $g->nama }}</option>
                    @endforeach
                </select>
                @error('guru') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>

            <!-- Orangtua -->
            <div class="col-md-6 mb-3">
                <label for="orangtua">Orangtua <span class="text-danger">*</span></label>
                <input type="text" name="orangtua" id="orangtua" class="form-control" value="{{ old('orangtua') }}" required>
                @error('orangtua') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>

            <!-- No Telp/HP -->
            <div class="col-md-6 mb-3">
                <label for="no_telp_hp">No Telp/HP</label>
                <input type="text" name="no_telp_hp" id="no_telp_hp" class="form-control" value="{{ old('no_telp_hp') }}">
            </div>

            <!-- Note -->
            <div class="col-md-6 mb-3">
                <label for="note">Note</label>
                <select name="note" id="note" class="form-control">
                    <option value="">-- Pilih Note --</option>
                    @foreach($noteOptions as $n)
                        <option value="{{ $n }}">{{ $n }}</option>
                    @endforeach
                </select>
            </div>

            <!-- Note Garansi -->
            <div class="col-md-6 mb-3">
                <label for="note_garansi">Note Garansi</label>
                <select name="note_garansi" id="note_garansi" class="form-control">
                    <option value="">-- Pilih --</option>
                    @foreach($noteGaransiOptions as $ng)
                        <option value="{{ $ng }}">{{ $ng }}</option>
                    @endforeach
                </select>
            </div>

            <!-- Tgl Keluar -->
            <div class="col-md-6 mb-3">
                <label for="tgl_keluar">Tanggal Keluar</label>
                <input type="date" name="tgl_keluar" id="tgl_keluar" class="form-control" value="{{ old('tgl_keluar') }}">
            </div>

            <!-- SPP -->
            <div class="col-md-6 mb-3">
                <label for="spp">SPP</label>
                <input type="text" id="spp" name="spp" class="form-control" readonly>
            </div>

            <!-- Status -->
            <div class="col-md-6 mb-3">
                <label>Status</label>
                <input type="text" class="form-control" value="Baru" readonly>
                <input type="hidden" name="status" value="Baru">
            </div>

            <!-- Separator -->
            <div class="col-12"><hr class="my-4"></div>

            <h4 class="col-12 mb-3">🗓️ Masa Aktif (Dhuafa & BNF)</h4>

            <div class="col-md-6 mb-3">
                <label for="periode">Periode</label>
                <select name="periode" id="periode" class="form-control">
                    <option value="">-- Pilih --</option>
                    @foreach($periodeOptions as $p)
                        <option value="{{ $p }}">{{ $p }}</option>
                    @endforeach
                </select>
            </div>

            <div class="col-md-6 mb-3">
                <label for="alert">Alert Dhuafa</label>
                <input type="text" name="alert" id="alert" class="form-control" value="{{ old('alert') }}">
            </div>

            <div class="col-md-6 mb-3">
                <label for="tgl_mulai">Tanggal Mulai</label>
                <input type="date" name="tgl_mulai" id="tgl_mulai" class="form-control" value="{{ old('tgl_mulai') }}">
            </div>

            <div class="col-md-6 mb-3">
                <label for="tgl_akhir">Tanggal Akhir</label>
                <input type="date" name="tgl_akhir" id="tgl_akhir" class="form-control" value="{{ old('tgl_akhir') }}">
            </div>

            <div class="col-12"><hr class="my-4"></div>

            <h4 class="col-12 mb-3">⏱️ Masa Aktif Paket 72</h4>

            <div class="col-md-4 mb-3">
                <label for="tgl_bayar">Tanggal Bayar</label>
                <input type="date" name="tgl_bayar" id="tgl_bayar" class="form-control" value="{{ old('tgl_bayar') }}">
            </div>

            <div class="col-md-4 mb-3">
                <label for="tgl_selesai">Tanggal Selesai</label>
                <input type="date" name="tgl_selesai" id="tgl_selesai" class="form-control" value="{{ old('tgl_selesai') }}">
            </div>

            <div class="col-md-4 mb-3">
                <label for="alert2">Alert Paket (72)</label>
                <input type="text" name="alert2" id="alert2" class="form-control" value="{{ old('alert2') }}">
            </div>

            <div class="col-12"><hr class="my-4"></div>

            <h4 class="col-12 mb-3">📚 Supply Modul</h4>

            <div class="col-md-6 mb-3">
                <label for="asal_modul">Asal Modul</label>
                <select name="asal_modul" id="asal_modul" class="form-control">
                    <option value="">-- Pilih --</option>
                    @foreach($asalModulOptions as $am)
                        <option value="{{ $am }}">{{ $am }}</option>
                    @endforeach
                </select>
            </div>

            <div class="col-md-6 mb-3">
                <label for="keterangan_optional">Keterangan Optional</label>
                <input type="text" name="keterangan_optional" id="keterangan_optional" class="form-control" value="{{ old('keterangan_optional') }}">
            </div>

            <div class="col-12"><hr class="my-4"></div>

            <h4 class="col-12 mb-3">⏰ Jadwal biMBA</h4>

            <div class="col-md-6 mb-3">
                <label for="kode_jadwal">Kode Jadwal <span class="text-danger">*</span></label>
                <select name="kode_jadwal" id="kode_jadwal" class="form-control" required>
                    <option value="">-- Pilih --</option>
                    @foreach($kodeJadwalOptions as $kj)
                        <option value="{{ $kj }}">{{ $kj }}</option>
                    @endforeach
                </select>
                @error('kode_jadwal') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>

            <div class="col-md-6 mb-3">
                <label for="hari_jam">Hari & Jam</label>
                <input type="text" name="hari_jam" id="hari_jam" class="form-control" value="{{ old('hari_jam') }}">
            </div>

            <div class="col-12"><hr class="my-4"></div>

            <h4 class="col-12 mb-3">🔄 Murid Pindah ke Intervio</h4>

            <div class="col-md-6 mb-3">
                <label for="status_pindah">Status Pindah</label>
                <select name="status_pindah" id="status_pindah" class="form-control">
                    <option value="">-- Pilih --</option>
                    @foreach($statusPindahOptions as $sp)
                        <option value="{{ $sp }}">{{ $sp }}</option>
                    @endforeach
                </select>
            </div>

            <div class="col-md-6 mb-3">
                <label for="tanggal_pindah">Tanggal Pindah</label>
                <input type="date" name="tanggal_pindah" id="tanggal_pindah" class="form-control" value="{{ old('tanggal_pindah') }}">
            </div>

            <div class="col-md-6 mb-3">
                <label for="ke_bimba_intervio">Ke biMBA Intervio</label>
                <select name="ke_bimba_intervio" id="ke_bimba_intervio" class="form-control">
                    <option value="">-- Pilih --</option>
                    @foreach($keBimbaIntervioOptions as $kbi)
                        <option value="{{ $kbi }}">{{ $kbi }}</option>
                    @endforeach
                </select>
            </div>

            <div class="col-md-6 mb-3">
                <label for="keterangan">Keterangan (Pindah)</label>
                <textarea name="keterangan" id="keterangan" class="form-control" rows="3">{{ old('keterangan') }}</textarea>
            </div>

            <div class="col-12"><hr class="my-4"></div>

            <h4 class="col-12 mb-3">Detail Lainnya</h4>

            <div class="col-md-6 mb-3">
                <label for="level">Level</label>
                <select name="level" id="level" class="form-control">
                    <option value="">-- Pilih --</option>
                    @foreach($levelOptions as $l)
                        <option value="{{ $l }}">{{ $l }}</option>
                    @endforeach
                </select>
            </div>

            <div class="col-md-6 mb-3">
                <label for="jenis_kbm">Jenis KBM</label>
                <select name="jenis_kbm" id="jenis_kbm" class="form-control">
                    <option value="">-- Pilih --</option>
                    @foreach($jenisKbmOptions as $jk)
                        <option value="{{ $jk }}">{{ $jk }}</option>
                    @endforeach
                </select>
            </div>

            <div class="col-md-6 mb-3">
                <label for="no_cab_merge">No Cab Merge</label>
                <input type="text" name="no_cab_merge" id="no_cab_merge" class="form-control" value="{{ old('no_cab_merge') }}">
            </div>

            <div class="col-md-6 mb-3">
                <label for="no_pembayaran_murid">No Pembayaran Murid</label>
                <input type="text" name="no_pembayaran_murid" id="no_pembayaran_murid" class="form-control" value="{{ old('no_pembayaran_murid') }}">
            </div>

            <div class="col-md-6 mb-3">
                <label for="alamat_murid">Alamat Murid</label>
                <textarea name="alamat_murid" id="alamat_murid" class="form-control" rows="3">{{ old('alamat_murid') }}</textarea>
            </div>

            <!-- Submit -->
            <div class="col-12 mt-5">
                <button type="submit" class="btn btn-success btn-lg">Simpan Data</button>
                <a href="{{ route('buku_induk.index') }}" class="btn btn-secondary btn-lg">Kembali</a>
            </div>
        </div>
    </form>
</div>

<!-- JavaScript -->
<script>
window.unitsData = {!! $unitsJson !!};

document.addEventListener('DOMContentLoaded', function () {
    const isAdmin = {{ $isAdmin ? 'true' : 'false' }};

    // ==================== LOGIC NIM OTOMATIS ====================
    let currentUnit = '{{ $userUnit ?? '' }}'; // default untuk non-admin

    function updateNim(unitValue = currentUnit) {
        if (!unitValue) {
            document.getElementById('nim-preview').textContent = '-----';
            document.getElementById('nim').value = '';
            return;
        }

        fetch(`/buku-induk/next-suffix?bimba_unit=${encodeURIComponent(unitValue)}`)
            .then(res => res.json())
            .then(data => {
                const nextSuffix = data.next_suffix || '0001';
                const padded = nextSuffix.toString().padStart(4, '0');
                const prefix = window.unitsData[unitValue] || '-----';
                const fullNim = prefix + padded;

                document.getElementById('nim-preview').textContent = fullNim;
                document.getElementById('nim').value = fullNim;
                document.getElementById('nim_suffix').value = padded;
            })
            .catch(() => {
                document.getElementById('nim-preview').textContent = 'Error load NIM';
            });
    }

    // Untuk ADMIN: update saat pilih unit
    if (isAdmin) {
        const unitSelect = document.getElementById('bimba_unit');
        unitSelect?.addEventListener('change', () => {
            currentUnit = unitSelect.value;
            updateNim(currentUnit);
        });

        // Jalankan awal jika ada old value
        if (unitSelect?.value) {
            updateNim(unitSelect.value);
        }
    } else {
        // Non-admin: langsung jalankan dengan unit user
        if (currentUnit) {
            updateNim(currentUnit);
        }
    }

    // ==================== LOGIC LAINNYA (usia, lama belajar, SPP) ====================
    document.getElementById('tgl_lahir')?.addEventListener('change', function () {
        if (!this.value) return;
        const tgl = new Date(this.value);
        const today = new Date();
        let age = today.getFullYear() - tgl.getFullYear();
        const m = today.getMonth() - tgl.getMonth();
        if (m < 0 || (m === 0 && today.getDate() < tgl.getDate())) age--;
        document.getElementById('usia').value = age > 0 ? age : '';
    });

    document.getElementById('tgl_masuk')?.addEventListener('change', function () {
        if (!this.value) return;
        const tgl = new Date(this.value);
        const today = new Date();
        let bulan = (today.getFullYear() - tgl.getFullYear()) * 12 + (today.getMonth() - tgl.getMonth());
        if (today.getDate() < tgl.getDate()) bulan--;
        document.getElementById('lama_bljr').value = bulan >= 0 ? bulan + ' bulan' : '';
    });

    const sppMapping = @json($sppMapping);
    const golSelect = document.getElementById('gol');
    const kdSelect = document.getElementById('kd');
    const sppInput = document.getElementById('spp');

    function updateSPP() {
        const gol = golSelect?.value;
        const kd = kdSelect?.value;
        if (gol && kd && sppMapping[gol] && sppMapping[gol][kd] !== undefined) {
            sppInput.value = 'Rp. ' + new Intl.NumberFormat('id-ID').format(sppMapping[gol][kd]);
        } else {
            sppInput.value = '';
        }
    }

    golSelect?.addEventListener('change', updateSPP);
    kdSelect?.addEventListener('change', updateSPP);
    updateSPP();
});
</script>
@endsection