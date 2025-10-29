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

    public function dupCheck(Request $request)
    {
        $name = trim((string) $request->query('name', ''));
        if ($name === '') {
            return response()->json(['exists' => false]);
        }

        // Cek exact (case-insensitive), lalu fallback "mirip" (LIKE) agar informatif saat user mengetik
        $kwLower = mb_strtolower($name);
        $exists = Customer::query()
            ->whereRaw('LOWER(name) = ?', [$kwLower])
            ->exists();

        if (!$exists && mb_strlen($name) >= 3) {
            $like = '%' . str_replace(['%','_'], ['\\%','\\_'], $name) . '%';
            $exists = Customer::where('name', 'like', $like)->exists();
        }

        return response()->json(['exists' => $exists]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'    => ['required','string','max:255'],
            'jenis_id'=> ['required','integer','exists:jenis,id'],

            // alamat & info lain → opsional
            'email'   => ['nullable','string','max:255'],
            'phone'   => ['nullable','string','max:100'],
            'npwp'    => ['nullable','string','max:100'],

            'address'  => ['nullable','string','max:255'],
            'city'     => ['nullable','string','max:100'],
            'province' => ['nullable','string','max:100'],
            'country'  => ['nullable','string','max:100'],
            'website'  => ['nullable','string','max:255'],
            'billing_terms_days' => ['nullable','integer','min:0'],
            'notes'    => ['nullable','string'],

            'billing_street'   => ['nullable','string','max:255'],
            'billing_city'     => ['nullable','string','max:100'],
            'billing_state'    => ['nullable','string','max:100'],
            'billing_zip'      => ['nullable','string','max:20'],
            'billing_country'  => ['nullable','string','max:100'],
            'billing_notes'    => ['nullable','string'],

            'shipping_street'  => ['nullable','string','max:255'],
            'shipping_city'    => ['nullable','string','max:100'],
            'shipping_state'   => ['nullable','string','max:100'],
            'shipping_zip'     => ['nullable','string','max:20'],
            'shipping_country' => ['nullable','string','max:100'],
            'shipping_notes'   => ['nullable','string'],
        ]);

        // (opsional) default kalau ingin isi otomatis
        // $data['country'] = $data['country'] ?? 'ID';

        $data['created_by'] = auth()->id();
        $data['name_key']   = mb_strtolower($data['name']);

        $customer = Customer::create($data);

        return redirect()
            ->route('customers.show', $customer)
            ->with('success', 'Customer berhasil dibuat');
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

    public function update(Request $request, Customer $customer)
    {
        $data = $request->validate([
            'name'    => ['required','string','max:255'],
            'jenis_id'=> ['required','integer','exists:jenis,id'],

            'email'   => ['nullable','string','max:255'],
            'phone'   => ['nullable','string','max:100'],
            'npwp'    => ['nullable','string','max:100'],

            'address'  => ['nullable','string','max:255'],
            'city'     => ['nullable','string','max:100'],
            'province' => ['nullable','string','max:100'],
            'country'  => ['nullable','string','max:100'],
            'website'  => ['nullable','string','max:255'],
            'billing_terms_days' => ['nullable','integer','min:0'],
            'notes'    => ['nullable','string'],

            'billing_street'   => ['nullable','string','max:255'],
            'billing_city'     => ['nullable','string','max:100'],
            'billing_state'    => ['nullable','string','max:100'],
            'billing_zip'      => ['nullable','string','max:20'],
            'billing_country'  => ['nullable','string','max:100'],
            'billing_notes'    => ['nullable','string'],

            'shipping_street'  => ['nullable','string','max:255'],
            'shipping_city'    => ['nullable','string','max:100'],
            'shipping_state'   => ['nullable','string','max:100'],
            'shipping_zip'     => ['nullable','string','max:20'],
            'shipping_country' => ['nullable','string','max:100'],
            'shipping_notes'   => ['nullable','string'],
        ]);

        $data['name_key'] = mb_strtolower($data['name']);

        $customer->update($data);

        return redirect()
            ->route('customers.show', $customer)
            ->with('success', 'Customer berhasil diperbarui');
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
