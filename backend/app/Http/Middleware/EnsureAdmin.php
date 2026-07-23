<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gate the admin API. Every admin route sits behind auth:sanctum already; this
 * adds the role check in one place so no admin controller has to repeat it.
 */
class EnsureAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        if (!$request->user() || !$request->user()->hasRole('admin')) {
            return response()->json(['message' => 'Admin access required.'], 403);
        }

        return $next($request);
    }
}
