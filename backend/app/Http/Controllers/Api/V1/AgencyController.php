<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\AgencyResource;
use App\Http\Resources\BookingResource;
use App\Models\Agency;
use App\Models\Booking;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class AgencyController extends Controller
{
    // POST /agencies/register — auth:sanctum
    public function register(Request $request)
    {
        $user = $request->user();

        if ($user->type !== 'agency') {
            return response()->json(['message' => 'Only agency accounts can register an agency profile'], 422);
        }

        if ($user->agency) {
            return response()->json(['message' => 'You already have an agency profile'], 422);
        }

        $validated = $request->validate([
            'business_name'  => 'required|string|max:150',
            'description'    => 'nullable|string',
            'license_number' => 'nullable|string|max:100',
            'contact_phone'  => 'nullable|string|max:20',
            'contact_email'  => 'nullable|email|max:150',
        ]);

        $slug = $this->uniqueSlug(Str::slug($validated['business_name']), Agency::class);

        $agency = Agency::create([
            ...$validated,
            'user_id'     => $user->id,
            'slug'        => $slug,
            'tier'        => 'pro',   // Default pro during development; Phase 11 admin upgrades
            'is_verified' => true,
            'verified_at' => now(),
        ]);

        return response()->json([
            'message' => 'Agency registered and is now live.',
            'data'    => new AgencyResource($agency),
        ], 201);
    }

    // GET /agencies/my — auth:sanctum
    public function myAgency(Request $request)
    {
        $agency = $request->user()->agency;

        if (!$agency) {
            return response()->json(['message' => 'No agency profile found'], 404);
        }

        $agency->loadCount('packages');

        return response()->json([
            'message' => 'Agency retrieved',
            'data'    => new AgencyResource($agency),
        ]);
    }

    // GET /agencies — public
    public function index(Request $request)
    {
        $query = Agency::withCount('packages');

        if ($request->search) {
            $query->where('business_name', 'like', '%' . $request->search . '%');
        }

        $agencies = $query->latest()->paginate(12);

        return AgencyResource::collection($agencies)->additional(['message' => 'Agencies retrieved']);
    }

    // GET /agencies/{agency} — public
    public function show(Agency $agency)
    {
        $agency->load('user')->loadCount('packages');

        return response()->json([
            'message' => 'Agency retrieved',
            'data'    => new AgencyResource($agency),
        ]);
    }

    // PUT /agencies/{agency} — auth:sanctum
    public function update(Request $request, Agency $agency)
    {
        if ($agency->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'business_name'  => 'sometimes|string|max:150',
            'logo'           => 'sometimes|nullable|string|max:2048',
            'cover_image'    => 'sometimes|nullable|string|max:2048',
            'description'    => 'sometimes|nullable|string',
            'contact_phone'  => 'sometimes|nullable|string|max:20',
            'contact_email'  => 'sometimes|nullable|email|max:150',
            'license_number' => 'sometimes|nullable|string|max:100',
        ]);

        $agency->update($validated);
        $agency->loadCount('packages');

        return response()->json([
            'message' => 'Agency updated',
            'data'    => new AgencyResource($agency),
        ]);
    }

    // POST /agencies/{agency}/follow — auth:sanctum
    public function follow(Request $request, Agency $agency)
    {
        $userId = $request->user()->id;
        $isFollowing = $agency->followers()->where('user_id', $userId)->exists();

        if ($isFollowing) {
            $agency->followers()->detach($userId);
            $nowFollowing = false;
        } else {
            // syncWithoutDetaching is idempotent — a double-click can't create
            // a duplicate follow row.
            $agency->followers()->syncWithoutDetaching([$userId]);
            $nowFollowing = true;
        }

        // Derive the count from the pivot rather than incrementing a counter.
        // The counter could drift out of sync (and even go negative) whenever a
        // toggle was applied against a stale is_following state.
        $count = $agency->followers()->count();
        $agency->forceFill(['follower_count' => $count])->save();

        return response()->json([
            'is_following'   => $nowFollowing,
            'follower_count' => $count,
        ]);
    }

    // GET /agencies/{agency}/analytics — auth:sanctum + agency.tier:pro
    public function analytics(Request $request, Agency $agency)
    {
        if ($agency->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $packageIds = $agency->packages()->pluck('id');
        $monthStart  = now()->startOfMonth();
        $monthEnd    = now()->endOfMonth();

        $totalRevenue = Booking::whereIn('agency_package_id', $packageIds)
            ->where('status', 'confirmed')
            ->sum('total_amount');

        $totalBookings = Booking::whereIn('agency_package_id', $packageIds)->count();

        $totalViews = $agency->packages()->sum('views_count');

        $bookingsThisMonth = Booking::whereIn('agency_package_id', $packageIds)
            ->where('status', 'confirmed')
            ->whereBetween('confirmed_at', [$monthStart, $monthEnd])
            ->count();

        $revenueThisMonth = Booking::whereIn('agency_package_id', $packageIds)
            ->where('status', 'confirmed')
            ->whereBetween('confirmed_at', [$monthStart, $monthEnd])
            ->sum('total_amount');

        $topPackages = $agency->packages()
            ->withCount('bookings')
            ->orderByDesc('bookings_count')
            ->limit(3)
            ->get()
            ->map(fn($p) => [
                'id'       => $p->id,
                'slug'     => $p->slug,
                'title'    => $p->title,
                'bookings' => $p->bookings_count,
                'revenue'  => $p->bookings()->where('status', 'confirmed')->sum('total_amount'),
            ]);

        $recentBookings = Booking::whereIn('agency_package_id', $packageIds)
            ->with(['user', 'agencyPackage'])
            ->latest()
            ->limit(5)
            ->get()
            ->map(fn($b) => [
                'id'            => $b->id,
                'traveler'      => $b->user->name,
                'package_title' => $b->agencyPackage->title,
                'status'        => $b->status,
                'total_amount'  => $b->total_amount,
                'created_at'    => $b->created_at->toDateTimeString(),
            ]);

        return response()->json([
            'total_revenue'        => $totalRevenue,
            'total_bookings'       => $totalBookings,
            'total_views'          => $totalViews,
            'bookings_this_month'  => $bookingsThisMonth,
            'revenue_this_month'   => $revenueThisMonth,
            'top_packages'         => $topPackages,
            'recent_bookings'      => $recentBookings,
        ]);
    }

    // GET /agencies/{agency}/bookings — auth:sanctum (owner only)
    public function allBookings(Request $request, Agency $agency)
    {
        if ($agency->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $packageIds = $agency->packages()->pluck('id');

        $query = Booking::whereIn('agency_package_id', $packageIds)
            ->with(['user', 'agencyPackage.trip'])
            ->latest();

        if ($request->status) {
            $query->where('status', $request->status);
        }

        $bookings = $query->paginate(15);

        return BookingResource::collection($bookings)->additional(['message' => 'Bookings retrieved']);
    }

    // ─── Helper ─────────────────────────────────────────────────────────────

    private function uniqueSlug(string $base, string $model): string
    {
        $slug = $base;
        $i    = 1;
        while ($model::where('slug', $slug)->exists()) {
            $slug = $base . '-' . $i++;
        }
        return $slug;
    }
}
