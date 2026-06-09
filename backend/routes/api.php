<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V1\AuthController;

Route::prefix('v1')->group(function () {
    Route::get('/ping', function () {
        return response()->json([
            'message' => 'Mera Musafir API is alive',
            'status'  => 'ok'
        ]);
    });

    // Auth Routes
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        
        // User check route
        Route::get('/user', function (\Illuminate\Http\Request $request) {
            return response()->json([
                'message' => 'User retrieved successfully',
                'data' => [
                    'user' => new \App\Http\Resources\UserResource($request->user()->load('roles')),
                ]
            ]);
        });
    });
});
