<?php

namespace App\Http\Controllers;

use App\Models\{PurchaseOrder, PurchaseOrderLine, GoodsReceipt, GoodsReceiptLine, Item, ItemVariant, Supplier, TermOfPayment};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\Company;
use App\Models\Warehouse;
use App\Services\DocNumberService;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Carbon;

class PurchaseOrderController extends Controller
{
    private const BILLING_DUE_TRIGGERS = [
        'on_invoice',
        'after_invoice_days',
        'on_delivery',
        'after_delivery_days',
        'eom_day',
        'next_month_day',
        'on_so',
        'end_of_month',
    ];

    public function index(Request $request) {
        $pos = PurchaseOrder::withCount('lines')
            ->with([
                'supplier:id,name',
                'company:id,name,alias',
                'warehouse:id,name',
            ])
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('po.index', compact('pos'));
    }

    public function create(Request $request) {
        $companies = Company::orderBy('name')->get(['id','name','alias']);
        $warehouses = Warehouse::orderBy('name')->get(['id','name']);
        $suppliers = Supplier::orderBy('name')->get(['id','name','is_active']);
        $topOptions = TermOfPayment::query()
            ->whereIn('code', TermOfPayment::ALLOWED_CODES)
            ->orderBy('code')
            ->get(['code','description','is_active','applicable_to']);
        $defaultCompanyId = old('company_id') ?: (auth()->user()->company_id ?? null);
        $billingTermsData = old('billing_terms', [
            ['top_code' => 'FINISH', 'percent' => 100, 'due_trigger' => 'on_invoice'],
        ]);

        return view('po.create', compact('companies', 'warehouses', 'suppliers', 'topOptions', 'defaultCompanyId', 'billingTermsData'));
    }

    public function store(Request $r) {
        $data = $r->validate([
            'company_id' => ['required','exists:companies,id'],
            'supplier_id' => ['required','exists:suppliers,id'],
            'warehouse_id' => ['nullable','exists:warehouses,id'],
            'order_date' => ['nullable','date'],
            'notes' => ['nullable','string'],
            'lines' => ['required','array','min:1'],
            'lines.*.item_id' => ['required','exists:items,id'],
            'lines.*.item_variant_id' => ['nullable','exists:item_variants,id'],
            'lines.*.qty_ordered' => ['required','numeric','min:0.0001'],
            'lines.*.uom' => ['nullable','string','max:16'],
            'lines.*.unit_price' => ['nullable','numeric','min:0'],
            'billing_terms' => ['required','array','min:1'],
            'billing_terms.*.top_code' => ['required','string','max:64'],
            'billing_terms.*.percent' => ['required','string'],
            'billing_terms.*.note' => ['nullable','string','max:190'],
            'billing_terms.*.due_trigger' => ['nullable', Rule::in(self::BILLING_DUE_TRIGGERS)],
            'billing_terms.*.offset_days' => ['nullable','integer','min:0'],
            'billing_terms.*.day_of_month' => ['nullable','integer','min:1','max:28'],
        ]);

        $billingTerms = $this->normalizeBillingTerms($data['billing_terms'] ?? []);
        $company = Company::findOrFail($data['company_id']);
        $orderDate = $data['order_date'] ? Carbon::parse($data['order_date']) : now();
        $number = app(DocNumberService::class)->next('purchase_order', $company, $orderDate);

        $po = DB::transaction(function () use ($data, $billingTerms, $company, $orderDate, $number) {
            $po = PurchaseOrder::create([
                'company_id'  => $data['company_id'],
                'supplier_id' => $data['supplier_id'],
                'warehouse_id'=> $data['warehouse_id'] ?? null,
                'number'      => $number,
                'order_date'  => $orderDate->toDateString(),
                'status'      => 'draft',
                'purchase_type' => 'item',
                'notes'       => $data['notes'] ?? null,
            ]);

            $subtotal = 0;
            foreach ($data['lines'] ?? [] as $ln) {
                $item = Item::findOrFail($ln['item_id']);
                $variantId = $ln['item_variant_id'] ?? null;
                $qty = $this->toNumber($ln['qty_ordered'] ?? 0);
                $price = $this->toNumber($ln['unit_price'] ?? 0);
                $lineTotal = $price * $qty;
                $subtotal += $lineTotal;

                $variantSku = null;
                if ($variantId) {
                    $variantSku = ItemVariant::where('id', $variantId)->value('sku');
                }

                PurchaseOrderLine::create([
                    'purchase_order_id'  => $po->id,
                    'item_id'            => $item->id,
                    'item_variant_id'    => $variantId,
                    'item_name_snapshot' => $item->name,
                    'sku_snapshot'       => $variantSku ?: $item->sku,
                    'qty_ordered'        => $qty,
                    'uom'                => $ln['uom'] ?? null,
                    'unit_price'         => $price,
                    'line_total'         => $lineTotal,
                ]);
            }

            $po->update([
                'subtotal' => $subtotal,
                'discount_amount' => 0,
                'tax_percent' => 0,
                'tax_amount' => 0,
                'total' => $subtotal,
            ]);

            if (!empty($billingTerms)) {
                $po->billingTerms()->createMany($billingTerms);
            }

            return $po;
        });
        return redirect()->route('po.show', $po);
    }

    public function approve(PurchaseOrder $po) {
        abort_if($po->status !== 'draft', 400, 'Invalid state');
        $po->update(['status' => 'approved', 'approved_at' => now(), 'approved_by' => auth()->id()]);
        return back()->with('success', 'PO approved');
    }

    public function show(PurchaseOrder $po) {
        $po->load('lines.item','lines.variant','billingTerms');
        return view('po.show', compact('po'));
    }

    /** Receive entry point: create a GR draft from PO lines (remaining qty) */
    public function receive(PurchaseOrder $po) {
        $po->load('lines');
        return view('po.receive', compact('po'));
    }

    /** Persist GR draft (not posted) */
    public function receiveStore(Request $r, PurchaseOrder $po) {
        $gr = DB::transaction(function () use ($r, $po) {
            $gr = GoodsReceipt::create([
                'company_id'      => $po->company_id,
                'warehouse_id'    => $po->warehouse_id,
                'purchase_order_id'=> $po->id,
                'number'          => $r->number,
                'gr_date'         => $r->gr_date,
                'status'          => 'draft',
                'notes'           => $r->notes,
            ]);
            foreach ($r->lines ?? [] as $ln) {
                if (($ln['qty_received'] ?? 0) > 0) {
                    $line = $po->lines()->findOrFail($ln['po_line_id']);
                    GoodsReceiptLine::create([
                        'goods_receipt_id'  => $gr->id,
                        'item_id'           => $line->item_id,
                        'item_variant_id'   => $line->item_variant_id,
                        'item_name_snapshot'=> $line->item_name_snapshot,
                        'sku_snapshot'      => $line->sku_snapshot,
                        'qty_received'      => $ln['qty_received'],
                        'uom'               => $line->uom,
                        'unit_cost'         => $ln['unit_cost'] ?? $line->unit_price,
                        'line_total'        => ($ln['unit_cost'] ?? $line->unit_price) * $ln['qty_received'],
                    ]);
                }
            }
            return $gr;
        });
        return redirect()->route('gr.show', $gr);
    }

    /** Ubah "1.234,56" → 1234.56; "1.234" → 1234 ; null → 0 */
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

    private function normalizeBillingTerms(array $terms): array
    {
        $tops = TermOfPayment::query()
            ->whereIn('code', TermOfPayment::ALLOWED_CODES)
            ->get(['code']);
        $allowedMap = [];
        foreach ($tops as $top) {
            $allowedMap[strtoupper((string) $top->code)] = true;
        }

        $clean = [];
        $sum = 0.0;
        $seen = [];

        foreach ($terms as $idx => $term) {
            $code = strtoupper(trim((string) ($term['top_code'] ?? '')));
            if ($code === '') {
                continue;
            }
            if (!isset($allowedMap[$code])) {
                throw ValidationException::withMessages([
                    "billing_terms.$idx.top_code" => 'Kode TOP tidak valid.',
                ]);
            }
            if (isset($seen[$code])) {
                throw ValidationException::withMessages([
                    "billing_terms.$idx.top_code" => 'Kode TOP duplikat di Purchase Order.',
                ]);
            }
            $seen[$code] = true;

            $percent = $this->toNumber($term['percent'] ?? 0);
            if ($percent < 0) {
                throw ValidationException::withMessages([
                    "billing_terms.$idx.percent" => 'Percent tidak boleh negatif.',
                ]);
            }

            $sum += $percent;
            $note = trim((string) ($term['note'] ?? ''));
            $dueTrigger = trim((string) ($term['due_trigger'] ?? ''));
            if ($dueTrigger !== '' && !in_array($dueTrigger, self::BILLING_DUE_TRIGGERS, true)) {
                throw ValidationException::withMessages([
                    "billing_terms.$idx.due_trigger" => 'Schedule trigger tidak valid.',
                ]);
            }
            if ($dueTrigger === 'on_so') {
                $dueTrigger = 'on_invoice';
            } elseif ($dueTrigger === 'end_of_month') {
                $dueTrigger = 'next_month_day';
            }

            $offsetDays = $term['offset_days'] ?? null;
            $dayOfMonth = $term['day_of_month'] ?? null;
            $offsetDays = $offsetDays !== '' && $offsetDays !== null ? (int) $offsetDays : null;
            $dayOfMonth = $dayOfMonth !== '' && $dayOfMonth !== null ? (int) $dayOfMonth : null;

            if (in_array($dueTrigger, ['after_invoice_days', 'after_delivery_days'], true)) {
                if ($offsetDays === null) {
                    throw ValidationException::withMessages([
                        "billing_terms.$idx.offset_days" => 'Offset Days wajib diisi.',
                    ]);
                }
                if ($dayOfMonth !== null) {
                    throw ValidationException::withMessages([
                        "billing_terms.$idx.day_of_month" => 'Day of Month tidak boleh diisi untuk schedule ini.',
                    ]);
                }
            } elseif (in_array($dueTrigger, ['eom_day', 'next_month_day'], true)) {
                if ($dayOfMonth === null) {
                    throw ValidationException::withMessages([
                        "billing_terms.$idx.day_of_month" => 'Day of Month wajib diisi.',
                    ]);
                }
                if ($dayOfMonth < 1 || $dayOfMonth > 28) {
                    throw ValidationException::withMessages([
                        "billing_terms.$idx.day_of_month" => 'Day of Month harus 1-28.',
                    ]);
                }
                if ($offsetDays !== null) {
                    throw ValidationException::withMessages([
                        "billing_terms.$idx.offset_days" => 'Offset Days tidak boleh diisi untuk schedule ini.',
                    ]);
                }
            } elseif (in_array($dueTrigger, ['on_invoice', 'on_delivery'], true)) {
                if ($offsetDays !== null) {
                    throw ValidationException::withMessages([
                        "billing_terms.$idx.offset_days" => 'Offset Days tidak boleh diisi untuk schedule ini.',
                    ]);
                }
                if ($dayOfMonth !== null) {
                    throw ValidationException::withMessages([
                        "billing_terms.$idx.day_of_month" => 'Day of Month tidak boleh diisi untuk schedule ini.',
                    ]);
                }
            }

            $clean[] = [
                'seq' => $idx + 1,
                'top_code' => $code,
                'percent' => $percent,
                'note' => $note !== '' ? $note : null,
                'due_trigger' => $dueTrigger !== '' ? $dueTrigger : null,
                'offset_days' => $offsetDays,
                'day_of_month' => $dayOfMonth,
                'status' => 'planned',
            ];
        }

        if (count($clean) < 1) {
            throw ValidationException::withMessages([
                'billing_terms' => 'Billing terms wajib diisi.',
            ]);
        }

        if (abs($sum - 100) > 0.01) {
            throw ValidationException::withMessages([
                'billing_terms' => 'Total persentase TOP harus 100%.',
            ]);
        }

        return $clean;
    }
}
