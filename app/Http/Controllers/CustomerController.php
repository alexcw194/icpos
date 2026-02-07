<?php

namespace App\Http\Controllers;

use App\Models\BillingDocument;
use App\Models\Customer;
use App\Models\Delivery;
use App\Models\Document;
use App\Models\Jenis;
use App\Models\User;
use App\Models\Contact;
use App\Models\ContactTitle;
use App\Models\Invoice;
use App\Models\Project;
use App\Models\ProjectQuotation;
use App\Models\Quotation;
use App\Models\SalesOrder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class CustomerController extends Controller
{
    private const DUP_SIMILAR_THRESHOLD = 72.0;
    private const DUP_MAX_RESULTS = 8;

    public function index(Request $request)
    {
        $this->authorize('viewAny', Customer::class);

        $q = Customer::query()
            ->with(['jenis', 'creator', 'salesOwner'])
            ->visibleTo(Auth::user())
            ->keyword($request->get('q'))
            ->inJenis($request->get('jenis_id'))
            ->ordered();

        $customers = $q->paginate(20)->withQueryString();

        $jenisList = Jenis::query()->active()->ordered()->get();

        return view('customers.index', compact('customers', 'jenisList'));
    }

    public function create()
    {
        $this->authorize('create', Customer::class);

        $customer = new Customer();

        $jenisList = Jenis::query()->active()->ordered()->get();

        $user = auth()->user();
        $salesUsers = ($user && $user->hasAnyRole(['Admin', 'SuperAdmin']))
            ? User::query()->orderBy('name')->get(['id', 'name'])
            : User::role('Sales')->orderBy('name')->get(['id', 'name']);

        return view('customers.create', compact('customer', 'jenisList', 'salesUsers'));
    }

    public function store(Request $request)
    {
        $this->authorize('create', Customer::class);

        $isPrivileged = $request->user()->hasAnyRole(['Admin', 'SuperAdmin', 'Finance']);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:190'],
            'jenis_id' => ['required', 'exists:jenis,id'],

            // ✅ owner (privileged boleh pilih, Sales akan di-lock)
            'sales_user_id' => [
                $isPrivileged ? 'required' : 'nullable',
                'nullable',
                'exists:users,id',
            ],

            'phone' => ['nullable', 'string', 'max:60'],
            'email' => ['nullable', 'email', 'max:190'],
            'website' => ['nullable', 'string', 'max:190'],
            'billing_terms_days' => ['nullable', 'integer', 'min:0', 'max:3650'],

            'address' => ['nullable', 'string'],
            'city' => ['nullable', 'string', 'max:100'],
            'province' => ['nullable', 'string', 'max:100'],
            'country' => ['nullable', 'string', 'max:100'],

            'npwp' => ['nullable', 'string', 'max:80'],
            'npwp_number' => ['nullable', 'string', 'max:40'],
            'npwp_name' => ['nullable', 'string', 'max:190'],
            'npwp_address' => ['nullable', 'string'],

            'notes' => ['nullable', 'string'],

            // Billing & Shipping (optional)
            'billing_street' => ['nullable', 'string'],
            'billing_city' => ['nullable', 'string', 'max:100'],
            'billing_state' => ['nullable', 'string', 'max:100'],
            'billing_zip' => ['nullable', 'string', 'max:30'],
            'billing_country' => ['nullable', 'string', 'max:10'],
            'billing_notes' => ['nullable', 'string'],

            'shipping_street' => ['nullable', 'string'],
            'shipping_city' => ['nullable', 'string', 'max:100'],
            'shipping_state' => ['nullable', 'string', 'max:100'],
            'shipping_zip' => ['nullable', 'string', 'max:30'],
            'shipping_country' => ['nullable', 'string', 'max:10'],
            'shipping_notes' => ['nullable', 'string'],
        ]);

        if ($guardResponse = $this->guardAgainstDuplicateNames($request, (string) $data['name'])) {
            return $guardResponse;
        }

        // hard lock untuk role Sales: owner = dirinya
        if ($request->user()->hasRole('Sales')) {
            $data['sales_user_id'] = $request->user()->id;
        }

        $customer = Customer::create($data);

        // sesuai preferensi kamu: setelah create, redirect ke show #contacts
        return redirect()
            ->route('customers.show', $customer)
            ->with('success', 'Customer berhasil dibuat.');
    }

    public function show(Request $request, Customer $customer)
    {
        $this->authorize('view', $customer);

        // tab & query (selaras dengan view)
        $tab = request('tab', 'profile');
        $q   = trim((string) request('q', ''));

        // Load data inti + counts untuk badge rail kiri (contacts/quotations/sales_orders)
        $customer->load(['contacts', 'jenis', 'creator', 'salesOwner'])
            ->loadCount([
                'contacts',
                'quotations',
                'salesOrders as sales_orders_count',
                'projects as projects_count',
            ]);

        // Selalu define, supaya view tidak pernah undefined variable.
        // Filter "q" mengikuti form di tab masing-masing.
        $quotations = $customer->quotations()
            ->with(['company', 'salesUser'])
            ->visibleTo(auth()->user())
            ->when($q !== '' && $tab === 'quotations', function ($qq) use ($q) {
                $qq->where('number', 'like', "%{$q}%");
            })
            ->orderByDesc('date')
            ->orderByDesc('id')
            ->paginate(10)
            ->withQueryString();

        $salesOrders = $customer->salesOrders()
            ->with(['company', 'salesUser', 'quotation'])
            ->visibleTo(auth()->user())
            ->when($q !== '' && $tab === 'sales_orders', function ($qq) use ($q) {
                // so_number sesuai view sales order tab
                $qq->where('so_number', 'like', "%{$q}%");
            })
            ->orderByDesc('order_date')
            ->orderByDesc('id')
            ->paginate(10)
            ->withQueryString();

        $projects = $customer->projects()
            ->visibleTo(auth()->user())
            ->with([
                'company:id,alias,name',
                'salesOwner:id,name',
                'quotations' => fn($qq) => $qq->latest('quotation_date')->limit(1),
            ])
            ->when($q !== '' && $tab === 'projects', function ($qq) use ($q) {
                $qq->where(function ($w) use ($q) {
                    $w->where('code', 'like', "%{$q}%")
                      ->orWhere('name', 'like', "%{$q}%");
                });
            })
            ->orderByDesc('updated_at')
            ->paginate(10)
            ->withQueryString();

        $contactTitles = ContactTitle::active()->ordered()->get(['id', 'name']);
        $canMergeCustomers = $this->canMergeCustomers($request->user());
        $mergeCandidates = collect();

        if ($canMergeCustomers) {
            $dups = $this->findDuplicateCandidates($customer->name, $customer->id);
            $mergeCandidates = $dups['exact']
                ->merge($dups['similar'])
                ->unique('id')
                ->take(self::DUP_MAX_RESULTS)
                ->values();
        }

        return view('customers.show', compact(
            'customer',
            'quotations',
            'salesOrders',
            'projects',
            'contactTitles',
            'canMergeCustomers',
            'mergeCandidates'
        ));
    }


    public function edit(Customer $customer)
    {
        $this->authorize('update', $customer);

        $jenisList = Jenis::query()->active()->ordered()->get();
        $user = auth()->user();
        $salesUsers = ($user && $user->hasAnyRole(['Admin', 'SuperAdmin']))
            ? User::query()->orderBy('name')->get(['id', 'name'])
            : User::role('Sales')->orderBy('name')->get(['id', 'name']);

        return view('customers.edit', compact('customer', 'jenisList', 'salesUsers'));
    }

    public function update(Request $request, Customer $customer)
    {
        $this->authorize('update', $customer);

        $isPrivileged = $request->user()->hasAnyRole(['Admin', 'SuperAdmin', 'Finance']);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:190'],
            'jenis_id' => ['required', 'exists:jenis,id'],

            'sales_user_id' => [
                $isPrivileged ? 'required' : 'nullable',
                'nullable',
                'exists:users,id',
            ],

            'phone' => ['nullable', 'string', 'max:60'],
            'email' => ['nullable', 'email', 'max:190'],
            'website' => ['nullable', 'string', 'max:190'],
            'billing_terms_days' => ['nullable', 'integer', 'min:0', 'max:3650'],

            'address' => ['nullable', 'string'],
            'city' => ['nullable', 'string', 'max:100'],
            'province' => ['nullable', 'string', 'max:100'],
            'country' => ['nullable', 'string', 'max:100'],

            'npwp' => ['nullable', 'string', 'max:80'],
            'npwp_number' => ['nullable', 'string', 'max:40'],
            'npwp_name' => ['nullable', 'string', 'max:190'],
            'npwp_address' => ['nullable', 'string'],

            'notes' => ['nullable', 'string'],

            // Billing & Shipping (optional)
            'billing_street' => ['nullable', 'string'],
            'billing_city' => ['nullable', 'string', 'max:100'],
            'billing_state' => ['nullable', 'string', 'max:100'],
            'billing_zip' => ['nullable', 'string', 'max:30'],
            'billing_country' => ['nullable', 'string', 'max:10'],
            'billing_notes' => ['nullable', 'string'],

            'shipping_street' => ['nullable', 'string'],
            'shipping_city' => ['nullable', 'string', 'max:100'],
            'shipping_state' => ['nullable', 'string', 'max:100'],
            'shipping_zip' => ['nullable', 'string', 'max:30'],
            'shipping_country' => ['nullable', 'string', 'max:10'],
            'shipping_notes' => ['nullable', 'string'],
        ]);

        if ($guardResponse = $this->guardAgainstDuplicateNames($request, (string) $data['name'], $customer->id)) {
            return $guardResponse;
        }

        // hard lock untuk role Sales: tidak boleh re-assign
        if ($request->user()->hasRole('Sales')) {
            $data['sales_user_id'] = $customer->sales_user_id ?: $request->user()->id;
        }

        $customer->update($data);

        // sesuai preferensi: setelah update balik ke index
        return redirect()
            ->route('customers.index')
            ->with('success', 'Customer berhasil diperbarui.');
    }

    public function destroy(Customer $customer)
    {
        $this->authorize('delete', $customer);

        $customer->delete();

        return redirect()
            ->route('customers.index')
            ->with('success', 'Customer berhasil dihapus.');
    }

    public function updateNotes(Request $request, Customer $customer): RedirectResponse
    {
        $this->authorize('update', $customer);

        $data = $request->validate([
            'notes' => ['nullable', 'string'],
        ]);

        $customer->update([
            'notes' => $data['notes'] ?? null,
        ]);

        return back()->with('success', 'Notes customer berhasil diperbarui.');
    }

    public function storeContact(Request $request, Customer $customer)
    {
        $this->authorize('update', $customer);

        $data = $request->validate([
            'first_name' => ['required', 'string', 'max:120'],
            'last_name' => ['nullable', 'string', 'max:120'],
            'contact_title_id' => ['nullable', 'exists:contact_titles,id'],
            'position' => ['nullable', 'string', 'max:120'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:120'],
            'notes' => ['nullable', 'string'],
        ]);

        $title = !empty($data['contact_title_id'])
            ? ContactTitle::find($data['contact_title_id'])
            : null;

        $data['title_snapshot'] = $title?->name;
        $positionText = trim((string) ($data['position'] ?? ''));
        $data['position_snapshot'] = $positionText !== '' ? $positionText : null;
        $data['position'] = $data['position_snapshot']; // legacy column compatibility

        $contact = $customer->contacts()->create($data);

        if ($request->expectsJson()) {
            return response()->json([
                'ok' => true,
                'contact' => $contact,
                'urls' => [
                    'update_url' => route('customers.contacts.update', [$customer, $contact]),
                    'delete_url' => route('customers.contacts.destroy', [$customer, $contact]),
                ],
            ]);
        }

        return back()->with('success', 'Kontak berhasil ditambahkan.');
    }

    public function updateContact(Request $request, Customer $customer, Contact $contact)
    {
        $this->authorize('update', $customer);

        if ((int) $contact->customer_id !== (int) $customer->id) {
            abort(404);
        }

        $data = $request->validate([
            'first_name' => ['required', 'string', 'max:120'],
            'last_name' => ['nullable', 'string', 'max:120'],
            'contact_title_id' => ['nullable', 'exists:contact_titles,id'],
            'position' => ['nullable', 'string', 'max:120'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:120'],
            'notes' => ['nullable', 'string'],
        ]);

        $title = !empty($data['contact_title_id'])
            ? ContactTitle::find($data['contact_title_id'])
            : null;

        $data['title_snapshot'] = $title?->name;
        $positionText = trim((string) ($data['position'] ?? ''));
        $data['position_snapshot'] = $positionText !== '' ? $positionText : null;
        $data['position'] = $data['position_snapshot']; // legacy column compatibility

        $contact->update($data);

        if ($request->expectsJson()) {
            return response()->json([
                'ok' => true,
                'contact' => $contact->fresh(),
            ]);
        }

        return back()->with('success', 'Kontak berhasil diperbarui.');
    }

    public function destroyContact(Request $request, Customer $customer, Contact $contact)
    {
        $this->authorize('update', $customer);

        if ((int) $contact->customer_id !== (int) $customer->id) {
            abort(404);
        }

        $contact->delete();

        if ($request->expectsJson()) {
            return response()->json(['ok' => true]);
        }

        return back()->with('success', 'Kontak berhasil dihapus.');
    }

    public function contacts(Customer $customer)
    {
        $this->authorize('view', $customer);

        $contacts = $customer->contacts()
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->get()
            ->map(function (Contact $contact) {
                return [
                    'id' => $contact->id,
                    'name' => $contact->full_name,
                    'title' => $contact->title_label,
                    'position' => $contact->position_label,
                    'email' => $contact->email,
                    'phone' => $contact->phone,
                ];
            });

        return response()->json($contacts);
    }

    public function quickSearch(Request $req)
    {
        $q = trim((string) $req->input('q', ''));
        $user = Auth::user();

        // Customers (respect visibility)
        $customers = Customer::query()
            ->visibleTo($user) // Sales: miliknya; Admin/SuperAdmin/Finance: semua :contentReference[oaicite:4]{index=4}
            ->select('id', 'name')
            ->when($q !== '', fn ($qq) => $qq->where('name', 'like', "%{$q}%"))
            ->orderBy('name')
            ->limit(20)
            ->get()
            ->map(fn ($c) => [
                'uid'         => 'customer-' . $c->id,
                'type'        => 'customer',
                'label'       => $c->name,
                'name'        => $c->name,
                'customer_id' => $c->id,
                'contact_id'  => null,
            ]);

        // Contacts (only from visible customers)
        $contacts = Contact::query()
            ->select('id', 'customer_id', 'first_name', 'last_name')
            ->with(['customer:id,name'])
            ->whereHas('customer', fn ($cq) => $cq->visibleTo($user))
            ->when($q !== '', function ($qq) use ($q) {
                $qq->where(function ($w) use ($q) {
                    $w->where('first_name', 'like', "%{$q}%")
                    ->orWhere('last_name', 'like', "%{$q}%");
                })->orWhereHas('customer', fn ($c) => $c->where('name', 'like', "%{$q}%"));
            })
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->limit(20)
            ->get()
            ->map(function ($ct) {
                $person  = trim(($ct->first_name ?? '') . ' ' . ($ct->last_name ?? ''));
                $company = optional($ct->customer)->name;

                return [
                    'uid'         => 'contact-' . $ct->id,
                    'type'        => 'contact',
                    'label'       => $company ? "{$company} ({$person})" : $person,
                    'name'        => $person,
                    'customer_id' => $ct->customer_id,
                    'contact_id'  => $ct->id,
                ];
            });

        return response()->json(
            $customers->merge($contacts)->take(30)->values()
        );
    }

    public function dupCheck(Request $request): JsonResponse
    {
        $name = trim((string) $request->input('q', $request->input('name', '')));
        $exceptId = $request->filled('except_id') ? (int) $request->input('except_id') : null;

        if ($name === '') {
            return response()->json([
                'ok' => true,
                'name' => '',
                'name_key' => '',
                'can_create' => true,
                'exact' => [],
                'similar' => [],
            ]);
        }

        $dups = $this->findDuplicateCandidates($name, $exceptId);
        $exact = $dups['exact']->values();
        $similar = $dups['similar']->values();

        return response()->json([
            'ok' => true,
            'name' => $name,
            'name_key' => Customer::makeNameKey($name),
            'can_create' => $exact->isEmpty(),
            'exact' => $exact->all(),
            'similar' => $similar->all(),
        ]);
    }

    public function quickStore(Request $request): JsonResponse|RedirectResponse
    {
        $this->authorize('create', Customer::class);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:190'],
            'confirm_similar_name' => ['nullable', 'boolean'],
        ]);

        if ($guardResponse = $this->guardAgainstDuplicateNames($request, (string) $data['name'])) {
            return $guardResponse;
        }

        $customer = Customer::create([
            'name' => $data['name'],
            'sales_user_id' => $request->user()?->id,
        ]);

        return response()->json([
            'ok' => true,
            'customer' => [
                'id' => (int) $customer->id,
                'name' => $customer->name,
                'uid' => 'customer-' . $customer->id,
                'type' => 'customer',
                'label' => $customer->name,
                'customer_id' => (int) $customer->id,
                'contact_id' => null,
            ],
        ]);
    }

    public function merge(Request $request, Customer $customer): RedirectResponse
    {
        $this->authorize('update', $customer);
        $this->authorize('delete', $customer);

        if (!$this->canMergeCustomers($request->user())) {
            abort(403);
        }

        $data = $request->validate([
            'target_customer_id' => ['required', 'integer', 'exists:customers,id'],
        ]);

        $target = Customer::query()->findOrFail((int) $data['target_customer_id']);
        $this->authorize('update', $target);

        if ((int) $target->id === (int) $customer->id) {
            return back()->withErrors([
                'target_customer_id' => 'Customer tujuan merge harus berbeda.',
            ]);
        }

        $sourceName = $customer->name;
        $targetName = $target->name;

        $moved = DB::transaction(function () use ($customer, $target) {
            $source = Customer::query()->lockForUpdate()->findOrFail($customer->id);
            $master = Customer::query()->lockForUpdate()->findOrFail($target->id);

            $this->mergeCustomerProfileData($source, $master);

            $sourceId = (int) $source->id;
            $masterId = (int) $master->id;

            $moved = [];
            $moved['contacts'] = Contact::where('customer_id', $sourceId)->update(['customer_id' => $masterId]);
            $moved['quotations'] = Quotation::where('customer_id', $sourceId)->update(['customer_id' => $masterId]);
            $moved['sales_orders'] = SalesOrder::where('customer_id', $sourceId)->update(['customer_id' => $masterId]);
            $moved['invoices'] = Invoice::where('customer_id', $sourceId)->update(['customer_id' => $masterId]);
            $moved['deliveries'] = Delivery::where('customer_id', $sourceId)->update(['customer_id' => $masterId]);
            $moved['billing_documents'] = BillingDocument::where('customer_id', $sourceId)->update(['customer_id' => $masterId]);
            $moved['projects'] = Project::where('customer_id', $sourceId)->update(['customer_id' => $masterId]);
            $moved['project_quotations'] = ProjectQuotation::where('customer_id', $sourceId)->update(['customer_id' => $masterId]);
            $moved['documents'] = Document::where('customer_id', $sourceId)->update(['customer_id' => $masterId]);

            $source->delete();

            return $moved;
        });

        $labels = [
            'contacts' => 'contacts',
            'quotations' => 'quotations',
            'sales_orders' => 'sales orders',
            'invoices' => 'invoices',
            'deliveries' => 'deliveries',
            'billing_documents' => 'billing documents',
            'projects' => 'projects',
            'project_quotations' => 'project quotations',
            'documents' => 'documents',
        ];

        $summary = collect($moved)
            ->filter(fn ($count) => (int) $count > 0)
            ->map(fn ($count, $key) => $count . ' ' . ($labels[$key] ?? $key))
            ->values()
            ->implode(', ');

        $summaryText = $summary !== '' ? $summary : 'tidak ada relasi yang perlu dipindahkan';

        return redirect()
            ->route('customers.show', $target)
            ->with('success', "Merge selesai: {$sourceName} digabung ke {$targetName}. Dipindahkan: {$summaryText}.");
    }

    private function canMergeCustomers(?User $user): bool
    {
        if (!$user) {
            return false;
        }

        return $user->hasAnyRole(['Admin', 'SuperAdmin', 'Finance']);
    }

    private function guardAgainstDuplicateNames(Request $request, string $name, ?int $exceptCustomerId = null): RedirectResponse|JsonResponse|null
    {
        $dups = $this->findDuplicateCandidates($name, $exceptCustomerId);
        $exact = $dups['exact']->values();
        $similar = $dups['similar']->values();

        if ($exact->isNotEmpty()) {
            $msg = 'Nama customer/perusahaan sudah ada persis. Gunakan data existing, tidak bisa membuat duplikat persis.';

            if ($request->expectsJson()) {
                return response()->json([
                    'ok' => false,
                    'reason' => 'exact_duplicate',
                    'message' => $msg,
                    'exact' => $exact->all(),
                    'similar' => $similar->all(),
                ], 422);
            }

            return back()
                ->withInput()
                ->withErrors(['name' => $msg])
                ->with('duplicate_exact_candidates', $exact->all());
        }

        if ($similar->isNotEmpty() && !$request->boolean('confirm_similar_name')) {
            $msg = 'Ditemukan nama mirip. Pilih data yang sudah ada untuk merge, atau centang konfirmasi jika memang perusahaan berbeda.';

            if ($request->expectsJson()) {
                return response()->json([
                    'ok' => false,
                    'reason' => 'similar_duplicate',
                    'message' => $msg,
                    'exact' => [],
                    'similar' => $similar->all(),
                ], 409);
            }

            return back()
                ->withInput()
                ->withErrors(['name' => $msg])
                ->with('similar_name_candidates', $similar->all());
        }

        return null;
    }

    /**
     * Return:
     * - exact: names with identical normalized key (hard-block)
     * - similar: names with high similarity score (require decision)
     */
    private function findDuplicateCandidates(string $name, ?int $exceptCustomerId = null): array
    {
        $nameKey = Customer::makeNameKey($name);
        if ($nameKey === '') {
            return ['exact' => collect(), 'similar' => collect()];
        }

        $tokens = collect(preg_split('/\s+/', $nameKey))
            ->filter(fn ($token) => mb_strlen((string) $token) >= 3)
            ->unique()
            ->values();

        $query = Customer::query()
            ->select(['id', 'name', 'name_key', 'city', 'phone', 'email'])
            ->when($exceptCustomerId, fn ($q) => $q->where('id', '!=', $exceptCustomerId))
            ->where(function ($q) use ($nameKey, $tokens) {
                $q->where('name_key', $nameKey);

                foreach ($tokens as $token) {
                    $q->orWhere('name_key', 'like', "%{$token}%");
                }
            })
            ->orderBy('name')
            ->limit(120);

        $candidates = $query->get();

        if ($candidates->isEmpty()) {
            $candidates = Customer::query()
                ->select(['id', 'name', 'name_key', 'city', 'phone', 'email'])
                ->when($exceptCustomerId, fn ($q) => $q->where('id', '!=', $exceptCustomerId))
                ->orderByDesc('id')
                ->limit(120)
                ->get();
        }

        $exact = collect();
        $similar = collect();

        foreach ($candidates as $candidate) {
            $candidateKey = Customer::makeNameKey((string) ($candidate->name_key ?: $candidate->name));
            if ($candidateKey === '') {
                continue;
            }

            if ($candidateKey === $nameKey) {
                $exact->push($this->formatDuplicateCandidate($candidate, 100.0));
                continue;
            }

            similar_text($nameKey, $candidateKey, $textScore);
            $tokenScore = $this->tokenSimilarityScore($nameKey, $candidateKey);
            $containsScore = (str_contains($candidateKey, $nameKey) || str_contains($nameKey, $candidateKey)) ? 85.0 : 0.0;
            $score = max($textScore, $tokenScore, $containsScore);

            if ($score >= self::DUP_SIMILAR_THRESHOLD) {
                $similar->push($this->formatDuplicateCandidate($candidate, $score));
            }
        }

        $exact = $exact
            ->unique('id')
            ->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)
            ->take(self::DUP_MAX_RESULTS)
            ->values();

        $similar = $similar
            ->unique('id')
            ->sortByDesc('score')
            ->take(self::DUP_MAX_RESULTS)
            ->values();

        return compact('exact', 'similar');
    }

    private function formatDuplicateCandidate(Customer $candidate, float $score): array
    {
        return [
            'id' => (int) $candidate->id,
            'name' => (string) $candidate->name,
            'city' => $candidate->city,
            'phone' => $candidate->phone,
            'email' => $candidate->email,
            'score' => round($score, 1),
        ];
    }

    private function tokenSimilarityScore(string $a, string $b): float
    {
        $aTokens = collect(preg_split('/\s+/', $a))->filter()->unique()->values();
        $bTokens = collect(preg_split('/\s+/', $b))->filter()->unique()->values();

        $unionCount = $aTokens->merge($bTokens)->unique()->count();
        if ($unionCount === 0) {
            return 0.0;
        }

        $intersectionCount = $aTokens->intersect($bTokens)->count();
        return ($intersectionCount / $unionCount) * 100.0;
    }

    private function mergeCustomerProfileData(Customer $source, Customer $target): void
    {
        $fillable = [
            'phone',
            'email',
            'website',
            'billing_terms_days',
            'address',
            'city',
            'province',
            'country',
            'npwp',
            'npwp_number',
            'npwp_name',
            'npwp_address',
            'notes',
            'billing_street',
            'billing_city',
            'billing_state',
            'billing_zip',
            'billing_country',
            'billing_notes',
            'shipping_street',
            'shipping_city',
            'shipping_state',
            'shipping_zip',
            'shipping_country',
            'shipping_notes',
            'jenis_id',
            'sales_user_id',
        ];

        $updates = [];
        foreach ($fillable as $field) {
            $targetValue = $target->{$field};
            $sourceValue = $source->{$field};

            $isTargetEmpty = $targetValue === null || (is_string($targetValue) && trim($targetValue) === '');
            $isSourceFilled = $sourceValue !== null && (!is_string($sourceValue) || trim($sourceValue) !== '');

            if ($isTargetEmpty && $isSourceFilled) {
                $updates[$field] = $sourceValue;
            }
        }

        if (!empty($updates)) {
            $target->update($updates);
        }
    }
}
