@extends('layouts.app')
@section('title', 'Buku Induk')
@section('content')
    <div class="container-fluid px-1 px-md-3">
        <div class="card card-header shadow-sm border-0">
            <div class="card-body px-2 px-md-4 py-4">
                <h2 class="mb-4">Data Buku Induk</h2>

                <div class="d-flex flex-wrap gap-2 mb-4">
                    <a href="{{ route('buku_induk.create') }}" class="btn btn-primary">+ Tambah Data</a>
                    <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#importModal">
                        Import Data Excel
                    </button>
                    <a href="{{ route('buku_induk.export') . '?' . http_build_query(request()->query()) }}"
                        class="btn btn-info">
                        <i class="fas fa-file-excel"></i> Export ke Excel
                    </a>
                </div>

                {{-- MODAL IMPORT --}}
                <div class="modal fade" id="importModal" tabindex="-1" aria-labelledby="importModalLabel"
                    aria-hidden="true">
                    <div class="modal-dialog">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title" id="importModalLabel">Import Data Buku Induk</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <form action="{{ route('buku_induk.import') }}" method="POST" enctype="multipart/form-data">
                                @csrf
                                <div class="modal-body">
                                    <div class="mb-3">
                                        <label for="file" class="form-label">Pilih File Excel (.xlsx, .xls, .csv)</label>
                                        <input type="file" name="file" id="file" class="form-control"
                                            accept=".xlsx,.xls,.csv" required>
                                        @error('file')
                                            <div class="text-danger mt-1">{{ $message }}</div>
                                        @enderror
                                    </div>
                                    <p class="text-muted small">Pastikan file mengikuti format template Buku Induk.</p>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                                    <button type="submit" class="btn btn-success">Import</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                {{-- FILTER FORM --}}
                <form method="GET" action="{{ route('buku_induk.index') }}" id="filterForm"
      class="card card-body shadow-sm border-0 rounded-3 mb-4">

    <div class="row g-3 align-items-end">

        <!-- Unit biMBA: paling kiri di md+ -->
        @if (auth()->check() && (auth()->user()->is_admin ?? false))
            <div class="col-12 col-md-3 order-md-1">
                <label for="unitFilter" class="form-label fw-bold">Unit biMBA</label>
                <select name="unit" id="unitFilter" class="form-select">
                    <option value="">— Semua Unit —</option>
                    @foreach ($unitOptions as $unit)
                        <option value="{{ $unit }}" {{ request('unit') == $unit ? 'selected' : '' }}>
                            {{ $unit }}
                        </option>
                    @endforeach
                </select>
            </div>
        @endif

        <!-- NIM / Nama: kedua (lebar lebih besar karena select banyak opsi) -->
        <div class="col-12 col-md-{{ auth()->check() && (auth()->user()->is_admin ?? false) ? '4' : '5' }} order-md-2">
            <label for="muridFilter" class="form-label fw-bold">Filter NIM / Nama</label>
            <select name="murid" id="muridFilter" class="form-select">
                <option value="">— Ketik untuk cari NIM atau Nama —</option>
                @foreach ($muridOptions as $o)
                    @php $nimPad = str_pad($o->nim, 3, '0', STR_PAD_LEFT); @endphp
                    <option value="{{ $o->nim }}" {{ request('murid') == $o->nim ? 'selected' : '' }}>
                        {{ $nimPad }} | {{ $o->nama }}
                    </option>
                @endforeach
            </select>
        </div>

        <!-- Status: ketiga -->
        <div class="col-12 col-md-2 order-md-3">
            <label for="statusFilter" class="form-label fw-bold">Status</label>
            <select name="status" id="statusFilter" class="form-select">
                <option value="">— Semua —</option>
                <option value="aktif" {{ request('status') == 'aktif' ? 'selected' : '' }}>Aktif</option>
                <option value="baru" {{ request('status') == 'baru' ? 'selected' : '' }}>Baru</option>
                <option value="keluar" {{ request('status') == 'keluar' ? 'selected' : '' }}>Keluar</option>
            </select>
        </div>

        <!-- Per halaman: keempat -->
        <div class="col-12 col-md-2 order-md-4">
            <label for="perPageFilter" class="form-label fw-bold">Per halaman</label>
            <select name="perPage" id="perPageFilter" class="form-select">
                @foreach ([25, 50, 100, 200] as $pp)
                    <option value="{{ $pp }}" {{ request('perPage', 50) == $pp ? 'selected' : '' }}>
                        {{ $pp }}
                    </option>
                @endforeach
            </select>
        </div>

        <!-- Tombol Reset: paling kanan -->
        <div class="col-12 col-md-1 order-md-5 d-flex align-items-end">
            <a href="{{ route('buku_induk.index') }}" class="btn btn-outline-secondary w-100">
                Reset
            </a>
        </div>

    </div>
</form>

                {{-- TABEL DATA --}}
                <div class="table-sticky-wrapper table-responsive">
                    <table class="table table-bordered table-striped table-hover" id="bukuIndukTable">
                        <thead class="tabel card-body">
                            <tr>
                                <th>NIM</th>
                                <th>NAMA</th>
                                <th>INFO</th>
                                <th>STATUS</th>
                                <th>KETERANGAN</th>
                                <th>PERIODE / GARANSI</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        @forelse ($bukuInduk as $index => $item)
                            <tr @class([
                                'table-danger' => $item->tgl_keluar && strtolower($item->status ?? '') === 'keluar',
                                'table-primary' => strtolower($item->status ?? '') === 'baru', 'card-body',
                            ])>

                                <!-- NIM -->
                                <td>{{ $item->nim ?? '-' }}</td>
                                <td>{{ $item->nama }}</td>
                                <!-- INFO -->
                                <td>
                                    Unit: {{ $item->bimba_unit }}<br>
                                    Tgl Daftar: {{ $item->tgl_daftar?->format('d-m-Y') }}<br>
                                    Tahap: {{ $item->tahap ?? '-' }}<br>
                                    Gol: {{ $item->gol ?? '-' }} | KD: {{ $item->kd ?? '-' }}<br>
                                    SPP: Rp {{ number_format((int) str_replace('.', '', $item->spp ?? '0'), 0, ',', '.') }}<br>
                                    Bulan: {{ $item->info_jadwal['bulan_tampil'] }}<br>
                                    Jadwal: {{ $item->kode_jadwal ? substr($item->kode_jadwal, 1) . ':00' : '-' }}<br>
                                    Guru: {{ $item->guru ?? '-' }}<br>
                                    <!-- misalnya di kolom INFO atau kolom Jadwal -->
                                    Hari: 
                                    @php
                                        $shift = '-';
                                        $hariList = [];

                                        $kode = (int) ($item->kode_jadwal ?? 0);

                                        if ($kode >= 108 && $kode <= 116) {
                                            $shift = 'SRJ';
                                            $hariList = ['Senin', 'Rabu', 'Jumat'];
                                        } elseif ($kode >= 208 && $kode <= 211) {
                                            $shift = 'SKS';
                                            $hariList = ['Selasa', 'Kamis', 'Sabtu'];
                                        } elseif ($kode >= 308 && $kode <= 311) {
                                            $shift = 'S6';
                                            $hariList = ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
                                        }
                                    @endphp

                                    @if ($shift !== '-')
                                        <strong>{{ $shift }}</strong> ({{ implode(' | ', $hariList) }})
                                    @else
                                        -
                                    @endif
                                </td>
                                <td>{{ $item->status }}</td>

                                <!-- KETERANGAN -->
                                <td>
                                    @if (strtolower($item->status ?? '') === 'keluar')
                                        @if ($item->tgl_keluar)
                                            Keluar: {{ \Carbon\Carbon::parse($item->tgl_keluar)->format('d/m/Y') }}
                                            @if ($item->alasan || $item->keterangan)
                                                <br>Ketegori: <small class="text-danger">{{ trim($item->kategori_keluar) }}</small>
                                                <br>Alasan: <small class="text-danger">{{ trim($item->alasan) }}</small> 
                                            @endif
                                        @else
                                            -
                                        @endif
                                    @else
                                        @if ($item->tgl_masuk)
                                            Masuk: {{ \Carbon\Carbon::parse($item->tgl_masuk)->format('d/m/Y') }}
                                        @else
                                            belum ada tgl masuk
                                        @endif
                                    @endif
                                </td>

                                <!-- PERIODE / GARANSI -->
                                <td>
    {{ $item->periode ?? '-' }}

    {{-- PERIODE BELAJAR --}}
    @if ($item->tgl_mulai && $item->tgl_akhir)
        <br>
        <small>
            ({{ \Carbon\Carbon::parse($item->tgl_mulai)->format('d-m-Y') }}
            →
            {{ \Carbon\Carbon::parse($item->tgl_akhir)->format('d-m-Y') }})
        </small>
    @endif

    {{-- ✅ GARANSI HANYA JIKA SUDAH APPROVE --}}
    @if ($item->tgl_surat_garansi)

        <hr class="my-1">

        <small class="text-primary fw-bold">
            📌 GARANSI 372
        </small>

        <br>
        <small>
            Diberikan:
            {{ \Carbon\Carbon::parse($item->tgl_surat_garansi)->format('d-m-Y') }}
        </small>

        <br>
        <small>
            Pengajuan:
            {{ \Carbon\Carbon::parse($item->tgl_pengajuan_garansi)->format('d-m-Y') }}
        </small>

        <br>
        <small>
            Selesai:
            {{ \Carbon\Carbon::parse($item->tgl_selesai_garansi)->format('d-m-Y') }}
        </small>

        <br>
        <small>
            Masa Aktif:
            {{ $item->masa_aktif_garansi ?? '-' }} bulan
        </small>

        <br>
        <small>
            Status:
            @if($item->perpanjang_garansi == 'Aktif')
                <span class="badge bg-success">Aktif</span>
            @elseif(str_contains($item->perpanjang_garansi, 'Segera'))
                <span class="badge bg-warning text-dark">
                    {{ $item->perpanjang_garansi }}
                </span>
            @else
                <span class="badge bg-danger">Habis</span>
            @endif
        </small>

        @if ($item->note_garansi)
            <br>
            <small class="text-muted">
                {{ $item->note_garansi }}
            </small>
        @endif

    @endif
</td>

                                <!-- Aksi -->
                                <td class="text-center" style="min-width: 140px;">
                                <div class="dropdown">
                                    <button class="btn btn-sm btn-outline-secondary dropdown-toggle w-100" type="button"
                                        data-bs-toggle="dropdown">
                                        Aksi
                                    </button>

                                    <ul class="dropdown-menu dropdown-menu-end">
                                        <li>
                                            <a class="dropdown-item" href="{{ route('buku_induk.edit', $item->id) }}">
                                                ✏️ Edit
                                            </a>
                                        </li>
                                        <li>
                                            <button class="dropdown-item" type="button" data-bs-toggle="modal"
                                                data-bs-target="#detailModal{{ $item->id }}">
                                                🔍 Detail
                                            </button>
                                        </li>

                                        @if (auth()->user()?->role === 'admin')
                                            <li>
                                                <a class="dropdown-item" href="{{ route('buku_induk.history', $item->id) }}">
                                                    🕒 Riwayat
                                                </a>
                                            </li>
                                        @endif

                                        <li>
                                            <hr class="dropdown-divider">
                                        </li>

                                        @if (auth()->user()?->role === 'admin')
                                            <li>
                                                <form action="{{ route('buku_induk.destroy', $item->id) }}" method="POST"
                                                    onsubmit="return confirm('Yakin hapus data ini?')">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button class="dropdown-item text-danger">
                                                        🗑️ Hapus
                                                    </button>
                                                </form>
                                            </li>
                                        @endif
                                    </ul>
                                </div>
                            </td>

                            </tr>
                            {{-- MODAL DETAIL – FULL FIELD --}}
                            <div class="modal fade" id="detailModal{{ $item->id }}" tabindex="-1"
                                aria-labelledby="detailModalLabel{{ $item->id }}" aria-hidden="true">
                                <div class="modal-dialog modal-xl">
                                    <div class="modal-content">
                                        <div class="modal-header bg-info text-white">
                                            <h5 class="modal-title" id="detailModalLabel{{ $item->id }}">
                                                Detail Murid: {{ $item->nama }}
                                            </h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal"
                                                aria-label="Close"></button>
                                        </div>
                                        <div class="modal-body">

                                        {{-- =========================
                                            📌 IDENTITAS MURID
                                        ========================= --}}
                                        <div class="border rounded p-3 mb-3">
                                            <h6 class="fw-bold text-primary mb-3">📌 Identitas Murid</h6>
                                            <dl class="row mb-0">
                                                <dt class="col-sm-4">NIM</dt>
                                                <dd class="col-sm-8">: {{ $item->nim }}</dd>

                                                <dt class="col-sm-4">Nama</dt>
                                                <dd class="col-sm-8">: {{ $item->nama }}</dd>

                                                <dt class="col-sm-4">Unit / Cabang</dt>
                                                <dd class="col-sm-8">
                                                    : {{ $item->no_cabang ?? '-' }} | {{ $item->bimba_unit ?? '-' }}
                                                    @if($item->no_cabang)
                                                        
                                                    @endif
                                                </dd>
                                                <dt class="col-sm-4">Tempat Lahir</dt>
                                                <dd class="col-sm-8">: {{ $item->tmpt_lahir }}</dd>

                                                <dt class="col-sm-4">Tanggal Lahir</dt>
                                                <dd class="col-sm-8">: {{ $item->tgl_lahir_formatted }}</dd>

                                                <dt class="col-sm-4">Usia</dt>
                                                @php
                                                    $usiaText = '-';
                                                    if ($item->tgl_lahir) {
                                                        $diff = \Carbon\Carbon::parse($item->tgl_lahir)->diff(now());
                                                        $usiaText = $diff->y . ' tahun ' . $diff->m . ' bulan';
                                                    }
                                                @endphp
                                                <dd class="col-sm-8">: {{ $usiaText }}</dd>
                                                 <dt class="col-sm-4">Tempat Lahir</dt>
                                                <dd class="col-sm-8">: {{ $item->tmpt_lahir }}</dd>
                                                <dt class="col-sm-4">Usia</dt>
                                                @php
                                                    $usiaText = '-';
                                                    if ($item->tgl_lahir) {
                                                        $diff = \Carbon\Carbon::parse($item->tgl_lahir)->diff(now());
                                                        $usiaText = $diff->y . ' tahun ' . $diff->m . ' bulan';
                                                    }
                                                @endphp
                                                <dd class="col-sm-8">: {{ $usiaText }}</dd>
                                                <dt class="col-sm-4">Tanggal Daftar</dt>
                                                <dd class="col-sm-8">: {{ $item->tgl_daftar?->format('d-m-Y') }}</dd>

                                                <dt class="col-sm-4">Tanggal Masuk</dt>
                                                <dd class="col-sm-8">: {{ $item->tgl_masuk_formatted }}</dd>

                                                <dt class="col-sm-4">Lama Belajar</dt>
                                                <dd class="col-sm-8">: {{ $item->lama_bljr }}</dd>

                                                <dt class="col-sm-4">Tahap</dt>
                                                <dd class="col-sm-8">: {{ $item->tahap }}</dd>

                                                <dt class="col-sm-4">Kelas</dt>
                                                <dd class="col-sm-8">: {{ $item->kelas }}</dd>

                                                <dt class="col-sm-4">Golongan</dt>
                                                <dd class="col-sm-8">: {{ $item->gol }} | {{ $item->kd }}</dd>
                                                 <dt class="col-sm-4">Jenis KBM</dt>
                                                <dd class="col-sm-8">: {{ $item->jenis_kbm }}</dd>
                                                <dt class="col-sm-4">Level</dt>
                                                <dd class="col-sm-8">: {{ $item->level }}</dd>
                                                <dt class="col-sm-4">Keterangan Level</dt>
                                                <dd class="col-sm-8">: {{ $item->keterangan_level }}</dd>
                                                <dt class="col-sm-4">Tanggal Perubahan Level</dt>
                                                <dd class="col-sm-8">: {{ $item->tgl_level_formatted }}</dd>
                                                <dt class="col-sm-4">Bulan Aktif</dt>
                                                <dd class="col-sm-8">
                                                    :
                                                    @if($item->info_jadwal['status'] === 'ok')
                                                        <span class="text-primary fw-semibold">
                                                            {{ $item->info_jadwal['bulan_tampil'] }}
                                                        </span>
                                                    @else
                                                        -
                                                    @endif
                                                </dd>
                                                <dt class="col-sm-4">Orangtua</dt>
                                                <dd class="col-sm-8">: {{ $item->orangtua }}</dd>

                                                <dt class="col-sm-4">No HP</dt>
                                                <dd class="col-sm-8">: {{ $item->no_telp_hp }}</dd>

                                                <dt class="col-sm-4">Alamat</dt>
                                                <dd class="col-sm-8">: {{ $item->alamat_murid }}</dd>
                                                <dt class="col-sm-4">SPP</dt>
                                                <dd class="col-sm-8">: Rp {{ number_format((int) str_replace('.', '', $item->spp), 0, ',', '.') }}</dd>

                                                <dt class="col-sm-4">No Pembayaran</dt>
                                                <dd class="col-sm-8">: {{ $item->no_pembayaran_murid }}</dd>
                                                <dt class="col-sm-4">Status</dt>
                                                <dd class="col-sm-8">: {{ $item->status }}</dd>

                                                <dt class="col-sm-4">Kategori Keluar</dt>
                                                <dd class="col-sm-8">: {{ $item->kategori_keluar }}</dd>

                                                <dt class="col-sm-4">Alasan</dt>
                                                <dd class="col-sm-8">: {{ $item->alasan }}</dd>

                                                <dt class="col-sm-4">Tanggal Keluar</dt>
                                                <dd class="col-sm-8">: {{ $item->tgl_keluar }}</dd>
                                                <dt class="col-sm-4">Keterangan Optional</dt>
                                                <dd class="col-sm-8">: {{ $item->keterangan_optional }}</dd>

                                                <dt class="col-sm-4">No Cabang Merge</dt>
                                                <dd class="col-sm-8">: {{ $item->no_cab_merge }}</dd>

                                                

                                                <dt class="col-sm-4">Petugas Trial</dt>
                                                <dd class="col-sm-8">: {{ $item->petugas_trial }}</dd>

                                                <dt class="col-sm-4">Guru</dt>
                                                <dd class="col-sm-8">: {{ $item->guru }}</dd>
                                            </dl>
                                        </div>

                                        {{-- =========================
                                            📊 JADWAL & AKTIVITAS
                                        ========================= --}}
                                        <div class="border rounded p-3 mb-3">
                                            <h6 class="fw-bold text-info mb-3">📊 Jadwal biMBA</h6>
                                            <dl class="row mb-0">

                                                <dt class="col-sm-4">Kode Jadwal</dt>
                                                <dd class="col-sm-8">: {{ $item->kode_jadwal }}</dd>
                                                <dt class="col-sm-4">Hari & Jam</dt>
                                                <dd class="col-sm-8">
                                                    :
                                                    @php
                                                        $shift = '-';
                                                        $hariList = [];
                                                        $jam = '-';

                                                        $kode = (int) ($item->kode_jadwal ?? 0);

                                                        // =====================
                                                        // SHIFT + HARI
                                                        // =====================
                                                        if ($kode >= 108 && $kode <= 116) {
                                                            $shift = 'SRJ';
                                                            $hariList = ['Senin', 'Rabu', 'Jumat'];
                                                        } elseif ($kode >= 208 && $kode <= 211) {
                                                            $shift = 'SKS';
                                                            $hariList = ['Selasa', 'Kamis', 'Sabtu'];
                                                        } elseif ($kode >= 308 && $kode <= 311) {
                                                            $shift = 'S6';
                                                            $hariList = ['Senin','Selasa','Rabu','Kamis','Jumat','Sabtu'];
                                                        }

                                                        // =====================
                                                        // JAM
                                                        // =====================
                                                        if ($kode > 0) {
                                                            $jamAngka = substr($kode, -2);
                                                            $jam = str_pad($jamAngka, 2, '0', STR_PAD_LEFT) . ':00';
                                                        }
                                                    @endphp

                                                    @if ($shift !== '-')
                                                        <strong>{{ $shift }}</strong>
                                                        <span class="text-muted">({{ implode(' | ', $hariList) }})</span>
                                                        -
                                                        <span class="fw-bold text-primary">{{ $jam }}</span>
                                                    @else
                                                        -
                                                    @endif
                                                </dd>
                                            </dl>
                                        </div>

                                        {{-- =========================
                                            📦 BNF dan DU'AFA
                                        ========================= --}}
                                        <div class="border rounded p-3 mb-3">
                                            <h6 class="fw-bold text-dark mb-3">📦 BNF dan DU'AFA</h6>
                                            <dl class="row mb-0">

                                                <dt class="col-sm-4">Periode</dt>
                                                <dd class="col-sm-8">: {{ $item->periode }}</dd>

                                                <dt class="col-sm-4">Tanggal Mulai</dt>
                                                <dd class="col-sm-8">
                                                    {{ $item->tgl_mulai ? \Carbon\Carbon::parse($item->tgl_mulai)->translatedFormat('d F Y') : '-' }}
                                                </dd>

                                                <dt class="col-sm-4">Tanggal Akhir</dt>
                                                <dd class="col-sm-8">
                                                    {{ $item->tgl_akhir ? \Carbon\Carbon::parse($item->tgl_akhir)->translatedFormat('d F Y') : '-' }}
                                                </dd>

                                                <dt class="col-sm-4">Alert</dt>
                                                <dd class="col-sm-8">: {{ $item->alert }}</dd>
                                            </dl>
                                        </div>
                                        
                                        {{-- =========================
                                            Paket 72
                                        ========================= --}}
                                        <div class="border rounded p-3 mb-3">
                                            <h6 class="fw-bold text-muted mb-3">📦 Paket 72
                                            </h6>
                                            <dl class="row mb-0">

                                                <dt class="col-sm-4">Tanggal Bayar</dt>
                                                <dd class="col-sm-8">: {{ $item->tgl_bayar }}</dd>

                                                <dt class="col-sm-4">Tanggal Selesai</dt>
                                                <dd class="col-sm-8">: {{ $item->tgl_selesai }}</dd>

                                                <dt class="col-sm-4">Alert 2</dt>
                                                <dd class="col-sm-8">: {{ $item->alert2 }}</dd>
                                            </dl>
                                        </div>
                                        {{-- =========================
                                            Supply Modul
                                            ========================= --}}
                                        <div class="border rounded p-3 mb-3">
                                            <h6 class="fw-bold text-muted mb-3">📦 Supply Modul
                                            </h6>
                                            <dl class="row mb-0">
                                    
                                                <dt class="col-sm-4">Asal Modul</dt>
                                                <dd class="col-sm-8">: {{ $item->asal_modul }}</dd>

                                                <dt class="col-sm-4">Note Garansi</dt>
                                                <dd class="col-sm-8">: {{ $item->note_garansi }}</dd>
                                            </dl>
                                        </div>

                                        <div class="border rounded p-3 mb-3">
                                            <h6 class="fw-bold text-muted mb-3">📌 MURID PINDAH KE INTERVIO (ONLINE)</h6>
                                            <dl class="row mb-0">
                                                <dt class="col-sm-4">Status Pindah</dt>
                                                <dd class="col-sm-8">: {{ $item->status_pindah }}</dd>

                                                <dt class="col-sm-4">Tanggal Pindah</dt>
                                                <dd class="col-sm-8">: {{ $item->tanggal_pindah }}</dd>

                                                <dt class="col-sm-4">Ke Bimba Intervio</dt>
                                                <dd class="col-sm-8">: {{ $item->ke_bimba_intervio }}</dd>

                                                <dt class="col-sm-4">Keterangan</dt>
                                                <dd class="col-sm-8">: {{ $item->keterangan }}</dd>
                                            </dl>
                                        </div>

                                        {{-- =========================
                                            Surat Garansi BCA 372 Bebas
                                            ========================= --}}

                                        @if ($item->tgl_surat_garansi)

<div class="border rounded p-3 mb-3">
    <h6 class="fw-bold text-muted mb-3">
        📌 SURAT GARANSI BCA 372 BEBAS
    </h6>

    <dl class="row mb-0">

        <dt class="col-sm-4">Tanggal Diberikan</dt>
        <dd class="col-sm-8">
            : {{ \Carbon\Carbon::parse($item->tgl_surat_garansi)->format('d-m-Y') }}
        </dd>

        <dt class="col-sm-4">Tanggal Pengajuan</dt>
        <dd class="col-sm-8">
            : {{ \Carbon\Carbon::parse($item->tgl_pengajuan_garansi)->format('d-m-Y') }}
        </dd>

        <dt class="col-sm-4">Berlaku Sampai</dt>
        <dd class="col-sm-8">
            : {{ \Carbon\Carbon::parse($item->tgl_selesai_garansi)->format('d-m-Y') }}
        </dd>

        <dt class="col-sm-4">Masa Aktif</dt>
        <dd class="col-sm-8">
            : {{ $item->masa_aktif_garansi }} bulan
        </dd>

        <dt class="col-sm-4">Status</dt>
        <dd class="col-sm-8">
            :
            @if($item->perpanjang_garansi == 'Aktif')
                <span class="badge bg-success">Aktif</span>
            @elseif(str_contains($item->perpanjang_garansi, 'Segera'))
                <span class="badge bg-warning text-dark">
                    {{ $item->perpanjang_garansi }}
                </span>
            @else
                <span class="badge bg-danger">Habis</span>
            @endif
        </dd>

        <dt class="col-sm-4">Note</dt>
        <dd class="col-sm-8">
            : {{ $item->note_garansi ?? '-' }}
        </dd>

    </dl>
</div>

@endif

                                    </div>
                                        <div class="modal-footer">
                                            <button type="button" class="btn btn-secondary"
                                                data-bs-dismiss="modal">Tutup</button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @empty
                            <tr>
                                <td colspan="5" class="text-center py-5 text-muted">Tidak ada data</td>
                            </tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="d-flex justify-content-center mt-4">
                    {{ $bukuInduk->onEachSide(1)->links() }}
                </div>
            </div>
        </div>
    </div>
@endsection

@push('styles')
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />

    <style>
        .select2-container .select2-selection--single {
            height: calc(1.5em + .75rem + 2px);
            padding-top: .375rem;
        }

        /* ============================================= */
        /* Mobile & tablet (max 1024px): tetap tabel 5 kolom, termasuk landscape */
        /* ============================================= */
        @media (max-width: 1024px) {
            .container-fluid {
                padding-left: 0 !important;
                padding-right: 0 !important;
            }

            .card-body {
                padding: 1rem 0.5rem !important;
            }

            .table-responsive {
                overflow-x: auto;
                -webkit-overflow-scrolling: touch;
                margin: 0 -0.5rem;
            }

            .table {
                font-size: 0.85rem;
                min-width: 800px;
                /* paksa scroll horizontal jika layar sempit */
            }

            .table th,
            .table td {
                padding: 0.45rem 0.6rem;
                white-space: normal;
                vertical-align: middle;
            }

            /* Sembunyikan header & kolom desktop */
            .d-none.d-lg-table-row,
            .d-none.d-lg-table-cell {
                display: none !important;
            }

            /* Tombol aksi vertikal di kolom kecil */
            .d-flex.gap-1 {
                flex-direction: column;
                gap: 0.35rem !important;
            }

            .btn-sm {
                padding: 0.25rem 0.45rem;
                font-size: 0.78rem;
            }
        }

        /* Desktop besar (>1024px): tabel full kolom */
        @media (min-width: 1025px) {

            .table th,
            .table td {
                white-space: nowrap;
            }
        }
    </style>
@endpush

@push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/jquery@3.7.1/dist/jquery.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script>
        $(document).ready(function () {
            $('#muridFilter').select2({
                width: '100%',
                placeholder: "— Ketik untuk cari NIM atau Nama —",
                allowClear: true
            });

            $('#unitFilter').select2({
                width: '100%',
                placeholder: "— Semua Unit —",
                allowClear: true
            });

            $('#muridFilter, #unitFilter, #statusFilter, #perPageFilter').on('change', function () {
                if (this.id === 'unitFilter') {
                    $('#muridFilter').val(null).trigger('change');
                }
                $('#filterForm').submit();
            });
        });
    </script>
@endpush