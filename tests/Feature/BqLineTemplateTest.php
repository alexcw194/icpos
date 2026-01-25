<?php

namespace Tests\Feature;

use App\Models\BqLineTemplate;
use App\Models\BqLineTemplateLine;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Project;
use App\Models\ProjectQuotation;
use App\Models\ProjectQuotationLine;
use App\Models\ProjectQuotationSection;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class BqLineTemplateTest extends TestCase
{
    use RefreshDatabase;

    private function makeAdminUser(): User
    {
        $role = Role::create(['name' => 'Admin', 'guard_name' => 'web']);
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    private function makeCompany(): Company
    {
        return Company::create([
            'name' => 'Test Company',
            'alias' => 'TST',
        ]);
    }

    private function makeCustomer(): Customer
    {
        return Customer::create([
            'name' => 'Test Customer',
        ]);
    }

    private function makeProject(User $user, Company $company, Customer $customer): Project
    {
        return Project::create([
            'company_id' => $company->id,
            'customer_id' => $customer->id,
            'code' => 'PRJ-001',
            'name' => 'Test Project',
            'sales_owner_user_id' => $user->id,
        ]);
    }

    private function makeQuotation(Project $project, Company $company, Customer $customer, User $user): ProjectQuotation
    {
        return ProjectQuotation::create([
            'project_id' => $project->id,
            'company_id' => $company->id,
            'customer_id' => $customer->id,
            'number' => 'BQ-TEST-1',
            'version' => 1,
            'status' => ProjectQuotation::STATUS_DRAFT,
            'quotation_date' => now()->toDateString(),
            'to_name' => $customer->name,
            'project_title' => $project->name,
            'working_time_hours_per_day' => 8,
            'validity_days' => 15,
            'tax_enabled' => false,
            'tax_percent' => 0,
            'subtotal_material' => 0,
            'subtotal_labor' => 0,
            'subtotal' => 0,
            'tax_amount' => 0,
            'grand_total' => 0,
            'sales_owner_user_id' => $user->id,
        ]);
    }

    public function test_admin_can_create_template_and_line(): void
    {
        $admin = $this->makeAdminUser();

        $response = $this->actingAs($admin)->post('/bq-line-templates', [
            'name' => 'Add-ons Default',
            'description' => 'Template untuk add-ons',
            'is_active' => 1,
        ]);

        $response->assertRedirect();
        $template = BqLineTemplate::first();
        $this->assertNotNull($template);

        $response = $this->actingAs($admin)->post("/bq-line-templates/{$template->id}/lines", [
            'type' => 'charge',
            'label' => 'Mobilisasi',
            'default_qty' => 1,
            'default_unit' => 'LS',
            'default_unit_price' => 1000,
            'editable_price' => 1,
            'can_remove' => 1,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('bq_line_template_lines', [
            'bq_line_template_id' => $template->id,
            'label' => 'Mobilisasi',
            'type' => 'charge',
        ]);
    }

    public function test_apply_template_creates_addons_and_prevents_duplicates(): void
    {
        $admin = $this->makeAdminUser();
        $company = $this->makeCompany();
        $customer = $this->makeCustomer();
        $project = $this->makeProject($admin, $company, $customer);
        $quotation = $this->makeQuotation($project, $company, $customer, $admin);

        $section = ProjectQuotationSection::create([
            'project_quotation_id' => $quotation->id,
            'name' => 'Pekerjaan Utama',
            'sort_order' => 1,
        ]);

        ProjectQuotationLine::create([
            'section_id' => $section->id,
            'line_no' => '1',
            'description' => 'Item 1',
            'source_type' => 'item',
            'line_type' => 'product',
            'qty' => 1,
            'unit' => 'LS',
            'unit_price' => 0,
            'material_total' => 1000,
            'labor_total' => 500,
            'line_total' => 1500,
        ]);

        $template = BqLineTemplate::create([
            'name' => 'Addon Template',
            'is_active' => true,
        ]);

        BqLineTemplateLine::create([
            'bq_line_template_id' => $template->id,
            'sort_order' => 1,
            'type' => 'charge',
            'label' => 'Mobilisasi',
            'default_qty' => 1,
            'default_unit' => 'LS',
            'default_unit_price' => 100,
            'editable_price' => true,
            'can_remove' => true,
        ]);

        BqLineTemplateLine::create([
            'bq_line_template_id' => $template->id,
            'sort_order' => 2,
            'type' => 'percent',
            'label' => 'O/H',
            'percent_value' => 10,
            'basis_type' => 'bq_product_total',
            'editable_percent' => true,
            'can_remove' => true,
        ]);

        $response = $this->actingAs($admin)->post(
            route('projects.quotations.apply-template', [$project, $quotation]),
            ['template_id' => $template->id]
        );

        $response->assertRedirect();
        $addonsSection = ProjectQuotationSection::where('project_quotation_id', $quotation->id)
            ->where('name', 'Add-ons')
            ->first();

        $this->assertNotNull($addonsSection);
        $this->assertEquals(2, $addonsSection->lines()->count());

        $response = $this->actingAs($admin)->post(
            route('projects.quotations.apply-template', [$project, $quotation]),
            ['template_id' => $template->id]
        );

        $response->assertRedirect();
        $this->assertEquals(2, $addonsSection->lines()->count());
    }

    public function test_percent_basis_excludes_charge_lines(): void
    {
        $admin = $this->makeAdminUser();
        $company = $this->makeCompany();
        $customer = $this->makeCustomer();
        $project = $this->makeProject($admin, $company, $customer);
        $quotation = $this->makeQuotation($project, $company, $customer, $admin);

        $section = ProjectQuotationSection::create([
            'project_quotation_id' => $quotation->id,
            'name' => 'Pekerjaan Utama',
            'sort_order' => 1,
        ]);

        ProjectQuotationLine::create([
            'section_id' => $section->id,
            'line_no' => '1',
            'description' => 'Item 1',
            'source_type' => 'item',
            'line_type' => 'product',
            'qty' => 1,
            'unit' => 'LS',
            'unit_price' => 0,
            'material_total' => 1000,
            'labor_total' => 500,
            'line_total' => 1500,
        ]);

        $template = BqLineTemplate::create([
            'name' => 'Addon Template',
            'is_active' => true,
        ]);

        BqLineTemplateLine::create([
            'bq_line_template_id' => $template->id,
            'sort_order' => 1,
            'type' => 'charge',
            'label' => 'Mobilisasi',
            'default_qty' => 1,
            'default_unit' => 'LS',
            'default_unit_price' => 100,
        ]);

        BqLineTemplateLine::create([
            'bq_line_template_id' => $template->id,
            'sort_order' => 2,
            'type' => 'percent',
            'label' => 'O/H',
            'percent_value' => 10,
            'basis_type' => 'bq_product_total',
        ]);

        $response = $this->actingAs($admin)->post(
            route('projects.quotations.apply-template', [$project, $quotation]),
            ['template_id' => $template->id]
        );

        $response->assertRedirect();
        $quotation->refresh();

        $percentLine = ProjectQuotationLine::where('section_id', function ($q) use ($quotation) {
            $q->select('id')
                ->from('project_quotation_sections')
                ->where('project_quotation_id', $quotation->id)
                ->where('name', 'Add-ons');
        })->where('line_type', 'percent')->first();

        $this->assertNotNull($percentLine);
        $this->assertEqualsWithDelta(150.0, (float) $percentLine->computed_amount, 0.01);
        $this->assertEqualsWithDelta(1750.0, (float) $quotation->subtotal, 0.01);
    }
}
