<?php

namespace App\Http\Controllers\Report;

use App\Http\Controllers\Controller;
use App\Services\FamilyPerformanceReportService;
use Illuminate\Http\Request;

class AparReportController extends Controller
{
    public function __construct(
        private readonly FamilyPerformanceReportService $familyPerformanceReportService
    ) {
    }

    public function index(Request $request)
    {
        $filters = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'family_code' => ['nullable', 'string', 'max:50'],
        ]);

        $report = $this->familyPerformanceReportService->buildReport($filters);

        return view('reports.apar', [
            'filters' => $report['filters'],
            'summaryRows' => $report['summary_rows'],
            'totals' => $report['totals'],
            'refill' => $report['refill'],
            'familyCodes' => $this->familyPerformanceReportService->familyCodeOptions(),
        ]);
    }
}
