<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Jenis;
use App\Models\User;
use App\Models\Contact;
use App\Models\ContactTitle;
use App\Models\ContactPosition;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\Quotation;
use App\Models\SalesOrder;
use Illuminate\Validation\Rule;

class CustomerController extends Controller
{
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

        // hanya list Sales untuk assignment owner
        $salesUsers = User::role('Sales')->orderBy('name')->get(['id', 'name']);

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

    public function show(Customer $customer)
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
        $contactPositions = ContactPosition::active()->ordered()->get(['id', 'name']);

        return view('customers.show', compact(
            'customer',
            'quotations',
            'salesOrders',
            'projects',
            'contactTitles',
            'contactPositions'
        ));
    }


    public function edit(Customer $customer)
    {
        $this->authorize('update', $customer);

        $jenisList = Jenis::query()->active()->ordered()->get();
        $salesUsers = User::role('Sales')->orderBy('name')->get(['id', 'name']);

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

    public function storeContact(Request $request, Customer $customer)
    {
        $this->authorize('update', $customer);

        $data = $request->validate([
            'first_name' => ['required', 'string', 'max:120'],
            'last_name' => ['nullable', 'string', 'max:120'],
            'contact_title_id' => ['nullable', 'exists:contact_titles,id'],
            'contact_position_id' => ['nullable', 'exists:contact_positions,id'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:120'],
            'notes' => ['nullable', 'string'],
        ]);

        $title = !empty($data['contact_title_id'])
            ? ContactTitle::find($data['contact_title_id'])
            : null;
        $position = !empty($data['contact_position_id'])
            ? ContactPosition::find($data['contact_position_id'])
            : null;

        $data['title_snapshot'] = $title?->name;
        $data['position_snapshot'] = $position?->name;

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
            'contact_position_id' => ['nullable', 'exists:contact_positions,id'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:120'],
            'notes' => ['nullable', 'string'],
        ]);

        $title = !empty($data['contact_title_id'])
            ? ContactTitle::find($data['contact_title_id'])
            : null;
        $position = !empty($data['contact_position_id'])
            ? ContactPosition::find($data['contact_position_id'])
            : null;

        $data['title_snapshot'] = $title?->name;
        $data['position_snapshot'] = $position?->name;

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
}
