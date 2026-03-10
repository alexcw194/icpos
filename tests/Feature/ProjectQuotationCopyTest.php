<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Customer;
use App\Models\Project;
use App\Models\ProjectQuotation;
use App\Models\ProjectQuotationLine;
use App\Models\ProjectQuotationPaymentTerm;
use App\Models\ProjectQuotationSection;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ProjectQuotationCopyTest extends TestCase
{
    use RefreshDatabase;

    private function makeUserWithRole(string $role): User
    {
        $roleRow = Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        $user = User::factory()->create();
        $user->assignRole($roleRow);

        return $user;
    }

    private function makeContext(User $owner): array
    {
        $company = Company::create([
            'name' => 'Copy BQ Co',
            'alias' => 'CBQ',
        ]);
        $customer = Customer::create([
            'name' => 'Copy BQ Customer',
        ]);
        $project = Project::create([
            'company_id' => $company->id,
            'customer_id' => $customer->id,
            'code' => 'PRJ-COPY-001',
            'name' => 'Project Copy',
            'systems_json' => ['fire_alarm'],
            'status' => 'active',
            'sales_owner_user_id' => $owner->id,
        ]);

        return compact('company', 'customer', 'project');
    }

    private function makeSourceQuotation(array $ctx, User $owner): ProjectQuotation
    {
        $quotation = ProjectQuotation::create([
            'project_id' => $ctx['project']->id,
            'company_id' => $ctx['company']->id,
            'customer_id' => $ctx['customer']->id,
            'number' => 'BQ/CBQ/2026/00001',
            'version' => 3,
            'status' => ProjectQuotation::STATUS_WON,
            'quotation_date' => now()->subDays(2)->toDateString(),
            'to_name' => 'PT Copy Customer',
            'attn_name' => 'PIC 1',
            'project_title' => 'Project Title Source',
            'working_time_days' => 14,
            'working_time_hours_per_day' => 8,
            'validity_days' => 30,
            'tax_enabled' => true,
            'tax_percent' => 11,
            'subtotal_material' => 1000000,
            'subtotal_labor' => 500000,
            'subtotal' => 1500000,
            'tax_amount' => 165000,
            'grand_total' => 1665000,
            'notes' => 'Source notes',
            'signatory_name' => 'Signer',
            'signatory_title' => 'Director',
            'issued_at' => now()->subDay(),
            'won_at' => now()->subDay(),
            'lost_at' => null,
            'sales_owner_user_id' => $owner->id,
        ]);

        $section = ProjectQuotationSection::create([
            'project_quotation_id' => $quotation->id,
            'name' => 'Main Section',
            'sort_order' => 1,
        ]);

        ProjectQuotationLine::create([
            'section_id' => $section->id,
            'line_no' => '1',
            'description' => 'Line A',
            'source_type' => 'project',
            'item_id' => null,
            'item_variant_id' => null,
            'item_label' => 'Line A label',
            'line_type' => 'product',
            'catalog_id' => null,
            'percent_value' => 0,
            'percent_basis' => 'product_subtotal',
            'computed_amount' => 0,
            'cost_bucket' => 'material',
            'qty' => 2,
            'unit' => 'LS',
            'unit_price' => 500000,
            'material_total' => 1000000,
            'labor_total' => 500000,
            'labor_source' => 'manual',
            'labor_unit_cost_snapshot' => 0,
            'labor_cost_amount' => null,
            'labor_margin_amount' => null,
            'labor_cost_missing' => false,
            'line_total' => 1500000,
        ]);

        ProjectQuotationPaymentTerm::create([
            'project_quotation_id' => $quotation->id,
            'code' => 'DP',
            'label' => 'Down Payment',
            'percent' => 50,
            'due_trigger' => 'on_invoice',
            'offset_days' => null,
            'day_of_month' => null,
            'sequence' => 1,
            'trigger_note' => 'Before start',
        ]);

        ProjectQuotationPaymentTerm::create([
            'project_quotation_id' => $quotation->id,
            'code' => 'FINISH',
            'label' => 'Finish',
            'percent' => 50,
            'due_trigger' => 'after_invoice_days',
            'offset_days' => 14,
            'day_of_month' => null,
            'sequence' => 2,
            'trigger_note' => 'After completion',
        ]);

        return $quotation->fresh(['sections.lines', 'paymentTerms']);
    }

    public function test_admin_can_copy_project_quotation_as_new_independent_bq(): void
    {
        $admin = $this->makeUserWithRole('Admin');
        $ctx = $this->makeContext($admin);
        $source = $this->makeSourceQuotation($ctx, $admin);

        $this->actingAs($admin)
            ->get(route('projects.show', ['project' => $ctx['project'], 'tab' => 'quotations']))
            ->assertOk()
            ->assertSee('Copy BQ');
        $this->actingAs($admin)
            ->get(route('projects.quotations.index', $ctx['project']))
            ->assertOk()
            ->assertSee('Copy BQ');

        $response = $this->actingAs($admin)
            ->post(route('projects.quotations.copy', [$ctx['project'], $source]))
            ->assertRedirect();

        $newQuotation = ProjectQuotation::query()
            ->where('project_id', $ctx['project']->id)
            ->where('id', '!=', $source->id)
            ->latest('id')
            ->first();

        $this->assertNotNull($newQuotation);
        $response->assertRedirect(route('projects.quotations.edit', [$ctx['project'], $newQuotation]));
        $this->assertNotSame($source->number, $newQuotation->number);
        $this->assertSame(now()->toDateString(), optional($newQuotation->quotation_date)->toDateString());
        $this->assertSame(ProjectQuotation::STATUS_DRAFT, (string) $newQuotation->status);
        $this->assertSame(1, (int) $newQuotation->version);
        $this->assertNull($newQuotation->parent_revision_id);
        $this->assertNull($newQuotation->issued_at);
        $this->assertNull($newQuotation->won_at);
        $this->assertNull($newQuotation->lost_at);

        $this->assertEqualsWithDelta((float) $source->subtotal_material, (float) $newQuotation->subtotal_material, 0.01);
        $this->assertEqualsWithDelta((float) $source->subtotal_labor, (float) $newQuotation->subtotal_labor, 0.01);
        $this->assertEqualsWithDelta((float) $source->grand_total, (float) $newQuotation->grand_total, 0.01);

        $this->assertSame(1, $newQuotation->sections()->count());
        $this->assertSame(1, $newQuotation->lines()->count());
        $this->assertSame(2, $newQuotation->paymentTerms()->count());

        $newLine = $newQuotation->lines()->first();
        $sourceLine = $source->lines()->first();
        $this->assertSame((string) $sourceLine->description, (string) $newLine->description);
        $this->assertEqualsWithDelta((float) $sourceLine->line_total, (float) $newLine->line_total, 0.01);
        if (Schema::hasColumn('project_quotation_lines', 'revision_source_line_id')) {
            $this->assertNull($newLine->revision_source_line_id);
        }

        $source->refresh();
        $this->assertSame(ProjectQuotation::STATUS_WON, (string) $source->status);
    }

    public function test_non_admin_cannot_copy_project_quotation(): void
    {
        $sales = $this->makeUserWithRole('Sales');
        $ctx = $this->makeContext($sales);
        $source = $this->makeSourceQuotation($ctx, $sales);

        $this->actingAs($sales)
            ->get(route('projects.show', ['project' => $ctx['project'], 'tab' => 'quotations']))
            ->assertOk()
            ->assertDontSee('Copy BQ');
        $this->actingAs($sales)
            ->get(route('projects.quotations.index', $ctx['project']))
            ->assertOk()
            ->assertDontSee('Copy BQ');

        $this->actingAs($sales)
            ->post(route('projects.quotations.copy', [$ctx['project'], $source]))
            ->assertStatus(403);

        $this->assertSame(1, ProjectQuotation::query()->where('project_id', $ctx['project']->id)->count());
    }
}
