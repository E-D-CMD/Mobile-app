<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsAdmin
{
    /**
     * Guards every /api/admin/* route. Runs after 'auth:sanctum' (see
     * routes/api.php), so $request->user() is always present here — this
     * only checks the role. A logged-in customer hitting an admin route
     * gets a clean 403, matching this API's {success, message} shape
     * everywhere else instead of the framework's default body.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user() || ! $request->user()->isAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have permission to access this resource.',
            ], 403);
        }

        return $next($request);
    }
}
