<?php

namespace App\Http\Controllers;

use App\Models\BillingDocument;
use App\Models\BillingDocumentLine;
use App\Models\SalesOrder;
use App\Services\AutoDeliveryDraftFromSoService;
use App\Services\BillingInvoiceSyncService;
use App\Services\DocNumberService;
use App\Services\SalesOrderStatusSyncService;
use Carbon\Carbon;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BillingDocumentController extends Controller
{
    public function __construct(
        private readonly BillingInvoiceSyncService $billingInvoiceSync,
        private readonly AutoDeliveryDraftFromSoService $autoDeliveryDraftFromSo,
        private readonly SalesOrderStatusSyncService $salesOrderStatusSync
    ) {
    }

    public function show(BillingDocument $billing)
    {
        $this->authorizePermission('invoices.view');
        $billing->load(['company','customer','salesOrder','lines']);

        return view('billings.show', compact('billing'));
    }

    public function createFromSalesOrder(SalesOrder $salesOrder)
    {
        $this->authorizePermission('invoices.create');

        if ($salesOrder->status === 'cancelled') {
            abort(422, 'SO sudah cancelled.');
        }

        $existing = BillingDocument::query()
            ->where('sales_order_id', $salesOrder->id)
            ->whereIn('status', ['draft','sent'])
            ->orderByDesc('id')
            ->first();

        if ($existing) {
            return redirect()
                ->route('billings.show', $existing)
                ->with('ok', 'Billing Draft sudah ada.');
        }

        $salesOrder->load(['company','customer','lines']);

        $billing = BillingDocument::make([
            'sales_order_id' => $salesOrder->id,
            'company_id' => $salesOrder->company_id,
            'customer_id' => $salesOrder->customer_id,
            'status' => 'draft',
            'mode' => null,
            'subtotal' => (float) ($salesOrder->lines_subtotal ?? 0),
            'discount_amount' => (float) ($salesOrder->total_discount_amount ?? 0),
            'tax_percent' => (float) ($salesOrder->tax_percent ?? 0),
            'tax_amount' => (float) ($salesOrder->tax_amount ?? 0),
            'total' => (float) ($salesOrder->total ?? 0),
            'currency' => $salesOrder->currency ?? 'IDR',
            'notes' => $salesOrder->notes,
        ]);

        $lines = $salesOrder->lines->map(function ($ln, $idx) {
            return new BillingDocumentLine([
                'sales_order_line_id' => $ln->id,
                'position' => $ln->position ?? $idx + 1,
                'name' => $ln->name,
                'description' => $ln->po_item_name ?: $ln->description,
                'unit' => $ln->unit,
                'qty' => (float) ($ln->qty_ordered ?? 0),
                'unit_price' => (float) ($ln->unit_price ?? 0),
                'discount_type' => $ln->discount_type ?? 'amount',
                'discount_value' => (float) ($ln->discount_value ?? 0),
                'discount_amount' => (float) ($ln->discount_amount ?? 0),
                'line_subtotal' => (float) ($ln->line_subtotal ?? 0),
                'line_total' => (float) ($ln->line_total ?? 0),
            ]);
        });

        $billing->setRelation('company', $salesOrder->company);
        $billing->setRelation('customer', $salesOrder->customer);
        $billing->setRelation('salesOrder', $salesOrder);
        $billing->setRelation('lines', $lines);

        return view('billings.show', compact('billing'));
    }

    public function storeFromSalesOrder(Request $request, SalesOrder $salesOrder)
    {
        $this->authorizePermission('invoices.create');

        if ($salesOrder->status === 'cancelled') {
            abort(422, 'SO sudah cancelled.');
        }

        $existing = BillingDocument::query()
            ->where('sales_order_id', $salesOrder->id)
            ->whereIn('status', ['draft','sent'])
            ->orderByDesc('id')
            ->first();

        if ($existing) {
            return redirect()
                ->route('billings.show', $existing)
                ->with('ok', 'Billing Draft sudah ada.');
        }

        $data = $request->validate([
            'notes' => ['nullable','string'],
            'discount_amount' => ['nullable','string'],
            'tax_percent' => ['nullable','string'],
            'lines' => ['required','array','min:1'],
            'lines.*.name' => ['required','string'],
            'lines.*.description' => ['nullable','string'],
            'lines.*.qty' => ['required','string'],
            'lines.*.unit' => ['nullable','string','max:16'],
            'lines.*.unit_price' => ['required','string'],
            'lines.*.discount_type' => ['nullable','in:amount,percent'],
            'lines.*.discount_value' => ['nullable','string'],
            'lines.*.sales_order_line_id' => ['nullable','integer'],
        ]);

        $salesOrder->load(['company','customer']);

        $billing = DB::transaction(function () use ($salesOrder, $data) {
            $subtotal = 0.0;
            $lines = [];

            foreach ($data['lines'] as $idx => $row) {
                $qty = $this->toNumber($row['qty'] ?? 0);
                $unitPrice = $this->toNumber($row['unit_price'] ?? 0);
                $discType = $row['discount_type'] ?? 'amount';
                $discValue = $this->toNumber($row['discount_value'] ?? 0);

                $lineSubtotal = round($qty * $unitPrice, 2);
                if ($discType === 'percent') {
                    $discAmount = round($lineSubtotal * max(min($discValue, 100), 0) / 100, 2);
                } else {
                    $discAmount = min(max($discValue, 0), $lineSubtotal);
                }
                $lineTotal = round(max($lineSubtotal - $discAmount, 0), 2);

                $lines[] = [
                    'sales_order_line_id' => $row['sales_order_line_id'] ?? null,
                    'position' => $idx + 1,
                    'name' => $row['name'],
                    'description' => $row['description'] ?? null,
                    'unit' => $row['unit'] ?? null,
                    'qty' => $qty,
                    'unit_price' => $unitPrice,
                    'discount_type' => $discType,
                    'discount_value' => $discValue,
                    'discount_amount' => $discAmount,
                    'line_subtotal' => $lineSubtotal,
                    'line_total' => $lineTotal,
                ];

                $subtotal += $lineTotal;
            }

            $discountAmount = $this->toNumber($data['discount_amount'] ?? 0);
            $taxPercent = $this->toNumber($data['tax_percent'] ?? 0);
            $taxPercent = max(min($taxPercent, 100), 0);

            $taxBase = max($subtotal - $discountAmount, 0);
            $taxAmount = round($taxBase * ($taxPercent / 100), 2);
            $total = $taxBase + $taxAmount;

            $billing = BillingDocument::create([
                'sales_order_id' => $salesOrder->id,
                'company_id' => $salesOrder->company_id,
                'customer_id' => $salesOrder->customer_id,
                'status' => 'draft',
                'mode' => null,
                'subtotal' => $subtotal,
                'discount_amount' => $discountAmount,
                'tax_percent' => $taxPercent,
                'tax_amount' => $taxAmount,
                'total' => $total,
                'currency' => $salesOrder->currency ?? 'IDR',
                'notes' => $data['notes'] ?? null,
                'created_by' => auth()->id(),
                'updated_by' => auth()->id(),
            ]);

            if ($lines) {
                $billing->lines()->createMany($lines);
            }

            return $billing;
        });

        return redirect()->route('billings.show', $billing)->with('success', 'Billing Draft dibuat.');
    }

    public function update(Request $request, BillingDocument $billing)
    {
        $this->authorizePermission('invoices.update');

        if (!$billing->isEditable()) {
            abort(422, 'Billing document sudah terkunci.');
        }

        $data = $request->validate([
            'notes' => ['nullable','string'],
            'discount_amount' => ['nullable','numeric','min:0'],
            'tax_percent' => ['nullable','numeric','min:0','max:100'],
            'lines' => ['required','array','min:1'],
            'lines.*.id' => ['required','integer'],
            'lines.*.name' => ['required','string'],
            'lines.*.description' => ['nullable','string'],
            'lines.*.qty' => ['required','numeric','min:0.0001'],
            'lines.*.unit' => ['nullable','string','max:16'],
            'lines.*.unit_price' => ['required','numeric','min:0'],
            'lines.*.discount_type' => ['nullable','in:amount,percent'],
            'lines.*.discount_value' => ['nullable','numeric','min:0'],
        ]);

        $lineIds = collect($data['lines'])->pluck('id')->map(fn ($id) => (int) $id)->all();
        $existing = $billing->lines()->whereIn('id', $lineIds)->get()->keyBy('id');

        if ($existing->count() !== count($lineIds)) {
            abort(422, 'Line tidak valid.');
        }

        DB::transaction(function () use ($billing, $data, $existing) {
            $subtotal = 0.0;

            foreach ($data['lines'] as $row) {
                $line = $existing[(int) $row['id']];
                $qty = $this->toNumber($row['qty'] ?? 0);
                $unitPrice = $this->toNumber($row['unit_price'] ?? 0);
                $discType = $row['discount_type'] ?? 'amount';
                $discValue = $this->toNumber($row['discount_value'] ?? 0);

                $lineSubtotal = round($qty * $unitPrice, 2);
                if ($discType === 'percent') {
                    $discAmount = round($lineSubtotal * max(min($discValue, 100), 0) / 100, 2);
                } else {
                    $discAmount = min(max($discValue, 0), $lineSubtotal);
                }
                $lineTotal = round(max($lineSubtotal - $discAmount, 0), 2);

                $line->update([
                    'name' => $row['name'],
                    'description' => $row['description'] ?? null,
                    'qty' => $qty,
                    'unit' => $row['unit'] ?? null,
                    'unit_price' => $unitPrice,
                    'discount_type' => $discType,
                    'discount_value' => $discValue,
                    'discount_amount' => $discAmount,
                    'line_subtotal' => $lineSubtotal,
                    'line_total' => $lineTotal,
                ]);

                $subtotal += $lineTotal;
            }

            $discountAmount = $this->toNumber($data['discount_amount'] ?? 0);
            $taxPercent = $this->toNumber($data['tax_percent'] ?? 0);
            $taxPercent = max(min($taxPercent, 100), 0);

            $taxBase = max($subtotal - $discountAmount, 0);
            $taxAmount = round($taxBase * ($taxPercent / 100), 2);
            $total = $taxBase + $taxAmount;

            $billing->update([
                'notes' => $data['notes'] ?? null,
                'subtotal' => $subtotal,
                'discount_amount' => $discountAmount,
                'tax_percent' => $taxPercent,
                'tax_amount' => $taxAmount,
                'total' => $total,
                'updated_by' => auth()->id(),
            ]);
        });

        return back()->with('success', 'Billing draft updated.');
    }

    public function issueProforma(BillingDocument $billing)
    {
        $this->authorizePermission('invoices.update');

        if ($billing->status === 'void') {
            abort(422, 'Billing document sudah void.');
        }

        if ($billing->locked_at) {
            abort(422, 'Invoice sudah issued.');
        }

        $billing->load('company');

        DB::transaction(function () use ($billing) {
            $now = now();
            $rev = (int) ($billing->pi_revision ?? 0);
            if (!empty($billing->pi_number)) {
                $rev += 1;
            }

            $billing->update([
                'status' => 'sent',
                'mode' => 'proforma',
                'pi_number' => DocNumberService::next('proforma', $billing->company, $now),
                'pi_revision' => $rev,
                'pi_issued_at' => $now,
                'updated_by' => auth()->id(),
            ]);
        });

        return back()->with('success', 'Proforma invoice issued.');
    }

    public function issueInvoice(Request $request, BillingDocument $billing)
    {
        $this->authorizePermission('invoices.update');

        if ($billing->status === 'void') {
            abort(422, 'Billing document sudah void.');
        }

        if ($billing->locked_at) {
            abort(422, 'Invoice sudah issued.');
        }

        $billing->load(['company','salesOrder','lines']);

        $so = $billing->salesOrder;
        if ($so && $so->npwp_required && ($so->npwp_status ?? 'missing') !== 'ok') {
            abort(422, 'NPWP wajib diisi sebelum issue invoice.');
        }

        $isGoods = $so && strtolower((string) ($so->po_type ?? 'goods')) === 'goods';
        $hasAnyDelivered = false;
        if ($isGoods && $so) {
            $deliveryStats = DB::table('sales_order_lines')
                ->where('sales_order_id', $so->id)
                ->selectRaw(
                    'COUNT(*) as total_lines, ' .
                    'SUM(CASE WHEN COALESCE(qty_delivered,0) > 0 THEN 1 ELSE 0 END) as any_delivered_lines, ' .
                    'SUM(CASE WHEN COALESCE(qty_delivered,0) >= COALESCE(qty_ordered,0) THEN 1 ELSE 0 END) as full_delivered_lines'
                )
                ->first();

            $totalLines = (int) ($deliveryStats->total_lines ?? 0);
            $hasAnyDelivered = ((int) ($deliveryStats->any_delivered_lines ?? 0)) > 0;
            $allDelivered = $totalLines > 0 && ((int) ($deliveryStats->full_delivered_lines ?? 0)) === $totalLines;

            if ($hasAnyDelivered && !$allDelivered) {
                abort(422, 'Pengiriman parsial terdeteksi. Selesaikan posting Delivery Note terlebih dulu sebelum issue invoice.');
            }
        }

        $data = $request->validate([
            'invoice_date' => ['nullable','date'],
        ]);

        $issueDate = $data['invoice_date']
            ? Carbon::parse($data['invoice_date'])
            : now();

        $autoDeliveryState = ['created' => false, 'reused' => false];
        DB::transaction(function () use ($billing, $issueDate, $so, $isGoods, $hasAnyDelivered, &$autoDeliveryState) {
            $invNumber = DocNumberService::next('invoice', $billing->company, $issueDate);
            $issuedAt = now();

            $billing->update([
                'status' => 'sent',
                'mode' => 'invoice',
                'inv_number' => $invNumber,
                'invoice_date' => $issueDate->toDateString(),
                'issued_at' => $issuedAt,
                'locked_at' => $issuedAt,
                'ar_posted_at' => $issuedAt,
                'updated_by' => auth()->id(),
            ]);
            $this->billingInvoiceSync->sync($billing, [
                'invoice_number' => $invNumber,
                'issue_date' => $issueDate,
                'posted_at' => $issuedAt,
                'sync_lines' => true,
                'preserve_paid' => true,
            ]);

            if ($isGoods && $so && !$hasAnyDelivered) {
                $result = $this->autoDeliveryDraftFromSo->ensureForSalesOrder($so);
                $autoDeliveryState = [
                    'created' => (bool) ($result['created'] ?? false),
                    'reused' => (bool) ($result['reused'] ?? false),
                ];
            }

            if ($so) {
                $this->salesOrderStatusSync->sync($so);
            }
        });

        $message = 'Invoice issued.';
        if ($autoDeliveryState['created']) {
            $message .= ' Delivery draft otomatis dibuat.';
        } elseif ($autoDeliveryState['reused']) {
            $message .= ' Delivery draft untuk SO ini sudah tersedia.';
        }

        return back()->with('success', $message);
    }

    public function cancelDraft(BillingDocument $billing)
    {
        $this->authorizePermission('invoices.update');

        if ($billing->status !== 'draft' || $billing->locked_at) {
            abort(422, 'Billing document tidak bisa dibatalkan.');
        }

        $salesOrderId = $billing->sales_order_id;
        $shouldDelete = $billing->updated_by === null;

        if ($shouldDelete) {
            DB::transaction(function () use ($billing) {
                $billing->delete();
            });
        }

        return redirect()
            ->route('sales-orders.show', $salesOrderId)
            ->with('success', $shouldDelete ? 'Billing draft dibatalkan.' : 'Billing draft tetap tersimpan.');
    }

    public function void(Request $request, BillingDocument $billing)
    {
        $this->authorizePermission('invoices.update');

        if ($billing->status === 'void') {
            abort(422, 'Billing document sudah void.');
        }

        $data = $request->validate([
            'void_reason' => ['nullable','string','max:255'],
            'create_replacement' => ['nullable','boolean'],
        ]);

        $replace = (bool) ($data['create_replacement'] ?? false);
        $newDoc = null;

        DB::transaction(function () use ($billing, $data, $replace, &$newDoc) {
            $billing->update([
                'status' => 'void',
                'void_reason' => $data['void_reason'] ?? null,
                'voided_at' => now(),
                'updated_by' => auth()->id(),
            ]);

            if ($replace) {
                $newDoc = BillingDocument::create([
                    'sales_order_id' => $billing->sales_order_id,
                    'company_id' => $billing->company_id,
                    'customer_id' => $billing->customer_id,
                    'status' => 'draft',
                    'mode' => null,
                    'subtotal' => $billing->subtotal,
                    'discount_amount' => $billing->discount_amount,
                    'tax_percent' => $billing->tax_percent,
                    'tax_amount' => $billing->tax_amount,
                    'total' => $billing->total,
                    'currency' => $billing->currency,
                    'notes' => $billing->notes,
                    'created_by' => auth()->id(),
                ]);

                $linePayloads = [];
                foreach ($billing->lines as $line) {
                    $linePayloads[] = [
                        'sales_order_line_id' => $line->sales_order_line_id,
                        'position' => $line->position,
                        'name' => $line->name,
                        'description' => $line->description,
                        'unit' => $line->unit,
                        'qty' => (float) $line->qty,
                        'unit_price' => (float) $line->unit_price,
                        'discount_type' => $line->discount_type,
                        'discount_value' => (float) $line->discount_value,
                        'discount_amount' => (float) $line->discount_amount,
                        'line_subtotal' => (float) $line->line_subtotal,
                        'line_total' => (float) $line->line_total,
                    ];
                }

                if ($linePayloads) {
                    $newDoc->lines()->createMany($linePayloads);
                }

                $billing->update([
                    'replaced_by_id' => $newDoc->id,
                ]);
            }
        });

        if ($newDoc) {
            return redirect()->route('billings.show', $newDoc)
                ->with('success', 'Billing document voided dan replacement dibuat.');
        }

        return back()->with('success', 'Billing document voided.');
    }

    public function pdfProforma(BillingDocument $billing)
    {
        $this->authorizePermission('invoices.view');
        $billing->load(['company','customer','salesOrder','lines']);

        $options = new Options();
        $options->set('isRemoteEnabled', true);
        $pdf = new Dompdf($options);

        $html = view('billings.pdf', [
            'billing' => $billing,
            'mode' => 'proforma',
        ])->render();

        $pdf->loadHtml($html);
        $pdf->setPaper('A4', 'portrait');
        $pdf->render();

        $filename = 'proforma-'.($billing->pi_number ?: 'DRAFT-'.$billing->id).'.pdf';

        return response($pdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$filename.'"',
        ]);
    }

    public function pdfInvoice(BillingDocument $billing)
    {
        $this->authorizePermission('invoices.view');
        $billing->load(['company','customer','salesOrder','lines']);

        $options = new Options();
        $options->set('isRemoteEnabled', true);
        $pdf = new Dompdf($options);

        $html = view('billings.pdf', [
            'billing' => $billing,
            'mode' => 'invoice',
        ])->render();

        $pdf->loadHtml($html);
        $pdf->setPaper('A4', 'portrait');
        $pdf->render();

        $filename = 'invoice-'.($billing->inv_number ?: 'DRAFT-'.$billing->id).'.pdf';

        return response($pdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$filename.'"',
        ]);
    }

    protected function authorizePermission(string $permission): void
    {
        abort_unless(auth()->user()?->can($permission), 403, 'This action is unauthorized.');
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
