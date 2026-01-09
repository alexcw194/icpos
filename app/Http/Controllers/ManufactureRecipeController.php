<?php

namespace App\Http\Controllers;

use App\Models\Item;
use App\Models\ItemVariant;
use App\Models\ManufactureRecipe;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ManufactureRecipeController extends Controller
{
    private function kitTypes(): array
    {
        return ['kit', 'bundle'];
    }

    private function parseUid(string $uid): array
    {
        // uid: item-123 / variant-456
        if (!preg_match('/^(item|variant)-(\d+)$/', $uid, $m)) {
            return ['type' => null, 'id' => null];
        }
        return ['type' => $m[1], 'id' => (int) $m[2]];
    }

    public function index(Request $request)
    {
        $q = trim((string) $request->query('q', ''));

        $kits = Item::query()
            ->whereIn('item_type', $this->kitTypes())
            ->whereHas('manufactureRecipes')
            ->when($q !== '', function ($query) use ($q) {
                $query->where(function ($sub) use ($q) {
                    $sub->where('name', 'like', "%{$q}%")
                        ->orWhere('sku', 'like', "%{$q}%");
                });
            })
            ->withCount('manufactureRecipes')
            ->withMax('manufactureRecipes', 'updated_at')
            ->with([
                'manufactureRecipes' => function ($r) {
                    $r->with(['componentVariant.item', 'componentItem'])
                        ->orderByRaw('COALESCE(component_variant_id, 0) asc, COALESCE(component_item_id, 0) asc');
                }
            ])
            ->orderBy('name')
            ->paginate(12)
            ->withQueryString();

        return view('manufacture_recipes.index', compact('kits', 'q'));
    }

    public function create()
    {
        $parentItems = Item::query()
            ->whereIn('item_type', $this->kitTypes())
            ->whereDoesntHave('manufactureRecipes') // hanya kit/bundle yang belum punya resep
            ->orderBy('name')
            ->get();

        // dropdown komponen pakai AJAX /api/items/search (seperti quotation)
        return view('manufacture_recipes.create', compact('parentItems'));
    }

    public function manage(Item $parentItem)
    {
        abort_unless(in_array($parentItem->item_type, $this->kitTypes(), true), 404);

        $recipes = ManufactureRecipe::query()
            ->where('parent_item_id', $parentItem->id)
            ->with(['componentVariant.item', 'componentItem'])
            ->orderByRaw('COALESCE(component_variant_id, 0) asc, COALESCE(component_item_id, 0) asc')
            ->get();

        // manage page juga idealnya pakai AJAX /api/items/search
        return view('manufacture_recipes.manage', compact('parentItem', 'recipes'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'parent_item_id' => [
                'required',
                Rule::exists('items', 'id')->whereIn('item_type', $this->kitTypes()),
            ],
            'components' => 'required|array|min:1',
            'components.*.component_variant_id' => ['required', 'string', 'regex:/^(item|variant)-\d+$/'],
            'components.*.qty_required' => 'required|numeric|min:0.1',
            'components.*.unit_factor' => 'nullable|numeric|min:0',
            'components.*.notes' => 'nullable|string|max:255',
        ]);

        $parentId = (int) $validated['parent_item_id'];

        // guard: kalau sudah ada resep, flow-nya lewat Manage/Bulk Update (konsisten)
        if (ManufactureRecipe::query()->where('parent_item_id', $parentId)->exists()) {
            return redirect()
                ->route('manufacture-recipes.manage', $parentId)
                ->with('success', 'Resep untuk item ini sudah ada. Silakan kelola di halaman ini.');
        }

        $this->syncRecipe($parentId, $validated['components']);

        return redirect()
            ->route('manufacture-recipes.manage', $parentId)
            ->with('success', 'Resep berhasil disimpan.');
    }

    public function bulkUpdate(Request $request, Item $parentItem)
    {
        abort_unless(in_array($parentItem->item_type, $this->kitTypes(), true), 404);

        $validated = $request->validate([
            'components' => 'required|array|min:1',
            'components.*.component_variant_id' => ['required', 'string', 'regex:/^(item|variant)-\d+$/'],
            'components.*.qty_required' => 'required|numeric|min:0.1',
            'components.*.unit_factor' => 'nullable|numeric|min:0',
            'components.*.notes' => 'nullable|string|max:255',
        ]);

        $this->syncRecipe((int) $parentItem->id, $validated['components']);

        return redirect()
            ->route('manufacture-recipes.manage', $parentItem)
            ->with('success', 'Resep berhasil diperbarui.');
    }

    private function syncRecipe(int $parentId, array $rows): void
    {
        // normalize uids
        $uids = collect($rows)->pluck('component_variant_id')->map(fn ($v) => trim((string) $v));

        if ($uids->count() !== $uids->unique()->count()) {
            abort(422, 'Komponen tidak boleh duplikat dalam satu resep.');
        }

        $parsed = $uids->map(fn ($uid) => ['uid' => $uid] + $this->parseUid($uid));

        $itemIds = $parsed->where('type', 'item')->pluck('id')->values();
        $variantIds = $parsed->where('type', 'variant')->pluck('id')->values();

        // guard: komponen tidak boleh sama dengan parent
        if ($itemIds->contains($parentId)) {
            abort(422, 'Komponen tidak boleh berasal dari Item Hasil.');
        }

        $variantItemMap = ItemVariant::query()
            ->whereIn('id', $variantIds->all())
            ->pluck('item_id', 'id'); // [variant_id => item_id]

        if ($variantItemMap->values()->contains($parentId)) {
            abort(422, 'Komponen tidak boleh berasal dari Item Hasil.');
        }

        DB::transaction(function () use ($parentId, $rows, $itemIds, $variantIds) {
            // DELETE yang tidak ada di payload (handle NULL legacy juga)
            ManufactureRecipe::query()
                ->where('parent_item_id', $parentId)
                ->where(function ($q) use ($itemIds, $variantIds) {
                    $q->where(function ($qq) use ($variantIds) {
                        $qq->whereNotNull('component_variant_id');
                        if ($variantIds->isNotEmpty()) {
                            $qq->whereNotIn('component_variant_id', $variantIds->all());
                        }
                    })
                    ->orWhere(function ($qq) use ($itemIds) {
                        $qq->whereNull('component_variant_id');
                        if ($itemIds->isNotEmpty()) {
                            $qq->whereNotIn('component_item_id', $itemIds->all());
                        }
                    })
                    ->orWhere(function ($qq) {
                        $qq->whereNull('component_variant_id')->whereNull('component_item_id');
                    });
                })
                ->delete();

            foreach ($rows as $row) {
                $uid = trim((string) ($row['component_variant_id'] ?? ''));
                $p = $this->parseUid($uid);

                $payload = [
                    'qty_required' => $row['qty_required'],
                    'unit_factor'  => $row['unit_factor'] ?? null,
                    'notes'        => $row['notes'] ?? null,
                ];

                if ($p['type'] === 'variant') {
                    ManufactureRecipe::updateOrCreate(
                        [
                            'parent_item_id' => $parentId,
                            'component_variant_id' => $p['id'],
                        ],
                        $payload + [
                            // IMPORTANT: jangan isi component_item_id untuk variant row (biar tidak bentrok unique)
                            'component_item_id' => null,
                        ]
                    );
                } elseif ($p['type'] === 'item') {
                    ManufactureRecipe::updateOrCreate(
                        [
                            'parent_item_id' => $parentId,
                            'component_item_id' => $p['id'],
                            'component_variant_id' => null,
                        ],
                        $payload
                    );
                }
            }
        });
    }

    public function destroy(ManufactureRecipe $manufactureRecipe)
    {
        $manufactureRecipe->delete();

        return back()->with('success', 'Resep dihapus.');
    }
}
