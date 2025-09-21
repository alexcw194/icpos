<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\CompanySuggestor;

class AiSuggestController extends Controller
{
    public function company(Request $request, CompanySuggestor $svc)
    {
        $q = trim((string) $request->get('q', ''));
        if (mb_strlen($q) < 3) {
            return response()->json(['items' => []]);
        }

        $items = $svc->search($q, 6);
        return response()->json(['items' => $items]);
    }
}
