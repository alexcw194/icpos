<?php

namespace App\Http\Controllers;

use App\Models\SalesOrder;
use App\Models\SalesOrderVariation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SalesOrderVariationController extends Controller
{
    public function create(SalesOrder $salesOrder)
    {
        $this->authorize('amend', $salesOrder);

        if ($salesOrder->status === 'cancelled') {
            abort(403, 'SO cancelled.');
        }

        return view('sales_orders.variations.create', [
            'salesOrder' => $salesOrder,
        ]);
    }

    public function store(Request $request, SalesOrder $salesOrder)
    {
        $this->authorize('amend', $salesOrder);

        if ($salesOrder->status === 'cancelled') {
            abort(403, 'SO cancelled.');
        }

        $data = $request->validate([
            'vo_date' => ['required', 'date'],
            'delta_amount' => ['required', 'string'],
            'reason' => ['nullable', 'string'],
        ]);

        $delta = $this->toNumber($data['delta_amount'] ?? 0);

        $variation = DB::transaction(function () use ($salesOrder, $data, $delta) {
            $nextSeq = (int) $salesOrder->variations()->lockForUpdate()->count() + 1;
            $voNumber = sprintf('VO-%s-%02d', $salesOrder->so_number, $nextSeq);

            return SalesOrderVariation::create([
                'sales_order_id' => $salesOrder->id,
                'vo_number' => $voNumber,
                'vo_date' => $data['vo_date'],
                'reason' => $data['reason'] ?? null,
                'delta_amount' => $delta,
                'status' => 'draft',
                'created_by' => auth()->id(),
            ]);
        });

        return redirect()
            ->route('sales-orders.show', $salesOrder)
            ->with('success', 'VO created: '.$variation->vo_number);
    }

    public function approve(SalesOrder $salesOrder, SalesOrderVariation $variation)
    {
        $this->authorize('amend', $salesOrder);

        if ((int) $variation->sales_order_id !== (int) $salesOrder->id) {
            abort(404);
        }

        if ($variation->status !== 'draft') {
            return back()->with('info', 'VO sudah diproses.');
        }

        $variation->update([
            'status' => 'approved',
        ]);

        return back()->with('success', 'VO approved.');
    }

    public function apply(SalesOrder $salesOrder, SalesOrderVariation $variation)
    {
        $this->authorize('amend', $salesOrder);

        if ((int) $variation->sales_order_id !== (int) $salesOrder->id) {
            abort(404);
        }

        if ($salesOrder->status === 'cancelled') {
            abort(422, 'SO cancelled.');
        }

        if ($variation->status !== 'approved') {
            return back()->with('info', 'VO harus approved dulu.');
        }

        DB::transaction(function () use ($salesOrder, $variation) {
            $current = (float) ($salesOrder->contract_value ?? $salesOrder->total ?? 0);
            $nextValue = $current + (float) $variation->delta_amount;
            if ($nextValue < 0) {
                abort(422, 'Contract value tidak boleh negatif.');
            }

            $salesOrder->update([
                'contract_value' => $nextValue,
            ]);

            $variation->update([
                'status' => 'applied',
            ]);
        });

        return back()->with('success', 'VO applied ke nilai kontrak.');
    }

    private function toNumber($val): float
    {
        if ($val === null || $val === '') return 0.0;
        if (is_numeric($val)) return (float) $val;
        $s = str_replace([' ', "\xc2\xa0"], '', (string) $val);
        if (str_contains($s, ',') && str_contains($s, '.')) {
            $s = str_replace('.', '', $s);
            $s = str_replace(',', '.', $s);
        } else {
            $s = str_replace(',', '.', $s);
        }
        return (float) $s;
    }
}
