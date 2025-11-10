<?php

namespace App\Http\Controllers;

use App\Models\Item;
use App\Models\ManufactureJob;
use App\Models\ManufactureRecipe;
use App\Services\StockMovementService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ManufactureJobController extends Controller
{
    public function index()
    {
        $jobs = ManufactureJob::with('parentItem')->latest()->paginate(20);
        return view('manufacture_jobs.index', compact('jobs'));
    }

    public function create()
    {
        $items = Item::orderBy('name')->get();
        return view('manufacture_jobs.create', compact('items'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'parent_item_id' => 'required|exists:items,id',
            'qty_produced' => 'required|numeric|min:0.001',
            'job_type' => 'required|in:cut,assembly,fill,bundle',
            'notes' => 'nullable|string',
        ]);

        $parent = Item::findOrFail($data['parent_item_id']);
        $recipes = ManufactureRecipe::where('parent_item_id', $parent->id)->get();

        if ($recipes->isEmpty()) {
            return back()->withErrors(['recipe' => 'Belum ada resep untuk item ini.']);
        }

        $components = [];
        $movements = [];

        foreach ($recipes as $r) {
            $qty_used = $r->qty_required * $data['qty_produced'];
            $components[] = [
                'item_id' => $r->component_item_id,
                'qty_used' => $qty_used,
            ];
            $movements[] = ['item_id' => $r->component_item_id, 'qty' => $qty_used, 'type' => 'decrease'];
        }

        $movements[] = ['item_id' => $parent->id, 'qty' => $data['qty_produced'], 'type' => 'increase'];

        StockMovementService::move($movements);

        $job =ManufactureJob::create([
            'parent_item_id' => $parent->id,
            'qty_produced' => $data['qty_produced'],
            'job_type' => $data['job_type'],
            'json_components' => $components,
            'produced_by' => Auth::id(),
            'produced_at' => now(),
            'notes' => $data['notes'] ?? null,
        ]);

        \App\Services\StockService::postManufactureJob($job);

        return redirect()->route('manufacture-jobs.index')->with('success', 'Proses produksi berhasil.');
    }

    public function show(ManufactureJob $manufactureJob)
    {
        return view('manufacture_jobs.show', ['job' => $manufactureJob]);
    }
}
