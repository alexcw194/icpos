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
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

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

        $documents = Document::query()
            ->where('status', Document::STATUS_SUBMITTED)
            ->with(['customer', 'creator'])
            ->latest('submitted_at')
            ->paginate(20)
            ->withQueryString();

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

        $draftToken = session('doc_draft_token');
        if (!$draftToken) {
            $draftToken = (string) Str::uuid();
            session(['doc_draft_token' => $draftToken]);
        }

        $customers = Customer::query()
            ->visibleTo($user)
            ->ordered()
            ->get(['id', 'name']);

        $signature = Signature::query()
            ->where('user_id', $user->id)
            ->first();

        $salesUsers = $this->salesSignerOptions();
        $owners = $this->ownerOptions();

        return view('documents.form', [
            'document' => $document,
            'customers' => $customers,
            'signature' => $signature,
            'salesUsers' => $salesUsers,
            'draftToken' => $draftToken,
            'owners' => $owners,
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

        $salesSignerId = $this->resolveSalesSignerId($user, $data);
        $ownerId = $this->resolveOwnerId($user, $data);
        $salesSignature = $salesSignerId
            ? Signature::query()->where('user_id', $salesSignerId)->first()
            : null;
        $titleUpper = $this->toUpper($data['title']);
        $positionInput = $this->toUpper($data['sales_signature_position'] ?? null);
        $salesPosition = $salesSignerId
            ? ($positionInput ?? $this->toUpper($salesSignature?->default_position))
            : null;

        $document = Document::create([
            'title' => $titleUpper ?? $data['title'],
            'body_html' => '',
            'customer_id' => $customer->id,
            'contact_id' => $contact?->id,
            'customer_snapshot' => $this->customerSnapshot($customer),
            'contact_snapshot' => $contact ? $this->contactSnapshot($contact) : null,
            'created_by_user_id' => $ownerId,
            'sales_signer_user_id' => $salesSignerId,
            'status' => Document::STATUS_DRAFT,
            'sales_signature_position' => $salesPosition,
        ]);

        $draftToken = $request->input('draft_token');
        $bodyHtml = $this->sanitizeHtml($this->resolveBodyHtml($data), $document->id, $draftToken);
        $bodyHtml = $this->migrateDraftImages($bodyHtml, $draftToken, $document->id);
        $document->update(['body_html' => $bodyHtml]);

        if ($draftToken) {
            $request->session()->forget('doc_draft_token');
        }

        return redirect()
            ->route('documents.show', $document)
            ->with('success', 'Draft dokumen dibuat.');
    }

    public function show(Document $document)
    {
        $this->authorize('view', $document);

        $document->load(['customer', 'contact', 'creator', 'adminApprover', 'approver', 'salesSigner']);

        return view('documents.show', compact('document'));
    }

    public function edit(Document $document)
    {
        $this->authorize('update', $document);
        abort_unless($document->isEditable(), 403, 'Dokumen terkunci.');

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
        $owners = $this->ownerOptions();

        return view('documents.form', [
            'document' => $document,
            'customers' => $customers,
            'signature' => $signature,
            'salesUsers' => $salesUsers,
            'draftToken' => null,
            'owners' => $owners,
            'mode' => 'edit',
        ]);
    }

    public function update(Request $request, Document $document)
    {
        $this->authorize('update', $document);
        abort_unless($document->isEditable(), 403, 'Dokumen terkunci.');

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

        $salesSignerId = $this->resolveSalesSignerId($user, $data, $document);
        $ownerId = $this->resolveOwnerId($user, $data, $document);
        $salesSignature = $salesSignerId
            ? Signature::query()->where('user_id', $salesSignerId)->first()
            : null;
        $titleUpper = $this->toUpper($data['title']);
        $positionInput = $this->toUpper($data['sales_signature_position'] ?? null);
        $salesPosition = $salesSignerId
            ? ($positionInput ?? $this->toUpper($salesSignature?->default_position))
            : null;

        $document->update([
            'title' => $titleUpper ?? $data['title'],
            'body_html' => $this->sanitizeHtml($this->resolveBodyHtml($data), $document->id),
            'customer_id' => $customer->id,
            'contact_id' => $contact?->id,
            'customer_snapshot' => $this->customerSnapshot($customer),
            'contact_snapshot' => $contact ? $this->contactSnapshot($contact) : null,
            'created_by_user_id' => $ownerId,
            'sales_signer_user_id' => $salesSignerId,
            'sales_signature_position' => $salesPosition,
        ]);

        return redirect()
            ->route('documents.show', $document)
            ->with('success', 'Dokumen disimpan.');
    }

    public function submit(Document $document)
    {
        $this->authorize('submit', $document);

        DB::transaction(function () use ($document) {
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
        $salesSignerId = $document->sales_signer_user_id;
        if ($salesSignerId && (int) $salesSignerId === (int) $user->id) {
            return back()->with('error', 'Signer tidak boleh sama dengan approver.');
        }
        $salesSignature = $salesSignerId
            ? Signature::query()->where('user_id', $salesSignerId)->first()
            : null;

        $signatures = [];
        if ($salesSignerId) {
            $salesUser = User::query()->find($salesSignerId);
            $signatures['sales'] = [
                'user_id' => $salesSignerId,
                'name' => $salesUser?->name,
                'image_path' => $salesSignature?->image_path,
                'position' => $document->sales_signature_position ?? $salesSignature?->default_position,
            ];
        }
        $signatures['director'] = [
            'name' => 'Christian Widargo',
            'position' => 'Direktur Utama',
            'image_path' => $this->directorSignaturePath(),
        ];

        DB::transaction(function () use ($document, $signatures, $user) {
            if (!$document->number) {
                $next = DocumentNumberService::next();
                $document->number = $next['number'];
                $document->year = $next['year'];
                $document->sequence = $next['sequence'];
            }

            $document->status = Document::STATUS_APPROVED;
            $document->approved_by_user_id = $user->id;
            $document->approved_at = now();
            $document->signatures = $signatures;
            $document->save();
        });

        return redirect()
            ->route('documents.pending')
            ->with('success', 'Dokumen disetujui.');
    }

    public function revise(Document $document)
    {
        $this->authorize('update', $document);
        abort_unless($document->status === Document::STATUS_APPROVED, 403);

        $document->update([
            'status' => Document::STATUS_DRAFT,
            'submitted_at' => null,
            'admin_approved_by_user_id' => null,
            'admin_approved_at' => null,
            'approved_by_user_id' => null,
            'approved_at' => null,
            'rejected_by_user_id' => null,
            'rejected_at' => null,
            'rejection_note' => null,
            'signatures' => null,
        ]);

        return redirect()
            ->route('documents.edit', $document)
            ->with('success', 'Dokumen masuk mode revisi. Nomor tetap sama dan perlu approval ulang.');
    }

    public function reject(Request $request, Document $document)
    {
        $this->authorize('reject', $document);

        $data = $request->validate([
            'rejection_note' => ['required', 'string', 'max:1000'],
        ]);

        $document->update([
            'status' => Document::STATUS_DRAFT,
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

    public function destroy(Document $document)
    {
        $this->authorize('delete', $document);

        Storage::disk('public')->deleteDirectory('documents/'.$document->id);
        $document->delete();

        $user = auth()->user();
        $route = $user && $user->hasAnyRole(['Admin', 'SuperAdmin'])
            ? 'documents.index'
            : 'documents.my';

        return redirect()
            ->route($route)
            ->with('success', 'Dokumen dihapus.');
    }

    public function pdf(Document $document)
    {
        $this->authorize('view', $document);
        return $this->renderPdf($document, 'inline');
    }

    public function pdfDownload(Document $document)
    {
        $this->authorize('view', $document);
        abort_unless($document->status === Document::STATUS_APPROVED, 403, 'Dokumen belum disetujui.');
        return $this->renderPdf($document, 'attachment');
    }

    private function validateDocument(Request $request): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:190'],
            'body' => ['required', 'string'],
            'body_html' => ['nullable', 'string'],
            'created_by_user_id' => ['nullable', 'exists:users,id', function ($attribute, $value, $fail) use ($request) {
                if ($request->user()->hasRole('Sales') && $value && (int) $value !== (int) $request->user()->id) {
                    $fail('Owner dokumen harus sesuai dengan user Sales.');
                }
            }],
            'customer_id' => ['required', 'exists:customers,id'],
            'contact_id' => ['nullable', 'exists:contacts,id'],
            'sales_signer_user_id' => ['required', function ($attribute, $value, $fail) use ($request) {
                if ($value === 'director') {
                    return;
                }
                if (!User::query()->whereKey($value)->exists()) {
                    $fail('Signature harus dipilih dari daftar yang tersedia.');
                }
                if ($request->user()->hasRole('Sales') && (int) $value !== (int) $request->user()->id) {
                    $fail('Sales hanya bisa memilih dirinya sendiri atau Direktur Utama.');
                }
            }],
            'sales_signature_position' => ['nullable', 'string', 'max:120'],
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

    private function resolveBodyHtml(array $data): string
    {
        $body = $data['body'] ?? $data['body_html'] ?? '';
        return is_string($body) ? $body : '';
    }

    private function sanitizeHtml(string $html, int $documentId, ?string $draftToken = null): string
    {
        $allowedTags = [
            'p', 'br', 'strong', 'b', 'em', 'i', 'u',
            'ul', 'ol', 'li', 'div', 'span',
            'h1', 'h2', 'h3', 'h4', 'h5', 'h6',
            'table', 'thead', 'tbody', 'tr', 'td', 'th',
            'figure', 'figcaption', 'img',
            'a', 'hr',
        ];
        $allowedImageClasses = [
            'image',
            'doc-img-left',
            'doc-img-center',
            'doc-img-right',
        ];

        $doc = new \DOMDocument('1.0', 'utf-8');
        libxml_use_internal_errors(true);
        $doc->loadHTML('<?xml encoding="utf-8" ?>'.$html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        $xpath = new \DOMXPath($doc);

        foreach (iterator_to_array($xpath->query('//*')) as $node) {
            if (!in_array($node->nodeName, $allowedTags, true)) {
                $this->unwrapNode($node);
                continue;
            }

            if ($node->hasAttributes()) {
                $attrs = [];
                foreach ($node->attributes as $attr) {
                    $attrs[] = $attr->nodeName;
                }
                foreach ($attrs as $attrName) {
                    if (str_starts_with($attrName, 'on')) {
                        $node->removeAttribute($attrName);
                        continue;
                    }
                    if ($attrName === 'style') {
                        $style = $this->sanitizeStyle($node->getAttribute('style'));
                        if ($style === '') {
                            $node->removeAttribute('style');
                        } else {
                            $node->setAttribute('style', $style);
                        }
                        continue;
                    }
                    if ($attrName === 'class') {
                        if (in_array($node->nodeName, ['img', 'figure'], true)) {
                            $classes = $this->filterAllowedClasses($node->getAttribute('class'), $allowedImageClasses);
                            if ($classes === '') {
                                $node->removeAttribute('class');
                            } else {
                                $node->setAttribute('class', $classes);
                            }
                        } else {
                            $node->removeAttribute('class');
                        }
                        continue;
                    }
                    if ($node->nodeName === 'img' && in_array($attrName, ['src', 'alt', 'width', 'height'], true)) {
                        continue;
                    }
                    if ($node->nodeName === 'a' && in_array($attrName, ['href', 'target', 'rel'], true)) {
                        continue;
                    }
                    if (in_array($node->nodeName, ['td', 'th'], true) && in_array($attrName, ['colspan', 'rowspan'], true)) {
                        continue;
                    }
                    $node->removeAttribute($attrName);
                }
            }

            if ($node->nodeName === 'img') {
                $src = (string) $node->getAttribute('src');
                if ($src === '' || str_starts_with($src, 'data:')) {
                    $node->parentNode?->removeChild($node);
                    continue;
                }

                $path = $this->normalizeImagePath($src);
                $allowedPrefixes = [
                    '/storage/documents/'.$documentId.'/images/',
                ];
                if ($draftToken) {
                    $allowedPrefixes[] = '/storage/documents/tmp/'.$draftToken.'/images/';
                }

                $ok = false;
                foreach ($allowedPrefixes as $prefix) {
                    if (str_starts_with($path, $prefix)) {
                        $ok = true;
                        break;
                    }
                }
                if (!$ok) {
                    $node->parentNode?->removeChild($node);
                    continue;
                }
                $node->setAttribute('src', $path);

                foreach (['width', 'height'] as $attr) {
                    $val = $node->getAttribute($attr);
                    if ($val !== '' && !preg_match('/^\\d+$/', $val)) {
                        $node->removeAttribute($attr);
                    }
                }
            }

            if ($node->nodeName === 'a') {
                $href = (string) $node->getAttribute('href');
                if ($href === '' || preg_match('/^\\s*javascript:/i', $href) || preg_match('/^\\s*data:/i', $href)) {
                    $node->removeAttribute('href');
                }
            }
        }

        $clean = trim($doc->saveHTML());
        libxml_clear_errors();

        return $clean;
    }

    private function filterAllowedClasses(string $classValue, array $allowed): string
    {
        $classes = preg_split('/\\s+/', trim($classValue)) ?: [];
        $filtered = array_values(array_intersect($classes, $allowed));
        return implode(' ', $filtered);
    }

    private function sanitizeStyle(string $style): string
    {
        $allowed = [
            'text-align' => '/^(left|right|center|justify)$/',
            'font-size' => '/^\\d+(\\.\\d+)?(px|pt|em|rem|%)$/',
            'line-height' => '/^\\d+(\\.\\d+)?(px|pt|em|rem|%)?$/',
            'margin-top' => '/^\\d+(\\.\\d+)?(px|pt|em|rem|%)$/',
            'margin-bottom' => '/^\\d+(\\.\\d+)?(px|pt|em|rem|%)$/',
            'margin-left' => '/^\\d+(\\.\\d+)?(px|pt|em|rem|%)$/',
            'margin-right' => '/^\\d+(\\.\\d+)?(px|pt|em|rem|%)$/',
            'padding' => '/^\\d+(\\.\\d+)?(px|pt|em|rem|%)$/',
            'padding-top' => '/^\\d+(\\.\\d+)?(px|pt|em|rem|%)$/',
            'padding-bottom' => '/^\\d+(\\.\\d+)?(px|pt|em|rem|%)$/',
            'padding-left' => '/^\\d+(\\.\\d+)?(px|pt|em|rem|%)$/',
            'padding-right' => '/^\\d+(\\.\\d+)?(px|pt|em|rem|%)$/',
            'float' => '/^(left|right|none)$/',
            'width' => '/^\\d+(\\.\\d+)?(px|%)$/',
            'height' => '/^\\d+(\\.\\d+)?(px|%)$/',
            'max-width' => '/^\\d+(\\.\\d+)?(px|%)$/',
            'min-width' => '/^\\d+(\\.\\d+)?(px|%)$/',
            'border' => '/^\\d+(\\.\\d+)?px\\s+(solid|dashed|dotted)\\s+#[0-9a-f]{3,6}$/',
            'border-collapse' => '/^(collapse|separate)$/',
            'border-spacing' => '/^\\d+(\\.\\d+)?(px|pt|em|rem)$/',
            'display' => '/^(block|inline|inline-block|table|table-row|table-cell)$/',
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

    private function unwrapNode(\DOMNode $node): void
    {
        $parent = $node->parentNode;
        if (!$parent) {
            return;
        }
        while ($node->firstChild) {
            $parent->insertBefore($node->firstChild, $node);
        }
        $parent->removeChild($node);
    }

    private function normalizeImagePath(string $src): string
    {
        $path = parse_url($src, PHP_URL_PATH);
        if (is_string($path) && $path !== '') {
            return $path;
        }
        return $src;
    }

    private function toUpper(?string $value): ?string
    {
        if (!is_string($value)) {
            return null;
        }
        $trimmed = trim($value);
        if ($trimmed === '') {
            return null;
        }
        return Str::upper($trimmed);
    }

    private function renderPdf(Document $document, string $disposition)
    {
        $document->load(['customer', 'contact', 'creator', 'adminApprover', 'approver', 'salesSigner']);

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
        $user = auth()->user();
        $query = User::query();
        if ($user && $user->hasRole('Sales')) {
            $query->whereKey($user->id);
        }

        return $query
            ->leftJoin('signatures', 'signatures.user_id', '=', 'users.id')
            ->orderBy('users.name')
            ->get([
                'users.id',
                'users.name',
                'signatures.default_position as default_position',
            ]);
    }

    private function resolveSalesSignerId($user, array $data, ?Document $document = null): ?int
    {
        $selected = $data['sales_signer_user_id'] ?? null;
        if ($selected === 'director') {
            return null;
        }
        if ($user->hasRole('Sales')) {
            return $user->id;
        }

        if (!empty($selected)) {
            return (int) $selected;
        }

        if ($document && $document->sales_signer_user_id) {
            return (int) $document->sales_signer_user_id;
        }

        return (int) $user->id;
    }

    private function ownerOptions()
    {
        $user = auth()->user();
        if (!$user || !$user->hasAnyRole(['Admin', 'SuperAdmin'])) {
            return collect();
        }

        return User::query()
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    private function resolveOwnerId($user, array $data, ?Document $document = null): int
    {
        if ($user->hasAnyRole(['Admin', 'SuperAdmin'])) {
            if (!empty($data['created_by_user_id'])) {
                return (int) $data['created_by_user_id'];
            }
            if ($document && $document->created_by_user_id) {
                return (int) $document->created_by_user_id;
            }
        }

        return (int) $user->id;
    }

    private function migrateDraftImages(string $html, ?string $draftToken, int $documentId): string
    {
        if (!$draftToken) {
            return $html;
        }

        $tempDir = 'documents/tmp/'.$draftToken.'/images';
        $targetDir = 'documents/'.$documentId.'/images';
        if (Storage::disk('public')->exists($tempDir)) {
            foreach (Storage::disk('public')->allFiles($tempDir) as $file) {
                $newPath = str_replace('documents/tmp/'.$draftToken, 'documents/'.$documentId, $file);
                Storage::disk('public')->makeDirectory(dirname($newPath));
                Storage::disk('public')->move($file, $newPath);
            }
            Storage::disk('public')->deleteDirectory('documents/tmp/'.$draftToken);
        }

        return str_replace(
            '/storage/documents/tmp/'.$draftToken.'/',
            '/storage/documents/'.$documentId.'/',
            $html
        );
    }
}
