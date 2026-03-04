<?php

namespace App\Http\Controllers\LeadDiscovery\Admin;

use App\Http\Controllers\Controller;
use App\Models\LdGridCell;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class GridCellController extends Controller
{
    public function index(): RedirectResponse
    {
        return redirect()->route('admin.lead-discovery.config', ['tab' => 'cells']);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->ensureAdminAccess($request);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120', 'unique:ld_grid_cells,name'],
            'center_lat' => ['required', 'numeric', 'between:-90,90'],
            'center_lng' => ['required', 'numeric', 'between:-180,180'],
            'radius_m' => ['required', 'integer', 'min:100', 'max:50000'],
            'region_code' => ['nullable', 'string', 'max:32'],
            'city' => ['nullable', 'string', 'max:100'],
            'province' => ['nullable', 'string', 'max:100'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        LdGridCell::query()->create([
            'name' => trim((string) $data['name']),
            'center_lat' => (float) $data['center_lat'],
            'center_lng' => (float) $data['center_lng'],
            'radius_m' => (int) $data['radius_m'],
            'region_code' => $data['region_code'] ?? null,
            'city' => $data['city'] ?? null,
            'province' => $data['province'] ?? null,
            'is_active' => $request->boolean('is_active', true),
        ]);

        return back()->with('success', 'Grid cell berhasil ditambahkan.');
    }

    public function update(Request $request, LdGridCell $gridCell): RedirectResponse
    {
        $this->ensureAdminAccess($request);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120', 'unique:ld_grid_cells,name,' . $gridCell->id],
            'center_lat' => ['required', 'numeric', 'between:-90,90'],
            'center_lng' => ['required', 'numeric', 'between:-180,180'],
            'radius_m' => ['required', 'integer', 'min:100', 'max:50000'],
            'region_code' => ['nullable', 'string', 'max:32'],
            'city' => ['nullable', 'string', 'max:100'],
            'province' => ['nullable', 'string', 'max:100'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $gridCell->update([
            'name' => trim((string) $data['name']),
            'center_lat' => (float) $data['center_lat'],
            'center_lng' => (float) $data['center_lng'],
            'radius_m' => (int) $data['radius_m'],
            'region_code' => $data['region_code'] ?? null,
            'city' => $data['city'] ?? null,
            'province' => $data['province'] ?? null,
            'is_active' => $request->boolean('is_active'),
        ]);

        return back()->with('success', 'Grid cell berhasil diperbarui.');
    }

    public function destroy(Request $request, LdGridCell $gridCell): RedirectResponse
    {
        $this->ensureAdminAccess($request);
        $gridCell->delete();

        return back()->with('success', 'Grid cell berhasil dihapus.');
    }

    public function toggleActive(Request $request, LdGridCell $gridCell): RedirectResponse
    {
        $this->ensureAdminAccess($request);

        $gridCell->is_active = !$gridCell->is_active;
        $gridCell->save();

        return back()->with('success', 'Status aktif grid cell berhasil diubah.');
    }

    private function ensureAdminAccess(Request $request): void
    {
        $user = $request->user();
        if (!$user || !$user->hasAnyRole(['Admin', 'SuperAdmin'])) {
            abort(403);
        }
    }
}
