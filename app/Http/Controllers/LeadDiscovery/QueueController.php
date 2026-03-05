<?php

namespace App\Http\Controllers\LeadDiscovery;

use App\Http\Controllers\Controller;
use App\Models\LdScanRun;
use App\Models\Prospect;
use App\Models\ProspectAnalysis;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class QueueController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('viewAny', Prospect::class);
        $this->closeStaleAnalyses();

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

    public function cleanupStuck(Request $request): RedirectResponse
    {
        $this->authorize('viewAny', Prospect::class);

        $affected = $this->closeStaleAnalyses(true);
        if ($affected > 0) {
            return back()->with('success', "Berhasil membersihkan {$affected} queue analyze yang nyangkut.");
        }

        return back()->with('info', 'Tidak ada queue analyze yang nyangkut.');
    }

    private function closeStaleAnalyses(bool $forceAll = false): int
    {
        $now = Carbon::now();
        $queuedCutoff = $forceAll ? $now->copy()->subMinutes(1) : $now->copy()->subMinutes(5);
        $runningCutoff = $forceAll ? $now->copy()->subMinutes(1) : $now->copy()->subMinutes(20);

        return ProspectAnalysis::query()
            ->whereIn('status', [
                ProspectAnalysis::STATUS_QUEUED,
                ProspectAnalysis::STATUS_RUNNING,
            ])
            ->where(function ($query) use ($queuedCutoff, $runningCutoff) {
                $query->where(function ($queued) use ($queuedCutoff) {
                    $queued->where('status', ProspectAnalysis::STATUS_QUEUED)
                        ->where('created_at', '<=', $queuedCutoff);
                })->orWhere(function ($running) use ($runningCutoff) {
                    $running->where('status', ProspectAnalysis::STATUS_RUNNING)
                        ->where(function ($runningAge) use ($runningCutoff) {
                            $runningAge->where('started_at', '<=', $runningCutoff)
                                ->orWhere(function ($fallback) use ($runningCutoff) {
                                    $fallback->whereNull('started_at')
                                        ->where('created_at', '<=', $runningCutoff);
                                });
                        });
                });
            })
            ->update([
                'status' => ProspectAnalysis::STATUS_FAILED,
                'finished_at' => $now,
                'error_message' => 'Auto-closed stale analysis entry.',
                'updated_at' => $now,
            ]);
    }
}
