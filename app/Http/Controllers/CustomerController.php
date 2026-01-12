<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Jenis;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
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

        $customer->load(['contacts', 'jenis', 'creator', 'salesOwner']);

        return view('customers.show', compact('customer'));
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
}
