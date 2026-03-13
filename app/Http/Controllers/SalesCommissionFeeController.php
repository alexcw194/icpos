<?php

namespace App\Http\Controllers;

use App\Services\SalesCommissionFeeService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SalesCommissionFeeController extends Controller
{
    public function __construct(
        private readonly SalesCommissionFeeService $salesCommissionFeeService
    ) {
    }

    public function index(Request $request)
    {
        $filters = $request->validate([
            'month' => ['nullable', 'date_format:Y-m'],
            'sales_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'row_status' => ['nullable', Rule::in(['all', 'available', 'in_unpaid_note', 'in_paid_note'])],
        ]);

        $report = $this->salesCommissionFeeService->buildReport($filters);

        return view('sales_commission_fees.index', [
            'filters' => $report['filters'],
            'features' => $report['features'],
            'summary' => $report['summary'],
            'rows' => $report['rows'],
            'salesUsers' => $report['salesUsers'],
        ]);
    }
}
