<div class="list-group list-group-flush">
    @forelse($conversations as $conv)
        <a href="{{ route('chat.show', $conv->id) }}" 
           class="list-group-item list-group-item-action position-relative 
           {{ request()->routeIs('chat.show') && 
              (request()->route('conversation')->id ?? 0) == $conv->id ? 'active' : '' }}">
            
            <div class="d-flex w-100 justify-content-between">
                <strong>{{ $conv->other_user->name ?? 'User' }}</strong>
                
                @if($conv->unread_count > 0)
                    <span class="badge bg-danger rounded-pill">{{ $conv->unread_count }}</span>
                @endif
            </div>
            
            <small class="text-truncate d-block">
                {{ $conv->messages->first()?->message ?? 'Mulai percakapan baru...' }}
            </small>
        </a>
    @empty
        <div class="p-4 text-center text-muted">
            Belum ada percakapan
        </div>
    @endforelse
</div>