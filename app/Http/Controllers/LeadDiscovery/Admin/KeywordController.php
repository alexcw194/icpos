<?php

namespace App\Http\Controllers\LeadDiscovery\Admin;

use App\Http\Controllers\Controller;
use App\Models\LdKeyword;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class KeywordController extends Controller
{
    public function index(): RedirectResponse
    {
        return redirect()->route('admin.lead-discovery.config', ['tab' => 'keywords']);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->ensureAdminAccess($request);

        $data = $request->validate([
            'keyword' => ['required', 'string', 'max:190', 'unique:ld_keywords,keyword'],
            'category_label' => ['nullable', 'string', 'max:120'],
            'priority' => ['required', 'integer', 'min:1', 'max:9999'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        LdKeyword::query()->create([
            'keyword' => trim((string) $data['keyword']),
            'category_label' => $data['category_label'] ?? null,
            'priority' => (int) $data['priority'],
            'is_active' => $request->boolean('is_active', true),
        ]);

        return back()->with('success', 'Keyword berhasil ditambahkan.');
    }

    public function update(Request $request, LdKeyword $keyword): RedirectResponse
    {
        $this->ensureAdminAccess($request);

        $data = $request->validate([
            'keyword' => [
                'required',
                'string',
                'max:190',
                Rule::unique('ld_keywords', 'keyword')->ignore($keyword->id),
            ],
            'category_label' => ['nullable', 'string', 'max:120'],
            'priority' => ['required', 'integer', 'min:1', 'max:9999'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $keyword->update([
            'keyword' => trim((string) $data['keyword']),
            'category_label' => $data['category_label'] ?? null,
            'priority' => (int) $data['priority'],
            'is_active' => $request->boolean('is_active'),
        ]);

        return back()->with('success', 'Keyword berhasil diperbarui.');
    }

    public function destroy(Request $request, LdKeyword $keyword): RedirectResponse
    {
        $this->ensureAdminAccess($request);
        $keyword->delete();

        return back()->with('success', 'Keyword berhasil dihapus.');
    }

    public function toggleActive(Request $request, LdKeyword $keyword): RedirectResponse
    {
        $this->ensureAdminAccess($request);

        $keyword->is_active = !$keyword->is_active;
        $keyword->save();

        return back()->with('success', 'Status aktif keyword berhasil diubah.');
    }

    private function ensureAdminAccess(Request $request): void
    {
        $user = $request->user();
        if (!$user || !$user->hasAnyRole(['Admin', 'SuperAdmin'])) {
            abort(403);
        }
    }
}
