<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\BookingResource;
use App\Http\Resources\PackageResource;
use App\Models\AgencyPackage;
use App\Models\Booking;
use App\Services\BookingService;
use App\Models\GroupChat;
use App\Models\Report;
use App\Models\Trip;
use App\Models\TripMember;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class PackageController extends Controller
{
    // GET /packages — public
    public function index(Request $request)
    {
        $query = AgencyPackage::with(['agency', 'destination'])
            ->where('status', 'published')
            ->orderBy('start_date');

        if ($request->destination_id) {
            $query->where('destination_id', $request->destination_id);
        }
        if ($request->type) {
            $query->where('type', $request->type);
        }
        if ($request->search) {
            $query->where('title', 'like', '%' . $request->search . '%');
        }
        if ($request->agency_id) {
            $query->where('agency_id', $request->agency_id);
        }
        if ($request->min_price) {
            $query->where('price_per_person', '>=', $request->min_price);
        }
        if ($request->max_price) {
            $query->where('price_per_person', '<=', $request->max_price);
        }
        if ($request->start_date) {
            $query->whereDate('start_date', '>=', $request->start_date);
        }

        $packages = $query->paginate($request->integer('per_page', 12));

        return PackageResource::collection($packages)->additional(['message' => 'Packages retrieved']);
    }

    // GET /packages/my — auth:sanctum
    public function myPackages(Request $request)
    {
        $agency = $request->user()->agency;
        if (!$agency) {
            return response()->json(['message' => 'No agency profile found'], 404);
        }

        $packages = $agency->packages()
            ->with(['destination'])
            ->withCount('bookings')
            ->latest()
            ->paginate(15);

        return PackageResource::collection($packages)->additional(['message' => 'My packages retrieved']);
    }

    // GET /packages/{package} — public
    public function show(AgencyPackage $package)
    {
        if ($package->status === 'published') {
            $package->increment('views_count');
        }

        $package->load(['agency.user', 'destination']);

        // Whether the viewer has an unresolved report against this package,
        // so the UI can hide the report button until an admin resolves it.
        $authUser     = auth('sanctum')->user();
        $reportedByMe = $authUser
            ? Report::where('reporter_id', $authUser->id)
                ->where('reported_id', $package->id)
                ->where('reported_type', AgencyPackage::class)
                ->whereNotIn('status', ['actioned', 'dismissed'])
                ->exists()
            : false;

        return response()->json([
            'message' => 'Package retrieved',
            'data'    => array_merge(
                (new PackageResource($package))->resolve(),
                ['reported_by_me' => $reportedByMe],
            ),
        ]);
    }

    // POST /packages — auth:sanctum
    public function store(Request $request)
    {
        $agency = $request->user()->agency;
        if (!$agency) {
            return response()->json(['message' => 'No agency profile found. Please register your agency first.'], 404);
        }

        $validated = $request->validate([
            'title'               => 'required|string|max:255',
            'destination_id'      => 'required|exists:destinations,id',
            'description'         => 'required|string',
            'price_per_person'    => 'required|integer|min:1',
            'max_capacity'        => 'required|integer|min:1',
            'start_date'          => 'required|date',
            'end_date'            => 'required|date|after_or_equal:start_date',
            'type'                => 'required|in:day_trip,weekend,extended,custom',
            'includes'            => 'nullable|array',
            'includes.*'          => 'string',
            'itinerary_overview'  => 'nullable|array',
            'cover_image'         => 'nullable|string',
            'status'              => 'in:draft,published',
        ]);

        // Enforce monthly package limits by tier
        if ($agency->tier !== 'premium') {
            $limit      = $agency->tier === 'pro' ? 15 : 3;
            $thisMonth  = $agency->packages()
                ->whereMonth('created_at', now()->month)
                ->whereYear('created_at', now()->year)
                ->count();
            if ($thisMonth >= $limit) {
                return response()->json([
                    'message' => "Monthly package limit ({$limit}) reached for {$agency->tier} tier.",
                ], 422);
            }
        }

        $slug         = $this->uniqueSlug(Str::slug($validated['title']));
        $durationDays = now()->parse($validated['start_date'])->diffInDays($validated['end_date']) + 1;

        $package = AgencyPackage::create([
            ...$validated,
            'agency_id'     => $agency->id,
            'slug'          => $slug,
            'duration_days' => $durationDays,
            'status'        => $validated['status'] ?? 'draft',
        ]);

        $package->load(['agency', 'destination']);

        return response()->json([
            'message' => 'Package created',
            'data'    => new PackageResource($package),
        ], 201);
    }

    // PUT /packages/{package} — auth:sanctum
    public function update(Request $request, AgencyPackage $package)
    {
        if ($package->agency->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'title'               => 'sometimes|string|max:255',
            'destination_id'      => 'sometimes|exists:destinations,id',
            'description'         => 'sometimes|string',
            'price_per_person'    => 'sometimes|integer|min:1',
            'max_capacity'        => 'sometimes|integer|min:1',
            'start_date'          => 'sometimes|date',
            'end_date'            => 'sometimes|date',
            'type'                => 'sometimes|in:day_trip,weekend,extended,custom',
            'includes'            => 'sometimes|nullable|array',
            'includes.*'          => 'string',
            'itinerary_overview'  => 'sometimes|nullable|array',
            'cover_image'         => 'sometimes|nullable|string',
            'status'              => 'sometimes|in:draft,published,closed,archived',
        ]);

        // Recalculate duration if dates change
        $start = $validated['start_date'] ?? $package->start_date->format('Y-m-d');
        $end   = $validated['end_date']   ?? $package->end_date->format('Y-m-d');
        $validated['duration_days'] = now()->parse($start)->diffInDays($end) + 1;

        $package->update($validated);
        $package->load(['agency', 'destination']);

        return response()->json([
            'message' => 'Package updated',
            'data'    => new PackageResource($package),
        ]);
    }

    // DELETE /packages/{package} — auth:sanctum
    public function destroy(Request $request, AgencyPackage $package)
    {
        if ($package->agency->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $package->delete();

        return response()->json(['message' => 'Package deleted']);
    }

    // POST /packages/{package}/book — auth:sanctum
    public function book(Request $request, AgencyPackage $package)
    {
        if ($package->status !== 'published') {
            return response()->json(['message' => 'This package is not available for booking'], 422);
        }

        $userAgency = $request->user()->agency;
        if ($userAgency && $userAgency->id === $package->agency_id) {
            return response()->json(['message' => "You cannot book your own agency's package."], 422);
        }

        $validated = $request->validate([
            'travelers_count' => 'required|integer|min:1',
            'notes'           => 'nullable|string|max:1000',
        ]);

        if ($package->spotsLeft() < $validated['travelers_count']) {
            return response()->json(['message' => "Only {$package->spotsLeft()} spot(s) remaining"], 422);
        }

        $existing = Booking::where('agency_package_id', $package->id)
            ->where('user_id', $request->user()->id)
            ->whereNotIn('status', ['cancelled'])
            ->first();

        if ($existing) {
            return response()->json(['message' => 'You already have an active booking for this package'], 422);
        }

        $booking = Booking::create([
            'user_id'            => $request->user()->id,
            'agency_package_id'  => $package->id,
            'travelers_count'    => $validated['travelers_count'],
            'total_amount'       => $package->price_per_person * $validated['travelers_count'],
            'notes'              => $validated['notes'] ?? null,
            'status'             => 'pending',
            'payment_status'     => 'unpaid',
        ]);

        $package->increment('booked_count', $validated['travelers_count']);
        $booking->load('agencyPackage');

        return response()->json([
            'message' => 'Booking created. Waiting for agency confirmation.',
            'data'    => new BookingResource($booking),
        ], 201);
    }

    // GET /packages/{package}/bookings — auth:sanctum (agency owner)
    public function agencyBookings(Request $request, AgencyPackage $package)
    {
        if ($package->agency->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $query = $package->bookings()->with('user')->latest();

        if ($request->status) {
            $query->where('status', $request->status);
        }

        return BookingResource::collection($query->get())
            ->additional(['message' => 'Bookings retrieved']);
    }

    // POST /packages/{package}/bookings/{booking}/confirm — auth:sanctum
    public function confirmBooking(Request $request, AgencyPackage $package, Booking $booking)
    {
        if ($package->agency->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        // Confirming and seating the traveller lives in the service, because
        // a settled payment has to do exactly the same thing.
        app(BookingService::class)->confirm($booking);

        return response()->json([
            'message' => 'Booking confirmed',
            'data'    => new BookingResource($booking),
        ]);
    }

    // POST /packages/{package}/bookings/{booking}/cancel — auth:sanctum
    public function cancelBooking(Request $request, AgencyPackage $package, Booking $booking)
    {
        $userId          = $request->user()->id;
        $isAgencyOwner   = $package->agency->user_id === $userId;
        $isBookingOwner  = $booking->user_id === $userId;

        if (!$isAgencyOwner && !$isBookingOwner) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        if ($booking->status === 'cancelled') {
            return response()->json(['message' => 'Booking is already cancelled'], 422);
        }

        // Refunds cancel bookings too, so the seat-releasing lives in one place.
        app(BookingService::class)->release($booking);

        return response()->json([
            'message' => 'Booking cancelled',
            'data'    => new BookingResource($booking),
        ]);
    }

    // ─── Helper ─────────────────────────────────────────────────────────────

    private function uniqueSlug(string $base): string
    {
        $slug = $base;
        $i    = 1;
        while (AgencyPackage::where('slug', $slug)->withTrashed()->exists()) {
            $slug = $base . '-' . $i++;
        }
        return $slug;
    }
}
