@extends('layouts.app')

@section('content')
<div class="container">
    <h2>Edit Data Humas</h2>

    <form action="{{ route('humas.update', $huma->id) }}" method="POST">
        @csrf
        @method('PUT')
        <div class="mb-3">
            <label>Tanggal Registrasi</label>
            <input type="date" name="tgl_reg" class="form-control" value="{{ $huma->tgl_reg }}" required>
        </div>
        <div class="mb-3">
            <label>NIH</label>
            <input type="text" name="nih" class="form-control" value="{{ $huma->nih }}" required>
        </div>
        <div class="mb-3">
            <label>Nama</label>
            <input type="text" name="nama" class="form-control" value="{{ $huma->nama }}" required>
        </div>
        <div class="mb-3">
            <label>Status</label>
            <input type="text" name="status" class="form-control" value="{{ $huma->status }}">
        </div>
        <div class="mb-3">
            <label>No Telp/HP</label>
            <input type="text" name="no_telp" class="form-control" value="{{ $huma->no_telp }}">
        </div>
        <div class="mb-3">
            <label>Pekerjaan</label>
            <input type="text" name="pekerjaan" class="form-control" value="{{ $huma->pekerjaan }}">
        </div>
        <div class="mb-3">
            <label>Alamat</label>
            <textarea name="alamat" class="form-control">{{ $huma->alamat }}</textarea>
        </div>
        <button type="submit" class="btn btn-success">Update</button>
        <a href="{{ route('humas.index') }}" class="btn btn-secondary">Kembali</a>
    </form>
</div>
@endsection
