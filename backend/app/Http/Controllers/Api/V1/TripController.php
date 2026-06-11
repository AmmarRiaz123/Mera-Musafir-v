<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Trip\CreateTripRequest;
use App\Http\Requests\Trip\UpdateTripRequest;
use App\Http\Resources\TripResource;
use App\Models\GroupChat;
use App\Models\Trip;
use App\Models\TripMember;
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
            ->where('visibility', '!=', 'invite_only');

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

        $trips = $query->latest()->paginate(12);

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

        $trip->load(['creator', 'destination', 'joinedMembers']);

        return response()->json([
            'message' => 'Trip created successfully',
            'data'    => new TripResource($trip),
        ], 201);
    }

    // Get a single trip
    public function show(Trip $trip)
    {
        $trip->load(['creator', 'destination', 'joinedMembers']);

        return response()->json([
            'message' => 'Trip retrieved successfully',
            'data'    => new TripResource($trip),
        ]);
    }

    // Update a trip
    public function update(UpdateTripRequest $request, Trip $trip)
    {
        $trip->update($request->validated());
        $trip->load(['creator', 'destination', 'joinedMembers']);

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

        // Check if trip is full
        if ($trip->isFull()) {
            return response()->json(['message' => 'This trip is full'], 422);
        }

        // Check if already a member
        if ($trip->hasMember($user->id)) {
            return response()->json(['message' => 'You are already in this trip'], 422);
        }

        // Enforce women-only restriction
        if ($trip->visibility === 'women_only' && $user->gender !== 'female') {
            return response()->json(['message' => 'This trip is for women only'], 403);
        }

        // Open trips: join instantly. Invite-only: pending approval
        $status = $trip->visibility === 'invite_only' ? 'pending' : 'joined';

        TripMember::create([
            'trip_id'   => $trip->id,
            'user_id'   => $user->id,
            'status'    => $status,
            'role'      => 'member',
            'joined_at' => $status === 'joined' ? now() : null,
        ]);

        // Increment count only if immediately joined
        if ($status === 'joined') {
            $trip->increment('current_count');
        }

        $message = $status === 'joined'
            ? 'You have joined the trip'
            : 'Join request sent — waiting for host approval';

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
            return response()->json(['message' => 'You are not in this trip'], 422);
        }

        if ($member->role === 'host') {
            return response()->json(['message' => 'Host cannot leave the trip'], 422);
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
                'id'        => $user->id,
                'name'      => $user->name,
                'avatar'    => $user->avatar,
                'city'      => $user->city,
                'status'    => $user->pivot->status,
                'role'      => $user->pivot->role,
                'joined_at' => $user->pivot->joined_at,
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
}