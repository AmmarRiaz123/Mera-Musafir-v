<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * A suspension has to bite the moment it's applied, not on next login. Login
 * already refuses a suspended account; this covers the gap — an already-signed-in
 * session — by rejecting every authenticated request from a suspended user.
 *
 * Runs on all /api routes but only acts when a Bearer token actually resolves,
 * so public and guest requests pass straight through.
 */
class EnsureNotSuspended
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user() ?? auth('sanctum')->user();

        if ($user && $user->is_blocked) {
            // 419-style would be misread as a CSRF issue; 403 with a clear code
            // lets the client wipe the session and bounce to login.
            return response()->json([
                'message' => 'Your account has been suspended.',
                'code'    => 'account_suspended',
            ], 403);
        }

        return $next($request);
    }
}
