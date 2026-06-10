<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\DestinationController;
use App\Http\Controllers\Api\V1\TripController;
use App\Http\Controllers\Api\V1\UserController;

// Public routes
Route::prefix('v1')->group(function () {

    // Auth
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login',    [AuthController::class, 'login']);

    // Destinations (public)
    Route::get('/destinations',          [DestinationController::class, 'index']);
    Route::get('/destinations/{destination}', [DestinationController::class, 'show']);

    // User profiles (public)
    Route::get('/users/{user}', [UserController::class, 'show']);

    // Trips (public browsing)
    Route::get('/trips',       [TripController::class, 'index']);
    Route::get('/trips/my',    [TripController::class, 'my'])->middleware('auth:sanctum'); // must be before {trip}
    Route::get('/trips/{trip}', [TripController::class, 'show']);
    Route::get('/trips/{trip}/members', [TripController::class, 'members']);

    // Protected routes
    Route::middleware('auth:sanctum')->group(function () {

        // Auth
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/user',    [AuthController::class, 'user']);

        // Profile
        Route::put('/users/{user}', [UserController::class, 'update']);

        // Trips
        Route::post('/trips',                              [TripController::class, 'store']);
        Route::put('/trips/{trip}',                        [TripController::class, 'update']);
        Route::delete('/trips/{trip}',                     [TripController::class, 'destroy']);
        Route::post('/trips/{trip}/join',                  [TripController::class, 'join']);
        Route::post('/trips/{trip}/leave',                 [TripController::class, 'leave']);
        Route::post('/trips/{trip}/members/{userId}/approve', [TripController::class, 'approve']);
    });
});