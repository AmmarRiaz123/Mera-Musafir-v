<?php

namespace App\Http\Controllers\Api\V1;

use App\Events\ConversationMessageSent;
use App\Http\Controllers\Controller;
use App\Models\BlockedUser;
use App\Models\Conversation;
use App\Models\ConversationMessage;
use App\Models\Trip;
use App\Models\TripInvite;
use App\Models\TripMember;
use App\Models\User;
use App\Models\UserFollow;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ConversationController extends Controller
{
    public function index(Request $request)
    {
        $userId = auth()->id();

        $conversations = Conversation::with(['userOne', 'userTwo'])
            ->where('user_one_id', $userId)
            ->orWhere('user_two_id', $userId)
            ->withCount(['messages as unread_count' => function ($q) use ($userId) {
                $q->where('sender_id', '!=', $userId)->whereNull('read_at');
            }])
            ->orderByDesc('last_message_at')
            ->get();

        $data = $conversations->map(function ($conv) use ($userId) {
            $other   = $conv->getOtherUser($userId);
            $lastMsg = $conv->messages()->latest()->first();

            return [
                'id'           => $conv->id,
                'other_user'   => [
                    'id'          => $other->id,
                    'name'        => $other->name,
                    'avatar'      => $other->avatar,
                    'is_verified' => (bool) $other->is_verified,
                ],
                'last_message' => $lastMsg ? [
                    'body'       => Str::limit($lastMsg->body, 60),
                    'type'       => $lastMsg->type,
                    'created_at' => $lastMsg->created_at,
                    'is_mine'    => $lastMsg->sender_id === $userId,
                ] : null,
                'unread_count'    => $conv->unread_count,
                'last_message_at' => $conv->last_message_at,
            ];
        });

        return response()->json(['data' => $data]);
    }

    public function start(Request $request)
    {
        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
        ]);

        $targetId = (int) $validated['user_id'];

        if ($targetId === auth()->id()) {
            return response()->json(['message' => 'Cannot message yourself'], 422);
        }

        $target = User::findOrFail($targetId);

        if ($target->dm_privacy === 'nobody') {
            return response()->json(['message' => 'This user is not accepting messages'], 403);
        }

        if ($target->dm_privacy === 'followers') {
            $isFollowing = UserFollow::where('follower_id', auth()->id())
                ->where('following_id', $target->id)
                ->exists();

            if (!$isFollowing) {
                return response()->json(['message' => 'This user only accepts messages from people they follow'], 403);
            }
        }

        $conversation = Conversation::findOrCreateBetween(auth()->id(), $target->id);

        return response()->json([
            'message' => 'Conversation ready',
            'data'    => [
                'id'         => $conversation->id,
                'other_user' => [
                    'id'          => $target->id,
                    'name'        => $target->name,
                    'avatar'      => $target->avatar,
                    'is_verified' => (bool) $target->is_verified,
                    'dm_privacy'  => $target->dm_privacy,
                ],
            ],
        ], 201);
    }

    public function show(Request $request, Conversation $conversation)
    {
        $userId = auth()->id();

        if ($conversation->user_one_id !== $userId && $conversation->user_two_id !== $userId) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        // Mark messages from other user as read
        $conversation->messages()
            ->where('sender_id', '!=', $userId)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        $messages = $conversation->messages()
            ->with('sender')
            ->oldest()
            ->limit(100)
            ->get()
            ->map(fn($msg) => $this->formatMessage($msg, $userId));

        $other = $conversation->getOtherUser($userId);

        $iBlockedThem = BlockedUser::where('blocker_id', $userId)
            ->where('blocked_id', $other->id)->exists();

        $theyBlockedMe = BlockedUser::where('blocker_id', $other->id)
            ->where('blocked_id', $userId)->exists();

        return response()->json([
            'data' => [
                'id'              => $conversation->id,
                'other_user'      => [
                    'id'          => $other->id,
                    'name'        => $other->name,
                    'avatar'      => $other->avatar,
                    'is_verified' => (bool) $other->is_verified,
                ],
                'blocked_by_me'   => $iBlockedThem,
                'blocked_by_them' => $theyBlockedMe,
                'messages'        => $messages,
            ],
        ]);
    }

    public function send(Request $request, Conversation $conversation)
    {
        $userId = auth()->id();

        if ($conversation->user_one_id !== $userId && $conversation->user_two_id !== $userId) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $recipientId = $conversation->user_one_id === $userId
            ? $conversation->user_two_id
            : $conversation->user_one_id;

        // Block check (either direction) — Instagram-style: neither party can message.
        $blockExists = BlockedUser::where(function ($q) use ($userId, $recipientId) {
            $q->where('blocker_id', $userId)->where('blocked_id', $recipientId);
        })->orWhere(function ($q) use ($userId, $recipientId) {
            $q->where('blocker_id', $recipientId)->where('blocked_id', $userId);
        })->exists();

        if ($blockExists) {
            return response()->json(['message' => 'Unable to send message'], 403);
        }

        // DM privacy check — enforced on every send, not just at conversation creation.
        $recipient = User::find($recipientId);

        if ($recipient && $recipient->dm_privacy === 'nobody') {
            return response()->json(['message' => 'This user is not accepting messages'], 403);
        }

        if ($recipient && $recipient->dm_privacy === 'followers') {
            $isFollowing = UserFollow::where('follower_id', $userId)
                ->where('following_id', $recipientId)
                ->exists();

            if (!$isFollowing) {
                return response()->json(['message' => 'This user only accepts messages from followers'], 403);
            }
        }

        $validated = $request->validate([
            'body'    => 'required_if:type,text|nullable|string|max:1000',
            'type'    => 'nullable|in:text,trip_invite',
            'trip_id' => 'required_if:type,trip_invite|nullable|exists:trips,id',
        ]);

        $type     = $validated['type'] ?? 'text';
        $metadata = null;
        $body     = $validated['body'] ?? '';

        if ($type === 'trip_invite') {
            $trip = Trip::with('destination')->findOrFail($validated['trip_id']);

            $isMember = TripMember::where('trip_id', $trip->id)
                ->where('user_id', $userId)
                ->where('status', 'joined')
                ->exists();

            if (!$isMember) {
                return response()->json(['message' => 'You are not a member of this trip'], 422);
            }

            $alreadyInvited = TripInvite::where('trip_id', $trip->id)
                ->where('invitee_id', $recipientId)
                ->where('status', 'pending')
                ->exists();

            if ($alreadyInvited) {
                return response()->json(['message' => 'This user already has a pending invite for this trip'], 422);
            }

            $metadata = [
                'trip_id'          => $trip->id,
                'trip_title'       => $trip->title,
                'destination_name' => $trip->destination?->name,
                'start_date'       => $trip->start_date,
                'end_date'         => $trip->end_date,
                'spots_left'       => $trip->max_travelers - $trip->current_count,
                'status'           => 'pending',
            ];

            $body = "Trip Invite: {$trip->title}";
        }

        $message = ConversationMessage::create([
            'conversation_id' => $conversation->id,
            'sender_id'       => $userId,
            'body'            => $body,
            'type'            => $type,
            'metadata'        => $metadata,
        ]);

        if ($type === 'trip_invite') {
            TripInvite::create([
                'trip_id'                 => $validated['trip_id'],
                'inviter_id'              => $userId,
                'invitee_id'              => $recipientId,
                'conversation_message_id' => $message->id,
                'status'                  => 'pending',
            ]);
        }

        $conversation->update(['last_message_at' => now()]);
        $message->load('sender');

        broadcast(new ConversationMessageSent($message))->toOthers();

        return response()->json([
            'message' => 'Message sent',
            'data'    => $this->formatMessage($message, $userId),
        ], 201);
    }

    public function respondToInvite(Request $request, Conversation $conversation, ConversationMessage $message)
    {
        $userId = auth()->id();

        if ($conversation->user_one_id !== $userId && $conversation->user_two_id !== $userId) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        if ($message->type !== 'trip_invite') {
            return response()->json(['message' => 'Not an invite message'], 422);
        }

        if ($message->sender_id === $userId) {
            return response()->json(['message' => 'You cannot respond to your own invite'], 422);
        }

        $validated = $request->validate([
            'action' => 'required|in:accept,decline',
        ]);

        $invite = TripInvite::where('conversation_message_id', $message->id)->first();

        if (!$invite) {
            return response()->json(['message' => 'Invite not found'], 404);
        }

        if ($invite->status !== 'pending') {
            return response()->json(['message' => 'Invite already responded to'], 422);
        }

        if ($validated['action'] === 'accept') {
            $trip = Trip::findOrFail($invite->trip_id);

            $alreadyMember = TripMember::where('trip_id', $trip->id)
                ->where('user_id', $userId)
                ->whereIn('status', ['joined', 'pending'])
                ->exists();

            if ($alreadyMember) {
                return response()->json(['message' => 'You are already in this trip'], 422);
            }

            if ($trip->current_count >= $trip->max_travelers) {
                return response()->json(['message' => 'This trip is now full'], 422);
            }

            TripMember::create([
                'trip_id'   => $trip->id,
                'user_id'   => $userId,
                'status'    => 'joined',
                'role'      => 'member',
                'joined_at' => now(),
            ]);
            $trip->increment('current_count');

            $invite->update(['status' => 'accepted']);
            $message->update(['metadata' => array_merge($message->metadata ?? [], ['status' => 'accepted'])]);

            $responseMessage = 'Invite accepted! You have joined the trip.';
        } else {
            $invite->update(['status' => 'declined']);
            $message->update(['metadata' => array_merge($message->metadata ?? [], ['status' => 'declined'])]);

            $responseMessage = 'Invite declined';
        }

        $message->load('sender');

        return response()->json([
            'message' => $responseMessage,
            'data'    => $this->formatMessage($message, $userId),
        ]);
    }

    private function formatMessage(ConversationMessage $msg, int $authId): array
    {
        return [
            'id'         => $msg->id,
            'body'       => $msg->body,
            'type'       => $msg->type,
            'metadata'   => $msg->metadata,
            'read_at'    => $msg->read_at,
            'is_mine'    => $msg->sender_id === $authId,
            'created_at' => $msg->created_at,
            'sender'     => [
                'id'          => $msg->sender->id,
                'name'        => $msg->sender->name,
                'avatar'      => $msg->sender->avatar,
                'is_verified' => (bool) $msg->sender->is_verified,
            ],
        ];
    }
}
