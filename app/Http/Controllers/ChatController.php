<?php

namespace App\Http\Controllers;

use App\Models\Conversation;
use Illuminate\Http\Request;

class ChatController extends Controller
{
    public function index()
    {
        return view('chat.index');
    }

    public function show(Conversation $conversation)
    {
        if (!in_array(auth()->id(), [$conversation->user1_id, $conversation->user2_id])) {
            abort(403, 'Anda tidak memiliki akses ke percakapan ini.');
        }

        return view('chat.show', compact('conversation'));
    }

    public function createConversation(Request $request)
    {
        $request->validate([
            'user_id' => 'required|exists:users,id|not_in:' . auth()->id()
        ]);

        $user1 = min(auth()->id(), $request->user_id);
        $user2 = max(auth()->id(), $request->user_id);

        $conversation = Conversation::firstOrCreate([
            'user1_id' => $user1,
            'user2_id' => $user2,
        ]);

        return redirect()->route('chat.show', $conversation->id)
                         ->with('success', 'Percakapan berhasil dibuat.');
    }

    public function markAsRead(Conversation $conversation)
    {
        // Optional: mark messages as read
        return response()->json(['success' => true]);
    }
}