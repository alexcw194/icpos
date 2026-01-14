<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\Customer;
use App\Models\Project;
use App\Models\User;
use App\Support\Number;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ProjectController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('viewAny', Project::class);

        $q = trim((string) $request->get('q', ''));
        $status = $request->get('status');

        $projects = Project::query()
            ->visibleTo($request->user())
            ->with(['customer:id,name', 'salesOwner:id,name'])
            ->when($q !== '', function ($query) use ($q) {
                $query->where(function ($w) use ($q) {
                    $w->where('code', 'like', "%{$q}%")
                        ->orWhere('name', 'like', "%{$q}%");
                });
            })
            ->when($status, fn($query) => $query->where('status', $status))
            ->orderByDesc('updated_at')
            ->paginate(15)
            ->withQueryString();

        return view('projects.index', compact('projects', 'q', 'status'));
    }

    public function create(Request $request)
    {
        $this->authorize('create', Project::class);

        $customers = Customer::orderBy('name')->get(['id', 'name']);
        $companies = Company::orderBy('name')->get(['id', 'alias', 'name']);
        $salesUsers = User::role('Sales')->orderBy('name')->get(['id', 'name']);

        $defaultCompanyId = Company::where('is_default', true)->value('id')
            ?? ($companies->first()->id ?? null);
        $defaultCustomerId = $request->get('customer_id');

        $systemsOptions = [
            'fire_alarm' => 'Fire Alarm',
            'fire_hydrant' => 'Fire Hydrant',
            'fire_sprinkler' => 'Fire Sprinkler',
            'cctv' => 'CCTV',
            'access_control' => 'Access Control',
            'others' => 'Lain-lain',
        ];

        return view('projects.create', compact(
            'customers',
            'companies',
            'salesUsers',
            'defaultCompanyId',
            'defaultCustomerId',
            'systemsOptions'
        ));
    }

    public function store(Request $request)
    {
        $this->authorize('create', Project::class);

        $data = $request->validate([
            'company_id' => ['nullable', 'exists:companies,id'],
            'customer_id' => ['required', 'exists:customers,id'],
            'code' => ['nullable', 'string', 'max:50', 'unique:projects,code'],
            'name' => ['required', 'string', 'max:190'],
            'systems_json' => ['nullable', 'array'],
            'systems_json.*' => ['string', 'max:50'],
            'status' => ['required', 'in:draft,active,closed,cancelled'],
            'sales_owner_user_id' => ['required', 'exists:users,id'],
            'start_date' => ['nullable', 'date'],
            'target_finish_date' => ['nullable', 'date'],
            'contract_value_baseline' => ['nullable'],
            'contract_value_current' => ['nullable'],
            'notes' => ['nullable', 'string'],
        ]);

        $company = null;
        if (!empty($data['company_id'])) {
            $company = Company::find($data['company_id']);
        } else {
            $company = Company::where('is_default', true)->first()
                ?? Company::orderBy('name')->first();
            $data['company_id'] = $company?->id;
        }

        $data['contract_value_baseline'] = Number::idToFloat($data['contract_value_baseline'] ?? 0);
        $data['contract_value_current'] = Number::idToFloat($data['contract_value_current'] ?? 0);

        if (empty($data['code'])) {
            if ($company) {
                $data['code'] = app(\App\Services\DocNumberService::class)
                    ->next('project', $company, now());
            } else {
                $data['code'] = 'PRJ/' . date('Y') . '/' . str_pad((string) random_int(1, 99999), 5, '0', STR_PAD_LEFT);
            }
        }

        DB::transaction(function () use ($data) {
            Project::create($data);
        });

        return redirect()->route('projects.index')->with('success', 'Project berhasil dibuat.');
    }

    public function show(Project $project)
    {
        $this->authorize('view', $project);

        $project->load([
            'customer',
            'company',
            'salesOwner',
            'quotations' => fn($q) => $q->latest('quotation_date'),
        ]);

        return view('projects.show', compact('project'));
    }

    public function edit(Project $project)
    {
        $this->authorize('update', $project);

        $customers = Customer::orderBy('name')->get(['id', 'name']);
        $companies = Company::orderBy('name')->get(['id', 'alias', 'name']);
        $salesUsers = User::role('Sales')->orderBy('name')->get(['id', 'name']);

        $systemsOptions = [
            'fire_alarm' => 'Fire Alarm',
            'fire_hydrant' => 'Fire Hydrant',
            'fire_sprinkler' => 'Fire Sprinkler',
            'cctv' => 'CCTV',
            'access_control' => 'Access Control',
            'others' => 'Lain-lain',
        ];

        return view('projects.edit', compact(
            'project',
            'customers',
            'companies',
            'salesUsers',
            'systemsOptions'
        ));
    }

    public function update(Request $request, Project $project)
    {
        $this->authorize('update', $project);

        $data = $request->validate([
            'company_id' => ['nullable', 'exists:companies,id'],
            'customer_id' => ['required', 'exists:customers,id'],
            'code' => ['required', 'string', 'max:50', Rule::unique('projects', 'code')->ignore($project->id)],
            'name' => ['required', 'string', 'max:190'],
            'systems_json' => ['nullable', 'array'],
            'systems_json.*' => ['string', 'max:50'],
            'status' => ['required', 'in:draft,active,closed,cancelled'],
            'sales_owner_user_id' => ['required', 'exists:users,id'],
            'start_date' => ['nullable', 'date'],
            'target_finish_date' => ['nullable', 'date'],
            'contract_value_baseline' => ['nullable'],
            'contract_value_current' => ['nullable'],
            'notes' => ['nullable', 'string'],
        ]);

        $data['contract_value_baseline'] = Number::idToFloat($data['contract_value_baseline'] ?? 0);
        $data['contract_value_current'] = Number::idToFloat($data['contract_value_current'] ?? 0);

        $project->update($data);

        return redirect()->route('projects.show', $project)->with('success', 'Project berhasil diperbarui.');
    }

    public function destroy(Project $project)
    {
        $this->authorize('delete', $project);

        $project->delete();

        return redirect()->route('projects.index')->with('success', 'Project dihapus.');
    }
}
