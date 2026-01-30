<?php

namespace App\Http\Controllers;

use App\Models\Item;
use App\Models\ItemLaborRate;
use App\Models\LaborCost;
use App\Models\ProjectItemLaborRate;
use App\Models\Setting;
use App\Support\Number;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Schema;

class ProjectLaborController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $canView = $user?->hasAnyRole(['Admin', 'SuperAdmin', 'Sales', 'Finance', 'PM']) ?? false;
        if (!$canView) {
            abort(403);
        }

        $type = (string) $request->input('type', 'item');
        $type = in_array($type, ['item', 'project'], true) ? $type : 'item';
        $q = trim((string) $request->input('q', ''));

        $itemsQuery = Item::query()
            ->select('id', 'name', 'sku', 'item_type', 'list_type')
            ->with(['variants' => fn($query) => $query->orderBy('id')]);
        if ($type === 'project') {
            $itemsQuery->where('list_type', 'project');
        } else {
            $itemsQuery->where(function ($query) {
                $query->whereNull('list_type')->orWhere('list_type', '!=', 'project');
            });
        }
        // keep parent items so variants can be expanded in the list

        if ($q !== '') {
            $like = '%' . $q . '%';
            $itemsQuery->where(function ($query) use ($like) {
                $query->where('name', 'like', $like)
                    ->orWhere('sku', 'like', $like)
                    ->orWhereHas('variants', function ($v) use ($like) {
                        $v->where('sku', 'like', $like);
                    });
            });
        }

        $baseItems = $itemsQuery->orderBy('name')->get();
        $rows = collect();
        foreach ($baseItems as $item) {
            $variants = collect($item->variants ?? []);
            $activeVariants = $variants;
            if (Schema::hasColumn('item_variants', 'is_active')) {
                $activeVariants = $variants->filter(function ($variant) {
                    return $variant->is_active === null
                        || (int) $variant->is_active === 1
                        || $variant->is_active === true;
                });
            }
            $displayVariants = $activeVariants->isNotEmpty() ? $activeVariants : $variants;

            if ($displayVariants->isNotEmpty()) {
                foreach ($displayVariants as $variant) {
                    $rows->push((object) [
                        'item_id' => $item->id,
                        'id' => $item->id,
                        'variant_id' => $variant->id,
                        'name' => $variant->label ?? $item->renderVariantLabel($variant->attributes ?? []),
                        'sku' => $variant->sku ?: $item->sku,
                    ]);
                }
            } else {
                $rows->push($item);
            }
        }

        $page = LengthAwarePaginator::resolveCurrentPage();
        $perPage = 25;
        $items = new LengthAwarePaginator(
            $rows->forPage($page, $perPage)->values(),
            $rows->count(),
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->query()]
        );

        $rateIds = $items->getCollection()->pluck('item_id')->filter()->unique()->values();
        if ($rateIds->isEmpty()) {
            $rateIds = $items->getCollection()->pluck('id')->filter()->unique()->values();
        }

        $rates = $type === 'project'
            ? ProjectItemLaborRate::whereIn('project_item_id', $rateIds)->get()
            : ItemLaborRate::whereIn('item_id', $rateIds)->get();
        $rates = $rates->keyBy(function ($row) use ($type) {
            $baseId = $type === 'project' ? $row->project_item_id : $row->item_id;
            $variantId = $row->item_variant_id ?? 0;
            return $baseId . ':' . $variantId;
        });

        $canUpdateItem = $user?->hasAnyRole(['Admin', 'SuperAdmin', 'Finance']) ?? false;
        $canUpdateProject = $user?->hasAnyRole(['Admin', 'SuperAdmin', 'PM']) ?? false;
        $canManageCost = $user?->hasAnyRole(['Admin', 'SuperAdmin']) ?? false;

        $subContractors = collect();
        $selectedSubContractorId = null;
        $defaultSubContractorId = null;
        $laborCosts = collect();

        if ($canManageCost && Schema::hasTable('sub_contractors')) {
            $subContractors = \App\Models\SubContractor::query()
                ->when(Schema::hasColumn('sub_contractors', 'is_active'), function ($query) {
                    $query->where('is_active', true);
                })
                ->orderBy('name')
                ->get(['id', 'name']);

            if (Schema::hasTable('settings')) {
                $defaultSubContractorId = (int) Setting::get('default_sub_contractor_id', 0);
                if ($defaultSubContractorId <= 0) {
                    $defaultSubContractorId = null;
                }
            }

            $selectedSubContractorId = (int) $request->input('sub_contractor_id', $defaultSubContractorId ?: 0);
            if ($selectedSubContractorId <= 0 && $subContractors->isNotEmpty()) {
                $selectedSubContractorId = (int) $subContractors->first()->id;
            }
            if ($selectedSubContractorId <= 0) {
                $selectedSubContractorId = null;
            }

            if (
                $selectedSubContractorId
                && Schema::hasTable('labor_costs')
                && Schema::hasColumn('labor_costs', 'item_id')
                && Schema::hasColumn('labor_costs', 'context')
            ) {
                $context = $type === 'project' ? 'project' : 'retail';
                $laborCosts = LaborCost::query()
                    ->where('sub_contractor_id', $selectedSubContractorId)
                    ->where('context', $context)
                    ->whereIn('item_id', $rateIds)
                    ->get(['item_id', 'item_variant_id', 'cost_amount'])
                    ->keyBy(function ($row) {
                        return $row->item_id . ':' . ($row->item_variant_id ?? 0);
                    });
            }
        }

        return view('projects.labor.index', compact(
            'items',
            'rates',
            'type',
            'q',
            'canUpdateItem',
            'canUpdateProject',
            'canManageCost',
            'subContractors',
            'selectedSubContractorId',
            'defaultSubContractorId',
            'laborCosts'
        ));
    }

    public function update(Request $request, Item $item)
    {
        $type = (string) $request->input('type', 'item');
        $type = in_array($type, ['item', 'project'], true) ? $type : 'item';

        $user = $request->user();
        $canUpdateItem = $user?->hasAnyRole(['Admin', 'SuperAdmin', 'Finance']) ?? false;
        $canUpdateProject = $user?->hasAnyRole(['Admin', 'SuperAdmin', 'PM']) ?? false;
        $canManageCost = $user?->hasAnyRole(['Admin', 'SuperAdmin']) ?? false;

        if ($type === 'project' && !$canUpdateProject) {
            abort(403);
        }
        if ($type === 'item' && !$canUpdateItem) {
            abort(403);
        }
        if ($type === 'project' && $item->list_type !== 'project') {
            return back()->with('error', 'Item bukan Project Item.');
        }
        if ($type === 'item' && $item->list_type === 'project') {
            return back()->with('error', 'Gunakan Project Item Labor untuk item ini.');
        }

        $rules = [
            'labor_unit_cost' => ['required', 'string'],
            'notes' => ['nullable', 'string', 'max:255'],
            'sub_contractor_id' => ['nullable', 'integer'],
            'variant_id' => ['nullable', 'integer', 'exists:item_variants,id'],
        ];
        if ($canManageCost && Schema::hasTable('sub_contractors')) {
            $rules['sub_contractor_id'][] = 'exists:sub_contractors,id';
            $rules['labor_cost_amount'] = ['nullable', 'string'];
        }

        $data = $request->validate($rules);
        $data['labor_unit_cost'] = Number::idToFloat($data['labor_unit_cost'] ?? 0);
        if ($data['labor_unit_cost'] < 0) {
            throw ValidationException::withMessages([
                'labor_unit_cost' => 'Labor unit harus >= 0.',
            ]);
        }
        if (array_key_exists('labor_cost_amount', $data)) {
            $rawCost = $data['labor_cost_amount'];
            $data['labor_cost_amount'] = $rawCost === null || $rawCost === ''
                ? 0
                : Number::idToFloat($rawCost);
            if ($data['labor_cost_amount'] < 0) {
                throw ValidationException::withMessages([
                    'labor_cost_amount' => 'Labor cost harus >= 0.',
                ]);
            }
        }

        $variantId = !empty($data['variant_id']) ? (int) $data['variant_id'] : null;
        if ($variantId) {
            $variant = \App\Models\ItemVariant::query()->find($variantId);
            if (!$variant || (int) $variant->item_id !== (int) $item->id) {
                return back()->withErrors(['variant_id' => 'Variant tidak sesuai dengan item.']);
            }
        }

        if ($type === 'project') {
            $rate = ProjectItemLaborRate::firstOrNew([
                'project_item_id' => $item->id,
                'item_variant_id' => $variantId,
            ]);
            $rate->labor_unit_cost = $data['labor_unit_cost'];
            $rate->notes = $data['notes'] ?? null;
            $rate->updated_by = $user?->id;
            $rate->save();
        } else {
            $rate = ItemLaborRate::firstOrNew([
                'item_id' => $item->id,
                'item_variant_id' => $variantId,
            ]);
            $rate->labor_unit_cost = $data['labor_unit_cost'];
            $rate->notes = $data['notes'] ?? null;
            $rate->updated_by = $user?->id;
            $rate->save();
        }

        $canSaveCost = $canManageCost
            && !empty($data['sub_contractor_id'])
            && Schema::hasTable('labor_costs')
            && Schema::hasColumn('labor_costs', 'item_id')
            && Schema::hasColumn('labor_costs', 'context')
            && Schema::hasColumn('labor_costs', 'cost_amount');

        $laborCostNotice = null;
        if ($canSaveCost) {
            $context = $type === 'project' ? 'project' : 'retail';
            $cost = LaborCost::firstOrNew([
                'sub_contractor_id' => (int) $data['sub_contractor_id'],
                'item_id' => $item->id,
                'item_variant_id' => $variantId,
                'context' => $context,
            ]);
            $cost->cost_amount = (float) ($data['labor_cost_amount'] ?? 0);
            $cost->save();
        } elseif ($canManageCost && !empty($data['sub_contractor_id'])) {
            $laborCostNotice = 'Labor cost belum bisa disimpan. Jalankan migration untuk tabel labor_costs.';
        }

        $redirect = redirect()
            ->route('projects.labor.index', [
                'type' => $type,
                'q' => $request->input('q'),
                'sub_contractor_id' => $request->input('sub_contractor_id'),
            ])
            ->with('success', 'Labor master tersimpan.');

        if ($laborCostNotice) {
            $redirect->with('warning', $laborCostNotice);
        }

        return $redirect;
    }

    public function setDefaultSubContractor(Request $request)
    {
        $user = $request->user();
        $canManageCost = $user?->hasAnyRole(['Admin', 'SuperAdmin']) ?? false;
        if (!$canManageCost) {
            abort(403);
        }

        if (!Schema::hasTable('settings') || !Schema::hasTable('sub_contractors')) {
            return back()->with('error', 'Sub-contractor belum tersedia.');
        }

        $data = $request->validate([
            'sub_contractor_id' => ['required', 'exists:sub_contractors,id'],
        ]);

        Setting::setMany([
            'default_sub_contractor_id' => (int) $data['sub_contractor_id'],
        ]);

        return back()->with('success', 'Default sub-contractor diperbarui.');
    }
}
