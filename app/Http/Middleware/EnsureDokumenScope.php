<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureDokumenScope
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();
        if (!$user || !method_exists($user, 'isDokumenOnly') || !$user->isDokumenOnly()) {
            return $next($request);
        }

        $routeName = (string) optional($request->route())->getName();
        if ($routeName === 'dashboard') {
            return redirect()->route('documents.index');
        }

        $allowedRoutePatterns = [
            'documents.*',
            'customers.index',
            'customers.show',
            'customers.contacts',
            'customers.search',
            'profile.*',
            'password.change',
            'password.update',
            'password.confirm',
            'verification.*',
            'logout',
        ];

        foreach ($allowedRoutePatterns as $pattern) {
            if ($request->routeIs($pattern)) {
                return $next($request);
            }
        }

        $path = trim($request->path(), '/');
        if (in_array($path, ['confirm-password', 'email/verification-notification'], true)) {
            return $next($request);
        }

        if ($request->expectsJson()) {
            abort(403, 'Akses role Dokumen dibatasi hanya untuk modul dokumen.');
        }

        return redirect()
            ->route('documents.index')
            ->with('error', 'Role Dokumen hanya diizinkan mengakses modul dokumen.');
    }
}
