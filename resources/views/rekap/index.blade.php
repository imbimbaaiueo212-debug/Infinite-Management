@extends('layouts.app')

@section('title', 'Rekap Jadwal Guru biMBA')

@section('content')
    <main>
        <div class="container-fluid px-4">
            <h2 class="mt-4 mb-4 fw-bold">Rekap Jadwal & Absensi Guru biMBA</h2>

            <div class="card shadow-sm mb-4">
                <div class="card-body">

                    <!-- Header + Tombol Sinkron -->
                    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
                        <h5 class="mb-0 fw-semibold">Rekap Jadwal Guru</h5>
                        <div class="d-flex gap-2">
                            <a href="{{ route('rekap.updateKodeJadwal') }}" 
                               class="btn btn-outline-primary btn-sm"
                               onclick="return confirm('Yakin sinkron semua data dari Profile?')">
                                <i class="bi bi-arrow-repeat me-1"></i> Sinkron Data
                            </a>

                            <div class="dropdown">
                                <button class="btn btn-outline-secondary btn-sm px-3" type="button" data-bs-toggle="dropdown">
                                    <i class="bi bi-three-dots-vertical"></i>
                                </button>
                                <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                                    <li>
                                        <a class="dropdown-item" href="{{ route('rekap.create') }}">
                                            <i class="bi bi-arrow-clockwise me-2"></i> Sinkron Guru Aktif
                                        </a>
                                    </li>
                                    <li><hr class="dropdown-divider"></li>
                                    <li>
                                        <a class="dropdown-item" href="{{ url()->current() }}">
                                            <i class="bi bi-arrow-clockwise me-2"></i> Refresh Halaman
                                        </a>
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </div>

                    <!-- Filter -->
                    <form id="filterForm" class="mb-4">
                        <div class="row g-3 align-items-end">

                            @if (auth()->check() && (auth()->user()->is_admin ?? false))
                                <div class="col-md-4 col-lg-3">
                                    <label class="form-label fw-bold small">Unit & Cabang</label>
                                    <select id="filterUnitCabang" class="form-select form-select-sm">
                                        <option value="">— Semua Unit & Cabang —</option>
                                        @foreach(
                                            $rekap
                                                ->unique(fn($row) => ($row->bimba_unit ?? '').'|'.($row->no_cabang ?? ''))
                                                ->sortBy(['bimba_unit', 'no_cabang']) as $row
                                        )
                                            @if($row->bimba_unit || $row->no_cabang)
                                                <option value="{{ ($row->bimba_unit ?? '').'|'.($row->no_cabang ?? '') }}">
                                                    {{ $row->bimba_unit ?? '—' }}
                                                    @if($row->no_cabang) ({{ $row->no_cabang }}) @endif
                                                </option>
                                            @endif
                                        @endforeach
                                    </select>
                                </div>
                            @endif

                            <div class="col-md-{{ auth()->check() && (auth()->user()->is_admin ?? false) ? '5' : '8' }} col-lg-{{ auth()->check() && (auth()->user()->is_admin ?? false) ? '5' : '8' }}">
                                <label class="form-label fw-bold small">Cari Nama / NIK</label>
                                <select id="searchInput" class="form-select form-select-sm">
                                    <option value="">— Semua —</option>
                                    @foreach($rekap->sortBy('nama_relawan') as $row)
                                        <option value="{{ strtolower($row->nama_relawan) }}|{{ strtolower($row->nik ?? '') }}">
                                            {{ $row->nik ?? '—' }} | {{ $row->nama_relawan }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="col-md-auto d-flex align-items-end gap-2">
                                <button type="button" onclick="resetFilters()" class="btn btn-outline-secondary btn-sm">
                                    <i class="bi bi-arrow-counterclockwise me-1"></i> Reset
                                </button>
                            </div>

                        </div>
                    </form>

                    <!-- Tabel -->
                    <div class="table-responsive">
                        <table class="table table-bordered table-hover table-sm mb-0 text-center align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th rowspan="2" class="fw-semibold align-middle">No</th>
                                    <th rowspan="2" class="fw-semibold align-middle">NIK</th>
                                    <th rowspan="2" class="fw-semibold text-start align-middle ps-3">Nama Relawan</th>
                                    <th rowspan="2" class="fw-semibold align-middle">Jabatan</th>
                                    <th rowspan="2" class="fw-semibold align-middle">Departemen</th>
                                    <th rowspan="2" class="fw-semibold align-middle">Unit biMBA</th>
                                    <th rowspan="2" class="fw-semibold align-middle">Cabang</th>

                                    <th colspan="8" class="bg-success-subtle text-success fw-bold">SRJ (Sen-Rab-Jum)</th>
                                    <th colspan="4" class="bg-info-subtle text-info fw-bold">SKS (Sel-Kam-Sab)</th>
                                    <th colspan="4" class="bg-warning-subtle text-warning-emphasis fw-bold">S6 (5-6 Hari)</th>

                                    <th rowspan="2" class="fw-semibold align-middle">Murid</th>
                                    <th rowspan="2" class="fw-semibold align-middle">Rombim</th>
                                    <th rowspan="2" class="fw-semibold align-middle">Adj. RB</th>
                                    <th rowspan="2" class="fw-semibold align-middle text-center">Aksi</th>
                                </tr>
                                <tr class="small fw-medium">
                                    @foreach([108,109,110,111,112,113,114,115] as $k)
                                        <th>{{ $k }}</th>
                                    @endforeach
                                    @foreach([208,209,210,211] as $k)
                                        <th>{{ $k }}</th>
                                    @endforeach
                                    @foreach([308,309,310,311] as $k)
                                        <th>{{ $k }}</th>
                                    @endforeach
                                </tr>
                            </thead>

                            <tbody id="rekapTable">
                                @forelse($rekap->sortBy('bimba_unit')->sortBy('no_cabang') as $index => $row)
                                    <tr data-unit="{{ $row->bimba_unit }}"
                                        data-cabang="{{ $row->no_cabang }}"
                                        data-nama="{{ strtolower($row->nama_relawan) }}"
                                        data-nik="{{ $row->nik ?? '' }}">
                                        <td class="fw-bold">{{ $loop->iteration }}</td>
                                        <td class="font-monospace">{{ $row->nik ?? '—' }}</td>
                                        <td class="text-start fw-semibold ps-3">{{ $row->nama_relawan }}</td>
                                        <td>{{ $row->jabatan ?? '—' }}</td>
                                        <td>{{ $row->departemen ?? '—' }}</td>
                                        <td class="bg-light fw-medium">{{ $row->bimba_unit ?? '—' }}</td>
                                        <td class="bg-light fw-bold text-primary">{{ $row->no_cabang ?? '—' }}</td>

                                        @foreach([108,109,110,111,112,113,114,115] as $kode)
                                            <td>{{ $row->{"srj_{$kode}"} ?? '' }}</td>
                                        @endforeach
                                        @foreach([208,209,210,211] as $kode)
                                            <td>{{ $row->{"sks_{$kode}"} ?? '' }}</td>
                                        @endforeach
                                        @foreach([308,309,310,311] as $kode)
                                            <td>{{ $row->{"s6_{$kode}"} ?? '' }}</td>
                                        @endforeach

                                        <td class="fw-medium">{{ $row->jumlah_murid ?? '—' }}</td>
                                        <td class="fw-medium">{{ $row->jumlah_rombim ?? '—' }}</td>
                                        <td class="fw-bold text-danger">{{ $row->penyesuaian_rb ?? 0 }}</td>
                                        <td class="text-center text-nowrap">
                                            <div class="btn-group btn-group-sm">
                                                <a href="{{ route('rekap.edit', $row->id) }}" 
                                                   class="btn btn-warning btn-sm" title="Edit">
                                                    <i class="fas fa-edit"></i>
                                                </a>

                                                @if(auth()->check() && auth()->user()->is_admin ?? false)
                                                    <form action="{{ route('rekap.destroy', $row->id) }}" method="POST" class="d-inline">
                                                        @csrf @method('DELETE')
                                                        <button type="submit" class="btn btn-danger btn-sm"
                                                                onclick="return confirm('Yakin hapus {{ addslashes($row->nama_relawan) }}?')">
                                                            <i class="fas fa-trash"></i>
                                                        </button>
                                                    </form>
                                                @endif
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="35" class="text-center py-5 text-muted">
                                            <div class="my-5">
                                                <i class="bi bi-inbox fs-1 text-secondary"></i><br><br>
                                                <h6 class="mb-3">Belum ada data rekap absensi</h6>
                                                <a href="{{ route('rekap.create') }}" class="btn btn-primary btn-sm">
                                                    <i class="bi bi-arrow-repeat me-1"></i> Sinkron Guru Aktif
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    <!-- Footer Info -->
                    <div class="d-flex justify-content-between align-items-center mt-4 text-muted small flex-wrap gap-3">
                        <div>
                            Total: <strong class="text-dark">{{ $rekap->count() }}</strong> guru
                        </div>
                        <div>
                            Terakhir update: {{ now()->format('d M Y • H:i') }}
                        </div>
                    </div>

                </div>
            </div>
        </div>
    </main>

    <script>
    document.addEventListener('DOMContentLoaded', () => {
        const searchInput      = document.getElementById('searchInput');
        const filterUnitCabang = document.getElementById('filterUnitCabang');
        const rows             = document.querySelectorAll('#rekapTable tr[data-nama]');

        function filterTable() {
            let searchValue = (searchInput?.value || '').trim().toLowerCase();
            if (searchValue.includes('|')) {
                searchValue = searchValue.split('|')[0].trim(); // ambil nama saja
            }

            const unitValue = (filterUnitCabang?.value || '').trim();

            let visibleCount = 0;

            rows.forEach(row => {
                const namaRaw   = (row.dataset.nama   || '').trim().toLowerCase();
                const nikRaw    = (row.dataset.nik    || '').trim().toLowerCase();
                const unit      = (row.dataset.unit   || '').trim();
                const cabang    = (row.dataset.cabang || '').trim();

                const matchSearch = !searchValue || 
                                    namaRaw.includes(searchValue) || 
                                    nikRaw.includes(searchValue);

                let matchUnit = true;
                if (unitValue) {
                    matchUnit = `${unit}|${cabang}` === unitValue;
                }

                const show = matchSearch && matchUnit;
                row.style.display = show ? '' : 'none';

                if (show) visibleCount++;
            });

            // Update total di footer
            const totalEl = document.querySelector('.d-flex.justify-content-between strong.text-dark');
            if (totalEl) {
                totalEl.textContent = visibleCount;
            }
        }

        if (searchInput) {
            searchInput.addEventListener('change', filterTable);
            searchInput.addEventListener('input', filterTable);
        }

        if (filterUnitCabang) {
            filterUnitCabang.addEventListener('change', filterTable);
        }

        // Jalankan awal
        filterTable();
    });

    function resetFilters() {
        const searchInput      = document.getElementById('searchInput');
        const filterUnitCabang = document.getElementById('filterUnitCabang');

        if (searchInput)      searchInput.value = '';
        if (filterUnitCabang) filterUnitCabang.value = '';

        document.querySelectorAll('#rekapTable tr[data-nama]').forEach(row => {
            row.style.display = '';
        });

        const totalEl = document.querySelector('.d-flex.justify-content-between strong.text-dark');
        if (totalEl) {
            totalEl.textContent = '{{ $rekap->count() }}';
        }
    }
    </script>
@endsection