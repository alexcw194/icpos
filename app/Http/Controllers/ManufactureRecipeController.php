<?php

namespace App\Http\Controllers;

use App\Models\Item;
use App\Models\ItemVariant;
use App\Models\ManufactureRecipe;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB;

class ManufactureRecipeController extends Controller
{
    private function kitTypes(): array
    {
        return ['kit', 'bundle'];
    }

    /**
     * Parse UID dari picker:
     * - "item-123"    => ['type'=>'item', 'id'=>123]
     * - "variant-456" => ['type'=>'variant', 'id'=>456]
     */
    private function parseComponentRef(string $ref): array
    {
        $ref = trim($ref);
        if (!preg_match('/^(item|variant)-(\d+)$/', $ref, $m)) {
            abort(422, "Invalid component ref: {$ref}");
        }

        return ['type' => $m[1], 'id' => (int) $m[2]];
    }

    /**
     * Normalize komponen dari request menjadi array rows siap simpan:
     * [
     *   [
     *     component_item_id => int,
     *     component_variant_id => ?int,
     *     qty_required => float,
     *     unit_factor => ?float,
     *     notes => ?string,
     *     token => string (unik)
     *   ],
     *   ...
     * ]
     */
    private function normalizeComponents(array $components, int $parentId): array
    {
        $out = [];
        $tokens = [];

        foreach ($components as $row) {
            $ref = (string) ($row['component_variant_id'] ?? '');
            $parsed = $this->parseComponentRef($ref);

            $componentItemId = null;
            $componentVariantId = null;
            $token = null;

            if ($parsed['type'] === 'item') {
                $item = Item::query()->findOrFail($parsed['id']);

                // guard: tidak boleh komponen = item hasil
                if ((int) $item->id === (int) $parentId) {
                    abort(422, 'Komponen tidak boleh berasal dari Item Hasil.');
                }

                $componentItemId = (int) $item->id;
                $componentVariantId = null;
                $token = "item-{$componentItemId}";
            } else {
                $variant = ItemVariant::query()->with('item')->findOrFail($parsed['id']);

                // guard: tidak boleh komponen berasal dari item hasil
                if ((int) $variant->item_id === (int) $parentId) {
                    abort(422, 'Komponen tidak boleh berasal dari Item Hasil.');
                }

                $componentItemId = (int) $variant->item_id;
                $componentVariantId = (int) $variant->id;
                $token = "variant-{$componentVariantId}";
            }

            // anti-duplikat (unik berdasarkan token)
            if (in_array($token, $tokens, true)) {
                abort(422, 'Komponen tidak boleh duplikat dalam satu resep.');
            }
            $tokens[] = $token;

            $out[] = [
                'component_item_id' => $componentItemId,
                'component_variant_id' => $componentVariantId,
                'qty_required' => (float) ($row['qty_required'] ?? 0),
                'unit_factor' => isset($row['unit_factor']) && $row['unit_factor'] !== '' ? (float) $row['unit_factor'] : null,
                'notes' => isset($row['notes']) && $row['notes'] !== '' ? (string) $row['notes'] : null,
                'token' => $token,
            ];
        }

        return $out;
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
                      ->orderByRaw('COALESCE(component_variant_id, 0) asc')
                      ->orderBy('component_item_id');
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
            ->orderBy('name')
            ->get();

        // Komponen picker via API (TomSelect) -> tidak perlu preload list
        return view('manufacture_recipes.create', compact('parentItems'));
    }

    public function manage(Item $parentItem)
    {
        abort_unless(in_array($parentItem->item_type, $this->kitTypes(), true), 404);

        $recipes = ManufactureRecipe::query()
            ->where('parent_item_id', $parentItem->id)
            ->with(['componentVariant.item', 'componentItem'])
            ->orderByRaw('COALESCE(component_variant_id, 0) asc')
            ->orderBy('component_item_id')
            ->get();

        // Komponen picker sebaiknya juga via API di halaman manage
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
            // UID dari picker quotation-style
            'components.*.component_variant_id' => ['required', 'string', 'regex:/^(item|variant)-\d+$/'],
            'components.*.qty_required' => 'required|numeric|decimal:0,1|min:0.1',
            'components.*.unit_factor' => 'nullable|numeric|min:0',
            'components.*.notes' => 'nullable|string|max:255',
        ]);

        $parentId = (int) $validated['parent_item_id'];
        $rows = $this->normalizeComponents($validated['components'], $parentId);

        DB::transaction(function () use ($parentId, $rows) {
            foreach ($rows as $r) {
                ManufactureRecipe::updateOrCreate(
                    [
                        'parent_item_id' => $parentId,
                        'component_item_id' => (int) $r['component_item_id'],
                        'component_variant_id' => $r['component_variant_id'], // null allowed
                    ],
                    [
                        'qty_required' => $r['qty_required'],
                        'unit_factor'  => $r['unit_factor'],
                        'notes'        => $r['notes'],
                    ]
                );
            }
        });

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
            'components.*.qty_required' => 'required|numeric|decimal:0,1|min:0.1',
            'components.*.unit_factor' => 'nullable|numeric|min:0',
            'components.*.notes' => 'nullable|string|max:255',
        ]);

        $rows = $this->normalizeComponents($validated['components'], (int) $parentItem->id);

        DB::transaction(function () use ($parentItem, $rows) {
            $desiredTokens = collect($rows)->pluck('token')->all();

            $existing = ManufactureRecipe::query()
                ->where('parent_item_id', $parentItem->id)
                ->get(['id', 'component_item_id', 'component_variant_id']);

            // delete yang tidak ada lagi (composite-safe via token)
            foreach ($existing as $ex) {
                $token = $ex->component_variant_id
                    ? "variant-{$ex->component_variant_id}"
                    : "item-{$ex->component_item_id}";

                if (!in_array($token, $desiredTokens, true)) {
                    $ex->delete();
                }
            }

            // upsert rows
            foreach ($rows as $r) {
                ManufactureRecipe::updateOrCreate(
                    [
                        'parent_item_id' => $parentItem->id,
                        'component_item_id' => (int) $r['component_item_id'],
                        'component_variant_id' => $r['component_variant_id'],
                    ],
                    [
                        'qty_required' => $r['qty_required'],
                        'unit_factor'  => $r['unit_factor'],
                        'notes'        => $r['notes'],
                    ]
                );
            }
        });

        return redirect()
            ->route('manufacture-recipes.manage', $parentItem)
            ->with('success', 'Resep berhasil diperbarui.');
    }

    public function destroy(ManufactureRecipe $manufactureRecipe)
    {
        $manufactureRecipe->delete();

        return back()->with('success', 'Resep dihapus.');
    }
}
