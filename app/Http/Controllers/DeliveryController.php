<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\Customer;
use App\Models\Delivery;
use App\Models\Invoice;
use App\Models\Item;
use App\Models\ItemStock;
use App\Models\ItemVariant;
use App\Models\SalesOrder;
use App\Models\SalesOrderLine;
use App\Models\QuotationLine;
use App\Models\Warehouse;
use App\Models\StockLedger;
use App\Services\StockService;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DeliveryController extends Controller
{
    public function index(Request $request)
    {
        $this->authorizePermission('deliveries.view');

        $query = Delivery::query()
            ->with(['customer:id,name', 'warehouse:id,name', 'company:id,name,alias'])
            ->latest('date')
            ->latest('id');

        if ($customerId = $request->integer('customer_id')) {
            $query->where('customer_id', $customerId);
        }
        if ($warehouseId = $request->integer('warehouse_id')) {
            $query->where('warehouse_id', $warehouseId);
        }
        if ($status = $request->string('status')->toString()) {
            $query->status($status);
        }
        if ($number = trim((string) $request->input('number'))) {
            $query->where('number', 'like', "%{$number}%");
        }
        if ($reference = trim((string) $request->input('reference'))) {
            $query->where('reference', 'like', "%{$reference}%");
        }
        if ($from = $request->date('date_from')) {
            $query->whereDate('date', '>=', $from);
        }
        if ($to = $request->date('date_to')) {
            $query->whereDate('date', '<=', $to);
        }

        $deliveries = $query->paginate(20)->withQueryString();

        $customers = Customer::orderBy('name')->get(['id', 'name']);
        $warehouses = Warehouse::orderBy('name')->get(['id', 'name']);
        $statuses = [
            Delivery::STATUS_DRAFT,
            Delivery::STATUS_POSTED,
            Delivery::STATUS_CANCELLED,
        ];

        return view('deliveries.index', compact(
            'deliveries', 'customers', 'warehouses', 'statuses'
        ))
        ->with([
            'filters' => $request->only([
                'customer_id', 'warehouse_id', 'status', 'number', 'reference', 'date_from', 'date_to',
            ]),
        ]);
    }

    public function create(Request $request)
    {
        $this->authorizePermission('deliveries.create');

        $delivery = new Delivery([
            'status' => Delivery::STATUS_DRAFT,
            'date'   => Carbon::now()->format('Y-m-d'),
        ]);

        $initialLines = collect();
        if ($salesOrderId = $request->integer('sales_order_id')) {
            $salesOrder = SalesOrder::with(['customer', 'company', 'lines.item', 'lines.variant'])->findOrFail($salesOrderId);
            // … set header fields seperti biasa …
            $initialLines = $this->buildLinesFromSalesOrder($salesOrder->lines);
            // Cegah pembuatan Delivery Note jika tidak ada item tersisa
            if ($initialLines->isEmpty()) {
                return redirect()->route('sales-orders.show', $salesOrder)
                    ->with('info', 'Semua item pada Sales Order ini telah terkirim. Tidak dapat membuat Delivery Note baru.');
            }
        }
        if ($invoiceId = $request->integer('invoice_id')) {
            $invoice = Invoice::with(['customer', 'company', 'quotation.lines'])->findOrFail($invoiceId);
            $delivery->fill([
                'company_id'   => $invoice->company_id,
                'customer_id'  => $invoice->customer_id,
                'invoice_id'   => $invoice->id,
                'quotation_id' => $invoice->quotation_id,
                'reference'    => $invoice->number,
                'date'         => $invoice->date?->format('Y-m-d') ?? Carbon::now()->format('Y-m-d'),
                'recipient'    => $invoice->customer->name ?? null,
                'address'      => $invoice->customer->address ?? null,
            ]);
            $delivery->setRelation('invoice', $invoice);
            $initialLines = $this->buildLinesFromQuotation($invoice->quotation?->lines);
        } elseif ($salesOrderId = $request->integer('sales_order_id')) {
            $salesOrder = SalesOrder::with(['customer', 'company', 'lines.item', 'lines.variant'])->findOrFail($salesOrderId);
            $delivery->fill([
                'company_id'     => $salesOrder->company_id,
                'customer_id'    => $salesOrder->customer_id,
                'quotation_id'   => $salesOrder->quotation_id,
                'sales_order_id' => $salesOrder->id,
                'reference'      => $salesOrder->so_number ?? $salesOrder->customer_po_number,
                'date'           => Carbon::now()->format('Y-m-d'),
                'recipient'      => $salesOrder->customer->name ?? null,
                'address'        => $salesOrder->ship_to ?: ($salesOrder->customer->address ?? null),
                'notes'          => $salesOrder->notes,
            ]);
            if (!empty($salesOrder->brand_snapshot)) {
                $delivery->brand_snapshot = $salesOrder->brand_snapshot;
            }
            $delivery->setRelation('salesOrder', $salesOrder);
            $initialLines = $this->buildLinesFromSalesOrder($salesOrder->lines);
        }

        $formPayload = $this->formPayload($delivery, $initialLines);

        return view('deliveries.create', $formPayload);
    }

    public function store(Request $request)
    {
        $this->authorizePermission('deliveries.create');

        $data = $this->validateDelivery($request);

        $delivery = DB::transaction(function () use ($data) {
            $company = Company::findOrFail($data['company_id']);
            $delivery = Delivery::create([
                'company_id'   => $company->id,
                'customer_id'  => $data['customer_id'],
                'warehouse_id' => $data['warehouse_id'] ?? null,
                'invoice_id'   => $data['invoice_id'] ?? null,
                'quotation_id' => $data['quotation_id'] ?? null,
                'sales_order_id' => $data['sales_order_id'] ?? null,
                'sales_order_id' => $data['sales_order_id'] ?? null,
                'status'       => Delivery::STATUS_DRAFT,
                'date'         => $data['date'],
                'reference'    => $data['reference'] ?? null,
                'recipient'    => $data['recipient'] ?? null,
                'address'      => $data['address'] ?? null,
                'notes'        => $data['notes'] ?? null,
                'brand_snapshot'=> $this->snapshotCompany($company),
                'created_by'   => auth()->id(),
            ]);

            $this->syncLines($delivery, collect($data['lines']));

            return $delivery;
        });

        return redirect()->route('deliveries.edit', $delivery)
            ->with('success', 'Delivery draft created.');
    }

    public function edit(Delivery $delivery)
    {
        $this->authorizePermission('deliveries.update');
        $delivery->load(['lines.item', 'lines.variant', 'customer', 'company', 'salesOrder']);

        if (!$delivery->is_editable) {
            return redirect()->route('deliveries.show', $delivery)
                ->with('info', 'Delivery sudah tidak bisa diedit.');
        }

        $formPayload = $this->formPayload($delivery, $delivery->lines);

        return view('deliveries.edit', $formPayload);
    }

    public function update(Request $request, Delivery $delivery)
    {
        $this->authorizePermission('deliveries.update');

        if (!$delivery->is_editable) {
            return redirect()->route('deliveries.show', $delivery)
                ->with('info', 'Delivery sudah tidak bisa diedit.');
        }

        $data = $this->validateDelivery($request);

        DB::transaction(function () use ($delivery, $data) {
            $company = Company::findOrFail($data['company_id']);

            $delivery->forceFill([
                'company_id'   => $company->id,
                'customer_id'  => $data['customer_id'],
                'warehouse_id' => $data['warehouse_id'] ?? null,
                'invoice_id'   => $data['invoice_id'] ?? null,
                'quotation_id' => $data['quotation_id'] ?? null,
                'date'         => $data['date'],
                'reference'    => $data['reference'] ?? null,
                'recipient'    => $data['recipient'] ?? null,
                'address'      => $data['address'] ?? null,
                'notes'        => $data['notes'] ?? null,
                'brand_snapshot'=> $this->snapshotCompany($company),
            ])->save();

            $this->syncLines($delivery, collect($data['lines']));
        });

        return redirect()->route('deliveries.edit', $delivery)
            ->with('success', 'Delivery draft updated.');
    }

    public function show(Delivery $delivery)
    {
        $this->authorizePermission('deliveries.view');

        $delivery->load(['company', 'customer', 'warehouse', 'lines.item', 'lines.variant', 'invoice', 'quotation', 'salesOrder']);

        $currentStocks = collect();
        if ($delivery->warehouse_id) {
            $stockRows = ItemStock::query()
                ->where('warehouse_id', $delivery->warehouse_id)
                ->whereIn('item_id', $delivery->lines->pluck('item_id')->filter())
                ->get(['item_id', 'item_variant_id', 'qty_on_hand']);

            $currentStocks = $stockRows->keyBy(fn ($row) => $row->item_id.'-'.($row->item_variant_id ?? '0'));
        }

        $ledgerEntries = StockLedger::with(['item:id,name', 'variant:id,item_id,sku,attributes', 'warehouse:id,name'])
            ->where('reference_id', $delivery->id)
            ->whereIn('reference_type', ['delivery', 'delivery_cancel'])
            ->orderBy('ledger_date')
            ->orderBy('id')
            ->get();

        return view('deliveries.show', [
            'delivery'      => $delivery,
            'currentStocks' => $currentStocks,
            'ledgerEntries' => $ledgerEntries,
        ]);
    }

    public function destroy(Delivery $delivery)
    {
        $this->authorizePermission('deliveries.delete');

        if (!$delivery->is_editable) {
            return redirect()->route('deliveries.show', $delivery)
                ->with('info', 'Delivery sudah tidak bisa dihapus.');
        }

        $delivery->delete();

        return redirect()->route('deliveries.index')->with('success', 'Delivery draft removed.');
    }

    public function post(Request $request, Delivery $delivery)
    {
        $this->authorizePermission('deliveries.post');

        if (!$delivery->warehouse_id) {
            throw ValidationException::withMessages([
                'warehouse_id' => 'Warehouse wajib diisi sebelum posting.',
            ]);
        }

        if ($delivery->lines()->count() === 0) {
            return redirect()->route('deliveries.edit', $delivery)
                ->with('error', 'Tidak bisa posting delivery tanpa item.');
        }

        StockService::postDelivery($delivery, auth()->id());

        return redirect()->route('deliveries.show', $delivery)
            ->with('success', 'Delivery berhasil diposting.');
    }

    public function cancel(Request $request, Delivery $delivery)
    {
        $this->authorizePermission('deliveries.cancel');

        $request->validate([
            'reason' => 'nullable|string|max:500',
        ]);

        StockService::cancelDelivery($delivery, auth()->id(), $request->input('reason'));

        return redirect()->route('deliveries.show', $delivery)
            ->with('success', 'Delivery telah dibatalkan.');
    }

    public function pdf(Delivery $delivery)
    {
        $this->authorizePermission('deliveries.view');
        $delivery->load(['company', 'customer', 'warehouse', 'lines.item', 'lines.variant']);

        $options = new Options();
        $options->set('isRemoteEnabled', true);
        $pdf = new Dompdf($options);

        $html = view('deliveries.pdf', ['delivery' => $delivery])->render();
        $pdf->loadHtml($html);
        $pdf->setPaper('A4', 'portrait');
        $pdf->render();

        $filename = 'delivery-'.($delivery->number ?: $delivery->id).'.pdf';

        return response($pdf->output(), 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$filename.'"',
        ]);
    }

    public function storeFromInvoice(Request $request, Invoice $invoice)
    {
        $this->authorizePermission('deliveries.create');

        $delivery = DB::transaction(function () use ($invoice) {
            $delivery = Delivery::create([
                'company_id'   => $invoice->company_id,
                'customer_id'  => $invoice->customer_id,
                'invoice_id'   => $invoice->id,
                'quotation_id' => $invoice->quotation_id,
                'status'       => Delivery::STATUS_DRAFT,
                'date'         => $invoice->date ?? Carbon::now(),
                'reference'    => $invoice->number,
                'recipient'    => $invoice->customer->name ?? null,
                'address'      => $invoice->customer->address ?? null,
                'brand_snapshot'=> $invoice->brand_snapshot,
                'created_by'   => auth()->id(),
            ]);

            if ($invoice->quotation?->lines) {
                $this->syncLines($delivery, $this->buildLinesFromQuotation($invoice->quotation->lines));
            }

            return $delivery;
        });

        return redirect()->route('deliveries.edit', $delivery)
            ->with('success', 'Delivery draft dibuat dari invoice. Lengkapi detail & warehouse sebelum posting.');
    }

    private function authorizePermission(string $permission): void
    {
        $user = auth()->user();
        if (!$user || !$user->can($permission)) {
            abort(403);
        }
    }

    private function formPayload(Delivery $delivery, Collection $lines): array
    {
        $companies = Company::orderBy('name')->get(['id', 'name', 'alias']);
        $customers = Customer::orderBy('name')->get(['id', 'name']);
        $warehouses = Warehouse::orderBy('name')->get(['id', 'name', 'allow_negative_stock']);
        $items = Item::with('unit:id,code')->orderBy('name')->get(['id', 'name', 'unit_id']);
        $variants = ItemVariant::with('item:id,name,variant_type,name_template')
            ->orderBy('sku')
            ->get(['id', 'item_id', 'sku', 'attributes']);
        $stocks = ItemStock::select('warehouse_id', 'item_id', 'item_variant_id', 'qty_on_hand')
            ->get()
            ->mapWithKeys(fn ($row) => [
                ($row->warehouse_id.'::'.$row->item_id.'::'.($row->item_variant_id ?? 0)) => (float) $row->qty_on_hand,
            ]);

        return [
            'delivery'   => $delivery,
            'lines'      => $lines,
            'companies'  => $companies,
            'customers'  => $customers,
            'warehouses' => $warehouses,
            'items'      => $items,
            'variants'   => $variants,
            'stocks'     => $stocks->toArray(),
        ];
    }

    private function validateDelivery(Request $request): array
    {
        $validated = $request->validate([
            'company_id'   => 'required|exists:companies,id',
            'customer_id'  => 'required|exists:customers,id',
            'warehouse_id' => 'nullable|exists:warehouses,id',
            'invoice_id'   => 'nullable|exists:invoices,id',
            'quotation_id' => 'nullable|exists:quotations,id',
            'sales_order_id' => 'nullable|exists:sales_orders,id',
            'date'         => 'required|date',
            'reference'    => 'nullable|string|max:120',
            'recipient'    => 'nullable|string|max:200',
            'address'      => 'nullable|string',
            'notes'        => 'nullable|string',
            'lines'        => 'required|array|min:1',
            'lines.*.item_id'         => 'nullable|exists:items,id',
            'lines.*.item_variant_id' => 'nullable|exists:item_variants,id',
            'lines.*.sales_order_line_id' => 'nullable|exists:sales_order_lines,id',
            'lines.*.description'     => 'nullable|string|max:255',
            'lines.*.unit'            => 'nullable|string|max:50',
            'lines.*.qty'             => 'required|numeric|min:0.0001',
            'lines.*.qty_requested'   => 'nullable|numeric|min:0',
            'lines.*.price_snapshot'  => 'nullable|numeric|min:0',
            'lines.*.line_notes'      => 'nullable|string',
            'lines.*.quotation_line_id' => 'nullable|exists:quotation_lines,id',
        ]);

        $lines = collect($validated['lines'])
            ->map(function (array $line) {
                $line['qty'] = (float) $line['qty'];
                $line['qty_requested'] = isset($line['qty_requested']) ? (float) $line['qty_requested'] : null;
                $line['price_snapshot'] = isset($line['price_snapshot']) ? (float) $line['price_snapshot'] : null;
                return $line;
            })
            ->filter(fn ($line) => $line['qty'] > 0)
            ->values();

        if ($lines->isEmpty()) {
            throw ValidationException::withMessages([
                'lines' => 'Minimal satu item delivery dengan qty > 0.',
            ]);
        }

        $variantIds = $lines->pluck('item_variant_id')->filter()->unique();
        if ($variantIds->isNotEmpty()) {
            $variants = ItemVariant::whereIn('id', $variantIds)
                ->get(['id', 'item_id'])
                ->keyBy('id');
            $lines->each(function (array $line, int $index) use ($variants) {
                if (!$line['item_variant_id']) {
                    return;
                }
                $variant = $variants->get($line['item_variant_id']);
                if (!$variant || (int) $variant->item_id !== (int) $line['item_id']) {
                    throw ValidationException::withMessages([
                        "lines.$index.item_variant_id" => 'Varian tidak sesuai dengan item.',
                    ]);
                }
            });
        }

        $salesOrderId = $validated['sales_order_id'] ?? null;
        $salesOrderLineIds = $lines->pluck('sales_order_line_id')->filter()->unique();
        if ($salesOrderLineIds->isNotEmpty()) {
            $salesOrderMap = DB::table('sales_order_lines')
                ->whereIn('id', $salesOrderLineIds)
                ->pluck('sales_order_id', 'id');

            if ($salesOrderMap->count() !== $salesOrderLineIds->count()) {
                throw ValidationException::withMessages([
                    'lines' => 'Baris sales order tidak ditemukan.',
                ]);
            }

            $resolvedSalesOrderIds = $salesOrderMap->filter()->unique();
            if ($salesOrderId) {
                if ($resolvedSalesOrderIds->count() > 1 || ($resolvedSalesOrderIds->count() === 1 && (int) $resolvedSalesOrderIds->first() !== (int) $salesOrderId)) {
                    throw ValidationException::withMessages([
                        'sales_order_id' => 'Sales order line tidak sesuai dengan header.',
                    ]);
                }
            } elseif ($resolvedSalesOrderIds->count() === 1) {
                $salesOrderId = (int) $resolvedSalesOrderIds->first();
            } elseif ($resolvedSalesOrderIds->count() > 1) {
                throw ValidationException::withMessages([
                    'sales_order_id' => 'Baris delivery berasal dari lebih dari satu sales order.',
                ]);
            }
        }

        $validated['sales_order_id'] = $salesOrderId;

        $validated['lines'] = $lines->all();

        return $validated;

    }

    private function syncLines(Delivery $delivery, Collection $rawLines): void
    {
        $itemIds = $rawLines->pluck('item_id')->filter()->unique();
        $variantIds = $rawLines->pluck('item_variant_id')->filter()->unique();

        $items = Item::whereIn('id', $itemIds)->get(['id', 'name'])->keyBy('id');
        $variants = ItemVariant::whereIn('id', $variantIds)
            ->with('item:id,name,variant_type,name_template')
            ->get(['id', 'item_id', 'sku', 'attributes'])
            ->keyBy('id');

        $delivery->lines()->delete();

        $rawLines->each(function (array $line) use ($delivery, $items, $variants) {
            $item = $line['item_id'] ? $items->get($line['item_id']) : null;
            $variant = $line['item_variant_id'] ? $variants->get($line['item_variant_id']) : null;

            $description = $line['description'] ?? ($variant->name ?? $item->name ?? '');
            $qtyRequested = $line['qty_requested'] ?? $line['qty'];
            $qtyBackordered = $line['qty_backordered'] ?? max(0, $qtyRequested - $line['qty']);

            $delivery->lines()->create([
                'quotation_line_id' => $line['quotation_line_id'] ?? null,
                'sales_order_line_id' => $line['sales_order_line_id'] ?? null,
                'item_id'           => $line['item_id'] ?? null,
                'item_variant_id'   => $line['item_variant_id'] ?? null,
                'description'       => $description,
                'unit'              => $line['unit'] ?? null,
                'qty'               => $line['qty'],
                'qty_requested'     => $qtyRequested,
                'price_snapshot'    => $line['price_snapshot'] ?? null,
                'qty_backordered'   => $qtyBackordered,
                'line_notes'        => $line['line_notes'] ?? null,
            ]);
        });
    }

    private function snapshotCompany(Company $company): array
    {
        return [
            'name'    => $company->name,
            'alias'   => $company->alias,
            'address' => $company->address,
            'tax_id'  => $company->tax_id,
            'phone'   => $company->phone,
            'email'   => $company->email,
            'logo'    => $company->logo_path,
        ];
    }

    private function buildLinesFromQuotation(?Collection $quotationLines): Collection
    {
        return collect($quotationLines)->map(function (QuotationLine $line) {
            $delivered = (float) ($line->qty_delivered ?? 0);
            $remaining = max(0, (float) $line->qty - $delivered);

            return [
                'quotation_line_id' => $line->id,
                'sales_order_line_id' => null,
                'item_id'           => $line->item_id,
                'item_variant_id'   => $line->item_variant_id,
                'description'       => $line->description ?? $line->name,
                'unit'              => $line->unit,
                'qty_requested'     => (float) $line->qty,
                'qty'               => $remaining > 0 ? $remaining : (float) $line->qty,
                'price_snapshot'    => $line->unit_price,
                'qty_backordered'   => max(0, (float) $line->qty - ($remaining > 0 ? $remaining : (float) $line->qty)),
            ];
        });
    }

    private function buildLinesFromSalesOrder(?Collection $orderLines): Collection
    {
        return collect($orderLines)
            ->map(function (SalesOrderLine $line) {
                $ordered = (float) ($line->qty_ordered ?? 0);
                $delivered = (float) ($line->qty_delivered ?? 0);
                $remaining = max(0.0, $ordered - $delivered);
                if ($ordered <= 0 || $remaining <= 0) {
                    return null;
                }

                return [
                    'quotation_line_id' => $line->quotation_line_id ?? null,
                    'sales_order_line_id' => $line->id,
                    'item_id'           => $line->item_id,
                    'item_variant_id'   => $line->item_variant_id,
                    'description'       => $line->description ?? $line->name,
                    'unit'              => $line->unit,
                    'qty_requested'     => $ordered,
                    'qty'               => $remaining,
                    'price_snapshot'    => $line->unit_price,
                    'qty_backordered'   => max(0.0, $ordered - $remaining),
                ];
            })
            ->filter()
            ->values();
    }
}
