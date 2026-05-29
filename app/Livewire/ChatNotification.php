<?php

namespace App\Livewire;

use Livewire\Component;
use App\Models\Conversation;

class ChatNotification extends Component
{
    public $unreadCount = 0;

    protected $listeners = ['new-message' => '$refresh'];

    public function mount()
    {
        $this->updateCount();
    }

    public function updateCount()
    {
        $userId = auth()->id();

        $this->unreadCount = Conversation::where('user1_id', $userId)
                            ->orWhere('user2_id', $userId)
                            ->withCount(['messages as unread_count' => function($q) use ($userId) {
                                $q->where('sender_id', '!=', $userId)
                                  ->where('is_read', false);
                            }])
                            ->get()
                            ->sum('unread_count');
    }

    public function render()
    {
        $this->updateCount();
        return view('livewire.chat-notification');
    }
}