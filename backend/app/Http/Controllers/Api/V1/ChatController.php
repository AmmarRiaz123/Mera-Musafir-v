<?php

namespace App\Http\Controllers\Api\V1;

use App\Events\MessageSent;
use App\Http\Controllers\Controller;
use App\Models\GroupChat;
use App\Models\Message;
use App\Models\Trip;
use Illuminate\Http\Request;

class ChatController extends Controller
{
    public function messages(Trip $trip)
    {
        if (!$trip->joinedMembers()->where('user_id', auth()->id())->exists()) {
            return response()->json(['message' => 'You are not a member of this trip'], 403);
        }

        $chat = $trip->chat ?? GroupChat::create([
            'trip_id' => $trip->id,
            'name'    => $trip->title . ' Chat',
        ]);

        $messages = $chat->messages()
            ->with('sender')
            ->latest()
            ->limit(50)
            ->get()
            ->reverse()
            ->values()
            ->map(fn($msg) => $this->formatMessage($msg));

        return response()->json(['data' => $messages]);
    }

    public function send(Request $request, Trip $trip)
    {
        if (!$trip->joinedMembers()->where('user_id', auth()->id())->exists()) {
            return response()->json(['message' => 'You are not a member of this trip'], 403);
        }

        $validated = $request->validate([
            'body' => 'required|string|max:1000',
        ]);

        $chat = $trip->chat ?? GroupChat::create([
            'trip_id' => $trip->id,
            'name'    => $trip->title . ' Chat',
        ]);

        $message = Message::create([
            'chat_id'   => $chat->id,
            'sender_id' => auth()->id(),
            'body'      => $validated['body'],
            'type'      => 'text',
        ]);

        $message->load('sender', 'chat');

        broadcast(new MessageSent($message));

        return response()->json([
            'message' => 'Message sent',
            'data'    => $this->formatMessage($message),
        ], 201);
    }

    private function formatMessage(Message $msg): array
    {
        return [
            'id'         => $msg->id,
            'body'       => $msg->body,
            'type'       => $msg->type,
            'sender'     => [
                'id'     => $msg->sender->id,
                'name'   => $msg->sender->name,
                'avatar' => $msg->sender->avatar,
            ],
            'created_at' => $msg->created_at,
            'chat_id'    => $msg->chat_id,
        ];
    }
}
