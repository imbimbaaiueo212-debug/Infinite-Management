@extends('layouts.app')

@section('title', 'Edit Pendaftaran')

@section('content')
<div class="container">
    <h2 class="mb-3">Edit Pendaftaran</h2>

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
        $useOld = $errors->any();

        // pastikan variabel ada
        $penerimaanPrefill = $penerimaanPrefill ?? [];

        // coba ambil penerimaan terakhir dari relasi registration->penerimaan (jika ada)
        $regPenerimaan = null;
        if (isset($registration) && $registration) {
            if (method_exists($registration, 'penerimaan')) {
                try {
                    $rel   = $registration->penerimaan();
                    $maybe = $rel->get();

                    if ($maybe instanceof \Illuminate\Support\Collection && $maybe->isNotEmpty()) {
                        $regPenerimaan = $maybe->last();
                    } else {
                        $regPenerimaan = $rel->first();
                    }
                } catch (\Throwable $e) {
                    $regPenerimaan = null; // abaikan error, fallback ke $penerimaanPrefill
                }
            }
        }

        // jika controller belum kirim $penerimaanPrefill tapi ada $regPenerimaan, bangun array prefill
        if (empty($penerimaanPrefill) && $regPenerimaan) {
            $penerimaanPrefill = [
                'kwitansi'   => $regPenerimaan->kwitansi   ?? null,
                'via'        => $regPenerimaan->via        ?? null,
                'bulan'      => $regPenerimaan->bulan      ?? null,
                'tahun'      => $regPenerimaan->tahun      ?? null,
                'tanggal'    => isset($regPenerimaan->tanggal)
                                    ? \Carbon\Carbon::parse($regPenerimaan->tanggal)->format('Y-m-d')
                                    : null,
                'daftar'     => $regPenerimaan->daftar     ?? null,
                'voucher'    => $regPenerimaan->voucher    ?? null,
                'spp_rp'     => $regPenerimaan->spp        ?? $regPenerimaan->spp_rp ?? null,
                'nilai_spp'  => $regPenerimaan->nilai_spp  ?? null,
                'kaos'       => $regPenerimaan->kaos       ?? null,
                'kpk'        => $regPenerimaan->kpk        ?? null,
                'sertifikat' => $regPenerimaan->sertifikat ?? null,
                'stpb'       => $regPenerimaan->stpb       ?? null,
                'tas'        => $regPenerimaan->tas        ?? null,
                'event'      => $regPenerimaan->event      ?? null,
                'lain_lain'  => $regPenerimaan->lain_lain  ?? null,
            ];
        }

        // convenience getter untuk field biasa (bi.*)
        $get = fn ($key, $fallback = null) => $useOld ? old($key, $fallback) : $fallback;

        // prefill BI
        $prefTahap      = $get('bi.tahap',       $biPrefill['tahap']       ?? null);
        $prefKelas      = $get('bi.kelas',       $biPrefill['kelas']       ?? null);
        $prefGol        = $get('bi.gol',         $biPrefill['gol']         ?? null);
        $prefKd         = $get('bi.kd',          $biPrefill['kd']          ?? null);
        $prefSpp        = $get('bi.spp',         $biPrefill['spp']         ?? null);
        $prefGuru       = $get('bi.guru',        $biPrefill['guru']        ?? null);
        $prefJam        = $get('bi.jam',         $biPrefill['jam']         ?? null);
        $prefKodeJadwal = $get('bi.kode_jadwal', $biPrefill['kode_jadwal'] ?? null);

        // helper prefill penerimaan:
        // prioritas old() > penerimaanPrefill (controller) > default
        $getP = function ($k, $f = null) use ($useOld, $penerimaanPrefill) {
            if ($useOld) {
                return old("penerimaan.$k", $f);
            }
            if (array_key_exists($k, $penerimaanPrefill) && $penerimaanPrefill[$k] !== null) {
                return $penerimaanPrefill[$k];
            }
            return $f;
        };
    @endphp

    <div class="card">
        <div class="card-body">
            {{-- SATU form saja, pakai enctype untuk upload --}}
            <form method="POST"
                  action="{{ route('registrations.update', $registration->id) }}"
                  enctype="multipart/form-data">
                @csrf
                @method('PUT')

                {{-- MURID --}}
                <div class="mb-3">
                    <label class="form-label fw-bold">Murid</label>
                    <select name="student_id" class="form-select" required>
                        @foreach ($students as $s)
                            <option value="{{ $s->id }}"
                                {{ (($useOld ? (int) old('student_id') : (int) $registration->student_id) === (int) $s->id) ? 'selected' : '' }}>
                                {{ $s->nim }} — {{ $s->nama }}
                            </option>
                        @endforeach
                    </select>
                </div>

                {{-- STATUS & TANGGAL DAFTAR --}}
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-bold">Status</label>
                        @php
                            $val = $useOld ? old('status') : $registration->status;
                        @endphp
                        <select name="status" class="form-select" required>
                            <option value="pending"  {{ $val === 'pending'  ? 'selected' : '' }}>pending</option>
                            <option value="verified" {{ $val === 'verified' ? 'selected' : '' }}>verified</option>
                            <option value="accepted" {{ $val === 'accepted' ? 'selected' : '' }}>accepted</option>
                            <option value="rejected" {{ $val === 'rejected' ? 'selected' : '' }}>rejected</option>
                        </select>
                    </div>

                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-bold">Tanggal Daftar</label>
                        <input type="date"
                               name="tanggal_daftar"
                               class="form-control"
                               value="{{ $useOld ? old('tanggal_daftar') : optional($registration->tanggal_daftar)->format('Y-m-d') }}">
                    </div>
                </div>

                {{-- DATA BUKU INDUK --}}
                <hr class="my-4">
                <h5 class="mb-3">Data Buku Induk</h5>

                <div class="row">
                    {{-- Tahapan --}}
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Tahapan</label>
                        <select class="form-control" name="bi[tahap]">
                            <option value="">-- Pilih Tahapan --</option>
                            @foreach ($tahapanOptions as $t)
                                <option value="{{ $t }}" @selected($prefTahap === $t)>{{ $t }}</option>
                            @endforeach
                        </select>
                    </div>

                    {{-- Kelas --}}
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Kelas</label>
                        <select class="form-control" name="bi[kelas]">
                            <option value="">-- Pilih Kelas --</option>
                            @foreach ($kelasOptions as $k)
                                <option value="{{ $k }}" @selected($prefKelas === $k)>{{ $k }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                {{-- TANGGAL LAHIR & TANGGAL MASUK --}}
                
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

                <div class="row">
                    {{-- Gol --}}
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Gol</label>
                        <select class="form-control" name="bi[gol]" id="bi_gol">
                            <option value="">-- Pilih Gol --</option>
                            @foreach ($hargaSaptataruna->unique('kode') as $row)
                                @if ($row->kode)
                                    <option value="{{ $row->kode }}" @selected($prefGol === $row->kode)>
                                        {{ $row->kode }}
                                    </option>
                                @endif
                            @endforeach
                        </select>
                    </div>

                    {{-- KD --}}
                    <div class="col-md-6 mb-3">
                        <label class="form-label">KD</label>
                        <select class="form-control" name="bi[kd]" id="bi_kd">
                            <option value="">-- Pilih KD --</option>
                            @foreach ($kdOptions as $kd)
                                <option value="{{ $kd }}" @selected($prefKd === $kd)>{{ $kd }}</option>
                            @endforeach
                        </select>
                    </div>

                    {{-- SPP --}}
                    <div class="col-md-6 mb-3">
                        <label class="form-label">SPP</label>
                        <input type="text"
                               class="form-control"
                               id="bi_spp_display"
                               value="{{ $prefSpp ? number_format((int) $prefSpp, 0, ',', '.') : '' }}"
                               placeholder="otomatis dari GOL+KD atau isi manual">
                        <input type="hidden"
                               name="bi[spp]"
                               id="bi_spp"
                               value="{{ $prefSpp ? (int) $prefSpp : '' }}">
                        <small class="text-muted">
                            Boleh kosong; sistem akan hitung dari GOL+KD.
                        </small>
                    </div>

                    {{-- Guru --}}
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Guru</label>
                        @if (!empty($guruOptions))
                            <select name="bi[guru]" class="form-control">
                                <option value="">-- Pilih Guru --</option>
                                @foreach ($guruOptions as $g)
                                    <option value="{{ $g }}" @selected($prefGuru === $g)>{{ $g }}</option>
                                @endforeach
                            </select>
                        @else
                            <input type="text"
                                   name="bi[guru]"
                                   class="form-control"
                                   value="{{ $prefGuru ?? '' }}"
                                   placeholder="Nama guru (opsional)">
                        @endif
                        <small class="text-muted">
                            Bisa kosong. Jika tersedia, pilih dari daftar.
                        </small>
                    </div>

                    {{-- Kode Jadwal --}}
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Kode Jadwal</label>
                        @if (!empty($kodeJadwalOptions))
                            <select name="bi[kode_jadwal]" class="form-control">
                                <option value="">-- Pilih Kode Jadwal --</option>
                                @foreach ($kodeJadwalOptions as $kj)
                                    <option value="{{ $kj }}" @selected($prefKodeJadwal === $kj)>{{ $kj }}</option>
                                @endforeach
                            </select>
                        @else
                            <input type="text"
                                   name="bi[kode_jadwal]"
                                   class="form-control"
                                   value="{{ $prefKodeJadwal ?? '' }}"
                                   placeholder="Kode jadwal (opsional)">
                        @endif
                        <small class="text-muted">
                            Opsional; jika ada kode jadwal gunakan dropdown.
                        </small>
                    </div>
                </div>

                {{-- PENERIMAAN / KWITANSI --}}
                <hr class="my-4">
                <h5 class="mb-3">Penerimaan / Kwitansi</h5>

                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label">No. Kwitansi</label>
                        <input type="text"
                               name="penerimaan[kwitansi]"
                               class="form-control"
                               value="{{ $getP('kwitansi') }}">
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">Via</label>
                        <select name="penerimaan[via]" class="form-select">
                            <option value="">-- Pilih Cara Pembayaran --</option>
                            <option value="Cash"     {{ $getP('via') === 'Cash'     ? 'selected' : '' }}>Cash</option>
                            <option value="Transfer" {{ $getP('via') === 'Transfer' ? 'selected' : '' }}>Transfer</option>
                        </select>
                    </div>

                    <div class="col-md-2">
                        <label class="form-label">Bulan</label>
                        <select name="penerimaan[bulan]" class="form-control">
                            <option value="">-- Bulan --</option>
                            @foreach (['Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'] as $m)
                                <option value="{{ $m }}" @selected($getP('bulan') === $m)>{{ $m }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-md-2">
                        <label class="form-label">Tahun</label>
                        @php $currentYear = date('Y'); @endphp
                        <select name="penerimaan[tahun]" class="form-control">
                            <option value="">-- Tahun --</option>
                            @for ($y = $currentYear - 1; $y <= $currentYear + 1; $y++)
                                <option value="{{ $y }}" @selected((string) $getP('tahun') === (string) $y)>{{ $y }}</option>
                            @endfor
                        </select>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">Tanggal</label>
                        <input type="date"
                               name="penerimaan[tanggal]"
                               class="form-control"
                               value="{{ $getP('tanggal') }}">
                    </div>

                    {{-- NOMINAL --}}
                    <div class="col-md-4">
                        <label class="form-label">Daftar (Rp)</label>
                        <input type="text"
                               name="penerimaan[daftar]"
                               class="form-control money-format"
                               value="{{ $getP('daftar') }}">
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">Voucher (Rp)</label>
                        <input type="text"
                               name="penerimaan[voucher]"
                               class="form-control money-format"
                               value="{{ $getP('voucher') }}">
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">SPP (Rp)</label>
                        <input type="text"
                               id="penerimaan_spp_rp"
                               name="penerimaan[spp_rp]"
                               class="form-control money-format"
                               value="{{ $getP('spp_rp') }}">
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">SPP (keterangan)</label>
                        <input type="text"
                               id="penerimaan_nilai_spp"
                               name="penerimaan[nilai_spp]"
                               class="form-control money-format"
                               value="{{ $getP('nilai_spp') }}">
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">Kaos (Rp)</label>
                        <input type="text"
                               name="penerimaan[kaos]"
                               class="form-control money-format"
                               value="{{ $getP('kaos') }}">
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">KPK (Rp)</label>
                        <input type="text"
                               name="penerimaan[kpk]"
                               class="form-control money-format"
                               value="{{ $getP('kpk') }}">
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">Tas (Rp)</label>
                        <input type="text"
                               name="penerimaan[tas]"
                               class="form-control money-format"
                               value="{{ $getP('tas') }}">
                    </div>
                </div>

                {{-- UPLOAD DOKUMEN --}}
                <div class="mb-3 mt-4">
                    <label class="form-label">Upload Dokumen (PDF/JPG/PNG) - opsional</label>

                    @if ($registration->attachment_path)
                        <div class="mb-2">
                            <a href="{{ asset('storage/' . $registration->attachment_path) }}"
                               target="_blank"
                               class="btn btn-outline-secondary btn-sm">
                                Lihat Dokumen Saat Ini
                            </a>
                        </div>
                    @endif

                    <input type="file"
                           name="attachment"
                           class="form-control @error('attachment') is-invalid @enderror"
                           accept=".pdf,image/*">
                    @error('attachment')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror

                    <small class="text-muted">
                        Maks 3MB. Format: pdf, jpg, jpeg, png, webp.
                    </small>
                </div>

                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-primary">Simpan</button>
                    <a href="{{ route('registrations.index') }}" class="btn btn-secondary">Kembali</a>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- Script kecil untuk SPP auto (tidak menimpa nilai old/prefill saat load awal) --}}
<script>
document.addEventListener('DOMContentLoaded', function () {
    const mapping = @json($sppMapping);
    const gol     = document.getElementById('bi_gol');
    const kd      = document.getElementById('bi_kd');
    const spp     = document.getElementById('bi_spp');
    const disp    = document.getElementById('bi_spp_display');

    // penerimaan inputs (kita tambahkan sinkron)
    const penerimaanSppRp   = document.getElementById('penerimaan_spp_rp');
    const penerimaanNilaiSpp= document.getElementById('penerimaan_nilai_spp');

    function unformat(s) { return (s || '').toString().replace(/\D/g, ''); }
    function fmt(n)       { return new Intl.NumberFormat('id-ID').format(n); }

    function updateSPP() {
        const g = gol?.value || '';
        const k = kd?.value  || '';
        const val = (mapping[g] && mapping[g][k] !== undefined)
            ? parseInt(mapping[g][k])
            : '';

        if (val) {
            spp.value  = String(val);
            disp.value = fmt(val);
        } else {
            spp.value  = '';
            disp.value = '';
        }

        syncPenerimaanFromSPP();
    }

    function syncPenerimaanFromSPP() {
        if (!penerimaanSppRp || !penerimaanNilaiSpp || !disp) return;

        const raw = unformat(disp.value); // angka murni
        penerimaanSppRp.value   = raw || '';
        penerimaanNilaiSpp.value= disp.value || '';
    }

    disp?.addEventListener('input', () => {
        const n = parseInt(unformat(disp.value) || 0);
        spp.value  = n ? String(n) : '';
        disp.value = n ? fmt(n) : '';
        syncPenerimaanFromSPP();
    });

    gol?.addEventListener('change', updateSPP);
    kd?.addEventListener('change', updateSPP);

    // INIT: kalau belum ada nilai SPP, isi dari mapping; kalau sudah ada, hanya sinkron ke penerimaan
    const initial = unformat(disp?.value);
    if (!initial) {
        updateSPP();
    } else {
        syncPenerimaanFromSPP();
    }

    // formatting visual untuk semua input uang
    function formatMoneyInput(el) {
        el.addEventListener('input', () => {
            const raw = el.value.replace(/[^\d]/g, '');
            if (!raw) { el.value = ''; return; }
            const n = parseInt(raw, 10);
            el.value = new Intl.NumberFormat('id-ID').format(n);
        });
    }

    document.querySelectorAll('.money-format').forEach(formatMoneyInput);
});
</script>
@endsection
