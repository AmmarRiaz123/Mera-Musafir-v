<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\Agency;
use App\Support\ImageUrl;
use Illuminate\Http\Request;

class AgencyAdminController extends Controller
{
    /** GET /admin/agencies?status=pending|verified|all */
    public function index(Request $request)
    {
        $status = $request->input('status', 'pending');

        $agencies = Agency::query()
            ->with('user:id,name,email')
            ->withCount('packages')
            ->when($status === 'pending', fn ($q) => $q->where('is_verified', false))
            ->when($status === 'verified', fn ($q) => $q->where('is_verified', true))
            ->latest()
            ->paginate(20);

        return response()->json([
            'data' => $agencies->through(fn (Agency $a) => [
                'id'             => $a->id,
                'business_name'  => $a->business_name,
                'slug'           => $a->slug,
                'logo'           => ImageUrl::resolve($a->logo),
                'tier'           => $a->tier,
                'is_verified'    => (bool) $a->is_verified,
                'license_number' => $a->license_number,
                'contact_email'  => $a->contact_email,
                'contact_phone'  => $a->contact_phone,
                'packages_count' => $a->packages_count,
                'owner'          => $a->user ? ['name' => $a->user->name, 'email' => $a->user->email] : null,
                'joined'         => $a->created_at->toDateString(),
            ])->items(),
            'meta' => [
                'total'        => $agencies->total(),
                'current_page' => $agencies->currentPage(),
                'last_page'    => $agencies->lastPage(),
            ],
        ]);
    }

    /** POST /admin/agencies/{agency}/verify — grant or revoke the verified badge. */
    public function toggleVerify(Agency $agency)
    {
        $agency->update([
            'is_verified' => !$agency->is_verified,
            'verified_at' => $agency->is_verified ? null : now(),
        ]);

        return response()->json([
            'message'     => $agency->is_verified
                ? "{$agency->business_name} verified."
                : "{$agency->business_name} unverified.",
            'is_verified' => (bool) $agency->is_verified,
        ]);
    }
}
