<?php

namespace App\Http\Controllers;

use App\Models\{Quotation, SalesOrder, SalesOrderLine, SalesOrderAttachment, Company, Customer};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use App\Services\DocNumberService;

class SalesOrderController extends Controller
{
    /**
     * Wizard: Create Sales Order from Quotation (UI).
     */
    public function createFromQuotation(Quotation $quotation)
    {
        $quotation->load(['customer','company','salesUser','lines']);

        // Flag NPWP wajib? (Soft policy: Create SO boleh tanpa NPWP; Invoice nanti hard lock)
        $npwpRequired = (bool) ($quotation->company->require_npwp_on_so ?? false);

        // Prefill NPWP dari master customer
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

    /**
     * Detail SO.
     */
    public function show(SalesOrder $salesOrder)
    {
        $salesOrder->load(['company','customer','salesUser','lines.variant.item','attachments','quotation']);
        return view('sales_orders.show', compact('salesOrder'));
    }

    /**
     * Edit form (header + lines + attachments upload).
     */
    public function edit(SalesOrder $salesOrder)
    {
        $this->authorize('update', $salesOrder);
        $salesOrder->load(['company','customer','salesUser','lines','attachments','quotation']);
        return view('sales_orders.edit', compact('salesOrder'));
    }

    /**
     * Update header + lines (+ upload attachments).
     */
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
            'lines.*.id'           => ['nullable','integer'],
            'lines.*.name'         => ['required','string','max:255'],
            'lines.*.description'  => ['nullable','string'],
            'lines.*.unit'         => ['nullable','string','max:20'],
            'lines.*.qty'          => ['required','string'],
            'lines.*.unit_price'   => ['required','string'],
            'lines.*.discount_type'  => ['nullable','in:amount,percent'],
            'lines.*.discount_value' => ['nullable','string'],
            'lines.*.item_id'         => ['nullable','integer','exists:items,id'],
            'lines.*.item_variant_id' => ['nullable','integer','exists:item_variants,id'],

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
                    $dv = $clamp($dvRaw, 0, 100);
                    $dcAmt = $lineSub * ($dv/100);
                } else {
                    $dv = max($dvRaw, 0);
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

                // NEW:
                'item_id'         => $ln['item_id'] ?? null,
                'item_variant_id' => $ln['item_variant_id'] ?? null,
            ];
        }

        $tdType  = $data['total_discount_type'] ?? 'amount';
        $tdValRaw= $parse($data['total_discount_value'] ?? 0);
        if ($mode === 'total') {
            if ($tdType === 'percent') {
                $tdVal = $clamp($tdValRaw, 0, 100);
                $totalDc = $sub * ($tdVal/100);
            } else {
                $tdVal = max($tdValRaw, 0);
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

        // Upload attachments (opsional, saat edit) – gunakan policy uploadAttachment
        if ($request->hasFile('attachments')) {
            $this->authorize('uploadAttachment', $salesOrder);
            foreach ($request->file('attachments') as $file) {
                if (!$file) continue;
                $path = $file->store("sales_orders/{$salesOrder->id}", 'public');
                $salesOrder->attachments()->create([
                    'path' => $path,
                    'original_name' => $file->getClientOriginalName(),
                    'mime' => $file->getClientMimeType(),
                    'size' => $file->getSize(),
                    'uploaded_by_user_id' => auth()->id(),
                ]);
            }
        }

        return redirect()->route('sales-orders.show', $salesOrder)->with('ok','Sales Order updated.');
    }

    /**
     * Cancel SO (status -> cancelled) dengan alasan.
     */
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

    /**
     * Hapus SO (hanya SuperAdmin, open & belum DN/INV).
     */
    public function destroy(SalesOrder $salesOrder)
    {
        $this->authorize('delete', $salesOrder);

        // Hapus file fisik lampiran (FK cascade akan hapus recordnya)
        foreach ($salesOrder->attachments as $att) {
            if ($att->path) Storage::disk('public')->delete($att->path);
        }

        $salesOrder->delete();

        return redirect()->route('sales-orders.index')->with('ok','Sales Order deleted.');
    }

    /**
     * Upload multiple attachments (route khusus, bila tidak lewat Edit).
     */
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
                    'path' => $path,
                    'original_name' => $file->getClientOriginalName(),
                    'mime' => $file->getClientMimeType(),
                    'size' => $file->getSize(),
                    'uploaded_by_user_id' => auth()->id(),
                ]);
            }
        }

        return back()->with('ok','Attachment(s) uploaded.');
    }

    /**
     * Delete attachment (cek kepemilikan & status via policy).
     */
    public function destroyAttachment(SalesOrder $salesOrder, SalesOrderAttachment $attachment)
    {
        // Pastikan attachment milik SO ini
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

    /**
     * Simpan hasil wizard Create SO.
     */
    public function storeFromQuotation(Request $request, Quotation $quotation)
    {
        $quotation->load(['customer','company','salesUser']);
        $company  = $quotation->company;
        $customer = $quotation->customer;

        // Parser angka locale-ID
        $parse = function($s){
            if ($s === null) return 0;
            $s = preg_replace('/[^\d,.\-]/', '', (string)$s);
            $s = str_replace('.', '', $s);
            $s = str_replace(',', '.', $s);
            $f = (float) $s;
            return is_finite($f) ? $f : 0;
        };
        // Clamp helper
        $clamp = function($n, $min, $max){
            $n = (float) $n;
            if ($n < $min) return $min;
            if ($n > $max) return $max;
            return $n;
        };

        // Validasi dasar
        $data = $request->validate([
            'po_number' => ['required','string','max:100'],
            'po_date'   => ['required','date'],
            'deadline'  => ['nullable','date'],
            'ship_to'   => ['nullable','string'],
            'bill_to'   => ['nullable','string'],
            'notes'     => ['nullable','string'],

            'discount_mode' => ['required','in:total,per_item'],

            // Total-mode fields
            'total_discount_type'  => ['nullable','in:amount,percent'],
            'total_discount_value' => ['nullable','string'],

            // Lines
            'lines' => ['required','array','min:1'],
            'lines.*.name'        => ['required','string','max:255'],
            'lines.*.description' => ['nullable','string'],
            'lines.*.unit'        => ['nullable','string','max:20'],
            'lines.*.qty'         => ['required','string'],
            'lines.*.unit_price'  => ['required','string'],
            'lines.*.discount_type'  => ['nullable','in:amount,percent'],
            'lines.*.discount_value' => ['nullable','string'],
            'lines.*.item_id'         => ['nullable','integer','exists:items,id'],
            'lines.*.item_variant_id' => ['nullable','integer','exists:item_variants,id'],

            // Tax
            'tax_percent' => ['required','string'],

            // NPWP (opsional di wizard; hard di Invoice)
            'npwp_number'  => ['nullable','string','max:32'],
            'npwp_name'    => ['nullable','string','max:255'],
            'npwp_address' => ['nullable','string'],
            'npwp_save_to_customer' => ['nullable','boolean'],

            // Attachments
            'attachments.*' => ['nullable','file','mimes:pdf,jpg,jpeg,png','max:5120'],
        ]);

        // Minimal 1 baris qty > 0
        $hasQty = false;
        foreach (($data['lines'] ?? []) as $ln) {
            if ($parse($ln['qty'] ?? 0) > 0) { $hasQty = true; break; }
        }
        if (!$hasQty) {
            return back()->withInput()->withErrors(['lines' => 'Minimal satu baris dengan Qty > 0.']);
        }

        // Compute totals (server-side) — dengan clamp persen & non-negative amounts
        $mode   = $data['discount_mode'];

        // Pajak: kalau non-taxable → paksa 0; kalau taxable → clamp 0–100
        $taxPctInput = $parse($data['tax_percent'] ?? 0);
        $taxPct = ($company->is_taxable ?? false) ? $clamp($taxPctInput, 0, 100) : 0.0;

        $sub = 0; $perLineDc = 0;
        $cleanLines = [];
        foreach ($data['lines'] as $i => $ln) {
            $qty   = max($parse($ln['qty'] ?? 0), 0);
            $price = max($parse($ln['unit_price'] ?? 0), 0);
            $lineSub = $qty * $price;

            $dt = $ln['discount_type'] ?? 'amount';
            $dvRaw = $parse($ln['discount_value'] ?? 0);

            $dcAmt = 0; $dv = 0;
            if ($mode === 'per_item') {
                if ($dt === 'percent') {
                    // clamp 0–100
                    $dv = $clamp($dvRaw, 0, 100);
                    $dcAmt = $lineSub * ($dv/100);
                } else {
                    // amount non-negative
                    $dv = max($dvRaw, 0);
                    $dcAmt = $dv;
                }
                if ($dcAmt > $lineSub) $dcAmt = $lineSub;
            } else {
                // mode total → nolkan set per-baris
                $dt = 'amount'; $dv = 0; $dcAmt = 0;
            }

            $lineTotal = max($lineSub - $dcAmt, 0);

            $sub += $lineSub;
            $perLineDc += $dcAmt;

            $cleanLines[] = [
                'id'               => $ln['id'] ?? null,
                'position'         => $i,
                'name'             => $ln['name'],
                'description'      => $ln['description'] ?? null,
                'unit'             => $ln['unit'] ?? null,
                'qty_ordered'      => $qty,
                'unit_price'       => $price,
                'discount_type'    => $dt,
                'discount_value'   => $dv,
                'discount_amount'  => $dcAmt,
                'line_subtotal'    => $lineSub,
                'line_total'       => $lineTotal,

                // NEW:
                'item_id'          => $ln['item_id'] ?? null,
                'item_variant_id'  => $ln['item_variant_id'] ?? null,
            ];

        }

        // Diskon total
        $totalDc = 0;
        $tdType = ($data['total_discount_type'] ?? 'amount');
        $tdValRaw  = $parse($data['total_discount_value'] ?? 0);
        if ($mode === 'total') {
            if ($tdType === 'percent') {
                $tdVal = $clamp($tdValRaw, 0, 100);
                $totalDc = $sub * ($tdVal/100);
            } else {
                $tdVal = max($tdValRaw, 0);
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

        // NPWP soft policy (ICP)
        $npwpRequired = (bool) ($company->require_npwp_on_so ?? false);
        $npwpNumber   = preg_replace('/\D+/', '', (string)($data['npwp_number'] ?? ''));
        $npwpName     = $data['npwp_name']    ?? null;
        $npwpAddress  = $data['npwp_address'] ?? null;
        $npwpStatus   = ($npwpRequired && (empty($npwpNumber) || empty($npwpName) || empty($npwpAddress))) ? 'missing' : 'ok';

        // Generate nomor SO via DocNumberService (anti-race + auto-seed)
        $soNumber = DocNumberService::next('sales_order', $company, Carbon::now());

        // Snapshot brand & currency
        $brandSnapshot = [
            'name'                => $company->name ?? null,
            'alias'               => $company->alias ?? null,
            'is_taxable'          => (bool)($company->is_taxable ?? false),
            'require_npwp_on_so'  => (bool)($company->require_npwp_on_so ?? false),
            'default_tax_percent' => (float)($company->default_tax_percent ?? 11),
        ];
        $currency = $company->currency ?? 'IDR';

        // Simpan
        $so = DB::transaction(function () use (
            $request, $quotation, $company, $customer, $data,
            $soNumber, $npwpRequired, $npwpStatus, $npwpNumber, $npwpName, $npwpAddress,
            $mode, $sub, $tdType, $tdVal, $totalDc, $dpp, $taxPct, $ppn, $grand, $cleanLines,
            $brandSnapshot, $currency
        ) {
            $so = SalesOrder::create([
                'company_id' => $company->id,
                'customer_id'=> $customer->id,
                'quotation_id' => $quotation->id,
                'sales_user_id'=> $quotation->sales_user_id ?? auth()->id(),

                'so_number'     => $soNumber,
                'order_date'    => now()->toDateString(),

                'customer_po_number' => $data['po_number'],
                'customer_po_date'   => $data['po_date'],
                'deadline'           => $data['deadline'] ?? null,

                'ship_to' => $data['ship_to'] ?? null,
                'bill_to' => $data['bill_to'] ?? null,
                'notes'   => $data['notes']   ?? null,

                'discount_mode' => $mode,

                'lines_subtotal'        => $sub,
                'total_discount_type'   => $tdType,
                'total_discount_value'  => $tdVal ?? 0,
                'total_discount_amount' => $totalDc,
                'taxable_base'          => $dpp,
                'tax_percent'           => $taxPct,
                'tax_amount'            => $ppn,
                'total'                 => $grand,

                'npwp_required'   => $npwpRequired,
                'npwp_status'     => $npwpStatus,
                'tax_npwp_number' => $npwpNumber ?: null,
                'tax_npwp_name'   => $npwpName ?: null,
                'tax_npwp_address'=> $npwpAddress ?: null,

                'status' => 'open',

                // snapshot
                'brand_snapshot' => $brandSnapshot,
                'currency'       => $currency,
            ]);

            foreach ($cleanLines as $i => $line) {
                $line['position'] = $i + 1; // lebih enak 1-based
                $so->lines()->create(collect($line)->except('id')->toArray());
            }

            // Attachments
            if ($request->hasFile('attachments')) {
                foreach ($request->file('attachments') as $file) {
                    if (!$file) continue;
                    $path = $file->store("sales_orders/{$so->id}", 'public');
                    $so->attachments()->create([
                        'path' => $path,
                        'original_name' => $file->getClientOriginalName(),
                        'mime' => $file->getClientMimeType(),
                        'size' => $file->getSize(),
                        'uploaded_by_user_id' => auth()->id(),
                    ]);
                }
            }

            // Update quotation → won & link ke SO
            $quotation->update([
                'status' => 'won',
                'won_at' => now(),
            ]);

            return $so;
        });

        // (Opsional) Perbarui master customer NPWP jika diminta dan tersedia
        if ($company->require_npwp_on_so && $request->boolean('npwp_save_to_customer') && $npwpNumber) {
            $customer->update([
                'npwp_number'  => $npwpNumber,
                'npwp_name'    => $npwpName,
                'npwp_address' => $npwpAddress,
            ]);
        }

        return redirect()->route('sales-orders.show', $so)
            ->with('ok', 'Sales Order berhasil dibuat dari quotation.');
    }
}
