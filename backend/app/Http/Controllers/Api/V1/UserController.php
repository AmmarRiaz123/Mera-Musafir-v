<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\BlockedUser;
use App\Models\User;
use App\Models\UserFollow;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    public function index(Request $request)
    {
        // Public route — resolve the optional Bearer token via the sanctum guard.
        $authUser = auth('sanctum')->user();

        $query = User::withCount(['followers', 'following']);

        if ($authUser) {
            $query->where('id', '!=', $authUser->id);

            // Hide users in a block relationship in either direction.
            $blockedIds = BlockedUser::relatedIds($authUser->id);
            if ($blockedIds->isNotEmpty()) {
                $query->whereNotIn('id', $blockedIds);
            }
        }

        if ($request->search) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('city', 'like', "%{$search}%");
            });
        }

        if ($request->city) {
            $query->where('city', 'like', '%' . $request->city . '%');
        }

        if ($request->gender) {
            $query->where('gender', $request->gender);
        }

        $candidates = $query->orderByDesc('is_verified')->orderByDesc('followers_count')->get();

        // Apply preference-based scoring if auth user has preferences
        if ($authUser && !empty($authUser->preferences)) {
            $authStyles  = $authUser->preferences['travel_style'] ?? [];
            $authRegions = $authUser->preferences['regions'] ?? [];
            $authCity    = $authUser->city;

            $candidates = $candidates->map(function ($user) use ($authStyles, $authRegions, $authCity) {
                $score = 0;
                if ($user->is_verified) $score += 10;

                $userStyles  = $user->preferences['travel_style'] ?? [];
                $userRegions = $user->preferences['regions'] ?? [];

                if (!empty(array_intersect($authStyles, $userStyles)) ||
                    !empty(array_intersect($authRegions, $userRegions))) {
                    $score += 50;
                }

                if ($authCity && $user->city && stripos($user->city, $authCity) !== false) {
                    $score += 20;
                }

                $user->_score = $score;
                return $user;
            })->sortByDesc('_score')->values();
        }

        // Manual pagination after scoring
        $perPage = (int) ($request->per_page ?? 20);
        $page    = (int) ($request->page ?? 1);
        $total   = $candidates->count();
        $items   = $candidates->slice(($page - 1) * $perPage, $perPage)->values();

        // Build social graph data in bulk for efficiency
        $followingIds = $authUser ? UserFollow::where('follower_id', $authUser->id)->pluck('following_id') : collect();
        $followerIds  = $authUser ? UserFollow::where('following_id', $authUser->id)->pluck('follower_id') : collect();

        $data = $items->map(function ($user) use ($authUser, $followingIds, $followerIds) {
            $isFollowing = $followingIds->contains($user->id);
            $isFriend    = $isFollowing && $followerIds->contains($user->id);

            return [
                'id'              => $user->id,
                'name'            => $user->name,
                'avatar'          => $user->avatar,
                'city'            => $user->city,
                'gender'          => $user->gender,
                'is_verified'     => (bool) $user->is_verified,
                'followers_count' => $user->followers_count,
                'following_count' => $user->following_count,
                'is_following'    => $isFollowing,
                'is_friend'       => $isFriend,
                'preferences'     => $user->preferences,
            ];
        });

        return response()->json([
            'data' => $data,
            'meta' => [
                'total'        => $total,
                'per_page'     => $perPage,
                'current_page' => $page,
                'last_page'    => (int) ceil($total / $perPage),
            ],
        ]);
    }

    public function show(User $user)
    {
        // Public route — resolve the optional Bearer token via the sanctum guard
        // so is_following / counts reflect the viewer when they're logged in.
        $authUser = auth('sanctum')->user();

        // Hide the profile if the viewer and this user have a block relationship
        // in either direction.
        if ($authUser && $authUser->id !== $user->id
            && BlockedUser::blockExistsBetween($authUser->id, $user->id)) {
            return response()->json(['message' => 'User not found'], 404);
        }

        $resource = new UserResource($user);

        // Always expose social-graph fields (fresh counts straight from the DB)
        // so the frontend never has to fall back to stale local state.
        $isSelf = $authUser && $authUser->id === $user->id;

        $extra = [
            'is_following'    => ($authUser && !$isSelf) ? $authUser->isFollowing($user->id) : false,
            'is_friend'       => ($authUser && !$isSelf) ? $authUser->isFriendsWith($user->id) : false,
            'followers_count' => $user->followers()->count(),
            'following_count' => $user->following()->count(),
        ];

        return response()->json([
            'message' => 'User profile retrieved successfully',
            'data'    => array_merge($resource->resolve(), $extra),
        ]);
    }

    public function update(Request $request, User $user)
    {
        if ($request->user()->id !== $user->id) {
            return response()->json(['message' => 'This action is unauthorized.'], 403);
        }

        $validated = $request->validate([
            'name'       => ['required', 'string', 'max:100'],
            'bio'        => ['nullable', 'string', 'max:500'],
            'city'       => ['nullable', 'string', 'max:100'],
            'phone'      => ['nullable', 'string', Rule::unique('users', 'phone')->ignore($user->id)],
            'gender'     => ['nullable', Rule::in(['male', 'female', 'other'])],
            'preferences' => ['nullable', 'array'],
            'dm_privacy' => ['nullable', Rule::in(['everyone', 'followers', 'nobody'])],
        ]);

        $user->update($validated);

        return response()->json([
            'message' => 'Profile updated successfully',
            'data'    => new UserResource($user),
        ]);
    }

    public function follow(Request $request, User $user)
    {
        $authId = $request->user()->id;

        if ($authId === $user->id) {
            return response()->json(['message' => 'Cannot follow yourself'], 422);
        }

        $existing = UserFollow::where('follower_id', $authId)
            ->where('following_id', $user->id)
            ->first();

        if ($existing) {
            $existing->delete();
            return response()->json(['following' => false, 'message' => 'Unfollowed']);
        }

        UserFollow::create(['follower_id' => $authId, 'following_id' => $user->id]);

        return response()->json(['following' => true, 'message' => 'Now following']);
    }
}
