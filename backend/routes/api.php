<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V1\AgencyController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\BookingController;
use App\Http\Controllers\Api\V1\ChatController;
use App\Http\Controllers\Api\V1\CommunityPostController;
use App\Http\Controllers\Api\V1\ConversationController;
use App\Http\Controllers\Api\V1\DestinationController;
use App\Http\Controllers\Api\V1\MatchingController;
use App\Http\Controllers\Api\V1\MediaSearchController;
use App\Http\Controllers\Api\V1\MessageRequestController;
use App\Http\Controllers\Api\V1\PackageController;
use App\Http\Controllers\Api\V1\PlanningController;
use App\Http\Controllers\Api\V1\PostCommentController;
use App\Http\Controllers\Api\V1\SafetyController;
use App\Http\Controllers\Api\V1\TripController;
use App\Http\Controllers\Api\V1\UploadController;
use App\Http\Controllers\Api\V1\UserController;

// Public routes
Route::prefix('v1')->group(function () {

    // Auth
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login',    [AuthController::class, 'login']);

    // Destinations (public)
    Route::get('/destinations',          [DestinationController::class, 'index']);
    Route::get('/destinations/{destination}', [DestinationController::class, 'show']);

    // User discovery — exact routes MUST be before /users/{user} wildcard
    Route::get('/users',         [UserController::class, 'index']);
    Route::get('/users/blocked', [SafetyController::class, 'blockList'])->middleware('auth:sanctum');
    Route::get('/users/{user}',  [UserController::class, 'show']);

    // Agencies (public) — /agencies/my MUST be before /agencies/{agency}
    Route::get('/agencies/my',          [AgencyController::class, 'myAgency'])->middleware('auth:sanctum');
    Route::get('/agencies',             [AgencyController::class, 'index']);
    Route::get('/agencies/{agency}',    [AgencyController::class, 'show']);

    // Packages (public) — /packages/my MUST be before /packages/{package}
    Route::get('/packages/my',          [PackageController::class, 'myPackages'])->middleware('auth:sanctum');
    Route::get('/packages',             [PackageController::class, 'index']);
    Route::get('/packages/{package}',   [PackageController::class, 'show']);

    // Community feed (public browsing)
    Route::get('/community/posts',                  [CommunityPostController::class, 'index']);
    Route::get('/community/posts/{post}',           [CommunityPostController::class, 'show']);
    Route::get('/community/posts/{post}/comments',  [PostCommentController::class, 'index']);

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

        // Agencies — /agencies/register MUST be before /agencies/{agency}
        Route::post('/agencies/register',                   [AgencyController::class, 'register']);
        Route::put('/agencies/{agency}',                    [AgencyController::class, 'update']);
        Route::post('/agencies/{agency}/follow',            [AgencyController::class, 'follow']);
        Route::get('/agencies/{agency}/analytics',          [AgencyController::class, 'analytics'])->middleware('agency.tier:pro');
        Route::get('/agencies/{agency}/bookings',           [AgencyController::class, 'allBookings']);

        // Bookings — traveler's own bookings (before any wildcard booking routes)
        Route::get('/bookings/my', [BookingController::class, 'my']);

        // Packages
        Route::post('/packages',                                                    [PackageController::class, 'store']);
        Route::put('/packages/{package}',                                           [PackageController::class, 'update']);
        Route::delete('/packages/{package}',                                        [PackageController::class, 'destroy']);
        Route::post('/packages/{package}/book',                                     [PackageController::class, 'book']);
        Route::get('/packages/{package}/bookings',                                  [PackageController::class, 'agencyBookings']);
        Route::post('/packages/{package}/bookings/{booking}/confirm',               [PackageController::class, 'confirmBooking']);
        Route::post('/packages/{package}/bookings/{booking}/cancel',                [PackageController::class, 'cancelBooking']);

        // Profile
        Route::put('/users/{user}',         [UserController::class, 'update']);

        // GIF + music catalogues for the post composer
        Route::get('/media/gifs',           [MediaSearchController::class, 'gifs']);
        Route::get('/media/music',          [MediaSearchController::class, 'music']);

        // Image uploads (avatars, covers, logos)
        Route::post('/uploads',             [UploadController::class, 'store']);

        // Safety
        Route::post('/report',              [SafetyController::class, 'report']);
        Route::post('/users/{user}/block',  [SafetyController::class, 'block']);

        // Social graph
        Route::post('/users/{user}/follow', [UserController::class, 'follow']);

        // Conversations (DMs)
        Route::get('/conversations',                                                           [ConversationController::class, 'index']);
        Route::post('/conversations',                                                          [ConversationController::class, 'start']);
        Route::get('/conversations/{conversation}',                                            [ConversationController::class, 'show']);
        Route::post('/conversations/{conversation}/messages',                                  [ConversationController::class, 'send']);
        Route::post('/conversations/{conversation}/messages/{message}/respond',                [ConversationController::class, 'respondToInvite']);

        // Message requests (DM-privacy nudges)
        Route::get('/message-requests',                     [MessageRequestController::class, 'index']);
        Route::post('/message-requests',                    [MessageRequestController::class, 'store']);
        Route::post('/message-requests/{messageRequest}/accept', [MessageRequestController::class, 'accept']);
        Route::delete('/message-requests/{messageRequest}', [MessageRequestController::class, 'dismiss']);

        // Community feed
        Route::post('/community/posts',                            [CommunityPostController::class, 'store']);
        Route::put('/community/posts/{post}',                      [CommunityPostController::class, 'update']);
        Route::delete('/community/posts/{post}',                   [CommunityPostController::class, 'destroy']);
        Route::post('/community/posts/{post}/like',                [CommunityPostController::class, 'toggleLike']);
        Route::post('/community/posts/{post}/share',               [CommunityPostController::class, 'share']);
        Route::post('/community/posts/{post}/comments',            [PostCommentController::class, 'store']);
        Route::delete('/community/posts/{post}/comments/{comment}', [PostCommentController::class, 'destroy']);

        // Trips
        Route::post('/trips',                              [TripController::class, 'store']);
        Route::put('/trips/{trip}',                        [TripController::class, 'update']);
        Route::delete('/trips/{trip}',                     [TripController::class, 'destroy']);
        Route::post('/trips/{trip}/join',                  [TripController::class, 'join']);
        Route::post('/trips/{trip}/leave',                 [TripController::class, 'leave']);
        Route::post('/trips/{trip}/members/{userId}/approve', [TripController::class, 'approve']);

        // Chat
        Route::get('/trips/{trip}/chat/messages',  [ChatController::class, 'messages']);
        Route::post('/trips/{trip}/chat/messages', [ChatController::class, 'send']);

        // Itinerary
        Route::get('/trips/{trip}/itinerary',                          [PlanningController::class, 'getItinerary']);
        Route::post('/trips/{trip}/itinerary/days',                    [PlanningController::class, 'addDay']);
        Route::post('/trips/{trip}/itinerary/days/{day}/items',        [PlanningController::class, 'addItineraryItem']);
        Route::put('/trips/{trip}/itinerary/items/{item}',             [PlanningController::class, 'updateItineraryItem']);
        Route::delete('/trips/{trip}/itinerary/items/{item}',          [PlanningController::class, 'deleteItineraryItem']);
        Route::delete('/trips/{trip}/itinerary/days/{day}',            [PlanningController::class, 'deleteDay']);

        // Expenses
        Route::get('/trips/{trip}/expenses',                           [PlanningController::class, 'getExpenses']);
        Route::post('/trips/{trip}/expenses',                          [PlanningController::class, 'addExpense']);
        Route::post('/trips/{trip}/expenses/shares/{share}/settle',    [PlanningController::class, 'settleShare']);

        // Matching / recommendations
        Route::get('/match/trips',                          [MatchingController::class, 'suggestTrips']);
        Route::get('/match/trips/{trip}/travelers',         [MatchingController::class, 'suggestTravelers']);

        // Checklist
        Route::get('/trips/{trip}/checklist',                          [PlanningController::class, 'getChecklist']);
        Route::post('/trips/{trip}/checklist',                         [PlanningController::class, 'addChecklistItem']);
        Route::put('/trips/{trip}/checklist/{item}',                   [PlanningController::class, 'updateChecklistItem']);
        Route::post('/trips/{trip}/checklist/{item}/toggle',           [PlanningController::class, 'toggleChecklistItem']);
        Route::delete('/trips/{trip}/checklist/{item}',                [PlanningController::class, 'deleteChecklistItem']);
    });
});