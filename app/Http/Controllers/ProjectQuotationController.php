<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\BqLineTemplate;
use App\Models\Project;
use App\Models\ProjectQuotation;
use App\Models\ProjectQuotationLine;
use App\Models\ProjectQuotationPaymentTerm;
use App\Models\ProjectQuotationSection;
use App\Models\User;
use App\Services\ProjectQuotationTotalsService;
use App\Support\Number;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ProjectQuotationController extends Controller
{
    private const TERM_CODES = ['DP', 'T1', 'T2', 'T3', 'T4', 'T5', 'FINISH', 'R1', 'R2', 'R3'];

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
        $contacts = $project->customer?->contacts()->orderBy('first_name')->get() ?? collect();

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
        ]);

        $paymentTerms = collect([
            ['code' => 'DP', 'label' => 'DP', 'percent' => 30, 'sequence' => 1],
            ['code' => 'T1', 'label' => 'T1', 'percent' => 30, 'sequence' => 2],
            ['code' => 'FINISH', 'label' => 'Finish', 'percent' => 40, 'sequence' => 3],
        ]);

        $sections = collect([
            (object) [
                'name' => 'Pekerjaan Utama',
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
                        'labor_override_reason' => null,
                    ],
                ]),
            ],
        ]);

        $bqTemplatesData = $this->activeBqTemplatesData();

        return view('projects.quotations.create', compact(
            'project',
            'quotation',
            'companies',
            'salesUsers',
            'paymentTerms',
            'sections',
            'contacts',
            'bqTemplatesData'
        ));
    }

    public function store(Request $request, Project $project, ProjectQuotationTotalsService $totals)
    {
        $this->authorize('create', ProjectQuotation::class);

        $data = $this->validateQuotation($request);
        $data['tax_enabled'] = !empty($data['tax_enabled']);
        $data['tax_percent'] = Number::idToFloat($data['tax_percent'] ?? 0);

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
        $contacts = $project->customer?->contacts()->orderBy('first_name')->get() ?? collect();

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
                    'name' => 'Pekerjaan Utama',
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
                            'labor_override_reason' => null,
                        ],
                    ]),
                ],
            ]);
        }

        $bqTemplatesData = $this->activeBqTemplatesData();

        return view('projects.quotations.edit', compact(
            'project',
            'quotation',
            'companies',
            'salesUsers',
            'paymentTerms',
            'sections',
            'contacts',
            'bqTemplatesData'
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

    public function applyTemplate(Request $request, ProjectQuotation $quotation)
    {
        $this->authorize('update', $quotation);

        $project = $request->route('project');
        if ($project instanceof Project) {
            $this->ensureProjectMatch($project, $quotation);
        }

        if ($quotation->isLocked()) {
            return back()->with('warning', 'BQ yang sudah issued/won/lost tidak bisa diedit.');
        }

        $data = $request->validate([
            'template_id' => ['required', 'exists:bq_line_templates,id'],
        ]);

        $template = BqLineTemplate::query()
            ->where('is_active', true)
            ->with(['lines' => fn ($q) => $q->orderBy('sort_order')->orderBy('id')])
            ->findOrFail($data['template_id']);

        $quotation->load(['sections.lines']);
        $section = $this->findAddonsSection($quotation);
        if (!$section) {
            $nextSort = (int) ($quotation->sections->max('sort_order') ?? 0) + 1;
            $section = ProjectQuotationSection::create([
                'project_quotation_id' => $quotation->id,
                'name' => 'Add-ons',
                'sort_order' => $nextSort,
            ]);
            $quotation->load(['sections.lines']);
        }

        $section->load('lines');
        $existing = [];
        foreach ($section->lines as $line) {
            $existing[$this->lineSignature(
                $line->line_type ?? 'product',
                $line->description ?? '',
                $line->percent_value,
                $line->basis_type
            )] = true;
        }

        $nextLineNo = $this->nextLineNumber($section->lines);
        foreach ($template->lines as $tplLine) {
            $type = $tplLine->type;
            $label = $tplLine->label;
            $signature = $this->lineSignature($type, $label, $tplLine->percent_value, $tplLine->basis_type);
            if (isset($existing[$signature])) {
                continue;
            }

            $qty = $type === 'charge' ? (float) ($tplLine->default_qty ?? 1) : 1;
            $unit = $type === 'charge' ? ($tplLine->default_unit ?? 'LS') : '%';
            $unitPrice = $type === 'charge' ? (float) ($tplLine->default_unit_price ?? 0) : 0;
            $materialTotal = $type === 'charge' ? ($qty * $unitPrice) : 0;
            $percentValue = $type === 'percent' ? (float) ($tplLine->percent_value ?? 0) : null;
            $basisType = $type === 'percent' ? ($tplLine->basis_type ?? 'bq_product_total') : null;

            ProjectQuotationLine::create([
                'section_id' => $section->id,
                'line_no' => (string) $nextLineNo++,
                'description' => $label,
                'source_type' => 'item',
                'item_id' => null,
                'item_label' => null,
                'line_type' => $type,
                'source_template_id' => $template->id,
                'source_template_line_id' => $tplLine->id,
                'percent_value' => $percentValue,
                'basis_type' => $basisType,
                'computed_amount' => null,
                'editable_price' => $tplLine->editable_price,
                'editable_percent' => $tplLine->editable_percent,
                'can_remove' => $tplLine->can_remove,
                'qty' => $qty,
                'unit' => $unit,
                'unit_price' => $unitPrice,
                'material_total' => $materialTotal,
                'labor_total' => 0,
                'labor_source' => 'manual',
                'labor_unit_cost_snapshot' => 0,
                'labor_override_reason' => null,
                'line_total' => $materialTotal,
            ]);

            $existing[$signature] = true;
        }

        $quotation->load(['sections.lines']);
        $this->recalculateTotalsFromLines($quotation);

        return back()->with('success', 'Template berhasil diterapkan.');
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

    private function activeBqTemplatesData(): array
    {
        return BqLineTemplate::query()
            ->where('is_active', true)
            ->with(['lines' => fn ($q) => $q->orderBy('sort_order')->orderBy('id')])
            ->orderBy('name')
            ->get()
            ->map(function (BqLineTemplate $template) {
                return [
                    'id' => $template->id,
                    'name' => $template->name,
                    'description' => $template->description,
                    'lines' => $template->lines->map(function ($line) {
                        return [
                            'id' => $line->id,
                            'sort_order' => $line->sort_order,
                            'type' => $line->type,
                            'label' => $line->label,
                            'default_qty' => $line->default_qty !== null ? (float) $line->default_qty : null,
                            'default_unit' => $line->default_unit,
                            'default_unit_price' => $line->default_unit_price !== null ? (float) $line->default_unit_price : null,
                            'percent_value' => $line->percent_value !== null ? (float) $line->percent_value : null,
                            'basis_type' => $line->basis_type,
                            'editable_price' => (bool) $line->editable_price,
                            'editable_percent' => (bool) $line->editable_percent,
                            'can_remove' => (bool) $line->can_remove,
                        ];
                    })->values()->all(),
                ];
            })
            ->values()
            ->all();
    }

    private function findAddonsSection(ProjectQuotation $quotation): ?ProjectQuotationSection
    {
        foreach ($quotation->sections as $section) {
            $name = strtolower(trim((string) $section->name));
            if (in_array($name, ['add-ons', 'add ons', 'biaya tambahan'], true)) {
                return $section;
            }
        }

        return null;
    }

    private function nextLineNumber($lines): int
    {
        $max = collect($lines)->map(fn ($line) => (int) ($line->line_no ?? 0))->max();
        return (int) ($max ?? 0) + 1;
    }

    private function lineSignature(string $type, ?string $label, $percentValue, ?string $basisType): string
    {
        $labelKey = mb_strtolower(trim((string) $label));
        $signature = $type . '|' . $labelKey;

        if ($type === 'percent') {
            $percent = number_format((float) ($percentValue ?? 0), 4, '.', '');
            $signature .= '|' . $percent . '|' . ($basisType ?? '');
        }

        return $signature;
    }

    private function recalculateTotalsFromLines(ProjectQuotation $quotation): void
    {
        $quotation->load(['sections.lines']);

        $subtotalMaterial = 0.0;
        $subtotalLabor = 0.0;
        $productSubtotal = 0.0;
        $chargeTotal = 0.0;
        $percentTotal = 0.0;

        $sectionProductTotals = [];
        foreach ($quotation->sections as $section) {
            $sectionProductTotal = 0.0;
            foreach ($section->lines as $line) {
                $lineType = $line->line_type ?? 'product';
                $material = (float) $line->material_total;
                $labor = (float) $line->labor_total;

                if ($lineType === 'product') {
                    $lineTotal = $material + $labor;
                    if ((float) $line->line_total !== $lineTotal) {
                        $line->line_total = $lineTotal;
                        $line->save();
                    }
                    $subtotalMaterial += $material;
                    $subtotalLabor += $labor;
                    $productSubtotal += $lineTotal;
                    $sectionProductTotal += $lineTotal;
                } elseif ($lineType === 'charge') {
                    $lineTotal = $material + $labor;
                    if ((float) $line->line_total !== $lineTotal) {
                        $line->line_total = $lineTotal;
                        $line->save();
                    }
                    $chargeTotal += $lineTotal;
                }
            }
            $sectionProductTotals[$section->id] = $sectionProductTotal;
        }

        foreach ($quotation->sections as $section) {
            $sectionProductTotal = $sectionProductTotals[$section->id] ?? 0.0;
            foreach ($section->lines as $line) {
                if (($line->line_type ?? 'product') !== 'percent') {
                    continue;
                }

                $basisType = $line->basis_type ?? 'bq_product_total';
                $basis = $basisType === 'section_product_total' ? $sectionProductTotal : $productSubtotal;
                if ($basis <= 0 && $basisType === 'section_product_total' && $productSubtotal > 0) {
                    $basis = $productSubtotal;
                }

                $percent = (float) ($line->percent_value ?? 0);
                $computed = round($basis * ($percent / 100), 2);
                $line->computed_amount = $computed;
                $line->material_total = $computed;
                $line->labor_total = 0;
                $line->line_total = $computed;
                $line->save();
                $percentTotal += $computed;
            }
        }

        $subtotal = $productSubtotal + $chargeTotal + $percentTotal;
        $taxPercent = (float) ($quotation->tax_percent ?? 0);
        $taxAmount = $quotation->tax_enabled ? ($subtotal * ($taxPercent / 100)) : 0.0;

        $quotation->update([
            'subtotal_material' => $subtotalMaterial,
            'subtotal_labor' => $subtotalLabor,
            'subtotal' => $subtotal,
            'tax_amount' => $taxAmount,
            'grand_total' => $subtotal + $taxAmount,
        ]);
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
            'payment_terms.*.code' => ['nullable', 'string', 'max:16'],
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
            'sections.*.lines.*.source_template_id' => ['nullable', 'exists:bq_line_templates,id'],
            'sections.*.lines.*.source_template_line_id' => ['nullable', 'exists:bq_line_template_lines,id'],
            'sections.*.lines.*.percent_value' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'sections.*.lines.*.basis_type' => ['nullable', 'in:bq_product_total,section_product_total'],
            'sections.*.lines.*.computed_amount' => ['nullable', 'numeric', 'min:0'],
            'sections.*.lines.*.editable_price' => ['nullable', 'boolean'],
            'sections.*.lines.*.editable_percent' => ['nullable', 'boolean'],
            'sections.*.lines.*.can_remove' => ['nullable', 'boolean'],
            'sections.*.lines.*.qty' => ['required', 'numeric', 'min:0'],
            'sections.*.lines.*.unit' => ['required', 'string', 'max:16'],
            'sections.*.lines.*.unit_price' => ['nullable', 'numeric', 'min:0'],
            'sections.*.lines.*.material_total' => ['required', 'numeric', 'min:0'],
            'sections.*.lines.*.labor_total' => ['required', 'numeric', 'min:0'],
            'sections.*.lines.*.labor_source' => ['nullable', 'in:master_item,master_project,manual'],
            'sections.*.lines.*.labor_unit_cost_snapshot' => ['nullable', 'numeric', 'min:0'],
            'sections.*.lines.*.labor_override_reason' => ['nullable', 'string', 'max:255'],
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
                    $basisType = $line['basis_type'] ?? null;
                    if ($percentValue === null || $percentValue === '') {
                        throw ValidationException::withMessages([
                            "sections.$sIndex.lines.$lIndex.percent_value" => 'Percent wajib diisi.',
                        ]);
                    }
                    if (!$basisType) {
                        throw ValidationException::withMessages([
                            "sections.$sIndex.lines.$lIndex.basis_type" => 'Basis type wajib diisi.',
                        ]);
                    }
                }

                $laborSource = $line['labor_source'] ?? 'manual';
                $reason = $line['labor_override_reason'] ?? null;
                if ($laborSource === 'manual' && !empty($itemId) && empty($reason)) {
                    throw ValidationException::withMessages([
                        "sections.$sIndex.lines.$lIndex.labor_override_reason" => 'Alasan override labor wajib diisi.',
                    ]);
                }
            }
        }

        return $data;
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
}
