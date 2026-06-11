<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireAgencyTier
{
    public function handle(Request $request, Closure $next, string $tier = 'pro'): Response
    {
        $user = $request->user();

        if (!$user || $user->type !== 'agency') {
            return response()->json(['message' => 'Agency account required'], 403);
        }

        $agency = $user->agency;
        if (!$agency) {
            return response()->json(['message' => 'Agency profile not found. Please complete registration.'], 403);
        }

        if (!$agency->hasTier($tier)) {
            $tierName = ucfirst($tier);
            return response()->json(['message' => "This feature requires {$tierName} tier"], 403);
        }

        return $next($request);
    }
}
