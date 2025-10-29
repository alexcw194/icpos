<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureAdmin
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();
        if (!$user || !$user->hasAnyRole(['Admin','SuperAdmin'])) {
            abort(403, 'Admin only.');
        }
        return $next($request);
    }
}
