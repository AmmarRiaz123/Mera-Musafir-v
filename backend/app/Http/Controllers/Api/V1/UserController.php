<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    /**
     * Display the specified user profile.
     */
    public function show(User $user)
    {
        return response()->json([
            'message' => 'User profile retrieved successfully',
            'data' => new UserResource($user),
        ]);
    }

    /**
     * Update the specified user profile.
     */
    public function update(Request $request, User $user)
    {
        // Authorization: User can only update their own profile
        if ($request->user()->id !== $user->id) {
            return response()->json([
                'message' => 'This action is unauthorized.',
            ], 403);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'bio' => ['nullable', 'string', 'max:500'],
            'city' => ['nullable', 'string', 'max:100'],
            'phone' => ['nullable', 'string', Rule::unique('users', 'phone')->ignore($user->id)],
            'gender' => ['nullable', Rule::in(['male', 'female', 'other'])],
            'preferences' => ['nullable', 'array'],
        ]);

        $user->update($validated);

        return response()->json([
            'message' => 'Profile updated successfully',
            'data' => new UserResource($user),
        ]);
    }
}
