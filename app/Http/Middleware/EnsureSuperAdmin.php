<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureSuperAdmin
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();
        if (!$user || !$user->hasRole('SuperAdmin')) {
            abort(403, 'Unauthorized.');
        }
        return $next($request);
    }
}
