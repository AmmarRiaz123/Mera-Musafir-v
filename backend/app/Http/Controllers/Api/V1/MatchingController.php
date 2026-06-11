<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\TripResource;
use App\Http\Resources\UserResource;
use App\Models\Trip;
use App\Services\MatchingService;
use Illuminate\Http\Request;

class MatchingController extends Controller
{
    public function __construct(private MatchingService $matching) {}

    // GET /match/trips — auth:sanctum
    public function suggestTrips(Request $request)
    {
        $results = $this->matching->suggestTrips($request->user());

        $data = array_map(function ($item) use ($request) {
            $score = $item['score'];
            $label = match (true) {
                $score >= 80 => 'Great match',
                $score >= 60 => 'Good match',
                $score >= 40 => 'Worth checking',
                default      => 'Suggested',
            };

            return [
                'trip'        => (new TripResource($item['trip']))->resolve($request),
                'score'       => $score,
                'score_label' => $label,
            ];
        }, $results);

        return response()->json([
            'message' => 'Suggested trips retrieved',
            'data'    => $data,
        ]);
    }

    // GET /match/trips/{trip}/travelers — auth:sanctum, trip creator only
    public function suggestTravelers(Request $request, Trip $trip)
    {
        if ($trip->creator_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $results = $this->matching->suggestTravelers($trip);

        $data = array_map(fn($item) => [
            'user'  => (new UserResource($item['user']))->resolve($request),
            'score' => $item['score'],
        ], $results);

        return response()->json([
            'message' => 'Suggested travelers retrieved',
            'data'    => $data,
        ]);
    }
}
