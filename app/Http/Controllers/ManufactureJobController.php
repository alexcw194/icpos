<?php

namespace App\Http\Controllers;

use App\Models\Item;
use App\Models\ManufactureJob;
use App\Models\ManufactureRecipe;
use App\Services\StockMovementService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ManufactureJobController extends Controller
{
    private function kitTypes(): array
    {
        return ['kit', 'bundle'];
    }

    public function index()
    {
        $jobs = ManufactureJob::with('parentItem')->latest()->paginate(20);
        return view('manufacture_jobs.index', compact('jobs'));
    }

    public function create()
    {
        // RECOMMENDED: hanya tampilkan kit/bundle yang punya recipe
        $items = Item::query()
            ->whereIn('item_type', $this->kitTypes())
            ->whereHas('manufactureRecipes')
            ->orderBy('name')
            ->get();

        // Jika kamu mau tetap semua item, ganti jadi:
        // $items = Item::orderBy('name')->get();

        return view('manufacture_jobs.create', compact('items'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'parent_item_id' => [
                'required',
                Rule::exists('items', 'id')->whereIn('item_type', $this->kitTypes()),
            ],
            'qty_produced' => ['required', 'numeric', 'min:0.001'],
            'job_type' => ['required', Rule::in(['cut', 'assembly', 'fill', 'bundle'])],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $parent = Item::findOrFail($data['parent_item_id']);

        // load recipes + relasi variant->item (fallback)
        $recipes = ManufactureRecipe::query()
            ->where('parent_item_id', $parent->id)
            ->with(['componentVariant:item_id,id']) // minimal fields
            ->get();

        if ($recipes->isEmpty()) {
            return back()
                ->withInput()
                ->withErrors(['recipe' => 'Belum ada resep untuk item ini.']);
        }

        $components = [];
        $movements  = [];

        foreach ($recipes as $r) {
            // Komponen item id:
            // - prioritas component_item_id (untuk komponen item langsung)
            // - fallback ke componentVariant->item_id (untuk komponen variant)
            $componentItemId = $r->component_item_id
                ?: ($r->componentVariant?->item_id);

            if (!$componentItemId) {
                return back()
                    ->withInput()
                    ->withErrors(['recipe' => 'Data resep invalid: ada komponen tanpa item/variant. Silakan perbaiki resep.']);
            }

            // Guard: komponen tidak boleh sama dengan item hasil
            if ((int) $componentItemId === (int) $parent->id) {
                return back()
                    ->withInput()
                    ->withErrors(['recipe' => 'Resep invalid: komponen tidak boleh berasal dari Item Hasil.']);
            }

            $qtyUsed = (float) $r->qty_required * (float) $data['qty_produced'];

            $components[] = [
                'item_id'   => (int) $componentItemId,
                'qty_used'  => $qtyUsed,
                'recipe_id' => (int) $r->id,
                // optional audit:
                'component_variant_id' => $r->component_variant_id,
            ];

            $movements[] = [
                'item_id' => (int) $componentItemId,
                'qty'     => $qtyUsed,
                'type'    => 'decrease',
            ];
        }

        // hasil produksi: increase parent item
        $movements[] = [
            'item_id' => (int) $parent->id,
            'qty'     => (float) $data['qty_produced'],
            'type'    => 'increase',
        ];

        // Jalankan sebagai 1 transaksi supaya stock + job konsisten
        $job = DB::transaction(function () use ($data, $parent, $components, $movements) {
            StockMovementService::move($movements);

            $job = ManufactureJob::create([
                'parent_item_id'    => (int) $parent->id,
                'qty_produced'      => (float) $data['qty_produced'],
                'job_type'          => (string) $data['job_type'],
                'json_components'   => $components,
                'produced_by'       => Auth::id(),
                'produced_at'       => now(),
                'notes'             => $data['notes'] ?? null,
            ]);

            // Posting tambahan (kalau service kamu melakukan jurnal/ledger)
            \App\Services\StockService::postManufactureJob($job);

            return $job;
        });

        return redirect()
            ->route('manufacture-jobs.index')
            ->with('success', 'Proses produksi berhasil.');
    }

    public function show(ManufactureJob $manufactureJob)
    {
        $manufactureJob->load('parentItem');
        return view('manufacture_jobs.show', ['job' => $manufactureJob]);
    }
}
