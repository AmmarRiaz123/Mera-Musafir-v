<?php

use App\Models\TripMember;
use Illuminate\Support\Facades\Broadcast;

// Protect the /broadcasting/auth endpoint with Sanctum so Bearer tokens are accepted
Broadcast::routes(['middleware' => ['auth:sanctum']]);

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

Broadcast::channel('trip.{tripId}', function ($user, $tripId) {
    return TripMember::where('trip_id', $tripId)
        ->where('user_id', $user->id)
        ->where('status', 'joined')
        ->exists();
});
