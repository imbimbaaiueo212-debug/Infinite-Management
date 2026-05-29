<?php

namespace App\Livewire;

use Livewire\Component;
use App\Models\Conversation;
use App\Models\Message;

class Chat extends Component
{
    public $conversationId;
    public $message = '';

    public function mount($conversationId)
    {
        $this->conversationId = $conversationId;
        $this->markAsRead();
    }

    public function markAsRead()
    {
        Message::where('conversation_id', $this->conversationId)
               ->where('sender_id', '!=', auth()->id())
               ->update(['is_read' => true]);
    }

    public function sendMessage()
    {
        $this->validate([
            'message' => 'required|string|min:1|max:1000'
        ]);

        Message::create([
            'conversation_id' => $this->conversationId,
            'sender_id' => auth()->id(),
            'message' => trim($this->message),
        ]);

        $this->message = '';
        $this->dispatch('new-message');
        $this->dispatch('message.processed');
    }

    // Tambahkan method ini
    public function loadMessages()
    {
        // Kosongkan saja, render() akan otomatis refresh
    }

    public function render()
    {
        $conversation = Conversation::with([
            'user1', 
            'user2', 
            'messages' => fn($q) => $q->orderBy('created_at', 'asc')
        ])->findOrFail($this->conversationId);

        return view('livewire.chat', [
            'conversation' => $conversation,
            'messages' => $conversation->messages ?? collect([])
        ]);
    }
}