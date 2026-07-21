<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\AgencyPackage;
use App\Models\CommunityPost;
use App\Models\BlockedUser;
use App\Models\Message;
use App\Models\Report;
use App\Models\User;
use App\Support\ImageUrl;
use Illuminate\Http\Request;

class SafetyController extends Controller
{
    // POST /report — auth:sanctum
    public function report(Request $request)
    {
        $validated = $request->validate([
            'reported_id'   => 'required|integer',
            'reported_type' => 'required|in:user,message,package,post',
            'reason'        => 'required|in:spam,harassment,inappropriate_content,fake_profile,scam,other',
            'description'   => 'nullable|string|max:500',
        ]);

        $typeMap = [
            'user'    => User::class,
            'message' => Message::class,
            'package' => AgencyPackage::class,
            'post'    => CommunityPost::class,
        ];

        $reportedType = $typeMap[$validated['reported_type']];

        if ($validated['reported_type'] === 'user' && $validated['reported_id'] === $request->user()->id) {
            return response()->json(['message' => 'You cannot report yourself.'], 422);
        }

        $exists = Report::where('reporter_id', $request->user()->id)
            ->where('reported_id', $validated['reported_id'])
            ->where('reported_type', $reportedType)
            ->exists();

        if ($exists) {
            return response()->json(['message' => 'You have already reported this content.'], 422);
        }

        Report::create([
            'reporter_id'   => $request->user()->id,
            'reported_id'   => $validated['reported_id'],
            'reported_type' => $reportedType,
            'reason'        => $validated['reason'],
            'description'   => $validated['description'] ?? null,
            'status'        => 'pending',
        ]);

        return response()->json(['message' => 'Report submitted. Our team will review it.'], 201);
    }

    // POST /users/{user}/block — auth:sanctum
    public function block(Request $request, User $user)
    {
        $authUser = $request->user();

        if ($authUser->id === $user->id) {
            return response()->json(['message' => 'You cannot block yourself.'], 422);
        }

        $existing = BlockedUser::where('blocker_id', $authUser->id)
            ->where('blocked_id', $user->id)
            ->first();

        if ($existing) {
            $existing->delete();
            return response()->json(['blocked' => false, 'message' => 'User unblocked']);
        }

        BlockedUser::create(['blocker_id' => $authUser->id, 'blocked_id' => $user->id]);

        return response()->json(['blocked' => true, 'message' => 'User blocked']);
    }

    // GET /users/blocked — auth:sanctum
    public function blockList(Request $request)
    {
        $blocked = $request->user()
            ->blockedUsers()
            ->with('blocked:id,name,avatar')
            ->get()
            ->map(fn($b) => [
                'id'     => $b->blocked->id,
                'name'   => $b->blocked->name,
                'avatar' => ImageUrl::resolve($b->blocked->avatar),
            ]);

        return response()->json(['data' => $blocked]);
    }
}
