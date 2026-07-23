<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Trip;
use Illuminate\Http\Request;

/**
 * The dates a traveller is already committed to, so the booking and trip-join
 * screens can warn about an overlap before someone double-books a weekend.
 *
 * A warning, not a gate — the frontend computes the overlap and lets them
 * through either way. This just hands over the ranges.
 */
class ScheduleController extends Controller
{
    public function commitments(Request $request)
    {
        $userId = $request->user()->id;

        // Trips they've actually joined (as a member or host), not ones they're
        // merely browsing or have a pending request on.
        $trips = Trip::query()
            ->whereHas('joinedMembers', fn ($q) => $q->where('users.id', $userId))
            ->get(['id', 'title', 'start_date', 'end_date'])
            ->map(fn (Trip $t) => [
                'kind'       => 'trip',
                'title'      => $t->title,
                'start_date' => $t->start_date->format('Y-m-d'),
                'end_date'   => $t->end_date->format('Y-m-d'),
                'link'       => '/trips/' . $t->id,
            ]);

        // Package bookings that are real commitments: approved (seat held,
        // waiting to pay) or confirmed. Pending is just an unreviewed request,
        // and cancelled is gone — neither should raise a clash.
        $bookings = Booking::query()
            ->where('user_id', $userId)
            ->whereIn('status', ['approved', 'confirmed'])
            ->with('agencyPackage:id,title,slug,start_date,end_date')
            ->get()
            ->filter(fn (Booking $b) => $b->agencyPackage)
            ->map(fn (Booking $b) => [
                'kind'       => 'package',
                'title'      => $b->agencyPackage->title,
                'start_date' => $b->agencyPackage->start_date->format('Y-m-d'),
                'end_date'   => $b->agencyPackage->end_date->format('Y-m-d'),
                'link'       => '/packages/' . $b->agencyPackage->slug,
            ])
            ->values();

        return response()->json(['data' => $trips->concat($bookings)->values()]);
    }
}
