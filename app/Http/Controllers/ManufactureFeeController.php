<?php

namespace App\Http\Controllers;

use App\Services\ManufactureFeeService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ManufactureFeeController extends Controller
{
    public function __construct(
        private readonly ManufactureFeeService $manufactureFeeService
    ) {
    }

    public function index(Request $request)
    {
        $filters = $request->validate([
            'month' => ['nullable', 'date_format:Y-m'],
            'apar_fee_rate' => ['nullable', 'numeric', 'min:0'],
            'firehose_fee_rate' => ['nullable', 'numeric', 'min:0'],
            'row_status' => ['nullable', Rule::in(['all', 'available', 'in_unpaid_note', 'in_paid_note'])],
        ]);

        $report = $this->manufactureFeeService->buildReport($filters);

        return view('manufacture_fees.index', [
            'filters' => $report['filters'],
            'summary' => $report['summary'],
            'categories' => $report['categories'],
            'activity' => $report['activity'],
        ]);
    }
}
