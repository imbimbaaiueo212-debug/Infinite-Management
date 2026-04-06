@extends('layouts.app')

@section('title', 'Garansi BCA 372')

@section('content')
<div class="card card-body">
    <div class="card shadow-sm">
        <div class="card-body">
            <h1 class="mb-4">Daftar Garansi BCA 372</h1>

            {{-- TOMBOL TAMBAH --}}
            <a href="{{ route('garansi-bca.create') }}" class="btn btn-primary mb-3">
                Tambah Garansi BCA
            </a>

            {{-- NOTIFIKASI --}}
            @if(session('success'))
                <div class="alert alert-success">
                    {{ session('success') }}
                </div>
            @endif

            {{-- TABEL --}}
            <div class="table-responsive">
                <table class="table table-bordered table-striped align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>No</th>
                            <th>Virtual Account</th>
                            <th>Nama Murid</th>
                            <th>Tempat & Tanggal Lahir</th>
                            <th>Tanggal Masuk</th>
                            <th>Tanggal Diberikan</th>
                            <th>Nama Orang Tua / Wali</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($data as $index => $d)
                            <tr>
                                <td>{{ $index + 1 }}</td>
                                <td>{{ $d->virtual_account ?? '-' }}</td>
                                <td>{{ $d->nama_murid ?? '-' }}</td>
                                <td>{{ $d->tempat_tanggal_lahir ?? '-' }}</td>

                                <td>
                                    {{ $d->tanggal_masuk
                                        ? $d->tanggal_masuk->format('d-m-Y')
                                        : '-' }}
                                </td>

                                <td>
                                    {{ $d->tanggal_diberikan
                                        ? $d->tanggal_diberikan->format('d-m-Y')
                                        : '-' }}
                                </td>

                                <td>{{ $d->nama_orang_tua_wali ?? '-' }}</td>

                                <td class="text-nowrap">
    <a href="{{ route('garansi-bca.edit', $d->id) }}"
       class="btn btn-sm btn-warning">
        Edit
    </a>
    <a href="{{ route('garansi-bca.pdf', $d->id) }}"
       target="_blank"
       class="btn btn-sm btn-info">
        PDF
    </a>

    @if (auth()->user()?->role === 'admin')
        <form action="{{ route('garansi-bca.destroy', $d->id) }}"
              method="POST"
              class="d-inline"
              onsubmit="return confirm('Yakin ingin menghapus data ini?')">
            @csrf
            @method('DELETE')
            <button type="submit"
                    class="btn btn-sm btn-danger">
                Hapus
            </button>
        </form>
    @endif
</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="text-center text-muted">
                                    Tidak ada data Garansi BCA.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

        </div>
    </div>
</div>
@endsection
