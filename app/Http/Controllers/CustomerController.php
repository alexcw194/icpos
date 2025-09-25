<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Contact;
use App\Models\Jenis;
use Illuminate\Http\Request;

class CustomerController extends Controller
{
    public function index(Request $request)
    {
        $kw      = $request->string('q')->toString();
        $jenisId = $request->input('jenis_id');

        $customers = Customer::with('jenis')
            ->visibleTo()
            ->keyword($kw)
            ->inJenis($jenisId)
            ->ordered()
            ->paginate(20)
            ->withQueryString();

        $jenises = Jenis::where('is_active', true)
            ->orderBy('name')
            ->get(['id','name']);

        return view('customers.index', compact('customers','jenises','kw','jenisId'));
    }

    public function create()
    {
        $jenises = Jenis::where('is_active', true)->orderBy('name')->get(['id','name']);
        return view('customers.create', compact('jenises'));
    }

    public function store(Request $r)
    {
        $data = $r->validate([
            'name'  => ['required','string','max:255'],
            'email' => ['nullable','email'],
            'phone' => ['nullable','string','max:50'],
            'npwp'  => ['nullable','string','max:50'],
            'address'  => ['nullable','string'],
            'city'     => ['nullable','string','max:80'],
            'province' => ['nullable','string','max:80'],
            'country'  => ['nullable','string','max:80'],
            'website'  => ['nullable','string','max:255'],
            'billing_terms_days' => ['nullable','integer','min:0','max:3650'],
            'notes' => ['nullable','string'],
            'jenis_id' => ['required','exists:jenis,id'],
            'billing_street'  => ['nullable','string'],
            'billing_city'    => ['nullable','string','max:128'],
            'billing_state'   => ['nullable','string','max:128'],
            'billing_zip'     => ['nullable','string','max:32'],
            'billing_country' => ['nullable','string','size:2'],
            'billing_notes'   => ['nullable','string'],

            'shipping_street'  => ['nullable','string'],
            'shipping_city'    => ['nullable','string','max:128'],
            'shipping_state'   => ['nullable','string','max:128'],
            'shipping_zip'     => ['nullable','string','max:32'],
            'shipping_country' => ['nullable','string','size:2'],
            'shipping_notes'   => ['nullable','string'],
        ]);

        $data['created_by'] = $data['created_by'] ?? auth()->id();

        $customer = Customer::create($data);
        $after = $r->string('after')->toString();
        if ($after === 'contacts') {
            return redirect()->to(route('customers.edit', $customer).'#contacts')
                            ->with('ok', 'Customer created.');
        }

        return redirect()->route('customers.index')->with('ok','Customer created.');
    }

    public function show(Customer $customer)
    {
        $tab = request('tab', 'profile');

        // preload relasi dasar + hitungan untuk badge
        $customer->load('jenis', 'contacts');
        $customer->loadCount([
            'contacts',
            'quotations',
            'salesOrders',        // << counter SO (sales_orders_count)
        ]);

        $quotations  = null;
        $salesOrders = null;

        if ($tab === 'quotations') {
            $q = trim((string) request('q', ''));
            $qq = \App\Models\Quotation::with('company')
                ->where('customer_id', $customer->id)
                ->latest('date')->latest('id');

            if ($q !== '') {
                $qq->where('number', 'like', "%{$q}%");
            }

            $quotations = $qq->paginate(15)->withQueryString();
        } elseif ($tab === 'sales_orders') {
            $q = trim((string) request('q', ''));
            $soq = \App\Models\SalesOrder::with('company')
                ->where('customer_id', $customer->id)
                ->latest('order_date')->latest('id');

            if ($q !== '') {
                $soq->where('so_number', 'like', "%{$q}%");
            }

            $salesOrders = $soq->paginate(15)->withQueryString();
        }

        return view('customers.show', compact('customer', 'tab', 'quotations', 'salesOrders'));
    }

    public function quickSearch(\Illuminate\Http\Request $req)
    {
        $q = trim((string) $req->input('q', ''));

        $customers = Customer::query()
            ->select('id','name')
            ->when($q !== '', fn($qq) => $qq->where('name','like',"%{$q}%"))
            ->orderBy('name')->limit(20)->get()
            ->map(fn($c) => [
                'uid'         => 'customer-'.$c->id,
                'type'        => 'customer',
                'label'       => $c->name,
                'name'        => $c->name,
                'customer_id' => $c->id,
                'contact_id'  => null,
            ]);

        $contacts = Contact::query()
            ->select('id','customer_id','first_name','last_name')
            ->with(['customer:id,name'])
            ->when($q !== '', function ($qq) use ($q) {
                $qq->where(function ($w) use ($q) {
                    $w->where('first_name','like',"%{$q}%")
                    ->orWhere('last_name','like',"%{$q}%");
                })->orWhereHas('customer', fn($c)=>$c->where('name','like',"%{$q}%"));
            })
            ->orderBy('first_name')->orderBy('last_name')
            ->limit(20)->get()
            ->map(function ($ct) {
                $person  = trim(($ct->first_name ?? '') . ' ' . ($ct->last_name ?? ''));
                $company = optional($ct->customer)->name;
                return [
                    'uid'         => 'contact-'.$ct->id,
                    'type'        => 'contact',
                    'label'       => $company ? "{$company} ({$person})" : $person,
                    'name'        => $person,
                    'customer_id' => $ct->customer_id,
                    'contact_id'  => $ct->id,
                ];
            });

        return response()->json($customers->merge($contacts)->take(30)->values());
    }

    public function edit(Customer $customer)
    {
        $this->authorize('update', $customer);

        $jenises = Jenis::where('is_active', true)->orderBy('name')->get(['id','name']);

        return view('customers.edit', compact('customer','jenises'));
    }

    public function update(Request $r, Customer $customer)
    {
        $this->authorize('update', $customer);

        $data = $r->validate([
            'name'  => ['required','string','max:255'],
            'email' => ['nullable','email'],
            'phone' => ['nullable','string','max:50'],
            'npwp'  => ['nullable','string','max:50'],
            'address'  => ['nullable','string'],
            'city'     => ['nullable','string','max:80'],
            'province' => ['nullable','string','max:80'],
            'country'  => ['nullable','string','max:80'],
            'website'  => ['nullable','string','max:255'],
            'billing_terms_days' => ['nullable','integer','min:0','max:3650'],
            'notes' => ['nullable','string'],
            'jenis_id' => ['required','exists:jenis,id'],
            'billing_street'  => ['nullable','string'],
            'billing_city'    => ['nullable','string','max:128'],
            'billing_state'   => ['nullable','string','max:128'],
            'billing_zip'     => ['nullable','string','max:32'],
            'billing_country' => ['nullable','string','size:2'],
            'billing_notes'   => ['nullable','string'],

            'shipping_street'  => ['nullable','string'],
            'shipping_city'    => ['nullable','string','max:128'],
            'shipping_state'   => ['nullable','string','max:128'],
            'shipping_zip'     => ['nullable','string','max:32'],
            'shipping_country' => ['nullable','string','size:2'],
            'shipping_notes'   => ['nullable','string'],
        ]);

        $customer->update($data);

        // ⬇️ pindah ke index setelah update
        return redirect()->route('customers.index')->with('ok', 'Customer updated.');
    }

    public function destroy(Customer $customer)
    {
        $this->authorize('delete', $customer);

        $customer->delete();

        return redirect()->route('customers.index')->with('ok','Customer deleted.');
    }

    public function updateNotes(Customer $customer)
    {
        $data = request()->validate([
            'notes' => ['nullable','string'],
        ]);
        $customer->update($data);

        return back()->with('success','Notes updated.');
    }
}
