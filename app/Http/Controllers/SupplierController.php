<?php

namespace App\Http\Controllers;

use App\Models\Supplier;
use App\Models\TermOfPayment;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class SupplierController extends Controller
{
    private const BILLING_DUE_TRIGGERS = [
        'on_invoice',
        'after_invoice_days',
        'on_delivery',
        'after_delivery_days',
        'eom_day',
        'next_month_day',
        'on_so',
        'end_of_month',
    ];

    public function index(Request $request)
    {
        $q = trim((string) $request->get('q', ''));
        $rows = Supplier::query()
            ->when($q !== '', function ($qq) use ($q) {
                $qq->where('name', 'like', "%{$q}%")
                   ->orWhere('phone', 'like', "%{$q}%")
                   ->orWhere('email', 'like', "%{$q}%");
            })
            ->orderBy('name')
            ->paginate($this->resolvePerPage())
            ->withQueryString();

        return view('admin.suppliers.index', compact('rows', 'q'));
    }

    public function create(Request $request)
    {
        $row = new Supplier(['is_active' => true]);
        $topOptions = $this->topOptions();
        $billingTermsData = old('billing_terms', $this->defaultBillingTermsForSupplier($row));

        return view('admin.suppliers.form', compact('row', 'topOptions', 'billingTermsData'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'phone' => ['nullable', 'string', 'max:32'],
            'email' => ['nullable', 'email', 'max:150'],
            'address' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
            'is_active' => ['sometimes', 'boolean'],
            'billing_terms' => ['required', 'array', 'min:1'],
            'billing_terms.*.top_code' => ['required', 'string', 'max:64'],
            'billing_terms.*.percent' => ['required', 'string'],
            'billing_terms.*.note' => ['nullable', 'string', 'max:190'],
            'billing_terms.*.due_trigger' => ['nullable', Rule::in(self::BILLING_DUE_TRIGGERS)],
            'billing_terms.*.offset_days' => ['nullable', 'integer', 'min:0'],
            'billing_terms.*.day_of_month' => ['nullable', 'integer', 'min:1', 'max:28'],
        ]);

        $billingTerms = $this->normalizeBillingTerms($data['billing_terms'] ?? []);

        unset($data['billing_terms']);
        $data['is_active'] = (bool) ($data['is_active'] ?? true);
        $data['default_billing_terms'] = $billingTerms;

        Supplier::create($data);

        return redirect()->route('suppliers.index')->with('success', 'Supplier dibuat.');
    }

    public function edit(Request $request, Supplier $supplier)
    {
        $row = $supplier;
        $topOptions = $this->topOptions();
        $billingTermsData = old('billing_terms', $this->defaultBillingTermsForSupplier($row));

        return view('admin.suppliers.form', compact('row', 'topOptions', 'billingTermsData'));
    }

    public function update(Request $request, Supplier $supplier)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'phone' => ['nullable', 'string', 'max:32'],
            'email' => ['nullable', 'email', 'max:150'],
            'address' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
            'is_active' => ['sometimes', 'boolean'],
            'billing_terms' => ['required', 'array', 'min:1'],
            'billing_terms.*.top_code' => ['required', 'string', 'max:64'],
            'billing_terms.*.percent' => ['required', 'string'],
            'billing_terms.*.note' => ['nullable', 'string', 'max:190'],
            'billing_terms.*.due_trigger' => ['nullable', Rule::in(self::BILLING_DUE_TRIGGERS)],
            'billing_terms.*.offset_days' => ['nullable', 'integer', 'min:0'],
            'billing_terms.*.day_of_month' => ['nullable', 'integer', 'min:1', 'max:28'],
        ]);

        $billingTerms = $this->normalizeBillingTerms($data['billing_terms'] ?? []);

        unset($data['billing_terms']);
        $data['is_active'] = (bool) ($data['is_active'] ?? $supplier->is_active);
        $data['default_billing_terms'] = $billingTerms;

        $supplier->update($data);

        return redirect()->route('suppliers.index')->with('success', 'Supplier diperbarui.');
    }

    public function destroy(Supplier $supplier)
    {
        $supplier->delete();
        return back()->with('success', 'Supplier dihapus.');
    }

    private function topOptions()
    {
        return TermOfPayment::query()
            ->whereIn('code', TermOfPayment::ALLOWED_CODES)
            ->orderBy('code')
            ->get(['code', 'description', 'is_active', 'applicable_to']);
    }

    private function defaultBillingTermsForSupplier(?Supplier $supplier): array
    {
        $saved = $supplier?->default_billing_terms;
        if (is_array($saved) && count($saved) > 0) {
            return $saved;
        }

        return [
            ['top_code' => 'FINISH', 'percent' => 100, 'due_trigger' => 'on_invoice'],
        ];
    }

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

    private function normalizeBillingTerms(array $terms): array
    {
        $tops = TermOfPayment::query()
            ->whereIn('code', TermOfPayment::ALLOWED_CODES)
            ->get(['code']);
        $allowedMap = [];
        foreach ($tops as $top) {
            $allowedMap[strtoupper((string) $top->code)] = true;
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
            if (isset($seen[$code])) {
                throw ValidationException::withMessages([
                    "billing_terms.$idx.top_code" => 'Kode TOP duplikat di supplier.',
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
}
