@extends('layouts.app')

@section('title', 'Chat')

@section('content')
<div class="container-fluid mt-4">
    <div class="row">
        
        <!-- LEFT: Daftar Chat -->
        <div class="col-lg-4 col-xl-3">
            <div class="card shadow-sm h-100">
                <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">💬 Percakapan</h5>
                    <button class="btn btn-sm btn-light" data-bs-toggle="modal" data-bs-target="#newChatModal">
                        <i class="bi bi-plus-lg"></i> Baru
                    </button>
                </div>
                <div class="card-body p-0">
                    @livewire('chat-list')
                </div>
            </div>
        </div>

        <!-- RIGHT: Area Chat -->
        <div class="col-lg-8 col-xl-9">
            <div class="card shadow-sm h-100">
                <div class="card-body d-flex flex-column align-items-center justify-content-center text-center text-muted" style="min-height: 70vh;">
                    <i class="bi bi-chat-dots-fill display-1 mb-3 opacity-50"></i>
                    <h4>Pilih Percakapan</h4>
                    <p class="lead">Pilih salah satu percakapan di sebelah kiri untuk mulai mengobrol</p>
                </div>
            </div>
        </div>

    </div>
</div>

<!-- Modal Buat Chat Baru -->
<div class="modal fade" id="newChatModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Mulai Percakapan Baru</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="{{ route('chat.create') }}" method="POST">
                @csrf
                <div class="modal-body">
                    <select name="user_id" class="form-select" required>
                        <option value="">-- Pilih User --</option>
                        @foreach(\App\Models\User::where('id', '!=', auth()->id())->orderBy('name')->get() as $user)
                            <option value="{{ $user->id }}">{{ $user->name }} ({{ $user->email }})</option>
                        @endforeach
                    </select>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary">Mulai Chat</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection