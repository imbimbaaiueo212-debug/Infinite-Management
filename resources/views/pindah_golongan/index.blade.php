@extends('layouts.app')

@section('title', 'Pindah Golongan')

@section('content')
    <main>
        <div class="container-fluid px-4">
            <h2 class="mt-4">Data Murid Pindah Golongan</h2>

            <div class="card mb-4">
                <div class="card-body">

                    {{-- Tombol Update Golongan --}}
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h5 class="mb-0 fw-semibold">Data Pindah Golongan</h5>
                        <div>
                            <a href="{{ route('pindah-golongan.index', ['sync' => 1]) }}"
                               class="btn btn-outline-primary"
                               onclick="return confirm('Jalankan sinkronisasi dari Google Sheet sekarang?')">
                                Update Golongan
                            </a>
                        </div>
                    </div>

                    @if (session('success'))
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            {{ session('success') }}
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    @endif

                    {{-- FORM FILTER --}}
                    <form method="GET" action="{{ route('pindah-golongan.index') }}" id="filterForm" class="mb-4">
                        <div class="row g-3 align-items-end">

                            @if (auth()->check() && (auth()->user()->is_admin ?? false))
                                <div class="col-md-3 col-lg-2">
                                    <label class="form-label fw-bold small">Unit</label>
                                    <select name="unit" id="unitFilter" class="form-select form-select-sm">
                                        <option value="">-- Semua Unit --</option>
                                        @foreach($units as $u)
                                            <option value="{{ $u }}" {{ request('unit') == $u ? 'selected' : '' }}>
                                                {{ $u }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                            @endif

                            <div class="col-md-{{ auth()->check() && (auth()->user()->is_admin ?? false) ? '5' : '6' }} col-lg-{{ auth()->check() && (auth()->user()->is_admin ?? false) ? '4' : '5' }}">
                                <label class="form-label fw-bold small">NIM | Nama Murid</label>
                                <select name="nim" id="nimFilter" class="form-select form-select-sm">
                                    <option value="">— Ketik untuk cari NIM atau Nama —</option>
                                    @foreach($muridOptions as $m)
                                        @php $nimPad = str_pad($m->nim, 3, '0', STR_PAD_LEFT); @endphp
                                        <option value="{{ $m->nim }}" {{ request('nim') == $m->nim ? 'selected' : '' }}>
                                            {{ $nimPad }} | {{ $m->nama }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="col-md-2 col-lg-2">
                                <label class="form-label fw-bold small">Tanggal Dari</label>
                                <input type="date" name="tanggal_dari" class="form-control form-control-sm"
                                       value="{{ request('tanggal_dari') }}">
                            </div>

                            <div class="col-md-2 col-lg-2">
                                <label class="form-label fw-bold small">Tanggal Sampai</label>
                                <input type="date" name="tanggal_sampai" class="form-control form-control-sm"
                                       value="{{ request('tanggal_sampai') }}">
                            </div>

                            <div class="col-md-auto col-lg-2 d-flex align-items-end gap-2">
                                <button type="submit" class="btn btn-primary btn-sm flex-grow-1">Filter</button>
                                <a href="{{ route('pindah-golongan.index') }}" 
                                   class="btn btn-outline-secondary btn-sm flex-grow-1">Reset</a>
                            </div>

                        </div>
                    </form>

                    <div class="table-responsive">
                        <table class="table table-bordered table-striped table-sm">
                            <thead class="table-light">
                                <tr>
                                    <th>NIM</th>
                                    <th>Nama</th>
                                    <th>Guru</th>
                                    <th>Gol Lama</th>
                                    <th>Kode Lama</th>
                                    <th>SPP Lama (Rp)</th>
                                    <th>Unit</th>
                                    <th>Cabang</th>
                                    <th>Gol Baru</th>
                                    <th>Kode Baru</th>
                                    <th>SPP Baru (Rp)</th>
                                    <th>Tanggal Pindah</th>
                                    <th>Keterangan</th>
                                    <th>Alasan Pindah</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($data as $item)
                                    @php
                                        $golLama    = $item->gol ?? ($item->bukuInduk->gol ?? '-');
                                        $kdLama     = $item->kd  ?? ($item->bukuInduk->kd  ?? '-');
                                        $sppLamaRaw = $item->spp ?? ($item->bukuInduk->spp ?? 0);
                                        $sppLama    = (int) str_replace('.', '', $sppLamaRaw);

                                        $unit = $item->bimba_unit ?? ($item->bukuInduk->bimba_unit ?? '-');
                                        $cab  = $item->no_cabang  ?? ($item->bukuInduk->no_cabang  ?? '-');

                                        $tglPindah = $item->tanggal_pindah_golongan
                                            ? \Carbon\Carbon::parse($item->tanggal_pindah_golongan)->format('d-m-Y')
                                            : '-';
                                    @endphp
                                    <tr>
                                        <td>{{ $item->nim }}</td>
                                        <td>{{ $item->nama }}</td>
                                        <td>{{ $item->guru ?? '-' }}</td>
                                        <td>{{ $golLama }}</td>
                                        <td>{{ $kdLama }}</td>
                                        <td class="text-end">Rp {{ number_format($sppLama, 0, ',', '.') }}</td>
                                        <td>{{ $unit }}</td>
                                        <td>{{ $cab }}</td>
                                        <td>{{ $item->gol_baru ?? '-' }}</td>
                                        <td>{{ $item->kd_baru ?? '-' }}</td>
                                        <td class="text-end">Rp {{ number_format((int) str_replace('.', '', $item->spp_baru ?? 0), 0, ',', '.') }}</td>
                                        <td>{{ $tglPindah }}</td>
                                        <td>{{ $item->keterangan ?? '-' }}</td>
                                        <td>{{ $item->alasan_pindah ?? '-' }}</td>
                                        <td class="text-nowrap">
                                            <a href="{{ route('pindah-golongan.edit', $item->id) }}"
                                               class="btn btn-warning btn-sm">Edit</a>

                                            @if (auth()->user()?->role === 'admin')
                                                <form action="{{ route('pindah-golongan.destroy', $item->id) }}" method="POST"
                                                      class="d-inline">
                                                    @csrf @method('DELETE')
                                                    <button type="submit" class="btn btn-danger btn-sm"
                                                            onclick="return confirm('Yakin hapus data ini?')">Hapus</button>
                                                </form>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="15" class="text-center py-4 text-muted fst-italic">
                                            Tidak ada data
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    {{-- Petunjuk --}}
                    <div class="mt-4 pt-3 border-top">
                        <h6 class="fw-bold">Cara Menggunakan Pindah Golongan:</h6>
                        <ul class="mb-0 small text-muted">
                            <li>Jika ada murid yang ingin pindah golongan, salin link form berikut dan kirim ke orang tua:</li>
                            <li>
                                <a href="javascript:void(0)" onclick="copyLink(this)"
                                   data-url="https://docs.google.com/forms/d/e/1FAIpQLSd2TtFBNPaUMJ7vq93Y2ZAevDQYVT_QW_iEcCkNwTwN08TJnQ/viewform?usp=dialog"
                                   class="text-primary text-decoration-underline">
                                    Salin Link Form
                                </a>
                            </li>
                            <li>Setelah orang tua mengisi form → klik tombol <strong>Update Golongan</strong> di atas.</li>
                        </ul>
                    </div>

                </div>
            </div>
        </div>
    </main>

    <!-- Script copy link (sama seperti sebelumnya) -->
    <script>
        function copyLink(el) {
            const url = el.getAttribute('data-url');
            navigator.clipboard.writeText(url).then(() => {
                const original = el.textContent;
                el.textContent = '✓ Tersalin!';
                el.style.color = '#198754';
                setTimeout(() => {
                    el.textContent = original;
                    el.style.color = '';
                }, 1800);
            }).catch(() => {
                alert('Gagal menyalin. Silakan salin manual.');
            });
        }
    </script>

    @push('styles')
        <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    @endpush

    @push('scripts')
        <script src="https://cdn.jsdelivr.net/npm/jquery@3.7.1/dist/jquery.min.js"></script>
        <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
        <script>
            $(document).ready(function () {
                $('#nimFilter').select2({
                    width: '100%',
                    placeholder: "— Ketik untuk cari NIM atau Nama —",
                    allowClear: true,
                    minimumInputLength: 2   // mulai cari setelah 2 karakter
                });

                $('#unitFilter').select2({
                    width: '100%',
                    placeholder: "-- Semua Unit --",
                    allowClear: true
                });

                // Auto submit saat ganti filter (kecuali jika masih mengetik di select2 nim)
                $('#unitFilter, #tanggalDari, #tanggalSampai').on('change', function () {
                    if ($(this).attr('id') === 'unitFilter') {
                        $('#nimFilter').val(null).trigger('change');
                    }
                    $('#filterForm').submit();
                });

                // Untuk nimFilter, submit hanya saat pilihan benar-benar dipilih (bukan saat mengetik)
                $('#nimFilter').on('select2:select', function () {
                    $('#filterForm').submit();
                });
            });
        </script>
    @endpush
@endsection