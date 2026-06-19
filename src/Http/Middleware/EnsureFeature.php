<?php

namespace Entitlements\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureFeature
{
    /**
     * Abort 403 unless the authenticated user is entitled to the given feature key.
     */
    public function handle(Request $request, Closure $next, string $key): mixed
    {
        $user = $request->user();

        if ($user === null || ! $user->hasFeature($key)) {
            abort(403);
        }

        return $next($request);
    }
}
