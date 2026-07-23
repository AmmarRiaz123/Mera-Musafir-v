<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\Destination;
use App\Support\ImageUrl;
use Illuminate\Http\Request;

class DestinationAdminController extends Controller
{
    /** GET /admin/destinations?search= — all of them, active or not. */
    public function index(Request $request)
    {
        $query = Destination::query()->withCount('trips');

        if ($search = trim((string) $request->input('search'))) {
            $query->where('name', 'like', "%{$search}%");
        }

        $destinations = $query->orderByDesc('is_featured')->orderBy('name')->paginate(30);

        return response()->json([
            'data' => $destinations->through(fn (Destination $d) => [
                'id'          => $d->id,
                'name'        => $d->name,
                'slug'        => $d->slug,
                'province'    => $d->province,
                'cover_image' => ImageUrl::resolve($d->cover_image),
                'is_featured' => (bool) $d->is_featured,
                'is_active'   => (bool) $d->is_active,
                'trips_count' => $d->trips_count,
            ])->items(),
            'meta' => [
                'total'        => $destinations->total(),
                'current_page' => $destinations->currentPage(),
                'last_page'    => $destinations->lastPage(),
            ],
        ]);
    }

    /** POST /admin/destinations/{destination}/feature — homepage feature toggle. */
    public function toggleFeatured(Destination $destination)
    {
        $destination->update(['is_featured' => !$destination->is_featured]);

        return response()->json(['is_featured' => (bool) $destination->is_featured]);
    }

    /**
     * POST /admin/destinations/{destination}/active — show or hide it.
     * A hidden destination stays in the DB (trips still reference it) but drops
     * out of discovery.
     */
    public function toggleActive(Destination $destination)
    {
        $destination->update(['is_active' => !$destination->is_active]);

        return response()->json(['is_active' => (bool) $destination->is_active]);
    }
}
