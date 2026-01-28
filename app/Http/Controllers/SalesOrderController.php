<?php

namespace App\Http\Controllers;

use App\Models\{
    Quotation,
    SalesOrder,
    SalesOrderLine,
    SalesOrderAttachment,
    SalesOrderBillingTerm,
    Company,
    Customer,
    Item,
    Project,
    TermOfPayment,
    User
};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Rule;
use App\Services\DocNumberService;
use App\Http\Controllers\SalesOrderAttachmentController as SOAtt;

class SalesOrderController extends Controller
{
    private const BILLING_DUE_TRIGGERS = [
        'on_invoice',
        'after_invoice_days',
        'on_delivery',
        'after_delivery_days',
        'eom_day',
        'next_month_day',
        // legacy support
        'on_so',
        'end_of_month',
    ];
    /** Wizard: Create Sales Order from Quotation (UI). */
    public function createFromQuotation(Quotation $quotation)
    {

        $quotation->load(['customer','company','salesUser','lines']);

        // data lain yang sudah kamu pakai di blade
        $items = Item::with('unit:id,code')
            ->orderBy('name')
            ->get(['id','name','price','unit_id']);
        $projects = Project::visibleTo(auth()->user())
            ->with('customer:id,name')
            ->orderBy('name')
            ->get(['id','name','code','customer_id']);
        $topOptions = TermOfPayment::query()
            ->whereIn('code', TermOfPayment::ALLOWED_CODES)
            ->orderBy('code')
            ->get(['code','description','is_active','applicable_to']);
        

        $npwpRequired = (bool) ($quotation->company->require_npwp_on_so ?? false);
        $cust = $quotation->customer;
        $npwp = [
            'number'  => $cust->npwp_number ?? '',
            'name'    => $cust->npwp_name ?? ($cust->name ?? ''),
            'address' => $cust->npwp_address ?? ($cust->address ?? ''),
        ];
        $npwpMissing = $npwpRequired && (
            empty($npwp['number']) || empty($npwp['name']) || empty($npwp['address'])
        );

        // ⚠️ TIDAK ADA SalesOrder::create DI SINI
        return view('sales_orders.create_from_quotation', compact(
            'quotation', 'npwpRequired', 'npwpMissing', 'npwp', 'items', 'projects', 'topOptions'
        ));
    }


    public function create()
    {
        $customers = Customer::orderBy('name')->get(['id','name']);
        $items     = Item::with('unit:id,code')->orderBy('name')->get(['id','name','price','unit_id']);
        $projects  = Project::visibleTo(auth()->user())
            ->with('customer:id,name')
            ->orderBy('name')
            ->get(['id','name','code','customer_id']);
        $topOptions = TermOfPayment::query()
            ->whereIn('code', TermOfPayment::ALLOWED_CODES)
            ->orderBy('code')
            ->get(['code','description','is_active','applicable_to']);
        
        // tambahkan is_default ke select
        $companies = Company::orderBy('name')->get(['id','name','alias','is_taxable','default_tax_percent','is_default']);
        $sales     = User::orderBy('name')->get(['id','name']);

        // Tentukan company terpilih di awal: old() → is_default → first()
        $selectedCompanyId = old('company_id')
            ?? optional($companies->firstWhere('is_default', true))->id
            ?? optional($companies->first())->id;

        // Hitung PPN default berdasar company terpilih
        $ppnDefault = 0.0;
        if ($selectedCompanyId) {
            $c = $companies->firstWhere('id', $selectedCompanyId);
            $ppnDefault = ($c && $c->is_taxable) ? (float) $c->default_tax_percent : 0.0;
        }

        $defaultSalesUserId  = auth()->id();
        $defaultDiscountMode = old('discount_mode', 'total');

        return view('sales_orders.create', [
            'customers'          => $customers,
            'items'              => $items,
            'projects'           => $projects,
            'topOptions'         => $topOptions,
            'companies'          => $companies,
            'sales'              => $sales,
            'selectedCompanyId'  => $selectedCompanyId, // dipakai di <select name="company_id">
            'defaultSalesUserId' => $defaultSalesUserId,
            'defaultDiscountMode'=> $defaultDiscountMode,
            'ppnDefault'         => $ppnDefault,        // dipakai di input PPN (%) saat load pertama
        ]);
    }

    public function store(Request $request)
    {
        // 1) Validasi (rules hanya string/array)
        $data = $request->validate([
            'company_id'           => ['required','exists:companies,id'],
            'customer_id'          => ['required','exists:customers,id'],
            'customer_po_number'   => ['required','string','max:100'],
            'customer_po_date'     => ['nullable','date'],
            'po_type'              => ['required','in:goods,project,maintenance'],
            'project_id'           => ['nullable','integer','exists:projects,id'],
            'project_name'         => ['nullable','string','max:255'],
            'deadline'             => ['nullable','date'],
            'ship_to'              => ['nullable','string'],
            'bill_to'              => ['nullable','string'],
            'notes'                => ['nullable','string'],
            'private_notes'        => ['nullable','string'],
            'discount_mode'        => ['required','in:total,per_item'],
            'tax_percent'          => ['nullable','string'], // akan diparse manual
            'under_amount'         => ['nullable','numeric','min:0'],
            'sales_user_id'        => ['nullable','exists:users,id'],
            'draft_token'          => ['nullable','string','max:64'],

            'total_discount_type'  => ['nullable','in:amount,percent'],
            'total_discount_value' => ['nullable','string'],

            'billing_terms' => ['required','array','min:1'],
            'billing_terms.*.top_code' => ['required','string','max:64'],
            'billing_terms.*.percent' => ['required','string'],
            'billing_terms.*.note' => ['nullable','string','max:190'],
            'billing_terms.*.due_trigger' => ['nullable', Rule::in(self::BILLING_DUE_TRIGGERS)],
            'billing_terms.*.offset_days' => ['nullable','integer','min:0'],
            'billing_terms.*.day_of_month' => ['nullable','integer','min:1','max:28'],

            'lines'               => ['required','array','min:1'],
            'lines.*.name'        => ['required','string'],
            'lines.*.description' => ['nullable','string'],
            'lines.*.unit'        => ['nullable','string','max:20'],
            'lines.*.qty'         => ['required','string'],
            'lines.*.unit_price'  => ['required','string'],
            'lines.*.discount_type'  => ['nullable','in:amount,percent'],
            'lines.*.discount_value' => ['nullable','string'],
            'lines.*.item_id'         => ['nullable','exists:items,id'],
            'lines.*.item_variant_id' => ['nullable','exists:item_variants,id'],
        ]);

        // Helper parse angka (ID locale -> float)
        $toNum = function ($v): float {
            if ($v === null) return 0.0;
            $s = trim((string)$v);
            if ($s === '') return 0.0;
            $s = str_replace(' ', '', $s);
            if (strpos($s, ',') !== false && strpos($s, '.') !== false) {
                $s = str_replace('.', '', $s);
                $s = str_replace(',', '.', $s);
            } else {
                $s = str_replace(',', '.', $s);
            }
            return is_numeric($s) ? (float)$s : 0.0;
        };

        // 2) Normalisasi input header
        $isScope      = in_array(($data['po_type'] ?? 'goods'), ['project', 'maintenance'], true);
        $mode         = $data['discount_mode'] ?? 'total';
        $tdType       = $data['total_discount_type'] ?? 'amount';
        $tdVal        = $toNum($data['total_discount_value'] ?? 0);
        $taxPct       = $toNum($data['tax_percent'] ?? 0);
        $salesUserId  = $request->input('sales_user_id') ?: auth()->id();
        $projectId    = !empty($data['project_id']) ? (int) $data['project_id'] : null;
        $projectName  = trim((string) ($data['project_name'] ?? ''));
        $projectName  = $projectName !== '' ? $projectName : null;

        if (($data['po_type'] ?? 'goods') === 'project' && !$projectId && !$projectName) {
            return back()
                ->withErrors(['project_name' => 'Project wajib diisi (pilih Project atau isi Project Name).'])
                ->withInput();
        }

        if ($isScope) {
            $mode = 'total';
            $data['discount_mode'] = 'total';
        }

        $billingTerms = $this->normalizeBillingTerms($data['billing_terms'] ?? [], $data['po_type'] ?? 'goods');

        // 3) Hitung per-baris
        $computedLines = [];
        $grossSubtotal = 0.0;
        $afterPerItem  = 0.0;

        $linesInput = collect($data['lines'] ?? [])->values();
        foreach ($linesInput as $idx => $ln) {
            $qty   = max($toNum($ln['qty'] ?? 0), 0);
            $price = max($toNum($ln['unit_price'] ?? 0), 0);
            $lineSubtotal = $qty * $price;
            $grossSubtotal += $lineSubtotal;

            $dType = $mode === 'per_item' ? ($ln['discount_type'] ?? 'amount') : 'amount';
            $dVal  = $mode === 'per_item' ? $toNum($ln['discount_value'] ?? 0) : 0.0;
            if ($isScope) {
                $dType = 'amount';
                $dVal = 0.0;
            }

            if ($dType === 'percent') {
                $discAmt = min(max($dVal, 0), 100) / 100 * $lineSubtotal;
            } else {
                $discAmt = min(max($dVal, 0), $lineSubtotal);
            }

            $lineTotal = max($lineSubtotal - $discAmt, 0);

            $computedLines[] = [
                'position'        => $idx + 1,
                'item_id'         => $isScope ? null : ($ln['item_id'] ?? null),
                'item_variant_id' => $isScope ? null : ($ln['item_variant_id'] ?? null),
                'name'            => $ln['name'] ?? null,
                'description'     => $ln['description'] ?? null,
                'unit'            => $ln['unit'] ?? null,
                'qty'             => $qty,
                'unit_price'      => $price,
                'discount_type'   => $dType,
                'discount_value'  => $dVal,
                'discount_amount' => $discAmt,
                'line_subtotal'   => $lineSubtotal,
                'line_total'      => $lineTotal,
            ];

            $afterPerItem += $lineTotal;
        }

        // 4) Diskon total
        $totalDc = 0.0;
        if ($mode !== 'per_item') {
            if ($tdType === 'percent') {
                $totalDc = min(max($tdVal, 0), 100) / 100 * $afterPerItem;
            } else {
                $totalDc = min(max($tdVal, 0), $afterPerItem);
            }
        } else {
            $tdType = 'amount';
            $tdVal  = 0.0;
        }

        // 5) Pajak & total
        $sub   = $afterPerItem;
        $dpp   = max($sub - $totalDc, 0);
        $ppn   = $dpp * max($taxPct, 0) / 100;
        $grand = $dpp + $ppn;

        // 6) Nomor dokumen & simpan
        $company = Company::findOrFail($data['company_id']);
        $number  = app(DocNumberService::class)->next('sales_order', $company, now());

        /** @var \App\Models\SalesOrder $so */
        $so = null;
        DB::transaction(function () use ($data, $company, $number, $salesUserId, $computedLines, $sub, $tdType, $tdVal, $totalDc, $dpp, $taxPct, $ppn, $grand, $projectId, $projectName, $billingTerms, &$so) {
            $so = SalesOrder::create([
                'company_id'          => $company->id,
                'customer_id'         => $data['customer_id'],
                'so_number'           => $number,
                'order_date'          => now()->toDateString(),
                'deadline'            => $data['deadline'] ?? null,
                'sales_user_id'       => $salesUserId, // ✅ JANGAN pakai $request di sini
                'customer_po_number'  => $data['customer_po_number'],
                'customer_po_date'    => $data['customer_po_date'] ?? null,
                'po_type'             => $data['po_type'],
                'payment_term_id'     => null,
                'payment_term_snapshot' => null,
                'project_id'          => $projectId,
                'project_name'        => $projectName,
                'ship_to'             => $data['ship_to'] ?? null,
                'bill_to'             => $data['bill_to'] ?? null,
                'notes'               => $data['notes'] ?? null,
                'private_notes'       => $data['private_notes'] ?? null,
                'under_amount'        => (float) ($data['under_amount'] ?? 0),
                'discount_mode'       => $mode,
                'tax_percent'         => $taxPct, // simpan numeric
                'status'              => 'open',
                'contract_value'      => $grand,
            ]);

            foreach ($computedLines as $ln) {
                SalesOrderLine::create([
                    'sales_order_id'   => $so->id,
                    'position'         => $ln['position'],
                    'name'             => $ln['name'],
                    'description'      => $ln['description'] ?? null,
                    'unit'             => $ln['unit'] ?? null,
                    'qty_ordered'      => $ln['qty'],
                    'unit_price'       => $ln['unit_price'],
                    'discount_type'    => $ln['discount_type'],
                    'discount_value'   => $ln['discount_value'],
                    'discount_amount'  => $ln['discount_amount'],
                    'line_subtotal'    => $ln['line_subtotal'],
                    'line_total'       => $ln['line_total'],
                    'item_id'          => $ln['item_id'] ?? null,
                    'item_variant_id'  => $ln['item_variant_id'] ?? null,
                ]);
            }

            foreach ($billingTerms as $term) {
                SalesOrderBillingTerm::create([
                    'sales_order_id' => $so->id,
                    'seq' => $term['seq'],
                    'top_code' => $term['top_code'],
                    'percent' => $term['percent'],
                    'due_trigger' => $term['due_trigger'] ?? null,
                    'offset_days' => $term['offset_days'] ?? null,
                    'day_of_month' => $term['day_of_month'] ?? null,
                    'note' => $term['note'],
                    'status' => $term['status'] ?? 'planned',
                ]);
            }

            $so->update([
                'lines_subtotal'        => $sub,
                'total_discount_type'   => $tdType,
                'total_discount_value'  => $tdVal,
                'total_discount_amount' => $totalDc,
                'taxable_base'          => $dpp,
                'tax_amount'            => $ppn,
                'total'                 => $grand,
                'contract_value'        => $grand,
            ]);
        });

        if (!empty($data['draft_token'])) {
            if (method_exists(\App\Http\Controllers\SalesOrderAttachmentController::class, 'attachFromDraft')) {
                \App\Http\Controllers\SalesOrderAttachmentController::attachFromDraft($data['draft_token'], $so);
            }
        }

        // ✅ HABISKAN TOKEN SESSION DI SINI
        session()->forget('so_draft_token');

        return redirect()->route('sales-orders.show', $so)->with('success', 'Sales Order dibuat.');
    }



    public function index(Request $request)
    {
        $allowed = ['open','partial_delivered','delivered','invoiced','closed','cancelled','partially_billed','fully_billed'];
        $status  = $request->query('status');
        if ($status && !in_array($status, $allowed, true)) $status = null;

        $q = SalesOrder::query()
            ->visibleTo(auth()->user())
            ->with(['customer','company'])
            ->when($status, fn($x) => $x->where('status',$status))
            ->latest();

        $orders = $q->paginate(15)->withQueryString();
        return view('sales_orders.index', compact('orders','status'));
    }

    /** Detail SO. */
    public function show(SalesOrder $salesOrder)
    {
        $this->authorize('view', $salesOrder);

        $salesOrder->load(['company','customer','salesUser','lines.variant.item','attachments','quotation','project','billingTerms','variations']);
        return view('sales_orders.show', compact('salesOrder'));
    }

    /** Edit form (header + lines + attachments upload). */
    public function edit(SalesOrder $salesOrder)
    {
        $this->authorize('update', $salesOrder);

        $salesOrder->load([
            'company',
            'customer',
            'salesUser',
            'lines',
            'attachments',
            'quotation',
            'billingTerms',
        ]);

        // Data item untuk TomSelect di staging row
        $items = Item::query()
            ->with('unit:id,code')
            ->orderBy('name')
            ->get(['id','name','unit_id','price']);
        $projects = Project::visibleTo(auth()->user())
            ->with('customer:id,name')
            ->orderBy('name')
            ->get(['id','name','code','customer_id']);
        $topOptions = TermOfPayment::query()
            ->whereIn('code', TermOfPayment::ALLOWED_CODES)
            ->orderBy('code')
            ->get(['code','description','is_active','applicable_to']);
        
        
         // Seed baris untuk view/JS (INI YANG PENTING)
        $lineSeed = ($salesOrder->lines ?? collect())->map(function ($l) {
            return [
                'item_id'         => $l->item_id,
                'item_variant_id' => $l->item_variant_id,
                'name'            => $l->name,
                'description'     => $l->description,
                'qty'             => (float) $l->qty_ordered,                 // <-- kirim qty
                'unit'            => $l->unit ?? 'pcs',
                'unit_price'      => (float) $l->unit_price,
                'discount_type'   => $l->discount_type ?? 'amount',
                'discount_value'  => (float) ($l->discount_value ?? 0),
            ];
        })->values();

        // Default nilai yang dipakai di view (sinkron dengan create)
        $ppnDefault          = (float) ($salesOrder->tax_percent ?? optional($salesOrder->company)->default_tax_percent ?? 0);
        $defaultDiscountMode = $salesOrder->discount_mode ?? 'total';

        return view('sales_orders.edit', compact(
            'salesOrder',
            'items',
            'projects',
            'topOptions',
            'lineSeed',
            'ppnDefault',
            'defaultDiscountMode',
        ));
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
            'customer_po_date'   => ['nullable','date'],
            'po_type'            => ['required','in:goods,project,maintenance'],
            'project_id'         => ['nullable','integer','exists:projects,id'],
            'project_name'       => ['nullable','string','max:255'],
            'deadline'           => ['nullable','date'],
            'ship_to'            => ['nullable','string'],
            'bill_to'            => ['nullable','string'],
            'notes'              => ['nullable','string'],

            'discount_mode' => ['required','in:total,per_item'],

            'total_discount_type'  => ['nullable','in:amount,percent'],
            'total_discount_value' => ['nullable','string'],

            'tax_percent' => ['required','string'],

            'billing_terms' => ['required','array','min:1'],
            'billing_terms.*.top_code' => ['required','string','max:64'],
            'billing_terms.*.percent' => ['required','string'],
            'billing_terms.*.note' => ['nullable','string','max:190'],
            'billing_terms.*.due_trigger' => ['nullable', Rule::in(self::BILLING_DUE_TRIGGERS)],
            'billing_terms.*.offset_days' => ['nullable','integer','min:0'],
            'billing_terms.*.day_of_month' => ['nullable','integer','min:1','max:28'],

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
        $isScope     = in_array(($data['po_type'] ?? 'goods'), ['project', 'maintenance'], true);
        $mode        = $data['discount_mode'];
        $taxPctInput = $parse($data['tax_percent'] ?? 0);
        $taxPct      = ($company->is_taxable ?? false) ? $clamp($taxPctInput, 0, 100) : 0.0;
        $projectId   = !empty($data['project_id']) ? (int) $data['project_id'] : null;
        $projectName = trim((string) ($data['project_name'] ?? ''));
        $projectName = $projectName !== '' ? $projectName : null;

        if (($data['po_type'] ?? 'goods') === 'project' && !$projectId && !$projectName) {
            return back()
                ->withErrors(['project_name' => 'Project wajib diisi (pilih Project atau isi Project Name).'])
                ->withInput();
        }

        if ($isScope) {
            $mode = 'total';
            $data['discount_mode'] = 'total';
        }

        $billingTerms = $this->normalizeBillingTerms($data['billing_terms'] ?? [], $data['po_type'] ?? 'goods');

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
            if ($isScope) {
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

                'item_id'         => $isScope ? null : ($ln['item_id'] ?? null),
                'item_variant_id' => $isScope ? null : ($ln['item_variant_id'] ?? null),
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
        $existingTerms = $salesOrder->billingTerms()->get()->keyBy('top_code');
        foreach ($existingTerms as $code => $term) {
            if (!in_array($term->status, ['invoiced', 'paid'], true)) {
                continue;
            }
            $incoming = collect($billingTerms)->firstWhere('top_code', $code);
            if (!$incoming) {
                return back()
                    ->withErrors(['billing_terms' => "TOP {$code} sudah invoiced/paid dan tidak boleh dihapus."])
                    ->withInput();
            }
            if (abs(((float) $incoming['percent']) - ((float) $term->percent)) > 0.01) {
                return back()
                    ->withErrors(['billing_terms' => "TOP {$code} sudah invoiced/paid dan percent tidak boleh diubah."])
                    ->withInput();
            }
            $incomingTrigger = (string) ($incoming['due_trigger'] ?? '');
            $incomingOffset = $incoming['offset_days'] ?? null;
            $incomingDay = $incoming['day_of_month'] ?? null;
            if ($incomingTrigger !== (string) ($term->due_trigger ?? '')) {
                return back()
                    ->withErrors(['billing_terms' => "TOP {$code} sudah invoiced/paid dan schedule tidak boleh diubah."])
                    ->withInput();
            }
            if ((string) ($incomingOffset ?? '') !== (string) ($term->offset_days ?? '')) {
                return back()
                    ->withErrors(['billing_terms' => "TOP {$code} sudah invoiced/paid dan schedule tidak boleh diubah."])
                    ->withInput();
            }
            if ((string) ($incomingDay ?? '') !== (string) ($term->day_of_month ?? '')) {
                return back()
                    ->withErrors(['billing_terms' => "TOP {$code} sudah invoiced/paid dan schedule tidak boleh diubah."])
                    ->withInput();
            }
        }

        DB::transaction(function () use ($salesOrder,$data,$mode,$sub,$tdType,$tdVal,$totalDc,$dpp,$taxPct,$ppn,$grand,$cleanLines,$projectId,$projectName,$billingTerms,$existingTerms) {
            $salesOrder->update([
                'customer_po_number'    => $data['customer_po_number'],
                'customer_po_date'      => $data['customer_po_date'] ?? null,
                'po_type'               => $data['po_type'],
                'payment_term_id'       => null,
                'payment_term_snapshot' => null,
                'project_id'            => $projectId,
                'project_name'          => $projectName,
                'deadline'              => $data['deadline'] ?? null,
                'ship_to'               => $data['ship_to'] ?? null,
                'bill_to'               => $data['bill_to'] ?? null,
                'notes'                 => $data['notes'] ?? null,
                'private_notes'         => $data['private_notes'] ?? null, 

                'discount_mode'         => $mode,
                'lines_subtotal'        => $sub,
                'total_discount_type'   => $tdType,
                'total_discount_value'  => $tdVal ?? 0,
                'total_discount_amount' => $totalDc,
                'taxable_base'          => $dpp,
                'tax_percent'           => $taxPct,
                'tax_amount'            => $ppn,
                'total'                 => $grand,
                'contract_value'        => $grand,
                'under_amount'          => (float) ($data['under_amount'] ?? 0),
            ]);

            $keepCodes = [];
            foreach ($billingTerms as $term) {
                $code = $term['top_code'];
                $keepCodes[] = $code;
                $existing = $existingTerms->get($code);
                if ($existing) {
                    $dataToUpdate = ['seq' => $term['seq']];
                    if ($existing->status === 'planned') {
                        $dataToUpdate['percent'] = $term['percent'];
                        $dataToUpdate['note'] = $term['note'];
                        $dataToUpdate['due_trigger'] = $term['due_trigger'] ?? null;
                        $dataToUpdate['offset_days'] = $term['offset_days'] ?? null;
                        $dataToUpdate['day_of_month'] = $term['day_of_month'] ?? null;
                    }
                    $existing->update($dataToUpdate);
                } else {
                    $salesOrder->billingTerms()->create([
                        'seq' => $term['seq'],
                        'top_code' => $term['top_code'],
                        'percent' => $term['percent'],
                        'due_trigger' => $term['due_trigger'] ?? null,
                        'offset_days' => $term['offset_days'] ?? null,
                        'day_of_month' => $term['day_of_month'] ?? null,
                        'note' => $term['note'],
                        'status' => $term['status'] ?? 'planned',
                    ]);
                }
            }

            $existingTerms
                ->filter(fn ($t) => $t->status === 'planned' && !in_array($t->top_code, $keepCodes, true))
                ->each->delete();

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

        if ($salesOrder->status === 'fully_billed') {
            return back()->withErrors(['cancel_reason' => 'SO sudah fully billed, tidak dapat dibatalkan.']);
        }

        $salesOrder->loadMissing('billingTerms');
        if ($salesOrder->billingTerms->isNotEmpty() &&
            $salesOrder->billingTerms->every(fn ($t) => $t->status === 'paid')) {
            return back()->withErrors(['cancel_reason' => 'Semua billing term sudah paid, SO tidak bisa dibatalkan.']);
        }

        $validated = $request->validate([
            'cancel_reason' => ['required','string','min:5'],
        ]);

        DB::transaction(function () use ($salesOrder, $validated) {
            $salesOrder->update([
                'status'                => 'cancelled',
                'cancelled_at'          => now(),
                'cancelled_by_user_id'  => auth()->id(),
                'cancel_reason'         => $validated['cancel_reason'],
            ]);

            $salesOrder->billingTerms()
                ->where('status', 'planned')
                ->update(['status' => 'cancelled']);
        });

        session()->forget('so_draft_token');

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
        // 1) Validasi (rules murni, tanpa angka)
        $data = $request->validate([
            'po_number'      => ['required','string','max:100'],
            'po_date'        => ['nullable','date'],
            'po_type'        => ['required','in:goods,project,maintenance'],
            'project_id'     => ['nullable','integer','exists:projects,id'],
            'project_name'   => ['nullable','string','max:255'],
            'deadline'       => ['nullable','date'],
            'ship_to'        => ['nullable','string'],
            'bill_to'        => ['nullable','string'],
            'notes'          => ['nullable','string'],
            'sales_user_id'  => ['nullable','exists:users,id'], // ✅ PERBAIKI INI

            'private_notes'  => ['nullable','string'],
            'under_amount'   => ['nullable','numeric','min:0'],

            'discount_mode'  => ['nullable','in:total,per_item'],
            'tax_percent'    => ['nullable','numeric','min:0'],

            'billing_terms' => ['required','array','min:1'],
            'billing_terms.*.top_code' => ['required','string','max:64'],
            'billing_terms.*.percent' => ['required','string'],
            'billing_terms.*.note' => ['nullable','string','max:190'],
            'billing_terms.*.due_trigger' => ['nullable', Rule::in(self::BILLING_DUE_TRIGGERS)],
            'billing_terms.*.offset_days' => ['nullable','integer','min:0'],
            'billing_terms.*.day_of_month' => ['nullable','integer','min:1','max:28'],

            'draft_token'    => ['nullable','string','max:64'],
        ]);

        // 2) Normalisasi nilai
        $under        = $this->toNumber($data['under_amount'] ?? 0);
        $taxPctInput  = $this->toNumber($data['tax_percent'] ?? ($quotation->tax_percent ?? 0));
        $discountMode = $data['discount_mode'] ?? ($quotation->discount_mode ?? 'total');
        $company      = $quotation->company()->firstOrFail();
        $isTaxable    = (bool)($company->is_taxable ?? false);
        $taxPct       = $isTaxable ? max(min($taxPctInput,100),0) : 0.0;
        $projectId    = !empty($data['project_id']) ? (int) $data['project_id'] : null;
        $projectName  = trim((string) ($data['project_name'] ?? ''));
        $projectName  = $projectName !== '' ? $projectName : null;
        $isScope      = in_array(($data['po_type'] ?? 'goods'), ['project', 'maintenance'], true);

        if (($data['po_type'] ?? 'goods') === 'project' && !$projectId && !$projectName) {
            return back()
                ->withErrors(['project_name' => 'Project wajib diisi (pilih Project atau isi Project Name).'])
                ->withInput();
        }

        if ($isScope) {
            $discountMode = 'total';
        }

        $billingTerms = $this->normalizeBillingTerms($data['billing_terms'] ?? [], $data['po_type'] ?? 'goods');

        // Sales agent: pilih urutan prioritas
        $salesUserId = $request->input('sales_user_id')
            ?: ($quotation->sales_user_id ?: auth()->id());

        /** @var SalesOrder $so */
        $so = DB::transaction(function() use ($quotation, $company, $data, $under, $discountMode, $taxPct, $isTaxable, $salesUserId, $projectId, $projectName, $billingTerms, $isScope) {

            // Nomor SO
            $number = app(DocNumberService::class)->next('sales_order', $company, now());

            // Header
            $so = SalesOrder::create([
                'quotation_id'        => $quotation->id,
                'company_id'          => $quotation->company_id,
                'customer_id'         => $quotation->customer_id,

                'so_number'           => $number,
                'order_date'          => now()->toDateString(),
                'deadline'            => $data['deadline'] ?? null,

                'customer_po_number'  => $data['po_number'],
                'customer_po_date'    => $data['po_date'] ?? null,
                'po_type'             => $data['po_type'],
                'payment_term_id'     => null,
                'payment_term_snapshot' => null,
                'project_id'          => $projectId,
                'project_name'        => $projectName,
                'ship_to'             => $data['ship_to'] ?? null,
                'bill_to'             => $data['bill_to'] ?? null,

                'status'              => 'open',
                'notes'               => $data['notes'] ?? null,
                'private_notes'       => $data['private_notes'] ?? null,
                'under_amount'        => $under,

                'discount_mode'       => $discountMode,
                'tax_percent'         => $taxPct,
                'sales_user_id'       => $salesUserId, // ✅ simpan agent
            ]);

            // Copy lines dari quotation
            $linesSubtotal = 0.0;
            foreach ($quotation->lines as $idx => $ql) {
                $qty       = (float)($ql->qty ?? $ql->quantity ?? 0);
                $unitPrice = (float)($ql->unit_price ?? 0);

                $discType  = $discountMode === 'per_item' ? ($ql->discount_type ?? 'amount') : 'amount';
                $discValue = $discountMode === 'per_item' ? (float)($ql->discount_value ?? 0) : 0.0;
                if ($isScope) {
                    $discType = 'amount';
                    $discValue = 0.0;
                }

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
                    'item_id'          => $isScope ? null : ($ql->item_id ?? null),
                    'item_variant_id'  => $isScope ? null : ($ql->item_variant_id ?? $ql->variant_id ?? null),
                ]);

                $linesSubtotal += $lineTotal;
            }

            foreach ($billingTerms as $term) {
                SalesOrderBillingTerm::create([
                    'sales_order_id' => $so->id,
                    'seq' => $term['seq'],
                    'top_code' => $term['top_code'],
                    'percent' => $term['percent'],
                    'due_trigger' => $term['due_trigger'] ?? null,
                    'offset_days' => $term['offset_days'] ?? null,
                    'day_of_month' => $term['day_of_month'] ?? null,
                    'note' => $term['note'],
                    'status' => $term['status'] ?? 'planned',
                ]);
            }

            // Diskon total (mode total)
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

            $so->update([
                'lines_subtotal'        => $linesSubtotal,
                'total_discount_type'   => $tdType,
                'total_discount_value'  => $discountMode === 'total' ? $tdVal : 0,
                'total_discount_amount' => $discountMode === 'total' ? $totalDiscountAmount : 0,
                'taxable_base'          => $taxableBase,
                'tax_amount'            => $taxAmount,
                'total'                 => $total,
                'contract_value'        => $total,
            ]);

            // Pindahkan lampiran draft → final (kalau ada)
            if (!empty($data['draft_token'])) {
                if (method_exists(SOAtt::class, 'attachFromDraft')) {
                    SOAtt::attachFromDraft($data['draft_token'], $so);
                } else {
                    $rows = SalesOrderAttachment::where('draft_token', $data['draft_token'])->get();
                    foreach ($rows as $att) {
                        $disk = $att->disk ?: 'public';
                        $old  = $att->path;
                        $filename = basename($old);
                        // NOTE: folder boleh diseragamkan; di sini tetap "so_attachments"
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

            // Tandai quotation WON
            $quotation->update([
                'status'         => 'won',
                'won_at'         => now(),
                'sales_order_id' => $so->id,
            ]);

            return $so;
        });

        if ($request->wantsJson()) {
            return response()->json(['ok' => true, 'id' => $so->id, 'number' => $so->so_number]);
        }

        session()->forget('so_draft_token');

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

    private function normalizeBillingTerms(array $terms, ?string $poType = null): array
    {
        $tops = TermOfPayment::query()
            ->whereIn('code', TermOfPayment::ALLOWED_CODES)
            ->get(['code','applicable_to']);
        $allowedMap = [];
        $applyMap = [];
        foreach ($tops as $top) {
            $code = strtoupper((string) $top->code);
            $allowedMap[$code] = true;
            $applyMap[$code] = is_array($top->applicable_to) ? $top->applicable_to : [];
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
            if ($poType && !empty($applyMap[$code]) && !in_array($poType, $applyMap[$code], true)) {
                throw ValidationException::withMessages([
                    "billing_terms.$idx.top_code" => 'Kode TOP tidak sesuai dengan PO Type.',
                ]);
            }

            if (isset($seen[$code])) {
                throw ValidationException::withMessages([
                    "billing_terms.$idx.top_code" => 'Kode TOP duplikat di Sales Order.',
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

    private function defaultPaymentTermIdFor(string $poType): ?int
    {
        $poType = $poType ?: 'goods';
        if ($poType === 'goods') {
            $id = TermOfPayment::where('code', 'DP50_BALANCE_ON_DELIVERY')->value('id');
            if ($id) return (int) $id;
        }

        $row = TermOfPayment::query()
            ->where('is_active', true)
            ->get(['id','applicable_to'])
            ->first(function ($term) use ($poType) {
                $applies = $term->applicable_to;
                if (!is_array($applies) || count($applies) === 0) return true;
                return in_array($poType, $applies, true);
            });

        return $row?->id ? (int) $row->id : null;
    }

    private function termMatchesPoType(TermOfPayment $term, string $poType): bool
    {
        $applies = $term->applicable_to;
        if (!is_array($applies) || count($applies) === 0) {
            return true;
        }
        return in_array($poType, $applies, true);
    }

}
