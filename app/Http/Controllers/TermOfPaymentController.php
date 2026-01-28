<?php

namespace App\Http\Controllers;

use App\Models\TermOfPayment;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TermOfPaymentController extends Controller
{
    private const DUE_TRIGGERS = [
        'on_invoice',
        'after_invoice_days',
        'on_delivery',
        'after_delivery_days',
        'eom_day',
        'next_month_day',
    ];
    public function index(Request $request)
    {
        $q = $request->string('q')->toString();
        $status = $request->string('status')->toString();

        $rows = TermOfPayment::query()
            ->when($q !== '', function ($qq) use ($q) {
                $qq->where('code', 'like', "%{$q}%")
                    ->orWhere('description', 'like', "%{$q}%");
            })
            ->when($status === 'active', fn ($qq) => $qq->where('is_active', true))
            ->when($status === 'inactive', fn ($qq) => $qq->where('is_active', false))
            ->orderBy('code')
            ->paginate(20)
            ->withQueryString();

        return view('admin.term_of_payments.index', compact('rows', 'q', 'status'));
    }

    public function create()
    {
        $existing = TermOfPayment::pluck('code')->all();
        $availableCodes = array_values(array_diff(TermOfPayment::ALLOWED_CODES, $existing));

        $row = new TermOfPayment([
            'is_active' => true,
            'applicable_to' => ['goods','project','maintenance'],
        ]);
        return view('admin.term_of_payments.form', compact('row', 'availableCodes'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:64', 'unique:term_of_payments,code'],
            'description' => ['nullable', 'string', 'max:255'],
            'is_active' => ['nullable', 'boolean'],
            'applicable_to' => ['nullable', 'array'],
            'applicable_to.*' => ['in:goods,project,maintenance'],
            'schedules' => ['nullable', 'array'],
            'schedules.*.portion_type' => ['nullable', 'in:percent,fixed'],
            'schedules.*.portion_value' => ['nullable', 'string'],
            'schedules.*.due_trigger' => ['nullable', Rule::in(self::DUE_TRIGGERS)],
            'schedules.*.offset_days' => ['nullable', 'integer', 'min:0'],
            'schedules.*.specific_day' => ['nullable', 'integer', 'min:1', 'max:31'],
            'schedules.*.notes' => ['nullable', 'string', 'max:255'],
        ]);

        $code = strtoupper(trim($data['code']));
        if (!in_array($code, TermOfPayment::ALLOWED_CODES, true)) {
            return back()->withErrors(['code' => 'Kode TOP tidak valid.'])->withInput();
        }

        $data['code'] = $code;
        $data['is_active'] = $request->boolean('is_active');
        $data['applicable_to'] = array_values(array_unique($data['applicable_to'] ?? []));

        $schedules = $this->normalizeSchedules($data['schedules'] ?? []);
        unset($data['schedules']);

        $row = TermOfPayment::create($data);
        foreach ($schedules as $sch) {
            $row->schedules()->create($sch);
        }

        return redirect()->route('term-of-payments.index')
            ->with('success', 'Term of Payment created.');
    }

    public function show(TermOfPayment $termOfPayment)
    {
        return redirect()->route('term-of-payments.edit', $termOfPayment);
    }

    public function edit(TermOfPayment $termOfPayment)
    {
        $row = $termOfPayment->load('schedules');
        $availableCodes = TermOfPayment::ALLOWED_CODES;
        return view('admin.term_of_payments.form', compact('row', 'availableCodes'));
    }

    public function update(Request $request, TermOfPayment $termOfPayment)
    {
        $data = $request->validate([
            'description' => ['nullable', 'string', 'max:255'],
            'is_active' => ['nullable', 'boolean'],
            'applicable_to' => ['nullable', 'array'],
            'applicable_to.*' => ['in:goods,project,maintenance'],
            'schedules' => ['nullable', 'array'],
            'schedules.*.portion_type' => ['nullable', 'in:percent,fixed'],
            'schedules.*.portion_value' => ['nullable', 'string'],
            'schedules.*.due_trigger' => ['nullable', Rule::in(self::DUE_TRIGGERS)],
            'schedules.*.offset_days' => ['nullable', 'integer', 'min:0'],
            'schedules.*.specific_day' => ['nullable', 'integer', 'min:1', 'max:31'],
            'schedules.*.notes' => ['nullable', 'string', 'max:255'],
        ]);

        $data['is_active'] = $request->boolean('is_active');
        $data['applicable_to'] = array_values(array_unique($data['applicable_to'] ?? []));

        $schedules = $this->normalizeSchedules($data['schedules'] ?? []);
        unset($data['schedules']);

        $termOfPayment->update($data);
        $termOfPayment->schedules()->delete();
        foreach ($schedules as $sch) {
            $termOfPayment->schedules()->create($sch);
        }

        return redirect()->route('term-of-payments.index')
            ->with('ok', 'Term of Payment updated.');
    }

    public function destroy(TermOfPayment $termOfPayment)
    {
        try {
            $termOfPayment->delete();
            return redirect()->route('term-of-payments.index')
                ->with('ok', 'Term of Payment deleted.');
        } catch (\Throwable $e) {
            return redirect()->route('term-of-payments.index')
                ->with('error', 'Term of Payment tidak bisa dihapus.');
        }
    }

    private function normalizeSchedules(array $rows): array
    {
        $clean = [];
        $percentSum = 0.0;
        $hasPercent = false;
        $hasFixed = false;
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

        foreach (array_values($rows) as $idx => $row) {
            $portionType = $row['portion_type'] ?? '';
            $dueTrigger = $row['due_trigger'] ?? '';
            if ($portionType === '' || $dueTrigger === '') {
                continue;
            }
            $portionValue = $toNum($row['portion_value'] ?? 0);
            $offsetDays = $row['offset_days'] ?? null;
            $specificDay = $row['specific_day'] ?? null;
            if ($portionType === 'percent') {
                $hasPercent = true;
                $percentSum += $portionValue;
            } elseif ($portionType === 'fixed') {
                $hasFixed = true;
            }
            if (in_array($dueTrigger, ['after_invoice_days', 'after_delivery_days'], true) && $offsetDays === null) {
                $offsetDays = 0;
            }
            if (in_array($dueTrigger, ['eom_day', 'next_month_day'], true) && $specificDay === null) {
                $specificDay = 1;
            }
            $clean[] = [
                'sequence' => $idx + 1,
                'portion_type' => $portionType,
                'portion_value' => $portionValue,
                'due_trigger' => $dueTrigger,
                'offset_days' => $offsetDays,
                'specific_day' => $specificDay,
                'notes' => $row['notes'] ?? null,
            ];
        }

        if ($hasPercent && !$hasFixed && abs($percentSum - 100) > 0.01) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'schedules' => 'Total percent pada schedule harus 100%.',
            ]);
        }

        return $clean;
    }
}
