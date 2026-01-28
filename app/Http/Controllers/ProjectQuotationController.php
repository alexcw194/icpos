<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\Project;
use App\Models\ProjectQuotation;
use App\Models\ProjectQuotationLine;
use App\Models\ProjectQuotationPaymentTerm;
use App\Models\ProjectQuotationSection;
use App\Models\TermOfPayment;
use App\Models\SubContractor;
use App\Models\LaborCost;
use App\Models\Setting;
use App\Models\User;
use App\Services\ProjectQuotationTotalsService;
use App\Support\Number;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class ProjectQuotationController extends Controller
{

    public function index(Project $project)
    {
        $this->authorize('view', $project);

        $quotations = $project->quotations()
            ->visibleTo(auth()->user())
            ->orderByDesc('quotation_date')
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        return view('projects.quotations.index', compact('project', 'quotations'));
    }

    public function create(Project $project)
    {
        $this->authorize('create', ProjectQuotation::class);

        $project->load(['customer', 'company', 'salesOwner']);
        $companies = Company::orderBy('name')->get(['id', 'alias', 'name', 'is_taxable', 'default_tax_percent']);
        $salesUsers = User::role('Sales')->orderBy('name')->get(['id', 'name']);
        $signatureUsers = $this->signatureOptions();
        $contacts = $project->customer?->contacts()->orderBy('first_name')->get() ?? collect();
        $canManageCost = $this->canManageLaborCost();
        $subContractors = $this->loadSubContractors($canManageCost);
        $defaultSubContractorId = $this->defaultSubContractorId($canManageCost);
        $selectedSubContractorId = old('sub_contractor_id') ?: ($defaultSubContractorId ?: null);
        $topOptions = TermOfPayment::query()
            ->orderBy('code')
            ->get(['code','description','is_active']);

        $quotation = new ProjectQuotation([
            'quotation_date' => now()->toDateString(),
            'to_name' => $project->customer?->name,
            'project_title' => $project->name,
            'working_time_hours_per_day' => 8,
            'validity_days' => 15,
            'tax_enabled' => false,
            'tax_percent' => 0,
            'sales_owner_user_id' => $project->sales_owner_user_id ?? auth()->id(),
            'signatory_name' => auth()->user()?->name,
            'sub_contractor_id' => $this->supportsSubContractor()
                ? $selectedSubContractorId
                : null,
        ]);

        $paymentTerms = collect([
            ['code' => 'DP', 'label' => 'DP', 'percent' => 30, 'sequence' => 1],
            ['code' => 'T1', 'label' => 'T1', 'percent' => 30, 'sequence' => 2],
            ['code' => 'FINISH', 'label' => 'Finish', 'percent' => 40, 'sequence' => 3],
        ]);

        $sections = collect([
            (object) [
                'name' => 'Perlengkapan Utama',
                'sort_order' => 1,
                'lines' => collect([
                    (object) [
                        'line_no' => '1',
                        'description' => '',
                        'source_type' => 'item',
                        'item_id' => null,
                        'item_label' => null,
                        'qty' => 1,
                        'unit' => 'LS',
                        'unit_price' => 0,
                        'material_total' => 0,
                        'labor_total' => 0,
                        'labor_source' => 'manual',
                        'labor_unit_cost_snapshot' => 0,
                    ],
                ]),
            ],
        ]);

        return view('projects.quotations.create', compact(
            'project',
            'quotation',
            'companies',
            'salesUsers',
            'signatureUsers',
            'paymentTerms',
            'sections',
            'contacts',
            'subContractors',
            'selectedSubContractorId',
            'canManageCost',
            'topOptions'
        ));
    }

    public function store(Request $request, Project $project, ProjectQuotationTotalsService $totals)
    {
        $this->authorize('create', ProjectQuotation::class);

        $data = $this->validateQuotation($request);
        $data['tax_enabled'] = !empty($data['tax_enabled']);
        $data['tax_percent'] = Number::idToFloat($data['tax_percent'] ?? 0);
        $canManageCost = $this->canManageLaborCost($request->user());
        $this->normalizeSubContractorInput($data, $canManageCost, null);

        if ((int) $data['customer_id'] !== (int) $project->customer_id) {
            throw ValidationException::withMessages([
                'customer_id' => 'Customer tidak sesuai dengan project.',
            ]);
        }

        $this->validatePaymentTermsSum($data['payment_terms'] ?? []);

        $company = Company::findOrFail($data['company_id']);
        $brand = $this->brandSnapshot($company);

        $computed = $totals->compute($data);

        $quotation = null;
        DB::transaction(function () use ($project, $data, $company, $brand, $computed, &$quotation) {
            $quotation = ProjectQuotation::create([
                'project_id' => $project->id,
                'company_id' => $company->id,
                'customer_id' => $data['customer_id'],
                'sub_contractor_id' => $data['sub_contractor_id'] ?? null,
                'number' => 'TEMP',
                'version' => 1,
                'status' => ProjectQuotation::STATUS_DRAFT,
                'quotation_date' => $data['quotation_date'],
                'to_name' => $data['to_name'],
                'attn_name' => $data['attn_name'] ?? null,
                'project_title' => $data['project_title'],
                'working_time_days' => $data['working_time_days'] ?? null,
                'working_time_hours_per_day' => $data['working_time_hours_per_day'] ?? 8,
                'validity_days' => $data['validity_days'] ?? 15,
                'tax_enabled' => $data['tax_enabled'],
                'tax_percent' => $computed['tax_percent'],
                'subtotal_material' => $computed['subtotal_material'],
                'subtotal_labor' => $computed['subtotal_labor'],
                'subtotal' => $computed['subtotal'],
                'tax_amount' => $computed['tax_amount'],
                'grand_total' => $computed['grand_total'],
                'notes' => $data['notes'] ?? null,
                'signatory_name' => $data['signatory_name'] ?? null,
                'signatory_title' => $data['signatory_title'] ?? null,
                'sales_owner_user_id' => $data['sales_owner_user_id'],
                'brand_snapshot' => $brand,
            ]);

            $quotation->update([
                'number' => app(\App\Services\DocNumberService::class)
                    ->next('project_quotation', $company, Carbon::parse($data['quotation_date'])),
            ]);

            foreach ($computed['sections'] as $sIndex => $sectionData) {
                $section = ProjectQuotationSection::create([
                    'project_quotation_id' => $quotation->id,
                    'name' => $sectionData['name'],
                    'sort_order' => $sectionData['sort_order'] ?? $sIndex,
                ]);

                foreach ($sectionData['lines'] as $lineData) {
                    ProjectQuotationLine::create(array_merge($lineData, [
                        'section_id' => $section->id,
                    ]));
                }
            }

            foreach ($data['payment_terms'] ?? [] as $pIndex => $term) {
                ProjectQuotationPaymentTerm::create([
                    'project_quotation_id' => $quotation->id,
                    'code' => $term['code'] ?? 'DP',
                    'label' => $term['label'] ?? ($term['code'] ?? 'DP'),
                    'percent' => Number::idToFloat($term['percent'] ?? 0),
                    'sequence' => (int) ($term['sequence'] ?? ($pIndex + 1)),
                    'trigger_note' => $term['trigger_note'] ?? null,
                ]);
            }
        });

        return redirect()
            ->route('projects.quotations.show', [$project, $quotation])
            ->with('success', 'BQ berhasil dibuat.');
    }

    public function show(Project $project, ProjectQuotation $quotation)
    {
        $this->authorize('view', $quotation);
        $this->ensureProjectMatch($project, $quotation);

        $quotation->load([
            'project',
            'customer',
            'company',
            'salesOwner',
            'sections.lines',
            'paymentTerms',
        ]);

        return view('projects.quotations.show', compact('project', 'quotation'));
    }

    public function edit(Project $project, ProjectQuotation $quotation)
    {
        $this->authorize('update', $quotation);
        $this->ensureProjectMatch($project, $quotation);

        if ($quotation->isLocked()) {
            return redirect()
                ->route('projects.quotations.show', [$project, $quotation])
                ->with('warning', 'BQ yang sudah issued/won/lost tidak bisa diedit.');
        }

        $quotation->load(['sections.lines', 'paymentTerms']);
        $companies = Company::orderBy('name')->get(['id', 'alias', 'name', 'is_taxable', 'default_tax_percent']);
        $salesUsers = User::role('Sales')->orderBy('name')->get(['id', 'name']);
        $signatureUsers = $this->signatureOptions();
        $contacts = $project->customer?->contacts()->orderBy('first_name')->get() ?? collect();
        $canManageCost = $this->canManageLaborCost();
        $subContractors = $this->loadSubContractors($canManageCost);
        $selectedSubContractorId = old('sub_contractor_id', $quotation->sub_contractor_id);
        $topOptions = TermOfPayment::query()
            ->orderBy('code')
            ->get(['code','description','is_active']);

        $paymentTerms = $quotation->paymentTerms;
        $sections = $quotation->sections;

        if ($paymentTerms->isEmpty()) {
            $paymentTerms = collect([
                ['code' => 'DP', 'label' => 'DP', 'percent' => 30, 'sequence' => 1],
                ['code' => 'T1', 'label' => 'T1', 'percent' => 30, 'sequence' => 2],
                ['code' => 'FINISH', 'label' => 'Finish', 'percent' => 40, 'sequence' => 3],
            ]);
        }

        if ($sections->isEmpty()) {
            $sections = collect([
                (object) [
                'name' => 'Perlengkapan Utama',
                    'sort_order' => 1,
                    'lines' => collect([
                        (object) [
                            'line_no' => '1',
                            'description' => '',
                            'source_type' => 'item',
                            'item_id' => null,
                            'item_label' => null,
                            'qty' => 1,
                            'unit' => 'LS',
                            'unit_price' => 0,
                            'material_total' => 0,
                            'labor_total' => 0,
                            'labor_source' => 'manual',
                            'labor_unit_cost_snapshot' => 0,
                        ],
                    ]),
                ],
            ]);
        }

        return view('projects.quotations.edit', compact(
            'project',
            'quotation',
            'companies',
            'salesUsers',
            'signatureUsers',
            'paymentTerms',
            'sections',
            'contacts',
            'subContractors',
            'selectedSubContractorId',
            'canManageCost',
            'topOptions'
        ));
    }

    public function update(Request $request, Project $project, ProjectQuotation $quotation, ProjectQuotationTotalsService $totals)
    {
        $this->authorize('update', $quotation);
        $this->ensureProjectMatch($project, $quotation);

        if ($quotation->isLocked()) {
            return back()->with('warning', 'BQ yang sudah issued/won/lost tidak bisa diedit.');
        }

        $data = $this->validateQuotation($request);
        $data['tax_enabled'] = !empty($data['tax_enabled']);
        $data['tax_percent'] = Number::idToFloat($data['tax_percent'] ?? 0);
        $canManageCost = $this->canManageLaborCost($request->user());
        $this->normalizeSubContractorInput($data, $canManageCost, $quotation);

        if ((int) $data['customer_id'] !== (int) $project->customer_id) {
            throw ValidationException::withMessages([
                'customer_id' => 'Customer tidak sesuai dengan project.',
            ]);
        }

        $this->validatePaymentTermsSum($data['payment_terms'] ?? []);

        $company = Company::findOrFail($data['company_id']);
        $brand = $this->brandSnapshot($company);

        $computed = $totals->compute($data);

        DB::transaction(function () use ($quotation, $data, $company, $brand, $computed) {
            $quotation->update([
                'company_id' => $company->id,
                'customer_id' => $data['customer_id'],
                'sub_contractor_id' => $data['sub_contractor_id'] ?? null,
                'quotation_date' => $data['quotation_date'],
                'to_name' => $data['to_name'],
                'attn_name' => $data['attn_name'] ?? null,
                'project_title' => $data['project_title'],
                'working_time_days' => $data['working_time_days'] ?? null,
                'working_time_hours_per_day' => $data['working_time_hours_per_day'] ?? 8,
                'validity_days' => $data['validity_days'] ?? 15,
                'tax_enabled' => $data['tax_enabled'],
                'tax_percent' => $computed['tax_percent'],
                'subtotal_material' => $computed['subtotal_material'],
                'subtotal_labor' => $computed['subtotal_labor'],
                'subtotal' => $computed['subtotal'],
                'tax_amount' => $computed['tax_amount'],
                'grand_total' => $computed['grand_total'],
                'notes' => $data['notes'] ?? null,
                'signatory_name' => $data['signatory_name'] ?? null,
                'signatory_title' => $data['signatory_title'] ?? null,
                'sales_owner_user_id' => $data['sales_owner_user_id'],
                'brand_snapshot' => $brand,
            ]);

            $quotation->sections()->delete();
            $quotation->paymentTerms()->delete();

            foreach ($computed['sections'] as $sIndex => $sectionData) {
                $section = ProjectQuotationSection::create([
                    'project_quotation_id' => $quotation->id,
                    'name' => $sectionData['name'],
                    'sort_order' => $sectionData['sort_order'] ?? $sIndex,
                ]);

                foreach ($sectionData['lines'] as $lineData) {
                    ProjectQuotationLine::create(array_merge($lineData, [
                        'section_id' => $section->id,
                    ]));
                }
            }

            foreach ($data['payment_terms'] ?? [] as $pIndex => $term) {
                ProjectQuotationPaymentTerm::create([
                    'project_quotation_id' => $quotation->id,
                    'code' => $term['code'] ?? 'DP',
                    'label' => $term['label'] ?? ($term['code'] ?? 'DP'),
                    'percent' => Number::idToFloat($term['percent'] ?? 0),
                    'sequence' => (int) ($term['sequence'] ?? ($pIndex + 1)),
                    'trigger_note' => $term['trigger_note'] ?? null,
                ]);
            }
        });

        return redirect()
            ->route('projects.quotations.show', [$project, $quotation])
            ->with('success', 'BQ berhasil diperbarui.');
    }

    public function repriceLabor(Request $request, Project $project, ProjectQuotation $quotation)
    {
        $this->authorize('update', $quotation);
        $this->ensureProjectMatch($project, $quotation);

        if (!$this->supportsSubContractor()) {
            abort(404);
        }

        $canManageCost = $this->canManageLaborCost($request->user());
        if (!$canManageCost) {
            abort(403);
        }

        $data = $request->validate([
            'sub_contractor_id' => ['required', 'exists:sub_contractors,id'],
        ]);

        $quotation->update([
            'sub_contractor_id' => $data['sub_contractor_id'],
        ]);

        $lines = $quotation->lines()->get(['id', 'item_id', 'source_type', 'line_type', 'labor_total']);
        $itemIds = $lines->where('line_type', 'product')
            ->pluck('item_id')
            ->filter()
            ->unique()
            ->values()
            ->all();

        $contexts = $lines->where('line_type', 'product')
            ->map(fn ($line) => ($line->source_type === 'project') ? 'project' : 'retail')
            ->unique()
            ->values()
            ->all();

        $costs = [];
        if ($itemIds) {
            $costs = LaborCost::query()
                ->where('sub_contractor_id', $data['sub_contractor_id'])
                ->whereIn('item_id', $itemIds)
                ->when($contexts, fn ($q) => $q->whereIn('context', $contexts))
                ->get(['item_id', 'context', 'cost_amount'])
                ->keyBy(fn ($row) => $row->item_id.'|'.$row->context);
        }

        foreach ($lines as $line) {
            $lineType = $line->line_type ?: 'product';
            if ($lineType !== 'product' || !$line->item_id) {
                $line->labor_cost_amount = null;
                $line->labor_margin_amount = null;
                $line->labor_cost_missing = false;
                $line->save();
                continue;
            }

            $context = $line->source_type === 'project' ? 'project' : 'retail';
            $key = $line->item_id.'|'.$context;
            $cost = $costs[$key] ?? null;
            if (!$cost) {
                $line->labor_cost_amount = null;
                $line->labor_margin_amount = null;
                $line->labor_cost_missing = true;
                $line->save();
                continue;
            }

            $costAmount = (float) $cost->cost_amount;
            $line->labor_cost_amount = $costAmount;
            $line->labor_margin_amount = (float) $line->labor_total - $costAmount;
            $line->labor_cost_missing = false;
            $line->save();
        }

        if ($request->expectsJson()) {
            return response()->json(['ok' => true]);
        }

        return redirect()
            ->route('projects.quotations.edit', [$project, $quotation])
            ->with('success', 'Labor cost berhasil diperbarui.');
    }

    public function destroy(Project $project, ProjectQuotation $quotation)
    {
        $this->authorize('delete', $quotation);
        $this->ensureProjectMatch($project, $quotation);

        if ($quotation->isLocked()) {
            return back()->with('warning', 'BQ yang sudah issued/won/lost tidak bisa dihapus.');
        }

        $quotation->delete();

        return redirect()
            ->route('projects.quotations.index', $project)
            ->with('success', 'BQ dihapus.');
    }

    public function issue(Project $project, ProjectQuotation $quotation)
    {
        $this->authorize('issue', $quotation);
        $this->ensureProjectMatch($project, $quotation);

        if ($quotation->status !== ProjectQuotation::STATUS_DRAFT) {
            return back()->with('warning', 'Hanya BQ draft yang bisa di-issue.');
        }

        $quotation->update([
            'status' => ProjectQuotation::STATUS_ISSUED,
            'issued_at' => now(),
        ]);

        return back()->with('success', 'BQ ditandai sebagai issued.');
    }

    public function markWon(Project $project, ProjectQuotation $quotation)
    {
        $this->authorize('markWon', $quotation);
        $this->ensureProjectMatch($project, $quotation);

        if (in_array($quotation->status, [ProjectQuotation::STATUS_WON, ProjectQuotation::STATUS_LOST], true)) {
            return back()->with('warning', 'Status sudah final.');
        }

        $quotation->update([
            'status' => ProjectQuotation::STATUS_WON,
            'won_at' => now(),
        ]);

        return back()->with('success', 'BQ ditandai sebagai won.');
    }

    public function markLost(Project $project, ProjectQuotation $quotation)
    {
        $this->authorize('markLost', $quotation);
        $this->ensureProjectMatch($project, $quotation);

        if (in_array($quotation->status, [ProjectQuotation::STATUS_WON, ProjectQuotation::STATUS_LOST], true)) {
            return back()->with('warning', 'Status sudah final.');
        }

        $quotation->update([
            'status' => ProjectQuotation::STATUS_LOST,
            'lost_at' => now(),
        ]);

        return back()->with('success', 'BQ ditandai sebagai lost.');
    }

    private function ensureProjectMatch(Project $project, ProjectQuotation $quotation): void
    {
        if ((int) $quotation->project_id !== (int) $project->id) {
            abort(404);
        }
    }

    private function validateQuotation(Request $request): array
    {
        $data = $request->validate([
            'company_id' => ['required', 'exists:companies,id'],
            'customer_id' => ['required', 'exists:customers,id'],
            'sub_contractor_id' => $this->subContractorRule(),
            'quotation_date' => ['required', 'date'],
            'to_name' => ['required', 'string', 'max:190'],
            'attn_name' => ['nullable', 'string', 'max:190'],
            'project_title' => ['required', 'string', 'max:190'],
            'working_time_days' => ['nullable', 'integer', 'min:0'],
            'working_time_hours_per_day' => ['required', 'integer', 'min:1', 'max:24'],
            'validity_days' => ['required', 'integer', 'min:1', 'max:365'],
            'tax_enabled' => ['nullable'],
            'tax_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'notes' => ['nullable', 'string'],
            'signatory_name' => ['nullable', 'string', 'max:190'],
            'signatory_title' => ['nullable', 'string', 'max:190'],
            'sales_owner_user_id' => ['required', 'exists:users,id'],

            'payment_terms' => ['required', 'array', 'min:1'],
            'payment_terms.*.code' => ['required', 'string', 'max:64', 'exists:term_of_payments,code'],
            'payment_terms.*.label' => ['nullable', 'string', 'max:50'],
            'payment_terms.*.percent' => ['nullable', 'numeric', 'min:0'],
            'payment_terms.*.sequence' => ['nullable', 'integer', 'min:0'],
            'payment_terms.*.trigger_note' => ['nullable', 'string', 'max:190'],

            'sections' => ['required', 'array', 'min:1'],
            'sections.*.name' => ['required', 'string', 'max:190'],
            'sections.*.sort_order' => ['nullable', 'integer', 'min:0'],
            'sections.*.lines' => ['required', 'array', 'min:1'],
            'sections.*.lines.*.line_no' => ['nullable', 'string', 'max:32'],
            'sections.*.lines.*.description' => ['required', 'string'],
            'sections.*.lines.*.source_type' => ['nullable', 'in:item,project'],
            'sections.*.lines.*.item_id' => ['nullable', 'exists:items,id'],
            'sections.*.lines.*.item_label' => ['nullable', 'string', 'max:255'],
            'sections.*.lines.*.line_type' => ['nullable', 'in:product,charge,percent'],
            'sections.*.lines.*.catalog_id' => ['nullable', 'exists:bq_line_catalogs,id'],
            'sections.*.lines.*.percent_value' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'sections.*.lines.*.percent_basis' => ['nullable', 'in:product_subtotal,section_product_subtotal'],
            'sections.*.lines.*.computed_amount' => ['nullable', 'numeric', 'min:0'],
            'sections.*.lines.*.cost_bucket' => ['nullable', 'in:material,labor,overhead,other'],
            'sections.*.lines.*.qty' => ['required', 'numeric', 'min:0'],
            'sections.*.lines.*.unit' => ['required', 'string', 'max:16'],
            'sections.*.lines.*.unit_price' => ['nullable', 'numeric', 'min:0'],
            'sections.*.lines.*.material_total' => ['required', 'numeric', 'min:0'],
            'sections.*.lines.*.labor_total' => ['required', 'numeric', 'min:0'],
            'sections.*.lines.*.labor_source' => ['nullable', 'in:master_item,master_project,manual'],
            'sections.*.lines.*.labor_unit_cost_snapshot' => ['nullable', 'numeric', 'min:0'],
        ]);

        foreach (($data['sections'] ?? []) as $sIndex => $section) {
            foreach (($section['lines'] ?? []) as $lIndex => $line) {
                $lineType = $line['line_type'] ?? 'product';
                $itemId = $line['item_id'] ?? null;

                if ($lineType !== 'product' && !empty($itemId)) {
                    throw ValidationException::withMessages([
                        "sections.$sIndex.lines.$lIndex.item_id" => 'Charge/percent lines tidak boleh punya item.',
                    ]);
                }

                if ($lineType === 'percent') {
                    $percentValue = $line['percent_value'] ?? null;
                    $basisType = $line['percent_basis'] ?? null;
                    if ($percentValue === null || $percentValue === '') {
                        throw ValidationException::withMessages([
                            "sections.$sIndex.lines.$lIndex.percent_value" => 'Percent wajib diisi.',
                        ]);
                    }
                    if (!$basisType) {
                        throw ValidationException::withMessages([
                            "sections.$sIndex.lines.$lIndex.percent_basis" => 'Basis type wajib diisi.',
                        ]);
                    }
                }

            }
        }

        return $data;
    }

    private function canManageLaborCost(?User $user = null): bool
    {
        $user = $user ?? auth()->user();
        if (!$user) {
            return false;
        }

        return $user->hasAnyRole(['Admin', 'SuperAdmin']) && $this->supportsSubContractor();
    }

    private function supportsSubContractor(): bool
    {
        return Schema::hasTable('sub_contractors')
            && Schema::hasColumn('project_quotations', 'sub_contractor_id');
    }

    private function loadSubContractors(bool $canManageCost)
    {
        if (!$canManageCost || !Schema::hasTable('sub_contractors')) {
            return collect();
        }

        $query = SubContractor::query();
        if (Schema::hasColumn('sub_contractors', 'is_active')) {
            $query->where('is_active', true);
        }

        return $query->orderBy('name')->get(['id', 'name']);
    }

    private function defaultSubContractorId(bool $canManageCost): ?int
    {
        if (!$canManageCost || !Schema::hasTable('settings')) {
            return null;
        }

        $id = (int) Setting::get('default_sub_contractor_id', 0);
        return $id > 0 ? $id : null;
    }

    private function normalizeSubContractorInput(array &$data, bool $canManageCost, ?ProjectQuotation $quotation): void
    {
        if (!$this->supportsSubContractor()) {
            unset($data['sub_contractor_id']);
            return;
        }

        if ($canManageCost) {
            return;
        }

        if ($quotation) {
            $data['sub_contractor_id'] = $quotation->sub_contractor_id;
            return;
        }

        $data['sub_contractor_id'] = $this->defaultSubContractorId(true);
    }

    private function subContractorRule(): array
    {
        if (!$this->supportsSubContractor()) {
            return ['nullable'];
        }

        return ['nullable', 'exists:sub_contractors,id'];
    }

    private function validatePaymentTermsSum(array $terms): void
    {
        $sum = 0.0;
        foreach ($terms as $term) {
            $sum += Number::idToFloat($term['percent'] ?? 0);
        }

        if (abs($sum - 100) > 0.01) {
            throw ValidationException::withMessages([
                'payment_terms' => 'Total persentase termin pembayaran harus 100%.',
            ]);
        }
    }

    private function brandSnapshot(Company $company): array
    {
        return [
            'name' => $company->name,
            'alias' => $company->alias,
            'address' => $company->address,
            'tax_id' => $company->tax_id,
            'logo_path' => $company->logo_path,
            'phone' => $company->phone,
            'email' => $company->email,
        ];
    }

    private function signatureOptions()
    {
        $user = auth()->user();
        $query = User::query();
        if ($user && $user->hasRole('Sales')) {
            $query->whereKey($user->id);
        }

        return $query
            ->leftJoin('signatures', 'signatures.user_id', '=', 'users.id')
            ->orderBy('users.name')
            ->get([
                'users.id',
                'users.name',
                'signatures.default_position as default_position',
            ]);
    }
}
