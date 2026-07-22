<?php

namespace App\Http\Controllers\Api\V1;

use App\Events\ConversationMessageSent;
use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Models\ConversationMessage;
use App\Models\MessageRequest;
use App\Models\UserFollow;
use App\Support\ImageUrl;
use Illuminate\Http\Request;

class MessageRequestController extends Controller
{
    /**
     * Pending message requests addressed to the authenticated user.
     */
    public function index(Request $request)
    {
        $requests = MessageRequest::with('requester')
            ->where('recipient_id', $request->user()->id)
            ->where('status', 'pending')
            ->latest()
            ->get()
            ->map(fn (MessageRequest $r) => [
                'id'         => $r->id,
                'message'    => $r->message,
                'created_at' => $r->created_at,
                'requester'  => [
                    'id'          => $r->requester->id,
                    'name'        => $r->requester->name,
                    'avatar'      => ImageUrl::resolve($r->requester->avatar),
                    'is_verified' => (bool) $r->requester->is_verified,
                ],
            ]);

        return response()->json(['data' => $requests]);
    }

    /**
     * Create/refresh a message request the authenticated user is sending to
     * someone whose DM privacy gates them. The message is held until accepted.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'recipient_id' => 'required|exists:users,id',
            'message'      => 'nullable|string|max:1000',
        ]);

        $me = $request->user()->id;

        if ((int) $validated['recipient_id'] === $me) {
            return response()->json(['message' => 'Cannot message yourself'], 422);
        }

        MessageRequest::updateOrCreate(
            ['requester_id' => $me, 'recipient_id' => $validated['recipient_id']],
            ['status' => 'pending', 'message' => $validated['message'] ?? null],
        );

        // Follow them as part of asking.
        //
        // Accepting already makes the recipient follow the requester, which lets
        // the requester keep writing. But the reply direction was never opened:
        // if the requester also limits DMs to people they follow, the person who
        // just accepted gets blocked answering the message they accepted. Asking
        // to talk to someone implies wanting to hear back, so the follow goes out
        // with the request — and the compose form says so before you send.
        UserFollow::firstOrCreate([
            'follower_id'  => $me,
            'following_id' => (int) $validated['recipient_id'],
        ]);

        return response()->json(['message' => 'Message request sent'], 201);
    }

    /**
     * Accept a request: open the conversation, deliver the held message (if any),
     * and follow the requester so their future messages pass the privacy gate.
     */
    public function accept(Request $request, MessageRequest $messageRequest)
    {
        $me = $request->user()->id;

        if ($messageRequest->recipient_id !== $me) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $conversation = Conversation::findOrCreateBetween($messageRequest->requester_id, $me);

        if (filled($messageRequest->message)) {
            $message = ConversationMessage::create([
                'conversation_id' => $conversation->id,
                'sender_id'       => $messageRequest->requester_id,
                'body'            => $messageRequest->message,
                'type'            => 'text',
                'metadata'        => null,
            ]);

            $conversation->update(['last_message_at' => now()]);
            $message->load('sender');

            broadcast(new ConversationMessageSent($message))->toOthers();
        }

        // Accepting is consent — follow the requester so their subsequent
        // messages satisfy the "People I follow" gate.
        UserFollow::firstOrCreate([
            'follower_id'  => $me,
            'following_id' => $messageRequest->requester_id,
        ]);

        $messageRequest->delete();

        return response()->json([
            'message'         => 'Request accepted',
            'conversation_id' => $conversation->id,
        ]);
    }

    /**
     * Dismiss (delete) a request the authenticated user received.
     */
    public function dismiss(Request $request, MessageRequest $messageRequest)
    {
        if ($messageRequest->recipient_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $messageRequest->delete();

        return response()->json(['message' => 'Dismissed']);
    }
}
