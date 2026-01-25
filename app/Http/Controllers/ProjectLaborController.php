<?php

namespace App\Http\Controllers;

use App\Models\Item;
use App\Models\ItemLaborRate;
use App\Models\ProjectItemLaborRate;
use Illuminate\Http\Request;

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

        $itemsQuery = Item::query()->select('id', 'name', 'sku', 'item_type', 'list_type');
        if ($type === 'project') {
            $itemsQuery->where('list_type', 'project');
        } else {
            $itemsQuery->where(function ($query) {
                $query->whereNull('list_type')->orWhere('list_type', '!=', 'project');
            });
        }

        if ($q !== '') {
            $like = '%' . $q . '%';
            $itemsQuery->where(function ($query) use ($like) {
                $query->where('name', 'like', $like)->orWhere('sku', 'like', $like);
            });
        }

        $items = $itemsQuery->orderBy('name')->paginate(25)->withQueryString();

        $rates = $type === 'project'
            ? ProjectItemLaborRate::whereIn('project_item_id', $items->pluck('id'))->get()->keyBy('project_item_id')
            : ItemLaborRate::whereIn('item_id', $items->pluck('id'))->get()->keyBy('item_id');

        $canUpdateItem = $user?->hasAnyRole(['Admin', 'SuperAdmin', 'Finance']) ?? false;
        $canUpdateProject = $user?->hasAnyRole(['Admin', 'SuperAdmin', 'PM']) ?? false;

        return view('projects.labor.index', compact('items', 'rates', 'type', 'q', 'canUpdateItem', 'canUpdateProject'));
    }

    public function update(Request $request, Item $item)
    {
        $type = (string) $request->input('type', 'item');
        $type = in_array($type, ['item', 'project'], true) ? $type : 'item';

        $user = $request->user();
        $canUpdateItem = $user?->hasAnyRole(['Admin', 'SuperAdmin', 'Finance']) ?? false;
        $canUpdateProject = $user?->hasAnyRole(['Admin', 'SuperAdmin', 'PM']) ?? false;

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

        $data = $request->validate([
            'labor_unit_cost' => ['required', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:255'],
        ]);

        if ($type === 'project') {
            $rate = ProjectItemLaborRate::firstOrNew(['project_item_id' => $item->id]);
            $rate->labor_unit_cost = $data['labor_unit_cost'];
            $rate->notes = $data['notes'] ?? null;
            $rate->updated_by = $user?->id;
            $rate->save();
        } else {
            $rate = ItemLaborRate::firstOrNew(['item_id' => $item->id]);
            $rate->labor_unit_cost = $data['labor_unit_cost'];
            $rate->notes = $data['notes'] ?? null;
            $rate->updated_by = $user?->id;
            $rate->save();
        }

        return redirect()
            ->route('projects.labor.index', ['type' => $type, 'q' => $request->input('q')])
            ->with('success', 'Labor master tersimpan.');
    }
}
