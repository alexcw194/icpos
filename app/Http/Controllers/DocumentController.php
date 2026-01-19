<?php

namespace App\Http\Controllers;

use App\Models\Contact;
use App\Models\Customer;
use App\Models\Document;
use App\Models\Signature;
use App\Models\User;
use App\Services\DocumentNumberService;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DocumentController extends Controller
{
    public function my()
    {
        $user = auth()->user();
        $documents = Document::query()
            ->where('created_by_user_id', $user->id)
            ->with(['customer', 'creator'])
            ->latest('updated_at')
            ->paginate(15)
            ->withQueryString();

        return view('documents.index', [
            'documents' => $documents,
            'pageTitle' => 'My Documents',
            'showOwner' => false,
            'showCreate' => $user->hasRole('Sales'),
            'mode' => 'my',
        ]);
    }

    public function index()
    {
        $this->authorize('viewAny', Document::class);

        $documents = Document::query()
            ->with(['customer', 'creator'])
            ->latest('updated_at')
            ->paginate(20)
            ->withQueryString();

        return view('documents.index', [
            'documents' => $documents,
            'pageTitle' => 'All Documents',
            'showOwner' => true,
            'showCreate' => auth()->user()?->hasAnyRole(['Admin', 'SuperAdmin']),
            'mode' => 'all',
        ]);
    }

    public function pending()
    {
        $user = auth()->user();
        abort_unless($user && $user->hasAnyRole(['Admin', 'SuperAdmin']), 403);

        $query = Document::query()
            ->where('status', Document::STATUS_SUBMITTED)
            ->with(['customer', 'creator']);

        if ($user->hasRole('SuperAdmin')) {
            $query->whereNotNull('admin_approved_at')
                ->whereNull('approved_at');
        } else {
            $query->whereNull('admin_approved_at');
        }

        $documents = $query->latest('submitted_at')->paginate(20)->withQueryString();

        return view('documents.index', [
            'documents' => $documents,
            'pageTitle' => 'Pending Approval',
            'showOwner' => true,
            'showCreate' => false,
            'mode' => 'pending',
        ]);
    }

    public function create()
    {
        $this->authorize('create', Document::class);

        $user = auth()->user();
        $document = new Document([
            'status' => Document::STATUS_DRAFT,
        ]);

        $customers = Customer::query()
            ->visibleTo($user)
            ->ordered()
            ->get(['id', 'name']);

        $signature = Signature::query()
            ->where('user_id', $user->id)
            ->first();

        $salesUsers = $this->salesSignerOptions();
        $directorUsers = $this->directorSignerOptions();

        return view('documents.form', [
            'document' => $document,
            'customers' => $customers,
            'signature' => $signature,
            'salesUsers' => $salesUsers,
            'directorUsers' => $directorUsers,
            'mode' => 'create',
        ]);
    }

    public function store(Request $request)
    {
        $this->authorize('create', Document::class);

        $data = $this->validateDocument($request);
        $user = $request->user();

        $customer = Customer::query()
            ->visibleTo($user)
            ->findOrFail($data['customer_id']);

        $contact = null;
        if (!empty($data['contact_id'])) {
            $contact = Contact::query()
                ->whereKey($data['contact_id'])
                ->where('customer_id', $customer->id)
                ->firstOrFail();
        }

        $signature = $this->storeSignature($request, $user);
        $salesSignerId = $this->resolveSalesSignerId($user, $data);
        $directorId = $this->resolveDirectorId($user, $data);

        $document = Document::create([
            'title' => $data['title'],
            'body_html' => $this->sanitizeHtml($data['body_html']),
            'customer_id' => $customer->id,
            'contact_id' => $contact?->id,
            'customer_snapshot' => $this->customerSnapshot($customer),
            'contact_snapshot' => $contact ? $this->contactSnapshot($contact) : null,
            'created_by_user_id' => $user->id,
            'sales_signer_user_id' => $salesSignerId,
            'director_user_id' => $directorId,
            'status' => Document::STATUS_DRAFT,
            'sales_signature_position' => $data['sales_signature_position'] ?? ($signature?->default_position),
        ]);

        return redirect()
            ->route('documents.edit', $document)
            ->with('success', 'Draft dokumen dibuat.');
    }

    public function show(Document $document)
    {
        $this->authorize('view', $document);

        $document->load(['customer', 'contact', 'creator', 'adminApprover', 'approver', 'salesSigner', 'directorSigner']);

        return view('documents.show', compact('document'));
    }

    public function edit(Document $document)
    {
        $this->authorize('update', $document);

        $user = auth()->user();
        $document->load('contact');
        $customers = Customer::query()
            ->visibleTo($user)
            ->ordered()
            ->get(['id', 'name']);

        $signature = Signature::query()
            ->where('user_id', $user->id)
            ->first();

        $salesUsers = $this->salesSignerOptions();
        $directorUsers = $this->directorSignerOptions();

        return view('documents.form', [
            'document' => $document,
            'customers' => $customers,
            'signature' => $signature,
            'salesUsers' => $salesUsers,
            'directorUsers' => $directorUsers,
            'mode' => 'edit',
        ]);
    }

    public function update(Request $request, Document $document)
    {
        $this->authorize('update', $document);

        $data = $this->validateDocument($request);
        $user = $request->user();

        $customer = Customer::query()
            ->visibleTo($user)
            ->findOrFail($data['customer_id']);

        $contact = null;
        if (!empty($data['contact_id'])) {
            $contact = Contact::query()
                ->whereKey($data['contact_id'])
                ->where('customer_id', $customer->id)
                ->firstOrFail();
        }

        $signature = $this->storeSignature($request, $user);
        $salesSignerId = $this->resolveSalesSignerId($user, $data, $document);
        $directorId = $this->resolveDirectorId($user, $data, $document);

        $document->update([
            'title' => $data['title'],
            'body_html' => $this->sanitizeHtml($data['body_html']),
            'customer_id' => $customer->id,
            'contact_id' => $contact?->id,
            'customer_snapshot' => $this->customerSnapshot($customer),
            'contact_snapshot' => $contact ? $this->contactSnapshot($contact) : null,
            'sales_signer_user_id' => $salesSignerId,
            'director_user_id' => $directorId,
            'sales_signature_position' => $data['sales_signature_position'] ?? ($signature?->default_position),
        ]);

        return redirect()
            ->route('documents.edit', $document)
            ->with('success', 'Dokumen disimpan.');
    }

    public function submit(Document $document)
    {
        $this->authorize('submit', $document);

        $salesSignerId = $document->sales_signer_user_id ?: $document->created_by_user_id;
        $salesSignature = Signature::query()
            ->where('user_id', $salesSignerId)
            ->first();

        if (!$salesSignature) {
            return back()->with('error', 'Tanda tangan Sales belum diunggah.');
        }

        DB::transaction(function () use ($document) {
            if (!$document->number) {
                $next = DocumentNumberService::next();
                $document->number = $next['number'];
                $document->year = $next['year'];
                $document->sequence = $next['sequence'];
            }

            $document->status = Document::STATUS_SUBMITTED;
            $document->submitted_at = now();
            $document->rejected_at = null;
            $document->rejected_by_user_id = null;
            $document->rejection_note = null;
            $document->save();
        });

        return redirect()
            ->route('documents.show', $document)
            ->with('success', 'Dokumen dikirim untuk approval.');
    }

    public function approve(Document $document)
    {
        $this->authorize('approve', $document);

        $user = auth()->user();
        $signature = Signature::query()
            ->where('user_id', $user->id)
            ->first();

        if (!$signature) {
            return back()->with('error', 'Tanda tangan approver belum diunggah.');
        }

        $salesSignerId = $document->sales_signer_user_id ?: $document->created_by_user_id;
        $salesSignature = Signature::query()
            ->where('user_id', $salesSignerId)
            ->first();

        if (!$salesSignature) {
            return back()->with('error', 'Tanda tangan Sales belum tersedia.');
        }

        $signatures = $document->signatures ?? [];
        $salesUser = User::query()->find($salesSignerId);
        $signatures['sales'] = [
            'user_id' => $salesSignerId,
            'name' => $salesUser?->name,
            'image_path' => $salesSignature->image_path,
            'position' => $document->sales_signature_position ?? $salesSignature->default_position,
        ];
        $signatures['approver'] = [
            'user_id' => $user->id,
            'image_path' => $signature->image_path,
            'position' => $document->approver_signature_position ?? $signature->default_position,
        ];

        $document->update([
            'admin_approved_by_user_id' => $user->id,
            'admin_approved_at' => now(),
            'approver_signature_position' => $document->approver_signature_position ?? $signature->default_position,
            'signatures' => $signatures,
        ]);

        return redirect()
            ->route('documents.pending')
            ->with('success', 'Dokumen disetujui (level Admin).');
    }

    public function finalApprove(Document $document)
    {
        $this->authorize('finalApprove', $document);

        $directorUser = null;
        $directorSignature = null;
        if ($document->director_user_id) {
            $directorUser = User::query()->find($document->director_user_id);
            $directorSignature = Signature::query()
                ->where('user_id', $document->director_user_id)
                ->first();

            if (!$directorSignature) {
                return back()->with('error', 'Tanda tangan Direktur belum diunggah.');
            }
        }

        $signatures = $document->signatures ?? [];
        $signatures['director'] = [
            'name' => $directorUser?->name ?? 'Christian Widargo',
            'position' => $directorSignature?->default_position ?? 'Direktur Utama',
            'image_path' => $directorSignature?->image_path ?: $this->directorSignaturePath(),
        ];

        $document->update([
            'status' => Document::STATUS_APPROVED,
            'approved_by_user_id' => auth()->id(),
            'approved_at' => now(),
            'signatures' => $signatures,
        ]);

        return redirect()
            ->route('documents.show', $document)
            ->with('success', 'Dokumen disetujui final.');
    }

    public function reject(Request $request, Document $document)
    {
        $this->authorize('reject', $document);

        $data = $request->validate([
            'rejection_note' => ['required', 'string', 'max:1000'],
        ]);

        $document->update([
            'status' => Document::STATUS_REJECTED,
            'submitted_at' => null,
            'admin_approved_by_user_id' => null,
            'admin_approved_at' => null,
            'approved_by_user_id' => null,
            'approved_at' => null,
            'rejected_by_user_id' => auth()->id(),
            'rejected_at' => now(),
            'rejection_note' => $data['rejection_note'],
            'signatures' => null,
        ]);

        return redirect()
            ->route('documents.show', $document)
            ->with('success', 'Dokumen ditolak dan dikembalikan ke draft.');
    }

    public function pdf(Document $document)
    {
        $this->authorize('view', $document);
        return $this->renderPdf($document, 'inline');
    }

    public function pdfDownload(Document $document)
    {
        $this->authorize('view', $document);
        return $this->renderPdf($document, 'attachment');
    }

    private function validateDocument(Request $request): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:190'],
            'body_html' => ['required', 'string'],
            'customer_id' => ['required', 'exists:customers,id'],
            'contact_id' => ['nullable', 'exists:contacts,id'],
            'sales_signer_user_id' => ['nullable', 'exists:users,id'],
            'director_user_id' => ['nullable', 'exists:users,id'],
            'sales_signature_position' => ['nullable', 'string', 'max:120'],
            'signature_file' => ['nullable', 'image', 'mimes:png,jpg,jpeg', 'max:2048'],
        ]);
    }

    private function customerSnapshot(Customer $customer): array
    {
        return [
            'name' => $customer->name,
            'address' => $customer->address,
            'city' => $customer->city,
            'province' => $customer->province,
            'country' => $customer->country,
            'phone' => $customer->phone,
            'email' => $customer->email,
            'npwp' => $customer->npwp,
        ];
    }

    private function contactSnapshot(Contact $contact): array
    {
        return [
            'name' => $contact->full_name,
            'email' => $contact->email,
            'phone' => $contact->phone,
            'title' => $contact->title_label,
            'position' => $contact->position_label,
        ];
    }

    private function sanitizeHtml(string $html): string
    {
        $allowed = '<p><br><strong><b><em><i><u><ul><ol><li><div><span><h1><h2><h3><h4><h5><h6><table><thead><tbody><tr><td><th><hr>';
        $clean = strip_tags($html, $allowed);
        $clean = preg_replace('/on\\w+=("|\')[^"\']*\\1/i', '', $clean);
        $clean = preg_replace('/javascript:/i', '', $clean);

        $clean = preg_replace_callback('/\\sstyle=("|\')(.*?)\\1/i', function ($m) {
            $style = $this->sanitizeStyle($m[2]);
            return $style !== '' ? ' style="'.$style.'"' : '';
        }, $clean);

        return trim($clean);
    }

    private function sanitizeStyle(string $style): string
    {
        $allowed = [
            'text-align' => '/^(left|right|center|justify)$/',
            'font-size' => '/^\\d+(\\.\\d+)?(px|pt|em|rem|%)$/',
            'line-height' => '/^\\d+(\\.\\d+)?(px|pt|em|rem|%)?$/',
            'margin-top' => '/^\\d+(\\.\\d+)?(px|pt|em|rem)?$/',
            'margin-bottom' => '/^\\d+(\\.\\d+)?(px|pt|em|rem)?$/',
        ];

        $out = [];
        foreach (preg_split('/;\\s*/', (string) $style) as $chunk) {
            if ($chunk === '' || !str_contains($chunk, ':')) {
                continue;
            }
            [$prop, $value] = array_map('trim', explode(':', $chunk, 2));
            $prop = strtolower($prop);
            $value = strtolower($value);
            if (!isset($allowed[$prop])) {
                continue;
            }
            if (!preg_match($allowed[$prop], $value)) {
                continue;
            }
            $out[] = $prop.':'.$value;
        }

        return implode(';', $out);
    }

    private function storeSignature(Request $request, $user): ?Signature
    {
        if (!$request->hasFile('signature_file')) {
            return Signature::query()->where('user_id', $user->id)->first();
        }

        $path = $request->file('signature_file')->store('signatures', 'public');

        return Signature::updateOrCreate(
            ['user_id' => $user->id],
            [
                'image_path' => $path,
                'default_position' => $request->input('sales_signature_position'),
            ]
        );
    }

    private function renderPdf(Document $document, string $disposition)
    {
        $document->load(['customer', 'contact', 'creator', 'adminApprover', 'approver', 'salesSigner', 'directorSigner']);

        $html = view('documents.pdf', [
            'document' => $document,
            'letterheadPath' => $this->letterheadPath(),
            'stampPath' => $this->stampPath(),
        ])->render();

        $opt = new Options();
        $opt->set('isRemoteEnabled', true);
        $opt->set('isHtml5ParserEnabled', true);

        $pdf = new Dompdf($opt);
        $pdf->loadHtml($html);
        $pdf->setPaper('A4', 'portrait');
        $pdf->render();

        $raw = (string) ($document->number ?: 'document');
        $safe = trim($raw);
        $safe = str_replace(['/', '\\'], '-', $safe);
        $safe = preg_replace('/[^A-Za-z0-9._-]+/', '-', $safe);
        $safe = preg_replace('/-+/', '-', $safe);
        $safe = trim($safe, '-');
        $filename = ($safe !== '' ? $safe : 'document') . '.pdf';

        return response($pdf->output(), 200)
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', $disposition.'; filename="'.$filename.'"');
    }

    private function letterheadPath(): ?string
    {
        $path = \App\Models\Setting::get('documents.letterhead_path');
        return $path ? asset('storage/'.$path) : null;
    }

    private function stampPath(): ?string
    {
        $path = \App\Models\Setting::get('documents.stamp_path');
        return $path ? asset('storage/'.$path) : null;
    }

    private function directorSignaturePath(): ?string
    {
        $path = \App\Models\Setting::get('documents.director_signature_path');
        return $path ? asset('storage/'.$path) : null;
    }

    private function salesSignerOptions()
    {
        return User::query()
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    private function directorSignerOptions()
    {
        return User::query()
            ->whereHas('roles', function ($q) {
                $q->whereIn('name', ['Director', 'SuperAdmin']);
            })
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    private function resolveSalesSignerId($user, array $data, ?Document $document = null): int
    {
        if ($user->hasRole('Sales')) {
            return $user->id;
        }

        if (!empty($data['sales_signer_user_id'])) {
            return (int) $data['sales_signer_user_id'];
        }

        if ($document && $document->sales_signer_user_id) {
            return (int) $document->sales_signer_user_id;
        }

        return (int) $user->id;
    }

    private function resolveDirectorId($user, array $data, ?Document $document = null): ?int
    {
        if ($user->hasRole('Sales')) {
            return null;
        }

        if (!empty($data['director_user_id'])) {
            return (int) $data['director_user_id'];
        }

        if ($document && $document->director_user_id) {
            return (int) $document->director_user_id;
        }

        return null;
    }
}
