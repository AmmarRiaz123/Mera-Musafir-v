<?php

namespace App\Http\Controllers\Api\V1;

use App\Events\ConversationMessageSent;
use App\Http\Controllers\Controller;
use App\Models\BlockedUser;
use App\Models\Booking;
use App\Models\Conversation;
use App\Models\ConversationMessage;
use App\Models\MessageRequest;
use App\Models\Trip;
use App\Models\TripInvite;
use App\Models\TripMember;
use App\Models\User;
use App\Models\UserFollow;
use App\Support\Messages;
use App\Support\ImageUrl;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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
                    'avatar'      => ImageUrl::resolve($other->avatar),
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

        // Agencies may reply to travellers and reach their own customers, but
        // may not cold-open a conversation — that's the classic B2C spam vector.
        $authUser = auth()->user();
        if ($authUser->type === 'agency' && !$this->agencyMayContact($authUser, $targetId)) {
            return Messages::json('agency_cannot_dm');
        }

        // When gated, signal the client to open the "send a message request"
        // flow — the request (with the composed message) is created via
        // MessageRequestController@store, so nothing is recorded if they cancel.
        if ($target->dm_privacy === 'nobody') {
            return response()->json([
                'message'   => "This user isn't accepting messages right now. You can send them a message request.",
                'requested' => true,
            ], 403);
        }

        if ($target->dm_privacy === 'followers') {
            // "People I follow": the recipient (target) must follow the sender.
            $targetFollowsSender = UserFollow::where('follower_id', $target->id)
                ->where('following_id', auth()->id())
                ->exists();

            if (!$targetFollowsSender) {
                return response()->json([
                    'message'   => "This user only accepts messages from people they follow. You can send them a message request.",
                    'requested' => true,
                ], 403);
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
                    'avatar'      => ImageUrl::resolve($target->avatar),
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
                    'avatar'      => ImageUrl::resolve($other->avatar),
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

        // Privacy changed after this conversation existed — capture the typed
        // message as a request so it can be delivered if the recipient accepts.
        if ($recipient && $recipient->dm_privacy === 'nobody') {
            $this->recordMessageRequest($userId, $recipientId, $request->input('body'));
            return response()->json([
                'message'   => "This user isn't accepting messages right now. We've saved your message as a request.",
                'requested' => true,
            ], 403);
        }

        if ($recipient && $recipient->dm_privacy === 'followers') {
            // "People I follow": the recipient must follow the sender.
            $recipientFollowsSender = UserFollow::where('follower_id', $recipientId)
                ->where('following_id', $userId)
                ->exists();

            if (!$recipientFollowsSender) {
                $this->recordMessageRequest($userId, $recipientId, $request->input('body'));
                return response()->json([
                    'message'   => "This user only accepts messages from people they follow. We've saved your message as a request.",
                    'requested' => true,
                ], 403);
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
                return Messages::json('not_a_member');
            }

            $alreadyInvited = TripInvite::where('trip_id', $trip->id)
                ->where('invitee_id', $recipientId)
                ->where('status', 'pending')
                ->exists();

            if ($alreadyInvited) {
                return Messages::json('invite_pending');
            }

            // Nothing to invite them to if they're already in the trip.
            $alreadyMember = TripMember::where('trip_id', $trip->id)
                ->where('user_id', $recipientId)
                ->whereIn('status', ['joined', 'pending'])
                ->exists();

            if ($alreadyMember) {
                return Messages::json('invite_already_member');
            }

            $metadata = [
                'trip_id'          => $trip->id,
                'trip_title'       => $trip->title,
                'destination_name' => $trip->destination?->name,
                'start_date'       => $trip->start_date,
                'end_date'         => $trip->end_date,
                'spots_left'       => $trip->max_travelers - $trip->current_count,
                'visibility'       => $trip->visibility,
                'status'           => 'pending',
            ];

            $body = "Trip Invite: {$trip->title}";
        }

        // Message + invite are written together: if the invite fails we must not
        // leave an orphaned invite card in the conversation.
        $message = DB::transaction(function () use ($conversation, $userId, $body, $type, $metadata, $validated, $recipientId) {
            $message = ConversationMessage::create([
                'conversation_id' => $conversation->id,
                'sender_id'       => $userId,
                'body'            => $body,
                'type'            => $type,
                'metadata'        => $metadata,
            ]);

            if ($type === 'trip_invite') {
                // (trip_id, invitee_id) is unique — a previously declined or
                // accepted invite must be revived, not inserted again.
                TripInvite::updateOrCreate(
                    ['trip_id' => $validated['trip_id'], 'invitee_id' => $recipientId],
                    [
                        'inviter_id'              => $userId,
                        'conversation_message_id' => $message->id,
                        'status'                  => 'pending',
                    ]
                );
            }

            return $message;
        });

        $conversation->update(['last_message_at' => now()]);
        $message->load('sender');

        broadcast(new ConversationMessageSent($message))->toOthers();

        // The chat channel updates an open conversation live; this is the badge
        // for someone who isn't looking at it. $recipient is already resolved
        // above for the privacy check.
        if ($recipient) {
            app(\App\Services\NotificationService::class)->push(
                recipient: $recipient,
                type: 'message',
                copy: [
                    'title' => auth()->user()->name . ' sent you a message',
                    'body'  => \Illuminate\Support\Str::limit($message->body ?: 'Sent an attachment', 60),
                    'link'  => '/messages/' . $conversation->id,
                ],
                actor: auth()->user(),
                subject: $conversation,
            );
        }

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
            return Messages::json('invite_own');
        }

        $validated = $request->validate([
            'action' => 'required|in:accept,decline',
        ]);

        $invite = TripInvite::where('conversation_message_id', $message->id)->first();

        if (!$invite) {
            return Messages::json('invite_not_found');
        }

        if ($invite->status !== 'pending') {
            return Messages::json('invite_answered');
        }

        if ($validated['action'] === 'accept') {
            $trip = Trip::findOrFail($invite->trip_id);

            $alreadyMember = TripMember::where('trip_id', $trip->id)
                ->where('user_id', $userId)
                ->whereIn('status', ['joined', 'pending'])
                ->exists();

            if ($alreadyMember) {
                return Messages::json('already_joined');
            }

            // Women-only trips are enforced here too — an invite is not a bypass.
            if ($trip->visibility === 'women_only' && auth()->user()->gender !== 'female') {
                return Messages::json('women_only');
            }

            if ($trip->current_count >= $trip->max_travelers) {
                return Messages::json('trip_full');
            }

            // (trip_id, user_id) is unique — reuse the row if they were here before.
            TripMember::updateOrCreate(
                ['trip_id' => $trip->id, 'user_id' => $userId],
                [
                    'status'    => 'joined',
                    'role'      => 'member',
                    'joined_at' => now(),
                ]
            );
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
        $metadata = $msg->metadata;

        // A shared post is hidden if the viewer has blocked its author — the
        // author may be a third party, so this can't be decided at send time.
        if ($msg->type === 'post_share' && !empty($metadata['author_id'])) {
            $authorId = (int) $metadata['author_id'];

            if ($authorId !== $authId && BlockedUser::blockExistsBetween($authId, $authorId)) {
                $metadata = ['unavailable' => true];
            }
        }

        return [
            'id'         => $msg->id,
            'body'       => $msg->body,
            'type'       => $msg->type,
            'metadata'   => $metadata,
            'read_at'    => $msg->read_at,
            'is_mine'    => $msg->sender_id === $authId,
            'created_at' => $msg->created_at,
            'sender'     => [
                'id'          => $msg->sender->id,
                'name'        => $msg->sender->name,
                'avatar'      => ImageUrl::resolve($msg->sender->avatar),
                'is_verified' => (bool) $msg->sender->is_verified,
            ],
        ];
    }

    /**
     * Record (or refresh) a pending message request so the recipient is notified
     * that someone whose DM they've gated would like to reach them.
     */
    /**
     * An agency owner may open a conversation with someone only if that person
     * already reached out, or is a customer of theirs.
     */
    private function agencyMayContact(User $agencyUser, int $targetId): bool
    {
        $conversationIds = Conversation::where(function ($q) use ($agencyUser, $targetId) {
            $q->where('user_one_id', $agencyUser->id)->where('user_two_id', $targetId);
        })->orWhere(function ($q) use ($agencyUser, $targetId) {
            $q->where('user_one_id', $targetId)->where('user_two_id', $agencyUser->id);
        })->pluck('id');

        // They messaged us first.
        if ($conversationIds->isNotEmpty()) {
            $theyWroteFirst = ConversationMessage::whereIn('conversation_id', $conversationIds)
                ->where('sender_id', $targetId)
                ->exists();

            if ($theyWroteFirst) {
                return true;
            }
        }

        // Or they're a customer of this agency.
        $agency = $agencyUser->agency;
        if (!$agency) {
            return true;
        }

        return Booking::whereIn('agency_package_id', $agency->packages()->pluck('id'))
            ->where('user_id', $targetId)
            ->exists();
    }

    private function recordMessageRequest(int $requesterId, int $recipientId, ?string $message = null): void
    {
        $attributes = ['status' => 'pending'];
        if (filled($message)) {
            $attributes['message'] = $message;
        }

        MessageRequest::updateOrCreate(
            ['requester_id' => $requesterId, 'recipient_id' => $recipientId],
            $attributes,
        );
    }
}
