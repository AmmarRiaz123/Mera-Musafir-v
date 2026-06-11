<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\DestinationResource;
use App\Models\Destination;
use Illuminate\Http\Request;

class DestinationController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request) // Paginated response using Resource collection
    {
        $query = Destination::where('is_active', true);

        if ($request->has('province')) {
            $query->where('province', $request->province);
        }

        if ($request->has('type')) {
            $query->where('type', $request->type);
        }

        if ($request->has('search')) {
            $query->where('name', 'like', '%' . $request->search . '%');
        }

        if ($request->boolean('featured')) {
            $query->where('is_featured', true);
        }

        $destinations = $query->paginate($request->integer('per_page', 15));

        // API convention: adding message to resource collection response
        return DestinationResource::collection($destinations)->additional([
            'message' => 'Destinations retrieved successfully',
        ]);
    }

    /**
     * Display the specified resource.
     */
    public function show(Destination $destination)
    {
        if (!$destination->is_active) {
            return response()->json([
                'message' => 'Destination not found.',
                'data' => (object)[]
            ], 404);
        }

        return response()->json([
            'message' => 'Destination retrieved successfully',
            'data' => new DestinationResource($destination),
        ]);
    }
}
