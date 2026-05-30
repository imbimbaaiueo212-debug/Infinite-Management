<!-- Chat Notification -->
<li class="nav-item position-relative me-3">
    <a href="{{ route('chat.index') }}" class="nav-link text-dark position-relative" title="Percakapan">
        <i class="fa-regular fa-message fs-5 
            @if($unreadCount > 0) fa-beat @endif">
        </i>
        
        @if($unreadCount > 0)
            <span class="badge bg-danger position-absolute top-0 start-100 translate-middle rounded-pill px-1" 
                  style="font-size: 0.75rem; font-weight: bold;">
                {{ $unreadCount > 9 ? '9+' : $unreadCount }}
            </span>
        @endif
    </a>
</li>

@push('scripts')
<script>
    Livewire.on('new-message', () => {
        console.log('🛎️ Pesan baru diterima!');
        
        // Optional: Toast Notification
        const toastHTML = `
            <div class="toast align-items-center text-white bg-primary border-0 shadow" role="alert">
                <div class="d-flex">
                    <div class="toast-body">
                        💬 <strong>Pesan baru masuk!</strong>
                    </div>
                    <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
                </div>
            </div>`;

        let container = document.getElementById('chatToastContainer');
        if (!container) {
            container = document.createElement('div');
            container.id = 'chatToastContainer';
            container.className = 'toast-container position-fixed bottom-0 end-0 p-3';
            document.body.appendChild(container);
        }

        container.insertAdjacentHTML('beforeend', toastHTML);
        const toast = new bootstrap.Toast(container.lastElementChild, { delay: 4000 });
        toast.show();

        setTimeout(() => container.lastElementChild.remove(), 5000);
    });
</script>
@endpush