<?php

namespace App\Http\Controllers\LeadDiscovery;

use App\Http\Controllers\Controller;
use App\Models\LdScanRun;
use App\Models\Prospect;
use App\Models\ProspectAnalysis;
use Illuminate\Http\Request;

class QueueController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('viewAny', Prospect::class);

        $scope = (string) $request->input('scope', 'processing');
        if (!in_array($scope, ['processing', 'completed', 'all'], true)) {
            $scope = 'processing';
        }

        $analyses = ProspectAnalysis::query()
            ->with(['prospect:id,name,place_id', 'requestedBy:id,name'])
            ->when($scope === 'processing', function ($query) {
                $query->whereIn('status', [
                    ProspectAnalysis::STATUS_QUEUED,
                    ProspectAnalysis::STATUS_RUNNING,
                ]);
            })
            ->when($scope === 'completed', function ($query) {
                $query->whereIn('status', [
                    ProspectAnalysis::STATUS_SUCCESS,
                    ProspectAnalysis::STATUS_FAILED,
                ]);
            })
            ->latest('id')
            ->simplePaginate(15, ['*'], 'analyses_page')
            ->withQueryString();

        $scanRuns = LdScanRun::query()
            ->with('creator:id,name')
            ->when($scope === 'processing', fn ($query) => $query->where('status', LdScanRun::STATUS_RUNNING))
            ->when($scope === 'completed', function ($query) {
                $query->whereIn('status', [
                    LdScanRun::STATUS_SUCCESS,
                    LdScanRun::STATUS_FAILED,
                ]);
            })
            ->latest('id')
            ->simplePaginate(15, ['*'], 'scans_page')
            ->withQueryString();

        $summary = [
            'analysis_processing' => ProspectAnalysis::query()
                ->whereIn('status', [ProspectAnalysis::STATUS_QUEUED, ProspectAnalysis::STATUS_RUNNING])
                ->count(),
            'analysis_completed' => ProspectAnalysis::query()
                ->whereIn('status', [ProspectAnalysis::STATUS_SUCCESS, ProspectAnalysis::STATUS_FAILED])
                ->count(),
            'scan_processing' => LdScanRun::query()
                ->where('status', LdScanRun::STATUS_RUNNING)
                ->count(),
            'scan_completed' => LdScanRun::query()
                ->whereIn('status', [LdScanRun::STATUS_SUCCESS, LdScanRun::STATUS_FAILED])
                ->count(),
        ];

        return view('lead-discovery.queue.index', compact(
            'scope',
            'analyses',
            'scanRuns',
            'summary'
        ));
    }
}

