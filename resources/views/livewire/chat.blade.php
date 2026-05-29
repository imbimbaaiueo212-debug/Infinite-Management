<div class="card h-100">
    <div class="card-header bg-primary text-white">
        <h5 class="mb-0">💬 {{ $conversation->other_user->name ?? '-' }}</h5>
    </div>

    <div class="card-body p-3 overflow-auto" id="chatBody" style="height: 65vh; background: #f8f9fa;">
        @if($messages->isEmpty())
            <div class="h-100 d-flex align-items-center justify-content-center text-muted">
                <div class="text-center">
                    <i class="bi bi-chat-dots-fill display-1 mb-3 opacity-50"></i>
                    <p class="lead">Belum ada pesan</p>
                    <small>Kirim pesan pertama!</small>
                </div>
            </div>
        @else
            @foreach($messages as $msg)
                <div class="mb-3 {{ $msg->sender_id == auth()->id() ? 'text-end' : 'text-start' }}">
                    <div class="d-inline-block p-3 rounded-3 {{ $msg->sender_id == auth()->id() ? 'bg-primary text-white' : 'bg-white border' }}">
                        {{ $msg->message }}
                    </div>
                    <small class="text-muted d-block mt-1">{{ $msg->created_at->diffForHumans() }}</small>
                </div>
            @endforeach
        @endif
    </div>

    <div class="card-footer p-3">
        <form wire:submit="sendMessage">
            <div class="input-group">
                <input type="text" 
                       wire:model="message" 
                       class="form-control" 
                       placeholder="Ketik pesan..." 
                       autocomplete="off">
                <button type="submit" class="btn btn-primary">Kirim</button>
            </div>
        </form>
    </div>
</div>

@push('scripts')
<script>
    function scrollToBottom() {
        const chatBody = document.getElementById('chatBody');
        if (chatBody) chatBody.scrollTop = chatBody.scrollHeight;
    }

    Livewire.hook('message.processed', scrollToBottom);

    // Polling
    setInterval(() => {
        @this.loadMessages();
        scrollToBottom();
    }, 2000);

    // Notifikasi Toast
    document.addEventListener('livewire:navigated', () => {
        window.Echo ? null : console.log('Livewire ready for toast');
    });

    Livewire.on('refresh-chat-list', () => {
        const toast = document.createElement('div');
        toast.className = 'toast align-items-center text-white bg-primary border-0 position-fixed bottom-0 end-0 m-3';
        toast.style.zIndex = '9999';
        toast.innerHTML = `
            <div class="d-flex">
                <div class="toast-body">
                    💬 <strong>Pesan baru masuk!</strong>
                </div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
            </div>
        `;
        document.body.appendChild(toast);
        
        const bsToast = new bootstrap.Toast(toast, { delay: 4000 });
        bsToast.show();

        setTimeout(() => toast.remove(), 4500);
    });
</script>
@endpush