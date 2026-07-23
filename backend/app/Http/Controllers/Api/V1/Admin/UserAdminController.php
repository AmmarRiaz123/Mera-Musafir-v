<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\ImageUrl;
use Illuminate\Http\Request;

class UserAdminController extends Controller
{
    /** GET /admin/users?search=&type=&status= */
    public function index(Request $request)
    {
        $query = User::query()->withCount(['followers', 'following']);

        if ($search = trim((string) $request->input('search'))) {
            $query->where(fn ($q) => $q
                ->where('name', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%"));
        }

        if ($type = $request->input('type')) {
            $query->where('type', $type);
        }

        match ($request->input('status')) {
            'suspended' => $query->where('is_blocked', true),
            'active'    => $query->where('is_blocked', false),
            default     => null,
        };

        $users = $query->latest()->paginate(20);

        return response()->json([
            'data' => $users->through(fn (User $u) => [
                'id'           => $u->id,
                'name'         => $u->name,
                'email'        => $u->email,
                'type'         => $u->type,
                'avatar'       => ImageUrl::resolve($u->avatar),
                'city'         => $u->city,
                'is_verified'  => (bool) $u->is_verified,
                'is_suspended' => (bool) $u->is_blocked,
                'followers'    => $u->followers_count,
                'joined'       => $u->created_at->toDateString(),
            ])->items(),
            'meta' => [
                'total'        => $users->total(),
                'current_page' => $users->currentPage(),
                'last_page'    => $users->lastPage(),
            ],
        ]);
    }

    /**
     * POST /admin/users/{user}/suspend — flip suspension.
     *
     * Reuses is_blocked, which already hides someone's trips and gates their
     * messaging platform-wide, so a suspended account can't keep operating.
     */
    public function toggleSuspend(Request $request, User $user)
    {
        if ($user->hasRole('admin')) {
            return response()->json(['message' => "You can't suspend an admin."], 422);
        }

        $user->update(['is_blocked' => !$user->is_blocked]);

        return response()->json([
            'message'      => $user->is_blocked ? "{$user->name} suspended." : "{$user->name} reinstated.",
            'is_suspended' => (bool) $user->is_blocked,
        ]);
    }
}
