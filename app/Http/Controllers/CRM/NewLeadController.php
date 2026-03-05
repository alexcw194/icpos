<?php

namespace App\Http\Controllers\CRM;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Jenis;
use App\Models\Prospect;
use App\Models\ProspectAssignmentLog;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class NewLeadController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('viewNewLeadsAny', Prospect::class);

        $user = $request->user();
        $isAdmin = $user->hasAnyRole(['Admin', 'SuperAdmin', 'Super Admin']);
        $q = trim((string) $request->input('q', ''));
        $status = trim((string) $request->input('status', ''));
        $ownerId = $request->filled('owner_user_id') ? (int) $request->input('owner_user_id') : null;
        $perPage = (int) $request->input('per_page', 25);
        if (!in_array($perPage, [25, 50, 75, 100, 150], true)) {
            $perPage = 25;
        }

        $rows = Prospect::query()
            ->with([
                'owner:id,name',
                'latestAnalysis:id,prospect_id,business_type,ai_industry_label,created_at',
            ])
            ->when($isAdmin, function ($query) use ($status, $ownerId) {
                $query->whereIn('status', [
                    Prospect::STATUS_ASSIGNED,
                    Prospect::STATUS_REJECTED,
                    Prospect::STATUS_CONVERTED,
                ]);

                if ($status !== '' && in_array($status, [
                    Prospect::STATUS_ASSIGNED,
                    Prospect::STATUS_REJECTED,
                    Prospect::STATUS_CONVERTED,
                ], true)) {
                    $query->where('status', $status);
                }
                if ($ownerId) {
                    $query->where('owner_user_id', $ownerId);
                }
            }, function ($query) use ($user) {
                $query->where('status', Prospect::STATUS_ASSIGNED)
                    ->where('owner_user_id', (int) $user->id);
            })
            ->when($q !== '', function ($query) use ($q) {
                $query->where(function ($nested) use ($q) {
                    $nested->where('name', 'like', "%{$q}%")
                        ->orWhere('place_id', 'like', "%{$q}%")
                        ->orWhere('formatted_address', 'like', "%{$q}%")
                        ->orWhere('short_address', 'like', "%{$q}%");
                });
            })
            ->orderByDesc('assigned_at')
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();

        $ownerOptions = User::role('Sales')->orderBy('name')->get(['id', 'name']);

        return view('crm.new-leads.index', [
            'rows' => $rows,
            'isAdmin' => $isAdmin,
            'ownerOptions' => $ownerOptions,
            'selectedStatus' => $status,
            'selectedOwnerId' => $ownerId,
            'perPage' => $perPage,
        ]);
    }

    public function show(Prospect $prospect)
    {
        $this->authorize('viewNewLead', $prospect);

        $prospect->load([
            'owner:id,name',
            'assignedBy:id,name',
            'rejectedBy:id,name',
            'keyword:id,keyword,category_label',
            'gridCell:id,name,city,province,radius_m',
            'convertedCustomer:id,name',
            'latestAnalysis.requestedBy:id,name',
            'analyses' => function ($query) {
                $query->latest('id')->limit(10);
            },
        ]);

        $user = request()->user();
        $isAdmin = $user->hasAnyRole(['Admin', 'SuperAdmin', 'Super Admin']);
        $ownerOptions = $isAdmin ? User::role('Sales')->orderBy('name')->get(['id', 'name']) : collect();
        $jenisList = Jenis::query()->active()->ordered()->get(['id', 'name']);

        return view('crm.new-leads.show', compact(
            'prospect',
            'isAdmin',
            'ownerOptions',
            'jenisList'
        ));
    }

    public function reject(Request $request, Prospect $prospect): RedirectResponse
    {
        $this->authorize('reject', $prospect);

        $data = $request->validate([
            'reason' => ['nullable', 'string', 'max:2000'],
        ]);

        $actorId = (int) $request->user()->id;
        $fromUserId = $prospect->owner_user_id ? (int) $prospect->owner_user_id : null;
        $reason = trim((string) ($data['reason'] ?? ''));
        if ($reason === '') {
            $reason = null;
        }

        $prospect->status = Prospect::STATUS_REJECTED;
        $prospect->rejected_at = Carbon::now();
        $prospect->rejected_by_user_id = $actorId;
        $prospect->reject_reason = $reason;
        $prospect->save();

        $this->logAction(
            prospectId: (int) $prospect->id,
            action: ProspectAssignmentLog::ACTION_REJECTED,
            fromUserId: $fromUserId,
            toUserId: null,
            actedByUserId: $actorId,
            note: $reason
        );

        return back()->with('success', 'Lead berhasil direject.');
    }

    public function reassign(Request $request, Prospect $prospect): RedirectResponse
    {
        $this->authorize('reassign', $prospect);

        $data = $request->validate([
            'owner_user_id' => ['required', 'exists:users,id'],
        ]);

        $newOwner = User::query()->findOrFail((int) $data['owner_user_id']);
        if (!$newOwner->hasRole('Sales')) {
            return back()->withErrors(['owner_user_id' => 'Owner baru harus role Sales.']);
        }

        $actorId = (int) $request->user()->id;
        $oldOwnerId = $prospect->owner_user_id ? (int) $prospect->owner_user_id : null;
        $newOwnerId = (int) $newOwner->id;

        $prospect->owner_user_id = $newOwnerId;
        $prospect->status = Prospect::STATUS_ASSIGNED;
        $prospect->assigned_at = Carbon::now();
        $prospect->assigned_by_user_id = $actorId;
        $prospect->rejected_at = null;
        $prospect->rejected_by_user_id = null;
        $prospect->reject_reason = null;
        $prospect->save();

        $this->logAction(
            prospectId: (int) $prospect->id,
            action: $oldOwnerId ? ProspectAssignmentLog::ACTION_REASSIGNED : ProspectAssignmentLog::ACTION_ASSIGNED,
            fromUserId: $oldOwnerId,
            toUserId: $newOwnerId,
            actedByUserId: $actorId,
            note: 'Reassigned from New Leads.'
        );

        return back()->with('success', 'Lead berhasil dipindahkan ke marketing baru.');
    }

    public function addAsCustomer(Request $request, Prospect $prospect): RedirectResponse
    {
        $this->authorize('addAsCustomer', $prospect);

        $actor = $request->user();
        $isAdmin = $actor->hasAnyRole(['Admin', 'SuperAdmin', 'Super Admin']);
        $rules = [
            'jenis_id' => ['required', 'exists:jenis,id'],
        ];
        if ($isAdmin) {
            $rules['sales_user_id'] = ['nullable', 'exists:users,id'];
        }
        $data = $request->validate($rules);

        $customerOwnerId = $isAdmin
            ? (int) ($data['sales_user_id'] ?: ($prospect->owner_user_id ?: $actor->id))
            : (int) $actor->id;

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
                'sales_user_id' => $customer->sales_user_id ?: $customerOwnerId,
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
                'sales_user_id' => $customerOwnerId,
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
        $prospect->owner_user_id = $customerOwnerId;
        $prospect->converted_customer_id = (int) $customer->id;
        $prospect->save();

        $this->logAction(
            prospectId: (int) $prospect->id,
            action: ProspectAssignmentLog::ACTION_CONVERTED,
            fromUserId: null,
            toUserId: $customerOwnerId,
            actedByUserId: (int) $actor->id,
            note: 'Converted from New Leads.'
        );

        return redirect()
            ->route('crm.new-leads.show', $prospect)
            ->with('success', 'Lead berhasil ditambahkan menjadi customer.');
    }

    private function appendNote(string $existing, string $line): string
    {
        $existing = trim($existing);
        if ($existing === '') {
            return $line;
        }

        return $existing . PHP_EOL . $line;
    }

    private function logAction(
        int $prospectId,
        string $action,
        ?int $fromUserId,
        ?int $toUserId,
        ?int $actedByUserId,
        ?string $note
    ): void {
        ProspectAssignmentLog::query()->create([
            'prospect_id' => $prospectId,
            'action' => $action,
            'from_user_id' => $fromUserId,
            'to_user_id' => $toUserId,
            'acted_by_user_id' => $actedByUserId,
            'note' => $note,
        ]);
    }
}
