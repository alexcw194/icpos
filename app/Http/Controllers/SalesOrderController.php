<?php

namespace App\Http\Controllers;

use App\Models\{
    Quotation,
    SalesOrder,
    SalesOrderLine,
    SalesOrderAttachment,
    Company,
    Customer
};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use App\Services\DocNumberService;
use App\Http\Controllers\SalesOrderAttachmentController as SOAtt;

class SalesOrderController extends Controller
{
    /** Wizard: Create Sales Order from Quotation (UI). */
    public function createFromQuotation(Quotation $quotation)
    {
        $quotation->load(['customer','company','salesUser','lines']);

        // Soft policy: SO boleh dibuat walau NPWP belum lengkap (Invoice yang hard lock)
        $npwpRequired = (bool) ($quotation->company->require_npwp_on_so ?? false);

        $cust = $quotation->customer;
        $npwp = [
            'number'  => $cust->npwp_number ?? '',
            'name'    => $cust->npwp_name ?? ($cust->name ?? ''),
            'address' => $cust->npwp_address ?? ($cust->address ?? ''),
        ];
        $npwpMissing = $npwpRequired && (empty($npwp['number']) || empty($npwp['name']) || empty($npwp['address']));

        return view('sales_orders.create_from_quotation', compact(
            'quotation', 'npwpRequired', 'npwpMissing', 'npwp'
        ));
    }

    public function index(Request $request)
    {
        $allowed = ['open','partial_delivered','delivered','invoiced','closed','cancelled'];
        $status  = $request->query('status');
        if ($status && !in_array($status, $allowed, true)) $status = null;

        $q = SalesOrder::with(['customer','company'])
            ->when($status, fn($x) => $x->where('status',$status))
            ->latest();

        $orders = $q->paginate(15)->withQueryString();
        return view('sales_orders.index', compact('orders','status'));
    }

    /** Detail SO. */
    public function show(SalesOrder $salesOrder)
    {
        $salesOrder->load(['company','customer','salesUser','lines.variant.item','attachments','quotation']);
        return view('sales_orders.show', compact('salesOrder'));
    }

    /** Edit form (header + lines + attachments upload). */
    public function edit(SalesOrder $salesOrder)
    {
        $this->authorize('update', $salesOrder);
        $salesOrder->load(['company','customer','salesUser','lines','attachments','quotation']);
        return view('sales_orders.edit', compact('salesOrder'));
    }

    /** Update header + lines (+ upload attachments). */
    public function update(Request $request, SalesOrder $salesOrder)
    {
        $this->authorize('update', $salesOrder);
        $salesOrder->load(['company','lines','attachments']);
        $company = $salesOrder->company;

        // Helpers
        $parse = function($s){
            if ($s === null) return 0;
            $s = preg_replace('/[^\d,.\-]/', '', (string)$s);
            $s = str_replace('.', '', $s);
            $s = str_replace(',', '.', $s);
            $f = (float) $s;
            return is_finite($f) ? $f : 0;
        };
        $clamp = function($n,$min,$max){
            $n = (float)$n;
            if ($n < $min) return $min;
            if ($n > $max) return $max;
            return $n;
        };

        // Validasi header + lines (+ attachments input)
        $data = $request->validate([
            'customer_po_number' => ['required','string','max:100'],
            'customer_po_date'   => ['required','date'],
            'deadline'           => ['nullable','date'],
            'ship_to'            => ['nullable','string'],
            'bill_to'            => ['nullable','string'],
            'notes'              => ['nullable','string'],

            'discount_mode' => ['required','in:total,per_item'],

            'total_discount_type'  => ['nullable','in:amount,percent'],
            'total_discount_value' => ['nullable','string'],

            'tax_percent' => ['required','string'],

            'lines' => ['required','array','min:1'],
            'lines.*.id'             => ['nullable','integer'],
            'lines.*.name'           => ['required','string','max:255'],
            'lines.*.description'    => ['nullable','string'],
            'lines.*.unit'           => ['nullable','string','max:20'],
            'lines.*.qty'            => ['required','string'],
            'lines.*.unit_price'     => ['required','string'],
            'lines.*.discount_type'  => ['nullable','in:amount,percent'],
            'lines.*.discount_value' => ['nullable','string'],
            'lines.*.item_id'         => ['nullable','integer','exists:items,id'],
            'lines.*.item_variant_id' => ['nullable','integer','exists:item_variants,id'],
            'private_notes' => ['nullable','string'],
            'under_amount'  => ['nullable','numeric','min:0'],

            // optional upload saat edit
            'attachments.*' => ['nullable','file','mimes:pdf,jpg,jpeg,png','max:5120'],
        ]);

        // Hitung ulang totals
        $mode        = $data['discount_mode'];
        $taxPctInput = $parse($data['tax_percent'] ?? 0);
        $taxPct      = ($company->is_taxable ?? false) ? $clamp($taxPctInput, 0, 100) : 0.0;

        $sub = 0; $perLineDc = 0;
        $cleanLines = [];
        foreach ($data['lines'] as $i => $ln) {
            $qty     = max($parse($ln['qty'] ?? 0), 0);
            $price   = max($parse($ln['unit_price'] ?? 0), 0);
            $lineSub = $qty * $price;

            $dt    = $ln['discount_type'] ?? 'amount';
            $dvRaw = $parse($ln['discount_value'] ?? 0);

            $dcAmt = 0; $dv = 0;
            if ($mode === 'per_item') {
                if ($dt === 'percent') {
                    $dv   = $clamp($dvRaw, 0, 100);
                    $dcAmt = $lineSub * ($dv/100);
                } else {
                    $dv   = max($dvRaw, 0);
                    $dcAmt = $dv;
                }
                if ($dcAmt > $lineSub) $dcAmt = $lineSub;
            } else {
                $dt = 'amount'; $dv = 0; $dcAmt = 0;
            }

            $lineTotal = max($lineSub - $dcAmt, 0);
            $sub += $lineSub; $perLineDc += $dcAmt;

            $cleanLines[] = [
                'id'              => $ln['id'] ?? null,
                'position'        => $i,
                'name'            => $ln['name'],
                'description'     => $ln['description'] ?? null,
                'unit'            => $ln['unit'] ?? null,
                'qty_ordered'     => $qty,
                'unit_price'      => $price,
                'discount_type'   => $dt,
                'discount_value'  => $dv,
                'discount_amount' => $dcAmt,
                'line_subtotal'   => $lineSub,
                'line_total'      => $lineTotal,

                'item_id'         => $ln['item_id'] ?? null,
                'item_variant_id' => $ln['item_variant_id'] ?? null,
            ];
        }

        $tdType  = $data['total_discount_type'] ?? 'amount';
        $tdValRaw= $parse($data['total_discount_value'] ?? 0);
        if ($mode === 'total') {
            if ($tdType === 'percent') {
                $tdVal   = $clamp($tdValRaw, 0, 100);
                $totalDc = $sub * ($tdVal/100);
            } else {
                $tdVal   = max($tdValRaw, 0);
                $totalDc = $tdVal;
            }
            if ($totalDc > $sub) $totalDc = $sub;
        } else {
            $tdType = 'amount'; $tdVal = 0;
            $totalDc = $perLineDc;
        }

        $dpp   = max($sub - $totalDc, 0);
        $ppn   = ($company->is_taxable ?? false) ? ($dpp * ($taxPct/100)) : 0;
        $grand = $dpp + $ppn;

        // Simpan header + sinkronisasi lines
        DB::transaction(function () use ($salesOrder,$data,$mode,$sub,$tdType,$tdVal,$totalDc,$dpp,$taxPct,$ppn,$grand,$cleanLines) {
            $salesOrder->update([
                'customer_po_number'    => $data['customer_po_number'],
                'customer_po_date'      => $data['customer_po_date'],
                'deadline'              => $data['deadline'] ?? null,
                'ship_to'               => $data['ship_to'] ?? null,
                'bill_to'               => $data['bill_to'] ?? null,
                'notes'                 => $data['notes'] ?? null,

                'discount_mode'         => $mode,
                'lines_subtotal'        => $sub,
                'total_discount_type'   => $tdType,
                'total_discount_value'  => $tdVal ?? 0,
                'total_discount_amount' => $totalDc,
                'taxable_base'          => $dpp,
                'tax_percent'           => $taxPct,
                'tax_amount'            => $ppn,
                'total'                 => $grand,
            ]);

            // Hapus line yang tidak dikirim lagi
            $keepIds = collect($cleanLines)->pluck('id')->filter()->values()->all();
            if (count($keepIds)) {
                $salesOrder->lines()->whereNotIn('id', $keepIds)->delete();
            } else {
                $salesOrder->lines()->delete();
            }

            // Upsert per line
            foreach ($cleanLines as $ln) {
                if (!empty($ln['id'])) {
                    $salesOrder->lines()->where('id', $ln['id'])
                        ->update(collect($ln)->except('id')->toArray());
                } else {
                    $salesOrder->lines()->create(collect($ln)->except('id')->toArray());
                }
            }
        });

        // Upload attachments (opsional, saat edit)
        if ($request->hasFile('attachments')) {
            $this->authorize('uploadAttachment', $salesOrder);
            foreach ($request->file('attachments') as $file) {
                if (!$file) continue;
                $path = $file->store("sales_orders/{$salesOrder->id}", 'public');
                $salesOrder->attachments()->create([
                    'disk'          => 'public',
                    'path'          => $path,
                    'original_name' => $file->getClientOriginalName(),
                    'mime'          => $file->getClientMimeType(),
                    'size'          => $file->getSize(),
                    'uploaded_by'   => auth()->id(),
                ]);
            }
        }

        return redirect()->route('sales-orders.show', $salesOrder)->with('ok','Sales Order updated.');
    }

    /** Cancel SO (status -> cancelled) dengan alasan. */
    public function cancel(Request $request, SalesOrder $salesOrder)
    {
        $this->authorize('cancel', $salesOrder);

        $validated = $request->validate([
            'cancel_reason' => ['required','string','min:5'],
        ]);

        $salesOrder->update([
            'status'                => 'cancelled',
            'cancelled_at'          => now(),
            'cancelled_by_user_id'  => auth()->id(),
            'cancel_reason'         => $validated['cancel_reason'],
        ]);

        return redirect()->route('sales-orders.show', $salesOrder)->with('ok','Sales Order cancelled.');
    }

    /** Hapus SO (SuperAdmin, open & belum DN/INV). */
    public function destroy(SalesOrder $salesOrder)
    {
        $this->authorize('delete', $salesOrder);

        foreach ($salesOrder->attachments as $att) {
            if ($att->path) Storage::disk('public')->delete($att->path);
        }

        $salesOrder->delete();

        return redirect()->route('sales-orders.index')->with('ok','Sales Order deleted.');
    }

    /** Upload multiple attachments (route khusus, bila tidak lewat Edit). */
    public function storeAttachment(Request $request, SalesOrder $salesOrder)
    {
        $this->authorize('uploadAttachment', $salesOrder);

        $request->validate([
            'attachments.*' => ['required','file','mimes:pdf,jpg,jpeg,png','max:5120'],
        ]);

        if ($request->hasFile('attachments')) {
            foreach ($request->file('attachments') as $file) {
                if (!$file) continue;
                $path = $file->store("sales_orders/{$salesOrder->id}", 'public');
                $salesOrder->attachments()->create([
                    'disk'          => 'public',
                    'path'          => $path,
                    'original_name' => $file->getClientOriginalName(),
                    'mime'          => $file->getClientMimeType(),
                    'size'          => $file->getSize(),
                    'uploaded_by'   => auth()->id(),
                ]);
            }
        }

        return back()->with('ok','Attachment(s) uploaded.');
    }

    /** Delete attachment (cek kepemilikan & status via policy). */
    public function destroyAttachment(SalesOrder $salesOrder, SalesOrderAttachment $attachment)
    {
        if ((int)$attachment->sales_order_id !== (int)$salesOrder->id) {
            abort(404);
        }

        $this->authorize('deleteAttachment', [$salesOrder, $attachment]);

        if ($attachment->path) {
            Storage::disk('public')->delete($attachment->path);
        }
        $attachment->delete();

        return back()->with('ok','Attachment deleted.');
    }

    /** Simpan hasil wizard Create SO. */
    public function storeFromQuotation(Request $request, Quotation $quotation)
    {
        $data = $request->validate([
            'po_number'      => ['nullable','string','max:100'],
            'po_date'        => ['nullable','date'],
            'deadline'       => ['nullable','date'],
            'ship_to'        => ['nullable','string'],
            'bill_to'        => ['nullable','string'],
            'notes'          => ['nullable','string'],

            'private_notes'  => ['nullable','string'],
            'under_amount'   => ['nullable','numeric','min:0'],

            'discount_mode'  => ['nullable','in:total,per_item'],
            'tax_percent'    => ['nullable','numeric','min:0'],

            'draft_token'    => ['nullable','string','max:64'],
        ]);

        $under       = $this->toNumber($data['under_amount'] ?? 0);
        $taxPctInput = $this->toNumber($data['tax_percent'] ?? ($quotation->tax_percent ?? 0));

        $discountMode = $data['discount_mode'] ?? ($quotation->discount_mode ?? 'total');

        $company   = $quotation->company()->first();
        $isTaxable = (bool)($company->is_taxable ?? false);
        $taxPct    = $isTaxable ? max(min($taxPctInput,100),0) : 0.0;

        /** @var SalesOrder $so */
        $so = DB::transaction(function() use ($quotation, $company, $data, $under, $discountMode, $taxPct, $isTaxable) {

            // === Generate nomor SO ===
            if (class_exists(DocNumberService::class)) {
                // Service butuh 3 argumen: docType, company, docDate
                $number = app(DocNumberService::class)->next('sales_order', $company, now());
            } else {
                $number = 'SO/'.date('Y').'/'.str_pad((string)(SalesOrder::max('id')+1), 5, '0', STR_PAD_LEFT);
            }

            // === Create header ===
            $so = SalesOrder::create([
                'quotation_id'        => $quotation->id,
                'company_id'          => $quotation->company_id,
                'customer_id'         => $quotation->customer_id,

                // GUNAKAN KOLOM ASLI:
                'so_number'           => $number,
                'order_date'          => now()->toDateString(),
                'deadline'            => $data['deadline'] ?? null,

                'customer_po_number'  => $data['po_number'] ?? null,
                'customer_po_date'    => $data['po_date']   ?? now()->toDateString(),

                'status'              => 'open',
                'notes'               => $data['notes'] ?? null,
                'private_notes'       => $data['private_notes'] ?? null,
                'under_amount'        => $under,

                'discount_mode'       => $discountMode,
                'tax_percent'         => $taxPct,
            ]);

            // === Copy lines dari quotation ===
            $linesSubtotal = 0.0;

            foreach ($quotation->lines as $idx => $ql) {
                $qty       = (float)($ql->qty ?? $ql->quantity ?? 0);
                $unitPrice = (float)($ql->unit_price ?? 0);

                $discType  = $discountMode === 'per_item' ? ($ql->discount_type ?? 'amount') : 'amount';
                $discValue = $discountMode === 'per_item' ? (float)($ql->discount_value ?? 0) : 0.0;

                $lineSub   = $qty * $unitPrice;
                $lineDcAmt = 0.0;
                if ($discountMode === 'per_item') {
                    $lineDcAmt = $discType === 'percent'
                        ? ($lineSub * $discValue / 100)
                        : max($discValue, 0);
                    $lineDcAmt = min($lineDcAmt, $lineSub);
                }
                $lineTotal = max(0, $lineSub - $lineDcAmt);

                SalesOrderLine::create([
                    'sales_order_id'   => $so->id,
                    'position'         => $idx,
                    'name'             => $ql->name,
                    'description'      => $ql->description,
                    'unit'             => $ql->unit ?? $ql->unit_name ?? 'PCS',

                    'qty_ordered'      => $qty,
                    'unit_price'       => $unitPrice,
                    'discount_type'    => $discType,
                    'discount_value'   => $discValue,
                    'discount_amount'  => $lineDcAmt,
                    'line_subtotal'    => $lineSub,
                    'line_total'       => $lineTotal,

                    'item_id'          => $ql->item_id ?? null,
                    'item_variant_id'  => $ql->item_variant_id ?? $ql->variant_id ?? null,
                ]);

                $linesSubtotal += $lineTotal; // subtotal setelah diskon per-item
            }

            // === Diskon total (jika mode total) ===
            $tdType = 'amount';
            $tdVal  = 0.0;
            $totalDiscountAmount = 0.0;

            if ($discountMode === 'total') {
                $tdType = $quotation->total_discount_type ?? 'amount';
                $tdVal  = (float)($quotation->total_discount_value ?? 0);
                $totalDiscountAmount = $tdType === 'percent'
                    ? ($linesSubtotal * $tdVal / 100)
                    : $tdVal;
                $totalDiscountAmount = min($totalDiscountAmount, $linesSubtotal);
            }

            $taxableBase = max(0, $linesSubtotal - $totalDiscountAmount);
            $taxAmount   = $isTaxable ? round($taxableBase * ($so->tax_percent/100), 2) : 0.0;
            $total       = $taxableBase + $taxAmount;

            // === Update totals header ===
            $so->update([
                'lines_subtotal'        => $linesSubtotal,
                'total_discount_type'   => $tdType,
                'total_discount_value'  => $discountMode === 'total' ? $tdVal : 0,
                'total_discount_amount' => $discountMode === 'total' ? $totalDiscountAmount : 0,
                'taxable_base'          => $taxableBase,
                'tax_amount'            => $taxAmount,
                'total'                 => $total,
            ]);

            // === Pindahkan lampiran draft → final ===
            if (!empty($data['draft_token'])) {
                if (method_exists(SOAtt::class, 'attachFromDraft')) {
                    SOAtt::attachFromDraft($data['draft_token'], $so);
                } else {
                    $rows = SalesOrderAttachment::where('draft_token', $data['draft_token'])->get();
                    foreach ($rows as $att) {
                        $disk = $att->disk ?: 'public';
                        $old  = $att->path;
                        $filename = basename($old);
                        $new = "so_attachments/{$so->id}/{$filename}";
                        if ($old && Storage::disk($disk)->exists($old)) {
                            Storage::disk($disk)->makeDirectory("so_attachments/{$so->id}");
                            Storage::disk($disk)->move($old, $new);
                        } else {
                            $new = $old;
                        }
                        $att->update([
                            'sales_order_id' => $so->id,
                            'draft_token'    => null,
                            'path'           => $new,
                            'uploaded_by'    => auth()->id(),
                        ]);
                    }
                }
            }

            // === Tandai quotation WON ===
            $quotation->update([
                'status'         => 'won',
                'won_at'         => now(),
                'sales_order_id' => $so->id,
            ]);

            return $so;
        });

        // Response
        if ($request->wantsJson()) {
            return response()->json(['ok' => true, 'id' => $so->id, 'number' => $so->so_number]);
        }

        return redirect()->route('sales-orders.show', $so)
            ->with('success', 'Sales Order dibuat.');
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
}
