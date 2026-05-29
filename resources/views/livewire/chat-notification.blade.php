<li class="nav-item position-relative me-3">
    <a href="{{ route('chat.index') }}" class="nav-link text-dark position-relative">
        <i class="fas fa-comment-dots fs-5"></i>
        @if($unreadCount > 0)
            <span class="badge bg-danger position-absolute top-0 start-100 translate-middle rounded-pill">
                {{ $unreadCount > 9 ? '9+' : $unreadCount }}
            </span>
        @endif
    </a>
</li>

@push('scripts')
<script>
    Livewire.on('new-message', () => {
        // Optional: Tambah efek atau toast kecil
        console.log('🛎️ Pesan baru diterima!');
    });
</script>
@endpush