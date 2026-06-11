<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthService
{
    /**
     * Register a new user and assign appropriate role.
     */
    public function register(array $data): array // returns ['user' => User, 'token' => string]
    {
        $user = User::create([
            'name'        => $data['name'],
            'email'       => $data['email'],
            'phone'       => $data['phone'] ?? null,
            'password'    => Hash::make($data['password']),
            'type'        => $data['type'],
            'city'        => $data['city'] ?? null,
            'gender'      => $data['gender'] ?? null,
            'preferences' => $data['preferences'] ?? null,
        ]);

        if (in_array($data['type'], ['traveler', 'agency'])) {
            $user->assignRole($data['type']);
        }

        $token = $user->createToken('auth_token')->plainTextToken;

        return ['user' => $user, 'token' => $token];
    }

    /**
     * Authenticate a user and return the token along with the user.
     *
     * @throws ValidationException
     */
    public function login(array $data): array
    {
        $user = User::where('email', $data['email'])->first();

        if (! $user || ! Hash::check($data['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials do not match our records.'],
            ]);
        }
        
        if ($user->is_blocked) {
            throw ValidationException::withMessages([
                'email' => ['This account has been blocked.'],
            ]);
        }

        $token = $user->createToken('auth_token')->plainTextToken;

        return [
            'user' => $user,
            'token' => $token,
        ];
    }
}
