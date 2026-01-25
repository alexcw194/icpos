<?php

namespace App\Http\Controllers;

use App\Models\BqLineTemplate;
use Illuminate\Http\Request;

class BqLineTemplateController extends Controller
{
    public function index(Request $request)
    {
        $q = $request->string('q')->toString();
        $status = $request->string('status')->toString();

        $rows = BqLineTemplate::query()
            ->withCount('lines')
            ->when($q !== '', function ($qq) use ($q) {
                $qq->where(function ($w) use ($q) {
                    $w->where('name', 'like', "%{$q}%")
                      ->orWhere('description', 'like', "%{$q}%");
                });
            })
            ->when($status === 'active', fn($qq) => $qq->where('is_active', true))
            ->when($status === 'inactive', fn($qq) => $qq->where('is_active', false))
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        return view('admin.bq_line_templates.index', compact('rows', 'q', 'status'));
    }

    public function create()
    {
        $row = new BqLineTemplate(['is_active' => true]);
        return view('admin.bq_line_templates.form', compact('row'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:190'],
            'description' => ['nullable', 'string'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $data['is_active'] = $request->boolean('is_active');
        $data['created_by_user_id'] = $request->user()?->id;
        $data['updated_by_user_id'] = $request->user()?->id;

        BqLineTemplate::create($data);

        return redirect()->route('bq-line-templates.index')
            ->with('success', 'Template created.');
    }

    public function show(BqLineTemplate $bqLineTemplate)
    {
        return redirect()->route('bq-line-templates.edit', $bqLineTemplate);
    }

    public function edit(BqLineTemplate $bqLineTemplate)
    {
        $row = $bqLineTemplate;
        return view('admin.bq_line_templates.form', compact('row'));
    }

    public function update(Request $request, BqLineTemplate $bqLineTemplate)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:190'],
            'description' => ['nullable', 'string'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $data['is_active'] = $request->boolean('is_active');
        $data['updated_by_user_id'] = $request->user()?->id;

        $bqLineTemplate->update($data);

        return redirect()->route('bq-line-templates.index')
            ->with('ok', 'Template updated.');
    }

    public function destroy(BqLineTemplate $bqLineTemplate)
    {
        $bqLineTemplate->delete();

        return redirect()->route('bq-line-templates.index')
            ->with('ok', 'Template deleted.');
    }
}
