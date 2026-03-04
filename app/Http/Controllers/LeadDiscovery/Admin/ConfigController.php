<?php

namespace App\Http\Controllers\LeadDiscovery\Admin;

use App\Http\Controllers\Controller;
use App\Models\LdGridCell;
use App\Models\LdKeyword;
use App\Models\LdScanRun;
use App\Models\Setting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ConfigController extends Controller
{
    public function index(Request $request)
    {
        $this->ensureAdminAccess($request);

        $tab = (string) $request->input('tab', 'runtime');

        $keywords = LdKeyword::query()
            ->orderBy('priority')
            ->orderBy('id')
            ->paginate(15, ['*'], 'keywords_page')
            ->withQueryString();

        $gridCells = LdGridCell::query()
            ->orderBy('province')
            ->orderBy('city')
            ->orderBy('name')
            ->paginate(15, ['*'], 'cells_page')
            ->withQueryString();

        $scanRuns = LdScanRun::query()
            ->with('creator:id,name')
            ->latest('id')
            ->limit(10)
            ->get();

        $settings = [
            'enabled' => (int) Setting::get('lead_discovery.enabled', 0),
            'max_cells_per_run' => (int) Setting::get('lead_discovery.max_cells_per_run', 20),
            'max_keywords_per_cell' => (int) Setting::get('lead_discovery.max_keywords_per_cell', 8),
            'max_pages_per_query' => (int) Setting::get('lead_discovery.max_pages_per_query', 3),
            'page_token_delay_ms' => (int) Setting::get('lead_discovery.page_token_delay_ms', 2200),
            'request_timeout_sec' => (int) Setting::get('lead_discovery.request_timeout_sec', 20),
            'retry_max' => (int) Setting::get('lead_discovery.retry_max', 2),
        ];

        return view('admin.lead-discovery.config', compact(
            'tab',
            'keywords',
            'gridCells',
            'scanRuns',
            'settings'
        ));
    }

    public function update(Request $request): RedirectResponse
    {
        $this->ensureAdminAccess($request);

        $data = $request->validate([
            'enabled' => ['nullable', 'boolean'],
            'max_cells_per_run' => ['required', 'integer', 'min:1', 'max:500'],
            'max_keywords_per_cell' => ['required', 'integer', 'min:1', 'max:100'],
            'max_pages_per_query' => ['required', 'integer', 'min:1', 'max:3'],
            'page_token_delay_ms' => ['required', 'integer', 'min:0', 'max:10000'],
            'request_timeout_sec' => ['required', 'integer', 'min:5', 'max:120'],
            'retry_max' => ['required', 'integer', 'min:0', 'max:10'],
        ]);

        Setting::setMany([
            'lead_discovery.enabled' => $request->boolean('enabled') ? '1' : '0',
            'lead_discovery.max_cells_per_run' => (string) $data['max_cells_per_run'],
            'lead_discovery.max_keywords_per_cell' => (string) $data['max_keywords_per_cell'],
            'lead_discovery.max_pages_per_query' => (string) $data['max_pages_per_query'],
            'lead_discovery.page_token_delay_ms' => (string) $data['page_token_delay_ms'],
            'lead_discovery.request_timeout_sec' => (string) $data['request_timeout_sec'],
            'lead_discovery.retry_max' => (string) $data['retry_max'],
        ]);

        return back()->with('success', 'Konfigurasi Lead Discovery berhasil disimpan.');
    }

    private function ensureAdminAccess(Request $request): void
    {
        $user = $request->user();
        if (!$user || !$user->hasAnyRole(['Admin', 'SuperAdmin'])) {
            abort(403);
        }
    }
}
