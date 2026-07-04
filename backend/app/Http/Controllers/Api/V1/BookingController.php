<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\BookingResource;
use App\Models\Booking;
use Illuminate\Http\Request;

class BookingController extends Controller
{
    // GET /bookings/my — auth:sanctum
    // All bookings belonging to the authenticated traveler, with the package
    // (and its spawned trip) eager-loaded so trip_id / title are available.
    public function my(Request $request)
    {
        $bookings = Booking::where('user_id', $request->user()->id)
            ->with(['agencyPackage.trip', 'agencyPackage.destination', 'agencyPackage.agency'])
            ->latest()
            ->get();

        return BookingResource::collection($bookings)
            ->additional(['message' => 'Your bookings retrieved']);
    }
}
