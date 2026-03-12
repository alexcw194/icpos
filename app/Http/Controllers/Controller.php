<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;   // <-- penting
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;
// (opsional) use Illuminate\Foundation\Bus\DispatchesJobs;

class Controller extends BaseController
{
    use AuthorizesRequests, ValidatesRequests;
    // (opsional) use DispatchesJobs;

    /**
     * Resolve per-page query from request using global contract.
     */
    protected function resolvePerPage(?Request $request = null): int
    {
        $request ??= request();
        $allowed = [20, 40, 80, 160];
        $default = 20;
        $value = (int) $request->input('per_page', $default);

        return in_array($value, $allowed, true) ? $value : $default;
    }
}
