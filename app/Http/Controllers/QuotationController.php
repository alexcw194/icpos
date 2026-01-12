<?php

namespace App\Http\Controllers;

use App\Models\{Quotation, QuotationLine, Customer, Item, Company, User};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;
use App\Services\QuotationCalculator;
use App\Support\Number;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Support\Facades\Mail;
use App\Mail\QuotationPdfMail;
use App\Support\MailConfigurator;

class QuotationController extends Controller
{
    /** Hanya 3 status ini yang dipakai */
    private const ALLOWED_STATUS = ['draft','sent','won'];

    public function index()
    {
        $q       = trim((string) request('q', ''));
        $cid     = request('company_id');
        $status  = request('status'); // 'draft' | 'sent' | 'won'

        // Dukung keduanya: ?selected=ID (baru) ATAU ?preview=ID (lama)
        $selectedId  = request()->has('selected') ? request('selected') : request('preview');
        $highlightId = (int) $selectedId;

        $query = Quotation::query()
            ->visibleTo(auth()->user())
            ->with(['customer','company'])
            ->latest('date')->latest('id');

        if ($cid) {
            $query->where('company_id', $cid);
        }
        if ($status && in_array($status, self::ALLOWED_STATUS, true)) {
            $query->where('status', $status);
        }
        if ($q !== '') {
            $query->where(function ($w) use ($q) {
                $w->where('number', 'like', "%{$q}%")
                ->orWhereHas('customer', fn($c) => $c->where('name','like',"%{$q}%"));
            });
        }

        $quotations = $query->paginate(15)->withQueryString();
        $companies  = Company::orderBy('name')->get(['id','alias','name']);

        // Data untuk panel preview (kanan) — wajib ikut visibleTo
        $active = null;
        if ($highlightId) {
            $active = Quotation::query()
                ->visibleTo(auth()->user())
                ->with(['customer','company','salesUser','lines'])
                ->whereKey($highlightId)
                ->first();
        }

        // Kompat untuk Blade lama agar tidak "undefined variable"
        $rows      = $quotations;
        $preview   = $active;
        $previewId = $highlightId;

        return view('quotations.index', compact(
            'quotations','companies','cid','status','q',
            'active','rows','preview','previewId'
        ));
    }


    public function create()
    {
        $customers = Customer::orderBy('name')->get(['id','name']);

        $items = Item::query()
            ->with('unit:id,code')
            ->orderBy('name')
            ->get(['id','name','price','unit_id']);

        $companies = Company::orderBy('name')->get([
            'id','name','alias','is_taxable','default_tax_percent','quotation_prefix','default_valid_days'
        ]);

        $defaultCompanyId = Company::where('is_default', true)->value('id')
            ?? optional(Company::where('alias','ICP')->first())->id
            ?? ($companies->first()->id ?? null);

        $sales               = User::orderBy('name')->get(['id','name']);
        $defaultSalesUserId  = auth()->id();
        $defaultDiscountMode = 'total';

        return view('quotations.create', compact(
            'customers','items','companies','defaultCompanyId','sales','defaultSalesUserId','defaultDiscountMode'
        ));
    }

    public function store(Request $request, QuotationCalculator $calc)
    {
        $data = $this->normalizeQuotation($request->all());

        if (!isset($data['discount_mode']) || !in_array($data['discount_mode'], ['total','per_item'], true)) {
            $data['discount_mode'] = 'total';
        }

        $v = $this->validateQuotation($data);

        if (($v['discount_mode'] ?? 'total') === 'total' && !empty($v['lines'])) {
            foreach ($v['lines'] as &$ln) {
                $ln['discount_type']  = 'amount';
                $ln['discount_value'] = 0;
            }
            unset($ln);
        }

        $company = Company::findOrFail($v['company_id']);
        $taxPercent = $company->is_taxable
            ? (isset($v['tax_percent']) ? (float)$v['tax_percent'] : (float)$company->default_tax_percent)
            : 0.0;

        $computed = $calc->compute(array_merge($v, ['tax_percent' => $taxPercent]));

        $validUntil = $v['valid_until'] ?? Carbon::parse($v['date'])
            ->addDays($company->default_valid_days ?? 30)
            ->toDateString();

        $brand = $this->brandSnapshot($company);

        DB::transaction(function () use ($v, $company, $brand, $computed, $validUntil) {

            $quotation = Quotation::create([
                'company_id'     => $company->id,
                'customer_id'    => $v['customer_id'],
                'sales_user_id'  => $v['sales_user_id'] ?? auth()->id(),
                'discount_mode'  => $v['discount_mode'] ?? 'total',
                'number'         => 'TEMP',
                'date'           => $v['date'],
                'valid_until'    => $validUntil,
                'status'         => 'draft',
                'sent_at'        => null,
                'notes'          => $v['notes'] ?? null,
                'terms'          => $v['terms'] ?? null,
                'currency'       => 'IDR',

                'lines_subtotal'        => $computed['lines_subtotal'],
                'total_discount_type'   => $computed['total_discount_type'],
                'total_discount_value'  => $computed['total_discount_value'],
                'total_discount_amount' => $computed['total_discount_amount'],
                'taxable_base'          => $computed['taxable_base'],
                'tax_percent'           => $computed['tax_percent'],
                'tax_amount'            => $computed['tax_amount'],
                'total'                 => $computed['total'],

                'brand_snapshot' => $brand,
            ]);

            $quotation->update([
                'number' => app(\App\Services\DocNumberService::class)
                    ->next('quotation', $company, Carbon::parse($v['date'])),
            ]);

            foreach ($computed['lines'] as $i => $line) {
                QuotationLine::create([
                    'quotation_id'   => $quotation->id,
                    'name'           => $line['name'] ?? '',
                    'description'    => $line['description'] ?? null,
                    'qty'            => $line['qty'] ?? 0,
                    'unit'           => $line['unit'] ?? 'pcs',
                    'unit_price'     => $line['unit_price'] ?? 0,
                    'discount_type'  => $line['discount_type'] ?? 'amount',
                    'discount_value' => $line['discount_value'] ?? 0,
                    'discount_amount'=> $line['discount_amount'] ?? 0,
                    'line_subtotal'  => $line['line_subtotal'] ?? 0,
                    'line_total'     => $line['line_total'] ?? 0,

                    // NEW: link ke master item/varian dari input tervalidasi
                    'item_id'         => $v['lines'][$i]['item_id']         ?? null,
                    'item_variant_id' => $v['lines'][$i]['item_variant_id'] ?? null,
                ]);
            }
        });

        return redirect()->route('quotations.index')->with('success', 'Quotation created!');
    }

    public function show(Quotation $quotation)
    {
        abort_unless(
            Quotation::query()->visibleTo(auth()->user())->whereKey($quotation->id)->exists(),
            403,
            'This action is unauthorized.'
        );

        $quotation->load([
            'customer','company','salesUser','lines','invoice.delivery',
            'salesOrders' => fn($q) => $q->latest()
        ]);

        return view('quotations.show', compact('quotation'));
    }


    public function print(Quotation $quotation)
    {
        $quotation->load(['customer','company','salesUser','lines']);
        return view('quotations.print', compact('quotation'));
    }

    public function pdf(Quotation $quotation)
    {
        $quotation->load(['customer','company','salesUser','lines']);
        $html = view('quotations.pdf', compact('quotation'))->render();

        $opt = new Options();
        $opt->set('isRemoteEnabled', true);
        $opt->set('isHtml5ParserEnabled', true);

        $pdf = new Dompdf($opt);
        $pdf->loadHtml($html);
        $pdf->setPaper('A4', 'portrait');
        $pdf->render();

        $filename = $quotation->number . '.pdf';
        return response($pdf->output(), 200)
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', 'inline; filename="'.$filename.'"');
    }

    public function pdfDownload(Quotation $quotation)
    {
        $quotation->load(['customer','company','salesUser','lines']);
        $html = view('quotations.pdf', compact('quotation'))->render();

        $opt = new Options();
        $opt->set('isRemoteEnabled', true);
        $opt->set('isHtml5ParserEnabled', true);

        $pdf = new Dompdf($opt);
        $pdf->loadHtml($html);
        $pdf->setPaper('A4', 'portrait');
        $pdf->render();

        $filename = $quotation->number . '.pdf';
        return response($pdf->output(), 200)
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', 'attachment; filename="'.$filename.'"');
    }

    public function emailPdf(Quotation $quotation)
    {
        $quotation->load(['customer','company','salesUser','lines']);

        // 1) Validasi penerima
        $to = trim((string) ($quotation->customer->email ?? ''));
        if ($to === '') {
            return back()->with('error', 'Customer belum memiliki email.');
        }

        // 2) Render PDF
        $pdf = app('dompdf.wrapper');
        $pdf->setPaper('A4');
        $pdf->loadView('quotations.pdf', ['quotation' => $quotation]);
        $pdfBinary = $pdf->output();

        $companyName = $quotation->company->alias ?? $quotation->company->name ?? 'Company';
        $subject     = 'Quotation '.$quotation->number.' — '.$companyName;

        // 3) Pengirim = sales user atau user login
        $sender = $quotation->salesUser ?? auth()->user();

        // 4) Cek policy & kredensial user (hindari kirim dengan password kosong)
        $policy = \App\Models\Setting::get('mail.username_policy', 'default_email');
        if (in_array($policy, ['force_email','custom_only'], true) && empty($sender->smtp_password)) {
            return back()->with('error', 'SMTP password belum diisi di profil sales (policy: '.$policy.').');
        }

        try {
            // 5) Override mailer dari Global Settings + profil user (menyalip .env)
            \App\Support\MailConfigurator::applyUser($sender);

            // From efektif
            $fromEmail = config('mail.from.address');
            $fromName  = config('mail.from.name');

            // Log config efektif (tanpa password)
            \Log::info('MAIL effective', [
                'driver' => config('mail.default'),
                'host'   => config('mail.mailers.smtp.host'),
                'port'   => config('mail.mailers.smtp.port'),
                'enc'    => config('mail.mailers.smtp.encryption'),
                'user'   => config('mail.mailers.smtp.username'),
            ]);

            // 6) Siapkan mailable
            $mailable = (new \App\Mail\QuotationPdfMail($quotation, $pdfBinary, $subject))
                ->from($fromEmail, $fromName)
                ->replyTo($sender->email ?? $fromEmail, $sender->name ?? $fromName);

            // 7) Tentukan CC sesuai preferensi user (email_cc_self) → ke email login user
            $cc = null;
            if (!empty($sender->email_cc_self)) {
                $cc = $sender->email ?? null;
            }

            // 8) Kirim
            $mailer = \Mail::to($to);

            // tambahkan CC bila dipilih & tidak duplikat TO/FROM
            if ($cc
                && strcasecmp($cc, $to) !== 0
                && strcasecmp($cc, $fromEmail) !== 0) {
                $mailer->cc($cc);
            }

            $mailer->send($mailable);

            \Log::info('Quotation PDF mailed', [
                'quotation_id' => $quotation->id,
                'number'       => $quotation->number,
                'to'           => $to,
                'cc'           => $cc,
                'from'         => $fromEmail,
                'at'           => now()->toDateTimeString(),
            ]);

            // 9) Auto mark Sent
            if ($quotation->status === 'draft') {
                $quotation->update([
                    'status'  => 'sent',
                    'sent_at' => now(),
                ]);
            }

            // Notifikasi profesional (tanpa "via ...")
            return back()->with('ok', "Quotation {$quotation->number} berhasil dikirim ke {$to}. Status ditandai Sent.");
        } catch (\Throwable $e) {
            \Log::error('Quotation PDF mail FAILED', [
                'quotation_id' => $quotation->id,
                'number'       => $quotation->number,
                'to'           => $to,
                'error'        => $e->getMessage(),
            ]);
            return back()->with('error', 'Gagal mengirim email: '.$e->getMessage());
        }
    }




    public function edit(Quotation $quotation)
    {
        $quotation->load(['lines','customer','company','salesUser']);

        $customers = Customer::orderBy('name')->get(['id','name']);
        $items     = Item::query()->with('unit:id,code')->orderBy('name')->get(['id','name','price','unit_id']);

        $companies = Company::orderBy('name')->get([
            'id','name','alias','is_taxable','default_tax_percent','quotation_prefix','default_valid_days'
        ]);

        $defaultCompanyId   = $quotation->company_id;
        $canChangeCompany   = $quotation->status === 'draft';

        $sales               = User::orderBy('name')->get(['id','name']);
        $defaultSalesUserId  = $quotation->sales_user_id ?? auth()->id();
        $defaultDiscountMode = $quotation->discount_mode ?? 'total';

        return view('quotations.edit', compact(
            'quotation','customers','items','companies','defaultCompanyId','canChangeCompany',
            'sales','defaultSalesUserId','defaultDiscountMode'
        ));
    }

    public function update(Request $request, Quotation $quotation, QuotationCalculator $calc)
    {
        if ($quotation->status !== 'draft') {
            return back()->with('warning', 'Quotation hanya bisa diedit saat status draft.');
        }

        $data = $this->normalizeQuotation($request->all());
        if (!isset($data['discount_mode']) || !in_array($data['discount_mode'], ['total','per_item'], true)) {
            $data['discount_mode'] = 'total';
        }

        $v = $this->validateQuotation($data);

        if (($v['discount_mode'] ?? 'total') === 'total' && !empty($v['lines'])) {
            foreach ($v['lines'] as &$ln) {
                $ln['discount_type']  = 'amount';
                $ln['discount_value'] = 0;
            }
            unset($ln);
        }

        $companyNew = Company::findOrFail($v['company_id']);
        $taxPercent = $companyNew->is_taxable
            ? (isset($v['tax_percent']) ? (float)$v['tax_percent'] : (float)$companyNew->default_tax_percent)
            : 0.0;

        $computed = $calc->compute(array_merge($v, ['tax_percent' => $taxPercent]));

        $validUntil = $v['valid_until'] ?? Carbon::parse($v['date'])
            ->addDays($companyNew->default_valid_days ?? 30)
            ->toDateString();

        $brand = $this->brandSnapshot($companyNew);

        DB::transaction(function () use ($quotation, $v, $companyNew, $brand, $computed, $validUntil) {

            $oldCompanyId = $quotation->company_id;
            $oldYear      = Carbon::parse($quotation->date)->year;
            $newYear      = Carbon::parse($v['date'])->year;

            $quotation->update([
                'company_id'     => $companyNew->id,
                'customer_id'    => $v['customer_id'],
                'sales_user_id'  => $v['sales_user_id'] ?? $quotation->sales_user_id ?? auth()->id(),
                'discount_mode'  => $v['discount_mode'] ?? 'total',
                'date'           => $v['date'],
                'valid_until'    => $validUntil,
                'notes'          => $v['notes'] ?? null,
                'terms'          => $v['terms'] ?? null,
                'currency'       => 'IDR',

                'lines_subtotal'        => $computed['lines_subtotal'],
                'total_discount_type'   => $computed['total_discount_type'],
                'total_discount_value'  => $computed['total_discount_value'],
                'total_discount_amount' => $computed['total_discount_amount'],
                'taxable_base'          => $computed['taxable_base'],
                'tax_percent'           => $computed['tax_percent'],
                'tax_amount'            => $computed['tax_amount'],
                'total'                 => $computed['total'],

                'brand_snapshot' => $brand,
            ]);

            if ($oldCompanyId !== $companyNew->id || $oldYear !== $newYear) {
                $quotation->update([
                    'number' => app(\App\Services\DocNumberService::class)
                        ->next('quotation', $companyNew, Carbon::parse($v['date'])),
                ]);
            }

            $quotation->lines()->delete();
            foreach ($computed['lines'] as $i => $line) {
                QuotationLine::create([
                    'quotation_id'   => $quotation->id,
                    'name'           => $line['name'] ?? '',
                    'description'    => $line['description'] ?? null,
                    'qty'            => $line['qty'] ?? 0,
                    'unit'           => $line['unit'] ?? 'pcs',
                    'unit_price'     => $line['unit_price'] ?? 0,
                    'discount_type'  => $line['discount_type'] ?? 'amount',
                    'discount_value' => $line['discount_value'] ?? 0,
                    'discount_amount'=> $line['discount_amount'] ?? 0,
                    'line_subtotal'  => $line['line_subtotal'] ?? 0,
                    'line_total'     => $line['line_total'] ?? 0,

                    // NEW:
                    'item_id'         => $v['lines'][$i]['item_id']         ?? null,
                    'item_variant_id' => $v['lines'][$i]['item_variant_id'] ?? null,
                ]);
            }
        });

        return redirect()->route('quotations.index', ['selected' => $quotation->id])
            ->with('success', 'Quotation updated!');
    }

    public function destroy(Quotation $quotation)
    {
        if ($quotation->status !== 'draft') {
            return back()->with('warning', 'Hanya quotation draft yang boleh dihapus.');
        }
        $quotation->delete();
        return redirect()->route('quotations.index')->with('success', 'Quotation deleted!');
    }

    /* ===============================
     |   STATUS ACTIONS (3 status)
     |===============================*/

    /** Tandai SENT & set sent_at */
    public function markSent(Quotation $quotation)
    {
        if (!in_array($quotation->status, ['draft'], true)) {
            return back()->with('warning', 'Hanya draft yang bisa ditandai terkirim.');
        }

        $quotation->update([
            'status'  => 'sent',
            'sent_at' => now(),
        ]);

        return back()->with('success', 'Quotation ditandai sebagai terkirim.');
    }

    /** Kembalikan ke DRAFT (hapus sent_at) */
    public function markDraft(Quotation $quotation)
    {
        if (!in_array($quotation->status, ['sent','won'], true)) {
            return back()->with('warning', 'Status sekarang tidak bisa dikembalikan ke draft.');
        }

        $quotation->update([
            'status'  => 'draft',
            'sent_at' => null,
            'won_at'  => null,
        ]);

        return back()->with('success', 'Quotation dikembalikan ke draft.');
    }

    /** Tandai menjadi PO (quotation disetujui / dibuatkan PO) */
    public function markWon(Quotation $quotation)
    {
        if (!in_array($quotation->status, ['sent','draft'], true)) {
            return back()->with('warning', 'Quotation hanya bisa diubah ke Won dari draft/terkirim.');
        }

        $quotation->update([
            'status' => 'won',
            'won_at' => now(),
        ]);

        return back()->with('success', 'Quotation ditandai sebagai Won.');
    }

    /* ===============================
     |   Helpers
     |===============================*/

    private function validateQuotation(array $data): array
    {
        return validator($data, [
            'company_id'              => ['required','exists:companies,id'],
            'customer_id'             => ['required','exists:customers,id'],
            'contact_id'              => ['nullable','exists:contacts,id'],
            'sales_user_id'           => ['nullable','exists:users,id'],
            'discount_mode'           => ['required','in:total,per_item'],
            'date'                    => ['required','date'],
            'valid_until'             => ['nullable','date','after_or_equal:date'],
            'currency'                => ['nullable','string','size:3'],
            'notes'                   => ['nullable','string'],
            'terms'                   => ['nullable','string'],

            'tax_percent'             => ['nullable','numeric','min:0','max:100'],

            'total_discount_type'     => ['required','in:amount,percent'],
            'total_discount_value'    => ['required','numeric','min:0'],

            'lines'                   => ['required','array','min:1'],
            'lines.*.name'            => ['required','string','max:255'],
            'lines.*.description'     => ['nullable','string'],
            'lines.*.qty'             => ['required','numeric','min:0'],
            'lines.*.unit'            => ['nullable','string','max:16'],
            'lines.*.unit_price'      => ['required','numeric','min:0'],
            'lines.*.discount_type'   => ['required','in:amount,percent'],
            'lines.*.discount_value'  => ['required','numeric','min:0'],
            'lines.*.item_id'         => ['nullable','integer','exists:items,id'],
            'lines.*.item_variant_id' => ['nullable','integer','exists:item_variants,id'],

        ])->validate();
    }

    private function normalizeQuotation(array $data): array
    {
        if (array_key_exists('tax_percent', $data)) {
            $data['tax_percent'] = Number::idToFloat($data['tax_percent']);
        }
        if (array_key_exists('total_discount_value', $data)) {
            $data['total_discount_value'] = Number::idToFloat($data['total_discount_value']);
        }

        if (!empty($data['lines']) && is_array($data['lines'])) {
            foreach ($data['lines'] as $i => $ln) {
                $data['lines'][$i]['qty']            = Number::idToFloat($ln['qty'] ?? 0);
                $data['lines'][$i]['unit_price']     = Number::idToFloat($ln['unit_price'] ?? 0);
                $data['lines'][$i]['discount_value'] = Number::idToFloat($ln['discount_value'] ?? 0);
            }
        }
        return $data;
    }

    private function brandSnapshot(Company $company): array
    {
        return [
            'name'      => $company->name,
            'alias'     => $company->alias,
            'address'   => $company->address,
            'tax_id'    => $company->tax_id,
            'logo_path' => $company->logo_path,
            'phone'     => $company->phone,
            'email'     => $company->email,
            'bank'      => [
                'name'         => $company->bank_name,
                'account_name' => $company->bank_account_name,
                'account_no'   => $company->bank_account_no,
                'branch'       => $company->bank_account_branch,
            ],
            'templates' => [
                'quotation' => $company->quotation_template ?? null,
                'invoice'   => $company->invoice_template ?? null,
                'delivery'  => $company->delivery_template ?? null,
            ],
            'prefix' => [
                'quotation' => $company->quotation_prefix,
                'invoice'   => $company->invoice_prefix,
                'delivery'  => $company->delivery_prefix,
            ],
        ];
    }

    public function preview(Quotation $quotation)
    {
        $quotation->load([
            'customer','company','salesUser','lines',
            'salesOrders' => fn($q) => $q->latest()
        ]);

        if (!request()->ajax()) {
            return redirect()->route('quotations.index', array_merge(
                request()->except(['page','preview','selected']),
                ['preview' => $quotation->id]
            ));
        }

        return view('quotations._preview', compact('quotation'));
    }
}
