<?php

namespace App\Http\Controllers;

use App\Models\{PurchaseOrder, PurchaseOrderLine, GoodsReceipt, GoodsReceiptLine, Item, ItemVariant, SalesOrderLine, Supplier, TermOfPayment};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\Company;
use App\Models\Warehouse;
use App\Services\DocNumberService;
use App\Services\PurchasePriceSyncService;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Carbon;
use Dompdf\Dompdf;
use Dompdf\Options;

class PurchaseOrderController extends Controller
{
    public function __construct(
        private readonly PurchasePriceSyncService $purchasePriceSyncService
    ) {
    }

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
            ->withExists(['goodsReceipts as has_goods_receipts'])
            ->withSum('lines as qty_received_sum', 'qty_received')
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
        $companies = Company::orderBy('name')->get(['id','name','alias','is_taxable','default_tax_percent']);
        $warehouses = Warehouse::orderBy('name')->get(['id','name']);
        $suppliers = Supplier::orderBy('name')->get(['id','name','is_active','default_billing_terms']);
        $topOptions = TermOfPayment::query()
            ->whereIn('code', TermOfPayment::ALLOWED_CODES)
            ->orderBy('code')
            ->get(['code','description','is_active','applicable_to']);
        $defaultCompanyId = old('company_id')
            ?: Company::where('is_default', true)->value('id')
            ?: (auth()->user()->company_id ?? null);
        $billingTermsData = old('billing_terms', [
            ['top_code' => 'FINISH', 'percent' => 100, 'due_trigger' => 'on_invoice'],
        ]);

        return view('po.create', compact('companies', 'warehouses', 'suppliers', 'topOptions', 'defaultCompanyId', 'billingTermsData'));
    }

    public function edit(PurchaseOrder $po)
    {
        $po->load(['lines.item:id,sku,name','lines.variant:id,item_id,sku,attributes','billingTerms']);

        if (!in_array($po->status, ['draft', 'approved'], true)) {
            return redirect()->route('po.show', $po)->with('error', 'PO pada status ini tidak dapat diedit.');
        }

        if ($po->lines->contains(fn ($line) => (float) ($line->qty_received ?? 0) > 0)) {
            return redirect()->route('po.show', $po)->with('error', 'PO yang sudah menerima barang tidak bisa diedit.');
        }

        if (GoodsReceipt::query()->where('purchase_order_id', $po->id)->exists()) {
            return redirect()->route('po.show', $po)->with('error', 'PO yang sudah memiliki draft/pencatatan receiving tidak bisa diedit.');
        }

        $companies = Company::orderBy('name')->get(['id','name','alias','is_taxable','default_tax_percent']);
        $warehouses = Warehouse::orderBy('name')->get(['id','name']);
        $suppliers = Supplier::orderBy('name')->get(['id','name','is_active','default_billing_terms']);
        $topOptions = TermOfPayment::query()
            ->whereIn('code', TermOfPayment::ALLOWED_CODES)
            ->orderBy('code')
            ->get(['code','description','is_active','applicable_to']);

        $defaultCompanyId = old('company_id', $po->company_id);

        $billingTermsData = old('billing_terms');
        if (!is_array($billingTermsData) || count($billingTermsData) < 1) {
            $billingTermsData = $po->billingTerms->map(function ($term) {
                return [
                    'top_code' => $term->top_code,
                    'percent' => (float) $term->percent,
                    'due_trigger' => $term->due_trigger,
                    'offset_days' => $term->offset_days,
                    'day_of_month' => $term->day_of_month,
                    'note' => $term->note,
                ];
            })->values()->all();
        }

        if (!is_array($billingTermsData) || count($billingTermsData) < 1) {
            $billingTermsData = [
                ['top_code' => 'FINISH', 'percent' => 100, 'due_trigger' => 'on_invoice'],
            ];
        }

        $linesData = old('lines');
        if (!is_array($linesData) || count($linesData) < 1) {
            $linesData = $po->lines->map(function ($line) {
                $itemSku = $line->sku_snapshot ?: ($line->variant->sku ?? ($line->item->sku ?? ''));
                $itemName = $line->item_name_snapshot ?: ($line->item->name ?? '');
                if ($line->item_variant_id && $line->variant && $line->item) {
                    $variantAttrs = is_array($line->variant->attributes) ? $line->variant->attributes : [];
                    $variantLabel = trim((string) $line->item->renderVariantDisplayName($variantAttrs, $line->variant->sku));
                    $parentName = trim((string) ($line->item->name ?? ''));
                    if ($variantLabel !== '' && ($itemName === '' || strcasecmp(trim((string) $itemName), $parentName) === 0)) {
                        $itemName = $variantLabel;
                    }
                }

                return [
                    'item_id' => $line->item_id,
                    'item_variant_id' => $line->item_variant_id,
                    'sales_order_line_id' => $line->sales_order_line_id,
                    'item_label' => trim($itemSku . ' - ' . $itemName, ' -'),
                    'qty_ordered' => (float) $line->qty_ordered,
                    'uom' => $line->uom,
                    'unit_price' => (float) ($line->unit_price ?? 0),
                ];
            })->values()->all();
        } else {
            $itemIds = collect($linesData)->pluck('item_id')->filter()->unique()->values()->all();
            $variantIds = collect($linesData)->pluck('item_variant_id')->filter()->unique()->values()->all();
            $items = Item::query()->whereIn('id', $itemIds)->get(['id', 'sku', 'name'])->keyBy('id');
            $variants = ItemVariant::query()->whereIn('id', $variantIds)->get(['id', 'item_id', 'sku', 'attributes'])->keyBy('id');
            foreach ($linesData as &$line) {
                if (!isset($line['item_label']) || trim((string) $line['item_label']) === '') {
                    $item = $items->get((int) ($line['item_id'] ?? 0));
                    $variant = $variants->get((int) ($line['item_variant_id'] ?? 0));

                    if ($item) {
                        $itemSku = $item->sku ?? '';
                        $itemName = $item->name ?? '';

                        if ($variant && (int) $variant->item_id === (int) $item->id) {
                            $variantAttrs = is_array($variant->attributes) ? $variant->attributes : [];
                            $variantLabel = trim((string) $item->renderVariantDisplayName($variantAttrs, $variant->sku));
                            if ($variantLabel !== '') {
                                $itemName = $variantLabel;
                            }
                            if (!empty($variant->sku)) {
                                $itemSku = (string) $variant->sku;
                            }
                        }

                        $line['item_label'] = trim($itemSku . ' - ' . $itemName, ' -');
                    }
                }
            }
            unset($line);
        }

        return view('po.edit', compact(
            'po',
            'companies',
            'warehouses',
            'suppliers',
            'topOptions',
            'defaultCompanyId',
            'billingTermsData',
            'linesData'
        ));
    }

    public function update(Request $r, PurchaseOrder $po)
    {
        $data = $r->validate([
            'company_id' => ['required','exists:companies,id'],
            'supplier_id' => ['required','exists:suppliers,id'],
            'warehouse_id' => ['nullable','exists:warehouses,id'],
            'order_date' => ['nullable','date'],
            'notes' => ['nullable','string'],
            'lines' => ['required','array','min:1'],
            'lines.*.item_id' => ['required','exists:items,id'],
            'lines.*.item_variant_id' => ['nullable','exists:item_variants,id'],
            'lines.*.sales_order_line_id' => ['nullable','integer','exists:sales_order_lines,id'],
            'lines.*.qty_ordered' => ['required','numeric','min:0.0001'],
            'lines.*.uom' => ['nullable','string','max:16'],
            'lines.*.unit_price' => ['nullable','numeric','min:0'],
            'tax_mode' => ['nullable', Rule::in(['none','exclude','include'])],
            'tax_percent' => ['nullable','numeric','min:0','max:100'],
            'billing_terms' => ['required','array','min:1'],
            'billing_terms.*.top_code' => ['required','string','max:64'],
            'billing_terms.*.percent' => ['required','string'],
            'billing_terms.*.note' => ['nullable','string','max:190'],
            'billing_terms.*.due_trigger' => ['nullable', Rule::in(self::BILLING_DUE_TRIGGERS)],
            'billing_terms.*.offset_days' => ['nullable','integer','min:0'],
            'billing_terms.*.day_of_month' => ['nullable','integer','min:1','max:28'],
        ]);

        $billingTerms = $this->normalizeBillingTerms($data['billing_terms'] ?? []);
        $orderDate = $data['order_date'] ? Carbon::parse($data['order_date']) : now();

        $taxMode = $data['tax_mode'] ?? 'none';
        $taxPctInput = $this->toNumber($data['tax_percent'] ?? 0);
        $taxPct = max(min($taxPctInput, 100), 0.0);
        if ($taxMode === 'none') {
            $taxPct = 0.0;
        }

        $wasApproved = false;

        DB::transaction(function () use (&$wasApproved, $data, $billingTerms, $orderDate, $taxMode, $taxPct, $po) {
            $po = PurchaseOrder::query()->whereKey($po->id)->lockForUpdate()->firstOrFail();

            if (!in_array($po->status, ['draft', 'approved'], true)) {
                abort(400, 'PO pada status ini tidak dapat diedit.');
            }

            if ($po->lines()->where('qty_received', '>', 0)->exists()) {
                throw ValidationException::withMessages([
                    'lines' => 'PO yang sudah menerima barang tidak bisa diedit.',
                ]);
            }

            if (GoodsReceipt::query()->where('purchase_order_id', $po->id)->exists()) {
                throw ValidationException::withMessages([
                    'lines' => 'PO yang sudah memiliki draft/pencatatan receiving tidak bisa diedit.',
                ]);
            }

            $wasApproved = $po->status === 'approved';

            $po->update([
                'company_id' => $data['company_id'],
                'supplier_id' => $data['supplier_id'],
                'warehouse_id' => $data['warehouse_id'] ?? null,
                'order_date' => $orderDate->toDateString(),
                'notes' => $data['notes'] ?? null,
                'status' => $wasApproved ? 'draft' : $po->status,
                'approved_at' => $wasApproved ? null : $po->approved_at,
                'approved_by' => $wasApproved ? null : $po->approved_by,
            ]);

            $po->lines()->delete();
            $subtotal = 0.0;

            foreach ($data['lines'] ?? [] as $lineIndex => $ln) {
                $salesOrderLineId = !empty($ln['sales_order_line_id']) ? (int) $ln['sales_order_line_id'] : null;
                $sourceSoLine = $salesOrderLineId ? SalesOrderLine::query()->findOrFail($salesOrderLineId) : null;

                if ($sourceSoLine) {
                    if (empty($ln['item_id']) && !empty($sourceSoLine->item_id)) {
                        $ln['item_id'] = $sourceSoLine->item_id;
                    }
                    if (empty($ln['item_variant_id']) && !empty($sourceSoLine->item_variant_id)) {
                        $ln['item_variant_id'] = $sourceSoLine->item_variant_id;
                    }
                }

                $item = Item::findOrFail($ln['item_id']);
                $variantId = $ln['item_variant_id'] ?? null;
                if ($sourceSoLine && (int) ($sourceSoLine->item_id ?? 0) !== (int) $item->id) {
                    throw ValidationException::withMessages([
                        "lines.{$lineIndex}.sales_order_line_id" => 'SO line tidak sesuai dengan item PO.',
                    ]);
                }
                if ($sourceSoLine && $sourceSoLine->item_variant_id && (int) $sourceSoLine->item_variant_id !== (int) ($variantId ?? 0)) {
                    throw ValidationException::withMessages([
                        "lines.{$lineIndex}.item_variant_id" => 'Variant PO harus sama dengan variant pada SO line sumber.',
                    ]);
                }
                $qty = $this->toNumber($ln['qty_ordered'] ?? 0);
                $price = $this->toNumber($ln['unit_price'] ?? 0);
                $lineTotal = $price * $qty;
                $subtotal += $lineTotal;
                $snapshot = $this->resolveItemSnapshot($item, $variantId, $lineIndex);

                PurchaseOrderLine::create([
                    'purchase_order_id'  => $po->id,
                    'sales_order_line_id' => $salesOrderLineId,
                    'item_id'            => $item->id,
                    'item_variant_id'    => $variantId,
                    'item_name_snapshot' => $snapshot['item_name_snapshot'],
                    'sku_snapshot'       => $snapshot['sku_snapshot'],
                    'qty_ordered'        => $qty,
                    'uom'                => $ln['uom'] ?? null,
                    'unit_price'         => $price,
                    'line_total'         => $lineTotal,
                ]);
            }

            $taxAmount = 0.0;
            $total = $subtotal;
            if ($taxMode === 'exclude' && $taxPct > 0) {
                $taxAmount = round($subtotal * ($taxPct / 100), 2);
                $total = $subtotal + $taxAmount;
            } elseif ($taxMode === 'include' && $taxPct > 0) {
                $taxAmount = round($subtotal * ($taxPct / (100 + $taxPct)), 2);
                $total = $subtotal;
            }

            $po->update([
                'subtotal' => $subtotal,
                'discount_amount' => 0,
                'tax_percent' => $taxPct,
                'tax_amount' => $taxAmount,
                'total' => $total,
            ]);

            $po->billingTerms()->delete();
            if (!empty($billingTerms)) {
                $po->billingTerms()->createMany($billingTerms);
            }
        });

        $message = $wasApproved
            ? 'PO updated. Karena ada perubahan, status dikembalikan ke draft dan wajib approve ulang.'
            : 'PO updated.';

        return redirect()->route('po.show', $po)->with('success', $message);
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
            'lines.*.sales_order_line_id' => ['nullable','integer','exists:sales_order_lines,id'],
            'lines.*.qty_ordered' => ['required','numeric','min:0.0001'],
            'lines.*.uom' => ['nullable','string','max:16'],
            'lines.*.unit_price' => ['nullable','numeric','min:0'],
            'tax_mode' => ['nullable', Rule::in(['none','exclude','include'])],
            'tax_percent' => ['nullable','numeric','min:0','max:100'],
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

        $taxMode = $data['tax_mode'] ?? 'none';
        $taxPctInput = $this->toNumber($data['tax_percent'] ?? 0);
        $taxPct = max(min($taxPctInput, 100), 0.0);
        if ($taxMode === 'none') {
            $taxPct = 0.0;
        }

        $po = DB::transaction(function () use ($data, $billingTerms, $company, $orderDate, $number, $taxMode, $taxPct) {
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
            foreach ($data['lines'] ?? [] as $lineIndex => $ln) {
                $salesOrderLineId = !empty($ln['sales_order_line_id']) ? (int) $ln['sales_order_line_id'] : null;
                $sourceSoLine = $salesOrderLineId ? SalesOrderLine::query()->findOrFail($salesOrderLineId) : null;

                if ($sourceSoLine) {
                    if (empty($ln['item_id']) && !empty($sourceSoLine->item_id)) {
                        $ln['item_id'] = $sourceSoLine->item_id;
                    }
                    if (empty($ln['item_variant_id']) && !empty($sourceSoLine->item_variant_id)) {
                        $ln['item_variant_id'] = $sourceSoLine->item_variant_id;
                    }
                }

                $item = Item::findOrFail($ln['item_id']);
                $variantId = $ln['item_variant_id'] ?? null;
                if ($sourceSoLine && (int) ($sourceSoLine->item_id ?? 0) !== (int) $item->id) {
                    throw ValidationException::withMessages([
                        "lines.{$lineIndex}.sales_order_line_id" => 'SO line tidak sesuai dengan item PO.',
                    ]);
                }
                if ($sourceSoLine && $sourceSoLine->item_variant_id && (int) $sourceSoLine->item_variant_id !== (int) ($variantId ?? 0)) {
                    throw ValidationException::withMessages([
                        "lines.{$lineIndex}.item_variant_id" => 'Variant PO harus sama dengan variant pada SO line sumber.',
                    ]);
                }
                $qty = $this->toNumber($ln['qty_ordered'] ?? 0);
                $price = $this->toNumber($ln['unit_price'] ?? 0);
                $lineTotal = $price * $qty;
                $subtotal += $lineTotal;
                $snapshot = $this->resolveItemSnapshot($item, $variantId, $lineIndex);

                PurchaseOrderLine::create([
                    'purchase_order_id'  => $po->id,
                    'sales_order_line_id' => $salesOrderLineId,
                    'item_id'            => $item->id,
                    'item_variant_id'    => $variantId,
                    'item_name_snapshot' => $snapshot['item_name_snapshot'],
                    'sku_snapshot'       => $snapshot['sku_snapshot'],
                    'qty_ordered'        => $qty,
                    'uom'                => $ln['uom'] ?? null,
                    'unit_price'         => $price,
                    'line_total'         => $lineTotal,
                ]);
            }

            $taxAmount = 0.0;
            $total = $subtotal;
            if ($taxMode === 'exclude' && $taxPct > 0) {
                $taxAmount = round($subtotal * ($taxPct / 100), 2);
                $total = $subtotal + $taxAmount;
            } elseif ($taxMode === 'include' && $taxPct > 0) {
                $taxAmount = round($subtotal * ($taxPct / (100 + $taxPct)), 2);
                $total = $subtotal;
            }

            $po->update([
                'subtotal' => $subtotal,
                'discount_amount' => 0,
                'tax_percent' => $taxPct,
                'tax_amount' => $taxAmount,
                'total' => $total,
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
        $syncStats = DB::transaction(function () use ($po) {
            $po->update(['status' => 'approved', 'approved_at' => now(), 'approved_by' => auth()->id()]);
            return $this->purchasePriceSyncService->syncFromApprovedPurchaseOrder($po);
        });

        return back()->with(
            'success',
            'PO approved. Harga beli tersinkron: '
            .($syncStats['updated_variants'] + $syncStats['updated_items'])
            .' line(s).'
        );
    }

    public function show(PurchaseOrder $po) {
        $po->load('lines.item','lines.variant','lines.salesOrderLine.salesOrder','billingTerms','supplier','company','warehouse');
        $hasGoodsReceipts = GoodsReceipt::query()->where('purchase_order_id', $po->id)->exists();
        return view('po.show', compact('po', 'hasGoodsReceipts'));
    }

    public function pdf(PurchaseOrder $po)
    {
        return $this->buildPdfResponse($po, false);
    }

    public function pdfDownload(PurchaseOrder $po)
    {
        return $this->buildPdfResponse($po, true);
    }

    private function buildPdfResponse(PurchaseOrder $po, bool $download)
    {
        $po->load('lines.item','lines.variant','billingTerms','supplier','company','warehouse');
        $html = view('po.pdf', compact('po'))->render();

        $opt = new Options();
        $opt->set('isRemoteEnabled', true);
        $opt->set('isHtml5ParserEnabled', true);

        $pdf = new Dompdf($opt);
        $pdf->loadHtml($html);
        $pdf->setPaper('A4', 'portrait');
        $pdf->render();

        $rawName = $po->status === 'draft'
            ? ('PO-DRAFT-' . $po->id)
            : ((string) ($po->number ?: ('PO-' . $po->id)));

        $safe = trim($rawName);
        $safe = str_replace(['/', '\\'], '-', $safe);
        $safe = preg_replace('/[^A-Za-z0-9._-]+/', '-', $safe);
        $safe = preg_replace('/-+/', '-', $safe);
        $safe = trim($safe, '-');
        $filename = ($safe !== '' ? $safe : 'purchase-order') . '.pdf';

        return response($pdf->output(), 200)
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', ($download ? 'attachment' : 'inline') . '; filename="' . $filename . '"');
    }

    /** Receive entry point: create a GR draft from PO lines (remaining qty) */
    public function receive(PurchaseOrder $po) {
        $po->load('lines.item','lines.variant');
        return view('po.receive', compact('po'));
    }

    /** Persist GR draft (not posted) */
    public function receiveStore(Request $r, PurchaseOrder $po) {
        abort_unless(in_array($po->status, ['approved', 'partial', 'partially_received'], true), 400, 'PO belum bisa diterima.');

        $data = $r->validate([
            'gr.number' => ['nullable', 'string', 'max:128'],
            'gr.gr_date' => ['nullable', 'date'],
            'gr.received_at' => ['nullable', 'date'],
            'gr.notes' => ['nullable', 'string'],
            'lines' => ['nullable', 'array'],
            'lines.*.qty_received' => ['nullable', 'numeric', 'min:0'],
            'lines.*.unit_cost' => ['nullable', 'numeric', 'min:0'],
        ]);

        $grData = $data['gr'] ?? [];
        $receivedAt = !empty($grData['received_at']) ? Carbon::parse($grData['received_at']) : now();
        $grDate = !empty($grData['gr_date'])
            ? Carbon::parse($grData['gr_date'])->toDateString()
            : $receivedAt->toDateString();

        $number = trim((string) ($grData['number'] ?? ''));
        if ($number === '') {
            $company = Company::findOrFail($po->company_id);
            $number = app(DocNumberService::class)->next('goods_receipt', $company, $receivedAt);
        }

        $gr = DB::transaction(function () use ($data, $po, $number, $grDate, $receivedAt, $grData) {
            $gr = GoodsReceipt::create([
                'company_id' => $po->company_id,
                'warehouse_id' => $po->warehouse_id,
                'purchase_order_id' => $po->id,
                'number' => $number,
                'gr_date' => $grDate,
                'received_at' => $receivedAt,
                'status' => 'draft',
                'notes' => $grData['notes'] ?? null,
            ]);

            foreach (($data['lines'] ?? []) as $poLineId => $ln) {
                $qtyReceived = $this->toNumber($ln['qty_received'] ?? 0);
                if ($qtyReceived <= 0) {
                    continue;
                }

                $line = $po->lines()->findOrFail((int) $poLineId);
                $item = Item::query()->find((int) $line->item_id);
                if ($item) {
                    $this->assertValidVariantSelection($item, $line->item_variant_id, $poLineId);
                }
                $remaining = max(0.0, (float) $line->qty_ordered - (float) ($line->qty_received ?? 0));
                if ($qtyReceived > $remaining + 0.000001) {
                    throw ValidationException::withMessages([
                        "lines.$poLineId.qty_received" => 'Qty receive melebihi remaining.',
                    ]);
                }

                $unitCost = $this->toNumber($ln['unit_cost'] ?? $line->unit_price);
                GoodsReceiptLine::create([
                    'goods_receipt_id' => $gr->id,
                    'item_id' => $line->item_id,
                    'item_variant_id' => $line->item_variant_id,
                    'item_name_snapshot' => $line->item_name_snapshot,
                    'sku_snapshot' => $line->sku_snapshot,
                    'qty_received' => $qtyReceived,
                    'uom' => $line->uom,
                    'unit_cost' => $unitCost,
                    'line_total' => $unitCost * $qtyReceived,
                ]);
            }

            return $gr;
        });

        return redirect()->route('gr.show', $gr);
    }

    /** Ubah "1.234,56" -> 1234.56; "1.234" -> 1234 ; null -> 0 */
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

    /**
     * Simpan snapshot nama/SKU berdasarkan item + variant terpilih.
     * Nama snapshot harus ikut varian agar tidak balik ke parent name.
     */
    private function resolveItemSnapshot(Item $item, $variantId, int $lineIndex): array
    {
        $skuSnapshot = (string) ($item->sku ?? '');
        $itemNameSnapshot = (string) ($item->name ?? '');

        $variant = $this->assertValidVariantSelection($item, $variantId, $lineIndex);
        if (!$variant) {
            return [
                'sku_snapshot' => $skuSnapshot,
                'item_name_snapshot' => $itemNameSnapshot,
            ];
        }

        $variantAttrs = is_array($variant->attributes) ? $variant->attributes : [];
        $variantLabel = trim((string) $item->renderVariantDisplayName($variantAttrs, $variant->sku));

        return [
            'sku_snapshot' => (string) ($variant->sku ?: $skuSnapshot),
            'item_name_snapshot' => $variantLabel !== '' ? $variantLabel : $itemNameSnapshot,
        ];
    }

    private function assertValidVariantSelection(Item $item, $variantId, $lineIndex): ?ItemVariant
    {
        $variantId = (int) ($variantId ?? 0);
        $hasActiveVariants = $item->activeVariants()->exists();

        if ($hasActiveVariants && $variantId <= 0) {
            throw ValidationException::withMessages([
                "lines.$lineIndex.item_variant_id" => 'Item ini memiliki varian aktif. Pilih varian yang sesuai.',
            ]);
        }

        if ($variantId <= 0) {
            return null;
        }

        $variant = ItemVariant::query()
            ->where('id', $variantId)
            ->where('item_id', (int) $item->id)
            ->first(['id', 'item_id', 'sku', 'attributes', 'is_active']);

        if (!$variant) {
            throw ValidationException::withMessages([
                "lines.$lineIndex.item_variant_id" => 'Variant tidak sesuai dengan item yang dipilih.',
            ]);
        }

        if ($hasActiveVariants && !$this->isVariantActiveValue($variant->is_active)) {
            throw ValidationException::withMessages([
                "lines.$lineIndex.item_variant_id" => 'Varian tidak aktif untuk transaksi baru.',
            ]);
        }

        return $variant;
    }

    private function isVariantActiveValue($isActive): bool
    {
        return $isActive === null || (bool) $isActive;
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

