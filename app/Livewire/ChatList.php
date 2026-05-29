<?php

namespace App\Livewire;

use Livewire\Component;
use App\Models\Conversation;

class ChatList extends Component
{
    protected $listeners = ['refresh-chat-list' => '$refresh'];

    public function render()
    {
        $userId = auth()->id();

        $conversations = Conversation::where('user1_id', $userId)
                        ->orWhere('user2_id', $userId)
                        ->with(['user1', 'user2'])
                        ->withCount(['messages as unread_count' => function($q) use ($userId) {
                            $q->where('sender_id', '!=', $userId);
                        }])
                        ->latest('updated_at')
                        ->get();

        return view('livewire.chat-list', [
            'conversations' => $conversations
        ]);
    }
}