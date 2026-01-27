<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Customer;
use App\Models\Item;
use App\Models\LaborCost;
use App\Models\Project;
use App\Models\ProjectQuotation;
use App\Models\ProjectQuotationLine;
use App\Models\ProjectQuotationSection;
use App\Models\Setting;
use App\Models\SubContractor;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SubContractorBqLaborCostTest extends TestCase
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

    public function test_master_labor_routes_are_inaccessible(): void
    {
        $admin = $this->makeAdminUser();

        $this->actingAs($admin)->get('/labors')->assertStatus(404);
        $this->actingAs($admin)->get('/labors/1/edit')->assertStatus(404);
    }

    public function test_default_sub_contractor_prefills_new_bq(): void
    {
        $admin = $this->makeAdminUser();
        $company = $this->makeCompany();
        $customer = $this->makeCustomer();
        $project = $this->makeProject($admin, $company, $customer);

        $sub = SubContractor::create([
            'name' => 'Sub Default',
            'is_active' => true,
        ]);

        Setting::setMany([
            'default_sub_contractor_id' => (string) $sub->id,
        ]);

        $response = $this->actingAs($admin)->get(route('projects.quotations.create', $project));
        $response->assertOk();
        $response->assertSee('Sub-Contractor');
        $response->assertSee('value="'.$sub->id.'" selected', false);
    }

    public function test_reprice_updates_labor_cost_snapshot(): void
    {
        $admin = $this->makeAdminUser();
        $company = $this->makeCompany();
        $customer = $this->makeCustomer();
        $project = $this->makeProject($admin, $company, $customer);

        $item = Item::create([
            'name' => 'Item A',
            'sku' => 'ITM-A',
            'price' => 1000,
            'list_type' => 'retail',
        ]);

        $sub = SubContractor::create([
            'name' => 'Sub A',
            'is_active' => true,
        ]);

        LaborCost::create([
            'sub_contractor_id' => $sub->id,
            'item_id' => $item->id,
            'context' => 'retail',
            'cost_amount' => 400,
        ]);

        $quotation = ProjectQuotation::create([
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
            'sales_owner_user_id' => $admin->id,
        ]);

        $section = ProjectQuotationSection::create([
            'project_quotation_id' => $quotation->id,
            'name' => 'Main',
            'sort_order' => 1,
        ]);

        $line = ProjectQuotationLine::create([
            'section_id' => $section->id,
            'line_no' => '1',
            'description' => 'Item A',
            'source_type' => 'item',
            'item_id' => $item->id,
            'line_type' => 'product',
            'qty' => 1,
            'unit' => 'LS',
            'unit_price' => 0,
            'material_total' => 0,
            'labor_total' => 1000,
            'line_total' => 1000,
        ]);

        $response = $this->actingAs($admin)->postJson(
            route('projects.quotations.reprice-labor', [$project, $quotation]),
            ['sub_contractor_id' => $sub->id]
        );

        $response->assertOk();

        $line->refresh();
        $this->assertEqualsWithDelta(400.0, (float) $line->labor_cost_amount, 0.01);
        $this->assertEqualsWithDelta(600.0, (float) $line->labor_margin_amount, 0.01);
        $this->assertFalse((bool) $line->labor_cost_missing);
        $this->assertEqualsWithDelta(1000.0, (float) $line->labor_total, 0.01);
    }

    public function test_non_admin_cannot_reprice_labor(): void
    {
        $user = User::factory()->create();
        $company = $this->makeCompany();
        $customer = $this->makeCustomer();
        $project = $this->makeProject($user, $company, $customer);

        $quotation = ProjectQuotation::create([
            'project_id' => $project->id,
            'company_id' => $company->id,
            'customer_id' => $customer->id,
            'number' => 'BQ-TEST-2',
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

        $sub = SubContractor::create([
            'name' => 'Sub B',
            'is_active' => true,
        ]);

        $this->actingAs($user)
            ->post(route('projects.quotations.reprice-labor', [$project, $quotation]), [
                'sub_contractor_id' => $sub->id,
            ])
            ->assertStatus(403);
    }
}
