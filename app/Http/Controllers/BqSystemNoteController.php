<?php

namespace App\Http\Controllers;

use App\Models\BqSystemNote;
use App\Support\ProjectSystems;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class BqSystemNoteController extends Controller
{
    public function index(Request $request)
    {
        $q = $request->string('q')->toString();
        $status = $request->string('status')->toString();
        $systems = ProjectSystems::all();

        $rows = BqSystemNote::query()
            ->when($q !== '', function ($qq) use ($q, $systems) {
                $keys = array_keys(array_filter($systems, function ($label) use ($q) {
                    return stripos($label, $q) !== false;
                }));
                $qq->where(function ($sub) use ($q, $keys) {
                    $sub->where('system_key', 'like', "%{$q}%")
                        ->orWhereIn('system_key', $keys);
                });
            })
            ->when($status === 'active', fn ($qq) => $qq->where('is_active', true))
            ->when($status === 'inactive', fn ($qq) => $qq->where('is_active', false))
            ->orderBy('system_key')
            ->paginate(20)
            ->withQueryString();

        return view('admin.bq_system_notes.index', compact('rows', 'q', 'status', 'systems'));
    }

    public function create()
    {
        $row = new BqSystemNote(['is_active' => true]);
        $systems = ProjectSystems::all();
        return view('admin.bq_system_notes.form', compact('row', 'systems'));
    }

    public function store(Request $request)
    {
        $systems = ProjectSystems::allowedKeys();
        $data = $request->validate([
            'system_key' => ['required', Rule::in($systems), 'max:64', 'unique:bq_system_notes,system_key'],
            'notes_template' => ['nullable', 'string'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $data['is_active'] = $request->boolean('is_active');

        BqSystemNote::create($data);

        return redirect()->route('bq-system-notes.index')
            ->with('success', 'BQ Systems Notes created.');
    }

    public function edit(BqSystemNote $bqSystemNote)
    {
        $row = $bqSystemNote;
        $systems = ProjectSystems::all();
        return view('admin.bq_system_notes.form', compact('row', 'systems'));
    }

    public function update(Request $request, BqSystemNote $bqSystemNote)
    {
        $systems = ProjectSystems::allowedKeys();
        $data = $request->validate([
            'system_key' => ['required', Rule::in($systems), 'max:64', Rule::unique('bq_system_notes', 'system_key')->ignore($bqSystemNote->id)],
            'notes_template' => ['nullable', 'string'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $data['is_active'] = $request->boolean('is_active');

        $bqSystemNote->update($data);

        return redirect()->route('bq-system-notes.index')
            ->with('ok', 'BQ Systems Notes updated.');
    }

    public function destroy(BqSystemNote $bqSystemNote)
    {
        try {
            $bqSystemNote->delete();
            return redirect()->route('bq-system-notes.index')
                ->with('ok', 'BQ Systems Notes deleted.');
        } catch (\Throwable $e) {
            return redirect()->route('bq-system-notes.index')
                ->with('error', 'BQ Systems Notes tidak bisa dihapus.');
        }
    }
}
