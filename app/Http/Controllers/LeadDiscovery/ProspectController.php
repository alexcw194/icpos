<?php

namespace App\Http\Controllers\LeadDiscovery;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Jenis;
use App\Models\Prospect;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class ProspectController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('viewAny', Prospect::class);

        $perPageOptions = [25, 50, 75, 100, 150];
        $perPage = (int) $request->input('per_page', 25);
        if (!in_array($perPage, $perPageOptions, true)) {
            $perPage = 25;
        }

        $statuses = [
            Prospect::STATUS_NEW,
            Prospect::STATUS_ASSIGNED,
            Prospect::STATUS_CONVERTED,
            Prospect::STATUS_IGNORED,
        ];
        $statusFilterOptions = [
            'all_active' => 'All Active (exclude ignored)',
            Prospect::STATUS_NEW => 'New',
            Prospect::STATUS_ASSIGNED => 'Assigned',
            Prospect::STATUS_CONVERTED => 'Converted',
            Prospect::STATUS_IGNORED => 'Ignored',
            'all' => 'All (include ignored)',
        ];

        $q = trim((string) $request->input('q', ''));
        $status = (string) $request->input('status', '');
        $selectedStatus = $status === '' ? 'all_active' : $status;
        $ownerId = $request->filled('owner_user_id') ? (int) $request->input('owner_user_id') : null;
        $keywordId = $request->filled('keyword_id') ? (int) $request->input('keyword_id') : null;
        $province = trim((string) $request->input('province', ''));
        $city = trim((string) $request->input('city', ''));
        [$provinceOptions, $cityOptionsByProvince, $cityOptionsAll] = $this->buildProspectLocationOptions();
        $selectedProvince = $province;
        $selectedCity = $city;
        $cityOptions = $selectedProvince !== ''
            ? ($cityOptionsByProvince[$selectedProvince] ?? [])
            : $cityOptionsAll;
        if ($selectedCity !== '' && !in_array($selectedCity, $cityOptions, true)) {
            $selectedCity = '';
            $city = '';
        }
        $from = trim((string) $request->input('discovered_from', ''));
        $to = trim((string) $request->input('discovered_to', ''));
        $hasPhone = (string) $request->input('has_phone', '');
        $hasWebsite = (string) $request->input('has_website', '');

        $rows = Prospect::query()
            ->with(['keyword:id,keyword,category_label', 'gridCell:id,name,city,province', 'owner:id,name'])
            ->when($q !== '', function ($builder) use ($q) {
                $builder->where(function ($nested) use ($q) {
                    $nested->where('name', 'like', "%{$q}%")
                        ->orWhere('place_id', 'like', "%{$q}%")
                        ->orWhere('formatted_address', 'like', "%{$q}%")
                        ->orWhere('short_address', 'like', "%{$q}%");
                });
            })
            ->when(in_array($status, $statuses, true), fn ($builder) => $builder->where('status', $status))
            ->when(!in_array($status, array_merge($statuses, ['all']), true), function ($builder) {
                $builder->where('status', '!=', Prospect::STATUS_IGNORED);
            })
            ->when($ownerId, fn ($builder) => $builder->where('owner_user_id', $ownerId))
            ->when($keywordId, fn ($builder) => $builder->where('keyword_id', $keywordId))
            ->when($province !== '', fn ($builder) => $builder->where('province', 'like', "%{$province}%"))
            ->when($city !== '', fn ($builder) => $builder->where('city', 'like', "%{$city}%"))
            ->when($from !== '', fn ($builder) => $builder->whereDate('discovered_at', '>=', $from))
            ->when($to !== '', fn ($builder) => $builder->whereDate('discovered_at', '<=', $to))
            ->when($hasPhone === '1', fn ($builder) => $builder->whereNotNull('phone')->where('phone', '!=', ''))
            ->when($hasPhone === '0', fn ($builder) => $builder->where(function ($nested) {
                $nested->whereNull('phone')->orWhere('phone', '');
            }))
            ->when($hasWebsite === '1', fn ($builder) => $builder->whereNotNull('website')->where('website', '!=', ''))
            ->when($hasWebsite === '0', fn ($builder) => $builder->where(function ($nested) {
                $nested->whereNull('website')->orWhere('website', '');
            }))
            ->orderByDesc('discovered_at')
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();

        $keywords = \App\Models\LdKeyword::query()
            ->orderBy('priority')
            ->orderBy('keyword')
            ->get(['id', 'keyword', 'category_label']);

        $owners = User::query()->orderBy('name')->get(['id', 'name']);

        return view('lead-discovery.prospects.index', [
            'rows' => $rows,
            'keywords' => $keywords,
            'owners' => $owners,
            'statuses' => $statuses,
            'statusFilterOptions' => $statusFilterOptions,
            'selectedStatus' => $selectedStatus,
            'provinceOptions' => $provinceOptions,
            'cityOptions' => $cityOptions,
            'selectedProvince' => $selectedProvince,
            'selectedCity' => $selectedCity,
            'cityOptionsByProvince' => ['__all' => $cityOptionsAll] + $cityOptionsByProvince,
            'perPage' => $perPage,
            'perPageOptions' => $perPageOptions,
        ]);
    }

    public function show(Prospect $prospect)
    {
        $this->authorize('view', $prospect);

        $prospect->load([
            'keyword:id,keyword,category_label',
            'gridCell:id,name,city,province,radius_m',
            'owner:id,name',
            'convertedCustomer:id,name',
        ]);

        $owners = User::query()->orderBy('name')->get(['id', 'name']);
        $jenisList = Jenis::query()->active()->ordered()->get(['id', 'name']);
        $statusOptions = [
            Prospect::STATUS_NEW,
            Prospect::STATUS_ASSIGNED,
            Prospect::STATUS_IGNORED,
        ];

        return view('lead-discovery.prospects.show', compact(
            'prospect',
            'owners',
            'jenisList',
            'statusOptions'
        ));
    }

    public function assign(Request $request, Prospect $prospect): RedirectResponse
    {
        $this->authorize('update', $prospect);

        $data = $request->validate([
            'owner_user_id' => ['nullable', 'exists:users,id'],
        ]);

        $prospect->owner_user_id = $data['owner_user_id'] ?? null;
        if ($prospect->owner_user_id && $prospect->status === Prospect::STATUS_NEW) {
            $prospect->status = Prospect::STATUS_ASSIGNED;
        }
        if (!$prospect->owner_user_id && $prospect->status === Prospect::STATUS_ASSIGNED) {
            $prospect->status = Prospect::STATUS_NEW;
        }
        $prospect->save();

        return back()->with('success', 'Owner prospect berhasil diperbarui.');
    }

    public function status(Request $request, Prospect $prospect): RedirectResponse
    {
        $this->authorize('update', $prospect);

        $allowed = [
            Prospect::STATUS_NEW,
            Prospect::STATUS_ASSIGNED,
            Prospect::STATUS_IGNORED,
        ];

        $data = $request->validate([
            'status' => ['required', 'in:' . implode(',', $allowed)],
        ]);

        $prospect->status = $data['status'];
        $prospect->save();

        return back()->with('success', 'Status prospect berhasil diperbarui.');
    }

    public function convert(Request $request, Prospect $prospect): RedirectResponse
    {
        $this->authorize('update', $prospect);

        $data = $request->validate([
            'jenis_id' => ['required', 'exists:jenis,id'],
            'sales_user_id' => ['required', 'exists:users,id'],
        ]);

        $customer = null;
        $nameKey = Customer::makeNameKey((string) $prospect->name);
        if ($nameKey !== '') {
            $customer = Customer::query()->where('name_key', $nameKey)->first();
        }

        $address = $prospect->formatted_address ?: $prospect->short_address;
        $noteLine = sprintf(
            'Source: Google Places | place_id: %s | discovered_at: %s | converted_at: %s',
            (string) $prospect->place_id,
            optional($prospect->discovered_at)->format('Y-m-d H:i:s') ?: '-',
            Carbon::now()->format('Y-m-d H:i:s')
        );

        if ($customer) {
            $customer->fill([
                'website' => $customer->website ?: $prospect->website,
                'address' => $customer->address ?: $address,
                'city' => $customer->city ?: $prospect->city,
                'province' => $customer->province ?: $prospect->province,
                'country' => $customer->country ?: ($prospect->country ?: 'Indonesia'),
                'jenis_id' => $customer->jenis_id ?: (int) $data['jenis_id'],
                'sales_user_id' => $customer->sales_user_id ?: (int) $data['sales_user_id'],
            ]);
            $customer->notes = $this->appendNote((string) $customer->notes, $noteLine);
            $customer->save();
        } else {
            $customer = Customer::query()->create([
                'name' => (string) $prospect->name,
                'website' => $prospect->website,
                'address' => $address,
                'city' => $prospect->city,
                'province' => $prospect->province,
                'country' => $prospect->country ?: 'Indonesia',
                'jenis_id' => (int) $data['jenis_id'],
                'sales_user_id' => (int) $data['sales_user_id'],
                'notes' => $noteLine,
            ]);
        }

        if ($prospect->phone) {
            $hasSamePhone = $customer->contacts()
                ->where('phone', $prospect->phone)
                ->exists();

            if (!$hasSamePhone) {
                $customer->contacts()->create([
                    'first_name' => 'General',
                    'position' => 'Frontdesk/General',
                    'position_snapshot' => 'Frontdesk/General',
                    'phone' => $prospect->phone,
                ]);
            }
        }

        $prospect->status = Prospect::STATUS_CONVERTED;
        $prospect->owner_user_id = (int) $data['sales_user_id'];
        $prospect->converted_customer_id = (int) $customer->id;
        $prospect->save();

        return redirect()
            ->route('lead-discovery.prospects.show', $prospect)
            ->with('success', 'Prospect berhasil dikonversi menjadi customer.');
    }

    private function appendNote(string $existing, string $line): string
    {
        $existing = trim($existing);
        if ($existing === '') {
            return $line;
        }

        return $existing . PHP_EOL . $line;
    }

    /**
     * @return array{0: array<int, string>, 1: array<string, array<int, string>>, 2: array<int, string>}
     */
    private function buildProspectLocationOptions(): array
    {
        $rows = Prospect::query()
            ->select(['province', 'city'])
            ->where(function ($query) {
                $query->where(function ($nested) {
                    $nested->whereNotNull('province')->where('province', '!=', '');
                })->orWhere(function ($nested) {
                    $nested->whereNotNull('city')->where('city', '!=', '');
                });
            })
            ->get();

        $provinceSet = [];
        $citySet = [];
        $cityByProvince = [];

        foreach ($rows as $row) {
            $province = trim((string) ($row->province ?? ''));
            $city = trim((string) ($row->city ?? ''));

            if ($province !== '') {
                $provinceSet[$province] = true;
            }
            if ($city !== '') {
                $citySet[$city] = true;
            }
            if ($province !== '' && $city !== '') {
                $cityByProvince[$province][$city] = true;
            }
        }

        $provinceOptions = array_keys($provinceSet);
        sort($provinceOptions, SORT_NATURAL | SORT_FLAG_CASE);

        $cityOptionsAll = array_keys($citySet);
        sort($cityOptionsAll, SORT_NATURAL | SORT_FLAG_CASE);

        $normalizedCityByProvince = [];
        foreach ($cityByProvince as $province => $cities) {
            $cityList = array_keys($cities);
            sort($cityList, SORT_NATURAL | SORT_FLAG_CASE);
            $normalizedCityByProvince[$province] = $cityList;
        }
        ksort($normalizedCityByProvince, SORT_NATURAL | SORT_FLAG_CASE);

        return [$provinceOptions, $normalizedCityByProvince, $cityOptionsAll];
    }
}
