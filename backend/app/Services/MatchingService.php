<?php

namespace App\Services;

use App\Models\Trip;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class MatchingService
{
    // ─── Public API ─────────────────────────────────────────────────────────

    public function suggestTrips(User $user, int $limit = 10): array
    {
        $cacheKey = "match:trips:user:{$user->id}";
        $scored   = Cache::remember($cacheKey, 3600, fn() => $this->scoreTrips($user, $limit));

        // Load fresh models from cached IDs (avoids stale serialised objects)
        $tripIds = array_column($scored, 'trip_id');
        $trips   = Trip::with(['creator', 'destination'])
            ->whereIn('id', $tripIds)
            ->get()
            ->keyBy('id');

        return array_values(array_filter(
            array_map(fn($item) => [
                'trip'  => $trips[$item['trip_id']] ?? null,
                'score' => $item['score'],
            ], $scored),
            // Eligibility is re-checked against the *live* trip, never the
            // cached score list — a trip may have become women-only since.
            fn($item) => $item['trip'] !== null
                && ($user->gender === 'female' || $item['trip']->visibility !== 'women_only')
        ));
    }

    public function suggestTravelers(Trip $trip, int $limit = 8): array
    {
        $cacheKey = "match:travelers:trip:{$trip->id}";
        $scored   = Cache::remember($cacheKey, 3600, fn() => $this->scoreTravelers($trip, $limit));

        $userIds = array_column($scored, 'user_id');
        $users   = User::whereIn('id', $userIds)->get()->keyBy('id');

        return array_values(array_filter(
            array_map(fn($item) => [
                'user'  => $users[$item['user_id']] ?? null,
                'score' => $item['score'],
            ], $scored),
            // Final gate applied to the live trip + user, so a cached list
            // built before the trip became women-only can't leak men through.
            fn($item) => $item['user'] !== null
                && ($trip->visibility !== 'women_only' || $item['user']->gender === 'female')
        ));
    }

    public function clearUserCache(int $userId): void
    {
        Cache::forget("match:trips:user:{$userId}");
    }

    public function clearTripCache(int $tripId): void
    {
        Cache::forget("match:travelers:trip:{$tripId}");
    }

    // ─── Scoring — Trips ────────────────────────────────────────────────────

    private function scoreTrips(User $user, int $limit): array
    {
        $userId      = $user->id;
        $userStyles  = $user->preferences['travel_style'] ?? [];
        $userRegions = $user->preferences['regions'] ?? [];
        $now         = now();

        // Destination IDs this user has visited before (status=joined)
        $visitedIds = DB::table('trip_members')
            ->join('trips', 'trips.id', '=', 'trip_members.trip_id')
            ->where('trip_members.user_id', $userId)
            ->where('trip_members.status', 'joined')
            ->pluck('trips.destination_id')
            ->unique()
            ->toArray();

        // Trip IDs the user is already part of (any status)
        $memberTripIds = DB::table('trip_members')
            ->where('user_id', $userId)
            ->pluck('trip_id')
            ->toArray();

        // Eligible trips
        $query = Trip::with(['destination'])
            ->whereIn('status', ['planning', 'active'])
            ->where('creator_id', '!=', $userId)
            ->whereNotIn('id', $memberTripIds)
            ->whereColumn('current_count', '<', 'max_travelers');

        if ($user->gender === 'female') {
            $query->whereIn('visibility', ['public', 'women_only']);
        } else {
            $query->where('visibility', 'public');
        }

        $trips = $query->get();

        $scored = $trips->map(function (Trip $trip) use ($userStyles, $userRegions, $visitedIds, $now) {
            $score  = 0;
            $region = $trip->destination?->region;

            // 1. Travel style (35 pts)
            if (empty($userStyles)) {
                $score += 15; // neutral — new user still gets suggestions
            } elseif (in_array($trip->type, $userStyles)) {
                $score += 35;
            }

            // 2. Destination region (25 pts)
            if (empty($userRegions)) {
                $score += 10;
            } elseif ($region && in_array($region, $userRegions)) {
                $score += 25;
            }

            // 3. Date proximity (25 pts)
            $days = (int) $now->diffInDays($trip->start_date, false);
            $score += match (true) {
                $days < 0    => 5,
                $days <= 7   => 25,
                $days <= 30  => 20,
                $days <= 60  => 15,
                $days <= 90  => 10,
                default      => 5,
            };

            // 4. Past destination history (15 pts)
            if (in_array($trip->destination_id, $visitedIds)) {
                $score += 15;
            }

            return ['trip_id' => $trip->id, 'score' => $score];
        });

        return $scored
            ->sortByDesc('score')
            ->take($limit)
            ->values()
            ->toArray();
    }

    // ─── Scoring — Travelers ────────────────────────────────────────────────

    private function scoreTravelers(Trip $trip, int $limit): array
    {
        $trip->loadMissing('destination');
        $tripRegion = $trip->destination?->region;
        $tripType   = $trip->type;

        $existingIds = DB::table('trip_members')
            ->where('trip_id', $trip->id)
            ->pluck('user_id')
            ->toArray();

        $query = User::where('type', 'traveler')
            ->where('is_blocked', false)
            ->whereNotIn('id', $existingIds);

        if ($trip->visibility === 'women_only') {
            $query->where('gender', 'female');
        }

        // Pre-fetch completed trip counts for all candidates in one query
        $candidates   = $query->get();
        $candidateIds = $candidates->pluck('id')->toArray();

        $completedCounts = DB::table('trip_members')
            ->join('trips', 'trips.id', '=', 'trip_members.trip_id')
            ->whereIn('trip_members.user_id', $candidateIds)
            ->where('trip_members.status', 'joined')
            ->where('trips.status', 'completed')
            ->groupBy('trip_members.user_id')
            ->selectRaw('trip_members.user_id, COUNT(*) as cnt')
            ->pluck('cnt', 'user_id')
            ->toArray();

        $scored = $candidates->map(function (User $user) use ($tripType, $tripRegion, $completedCounts) {
            $score   = 0;
            $styles  = $user->preferences['travel_style'] ?? [];
            $regions = $user->preferences['regions'] ?? [];

            // 1. Travel style (40 pts)
            if (!empty($styles) && in_array($tripType, $styles)) {
                $score += 40;
            }

            // 2. Region interest (30 pts)
            if ($tripRegion && !empty($regions) && in_array($tripRegion, $regions)) {
                $score += 30;
            }

            // 3. Trip activity (20 pts)
            $completed = $completedCounts[$user->id] ?? 0;
            $score += match (true) {
                $completed >= 5 => 20,
                $completed >= 3 => 15,
                $completed >= 1 => 10,
                default         => 5,
            };

            // 4. Verification bonus (10 pts)
            if ($user->is_verified) {
                $score += 10;
            }

            return ['user_id' => $user->id, 'score' => $score];
        });

        return $scored
            ->sortByDesc('score')
            ->take($limit)
            ->values()
            ->toArray();
    }
}
