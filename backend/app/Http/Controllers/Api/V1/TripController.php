<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Trip\CreateTripRequest;
use App\Http\Requests\Trip\UpdateTripRequest;
use App\Http\Resources\TripResource;
use App\Models\BlockedUser;
use App\Models\GroupChat;
use App\Models\Trip;
use App\Models\TripMember;
use App\Services\MatchingService;
use App\Support\Messages;
use App\Support\ImageUrl;
use Illuminate\Http\Request;

class TripController extends Controller
{
    // Return the authenticated user's created and joined trips
    public function my(Request $request)
    {
        $userId = auth()->id();

        $created = Trip::with(['creator', 'destination'])
            ->where('creator_id', $userId)
            ->latest()
            ->get()
            ->map(fn($trip) => (new TripResource($trip))->resolve($request));

        $joined = Trip::with(['creator', 'destination'])
            ->whereHas('members', function ($q) use ($userId) {
                $q->where('user_id', $userId)
                  ->where('status', 'joined')
                  ->where('role', 'member');
            })
            ->latest()
            ->get()
            ->map(fn($trip) => (new TripResource($trip))->resolve($request));

        return response()->json([
            'message' => 'My trips retrieved successfully',
            'data'    => [
                'created' => $created,
                'joined'  => $joined,
            ],
        ]);
    }

    // List all public trips with optional filters
    public function index(Request $request)
    {
        $query = Trip::with(['creator', 'destination'])
            ->where('status', '!=', 'archived')
            ->where('visibility', '!=', 'invite_only')
            // A suspended host's trips disappear from browse.
            ->whereHas('creator', fn ($q) => $q->where('is_blocked', false));

        // Filter by destination
        if ($request->destination_id) {
            $query->where('destination_id', $request->destination_id);
        }

        // Filter by type
        if ($request->type) {
            $query->where('type', $request->type);
        }

        // Filter by status
        if ($request->status) {
            $query->where('status', $request->status);
        }

        // Filter women-only trips
        if ($request->visibility) {
            $query->where('visibility', $request->visibility);
        }

        // Filter by date range
        if ($request->start_date) {
            $query->whereDate('start_date', '>=', $request->start_date);
        }

        // Search by title
        if ($request->search) {
            $query->where('title', 'like', '%' . $request->search . '%');
        }

        // Public route — resolve the optional Bearer token via the sanctum guard,
        // then hide trips CREATED by anyone in a block relationship (either
        // direction). Trips owned by a third party stay visible even if a
        // blocked user is a co-member.
        $authUser = auth('sanctum')->user();
        if ($authUser) {
            $blockedIds = BlockedUser::relatedIds($authUser->id);
            if ($blockedIds->isNotEmpty()) {
                $query->whereNotIn('creator_id', $blockedIds);
            }
        }

        $perPage = min(max((int) $request->input('per_page', 12), 1), 24);

        $trips = $query->latest()->paginate($perPage);

        return TripResource::collection($trips)->additional([
            'message' => 'Trips retrieved successfully'
        ]);
    }

    // Create a new trip
    public function store(CreateTripRequest $request)
    {
        $trip = Trip::create([
            ...$request->validated(),
            'creator_id'    => auth()->id(),
            'current_count' => 1,
        ]);

        // Auto-add creator as host member
        TripMember::create([
            'trip_id'   => $trip->id,
            'user_id'   => auth()->id(),
            'status'    => 'joined',
            'role'      => 'host',
            'joined_at' => now(),
        ]);

        // Auto-create group chat for this trip
        GroupChat::create([
            'trip_id' => $trip->id,
            'name'    => $trip->title . ' Chat',
        ]);

        $trip->load(['creator', 'destination', 'joinedMembers', 'pendingMembers']);

        return response()->json([
            'message' => 'Trip created successfully',
            'data'    => new TripResource($trip),
        ], 201);
    }

    // Get a single trip
    public function show(Trip $trip)
    {
        // Hide the trip if the viewer and its creator have a block relationship
        // (either direction). Co-membership in a third party's trip is unaffected.
        $authUser = auth('sanctum')->user();
        if ($authUser && BlockedUser::blockExistsBetween($authUser->id, $trip->creator_id)) {
            return response()->json(['message' => 'Trip not found'], 404);
        }

        $trip->load(['creator', 'destination', 'joinedMembers', 'pendingMembers']);

        return response()->json([
            'message' => 'Trip retrieved successfully',
            'data'    => new TripResource($trip),
        ]);
    }

    // Update a trip
    public function update(UpdateTripRequest $request, Trip $trip, MatchingService $matching)
    {
        $trip->update($request->validated());

        // Visibility/type/date changes alter who this trip should be shown to.
        $matching->clearTripCache($trip->id);

        $trip->load(['creator', 'destination', 'joinedMembers', 'pendingMembers']);

        return response()->json([
            'message' => 'Trip updated successfully',
            'data'    => new TripResource($trip),
        ]);
    }

    // Delete a trip
    public function destroy(Trip $trip)
    {
        if ($trip->creator_id !== auth()->id()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $trip->delete();

        return response()->json(['message' => 'Trip deleted successfully']);
    }

    // Join a trip
    public function join(Trip $trip)
    {
        $user = auth()->user();

        // Business accounts don't join travellers' trips — it turns personal
        // trips into a sales channel. They still host their own package trips,
        // which are created for them when a booking is confirmed.
        if ($user->type === 'agency' && $trip->creator_id !== $user->id) {
            return Messages::json('agency_cannot_join');
        }

        // Cannot join a trip created by someone in a block relationship.
        if (BlockedUser::blockExistsBetween($user->id, $trip->creator_id)) {
            return Messages::json('join_blocked');
        }

        // Check if trip is full
        if ($trip->isFull()) {
            return Messages::json('trip_full');
        }

        // Already joined, or already waiting? These feel identical to hasMember
        // but read very differently to the user — "you're already in" vs "your
        // request is pending" — so split them.
        $existingMembership = TripMember::where('trip_id', $trip->id)
            ->where('user_id', $user->id)
            ->whereIn('status', ['joined', 'pending'])
            ->first();

        if ($existingMembership) {
            return Messages::json(
                $existingMembership->status === 'pending' ? 'join_pending' : 'already_joined'
            );
        }

        // Enforce women-only restriction
        if ($trip->visibility === 'women_only' && $user->gender !== 'female') {
            return Messages::json('women_only');
        }

        // A member the host removed can ask back in, but the host has to approve
        // — otherwise a kick means nothing and they just re-add themselves.
        $priorRemoval = TripMember::where('trip_id', $trip->id)
            ->where('user_id', $user->id)
            ->where('status', 'removed')
            ->exists();

        // Open trips join instantly. Invite-only, host-requires-approval, and a
        // prior removal all mean the host reviews the request first.
        $needsApproval = $trip->visibility === 'invite_only'
            || $trip->requires_approval
            || $priorRemoval;
        $status = $needsApproval ? 'pending' : 'joined';

        // Reuse any previous membership row — (trip_id, user_id) is unique, so a
        // user who left and comes back must update their old row, not insert.
        TripMember::updateOrCreate(
            ['trip_id' => $trip->id, 'user_id' => $user->id],
            [
                'status'    => $status,
                'role'      => 'member',
                'joined_at' => $status === 'joined' ? now() : null,
            ]
        );

        // Increment count only if immediately joined
        if ($status === 'joined') {
            $trip->increment('current_count');
        }

        // Tell the host — whether it's a fresh join or a request to approve.
        $host = $trip->creator;
        if ($host) {
            app(\App\Services\NotificationService::class)->push(
                recipient: $host,
                type: 'trip_join',
                copy: [
                    'title' => $status === 'joined'
                        ? $user->name . ' joined ' . $trip->title
                        : $user->name . ' asked to join ' . $trip->title,
                    'link'  => '/trips/' . $trip->id,
                ],
                actor: $user,
                subject: $trip,
            );
        }

        $message = match (true) {
            $status === 'joined' => 'You have joined the trip',
            $priorRemoval        => 'Request sent — the host removed you before, so they need to approve you back in.',
            default              => 'Join request sent — waiting for host approval',
        };

        return response()->json(['message' => $message]);
    }

    // Leave a trip
    public function leave(Trip $trip)
    {
        $userId = auth()->id();

        $member = TripMember::where('trip_id', $trip->id)
            ->where('user_id', $userId)
            ->first();

        if (!$member) {
            return Messages::json('not_in_trip');
        }

        if ($member->role === 'host') {
            return Messages::json('host_cannot_leave');
        }

        if ($member->status === 'joined') {
            $trip->decrement('current_count');
        }

        $member->update(['status' => 'left']);

        return response()->json(['message' => 'You have left the trip']);
    }

    // Get all members of a trip
    public function members(Trip $trip)
    {
        $trip->load('members');

        return response()->json([
            'message' => 'Members retrieved successfully',
            'data'    => $trip->members->map(fn($user) => [
                'id'          => $user->id,
                'name'        => $user->name,
                'avatar'      => ImageUrl::resolve($user->avatar),
                'city'        => $user->city,
                'is_verified' => (bool) $user->is_verified,
                'status'      => $user->pivot->status,
                'role'        => $user->pivot->role,
                'joined_at'   => $user->pivot->joined_at,
            ]),
        ]);
    }

    // Approve a pending member (host only)
    public function approve(Trip $trip, $userId)
    {
        if ($trip->creator_id !== auth()->id()) {
            return response()->json(['message' => 'Only the host can approve members'], 403);
        }

        if ($trip->isFull()) {
            return response()->json(['message' => 'Trip is full'], 422);
        }

        $member = TripMember::where('trip_id', $trip->id)
            ->where('user_id', $userId)
            ->where('status', 'pending')
            ->firstOrFail();

        $member->update([
            'status'    => 'joined',
            'joined_at' => now(),
        ]);

        $trip->increment('current_count');

        return response()->json(['message' => 'Member approved']);
    }

    /**
     * POST /trips/{trip}/members/{userId}/remove — the host removes someone.
     *
     * Mirrors leave() so the headcount and membership row stay consistent: the
     * seat is given back and the row is marked 'left' (not deleted), so a
     * kicked traveller can still ask to rejoin later. The host can't remove
     * themselves — that's what leave/delete are for.
     */
    public function removeMember(Trip $trip, $userId)
    {
        if ($trip->creator_id !== auth()->id()) {
            return response()->json(['message' => 'Only the host can remove members'], 403);
        }

        if ((int) $userId === $trip->creator_id) {
            return response()->json(['message' => "You can't remove yourself — you're the host."], 422);
        }

        $member = TripMember::where('trip_id', $trip->id)
            ->where('user_id', $userId)
            ->whereIn('status', ['joined', 'pending'])
            ->first();

        if (!$member) {
            return Messages::json('not_a_member');
        }

        // Declining a pending request reads differently from kicking a member
        // who was already in — tailor the message to which it was.
        $wasPending = $member->status === 'pending';

        if ($member->status === 'joined') {
            $trip->decrement('current_count');
        }

        // 'removed', not 'left' — a kick (or a decline) is sticky: coming back
        // needs the host's approval.
        $member->update(['status' => 'removed']);

        $removed = \App\Models\User::find($userId);
        if ($removed) {
            app(\App\Services\NotificationService::class)->push(
                recipient: $removed,
                type: 'trip_join',
                copy: [
                    'title' => $wasPending
                        ? 'Your request to join ' . $trip->title . " wasn't approved"
                        : 'You were removed from ' . $trip->title,
                    'body'  => $wasPending
                        ? "The host declined your request. You can ask again."
                        : 'The host removed you from this trip.',
                    'link'  => '/trips',
                ],
                actor: auth()->user(),
                subject: $trip,
            );
        }

        return response()->json(['message' => 'Member removed from the trip']);
    }
}