<?php

namespace App\Http\Controllers\LeadDiscovery\Admin;

use App\Http\Controllers\Controller;
use App\Services\LeadDiscovery\ScanOrchestrator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ScanController extends Controller
{
    public function __construct(
        private readonly ScanOrchestrator $orchestrator
    ) {
    }

    public function runManual(Request $request): RedirectResponse
    {
        $this->ensureAdminAccess($request);

        $data = $request->validate([
            'note' => ['nullable', 'string', 'max:1000'],
            'force' => ['nullable', 'boolean'],
            'cells' => ['nullable', 'array'],
            'cells.*' => ['integer', 'exists:ld_grid_cells,id'],
            'keywords' => ['nullable', 'array'],
            'keywords.*' => ['integer', 'exists:ld_keywords,id'],
        ]);

        $run = $this->orchestrator->runScan([
            'mode' => \App\Models\LdScanRun::MODE_MANUAL,
            'force' => $request->boolean('force'),
            'note' => $data['note'] ?? null,
            'user' => $request->user(),
            'cell_ids' => $data['cells'] ?? [],
            'keyword_ids' => $data['keywords'] ?? [],
        ]);

        return back()->with('success', "Manual scan berhasil dipicu (run #{$run->id}).");
    }

    private function ensureAdminAccess(Request $request): void
    {
        $user = $request->user();
        if (!$user || !$user->hasAnyRole(['Admin', 'SuperAdmin'])) {
            abort(403);
        }
    }
}
