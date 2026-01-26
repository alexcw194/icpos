<?php

namespace Tests\Feature;

use App\Models\BqLineCatalog;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Project;
use App\Models\ProjectQuotation;
use App\Models\ProjectQuotationLine;
use App\Models\ProjectQuotationSection;
use App\Models\User;
use App\Services\ProjectQuotationTotalsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BqLineCatalogTest extends TestCase
{
    use RefreshDatabase;

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

    public function test_catalog_search_returns_defaults(): void
    {
        $user = User::factory()->create();

        BqLineCatalog::create([
            'name' => 'Mobilisasi',
            'type' => 'charge',
            'default_qty' => 1,
            'default_unit' => 'LS',
            'default_unit_price' => 1000,
            'percent_basis' => 'product_subtotal',
            'cost_bucket' => 'overhead',
            'is_active' => true,
        ]);

        $response = $this->actingAs($user)->getJson(route('bq-line-catalogs.search', ['q' => 'Mobi']));

        $response->assertOk()
            ->assertJsonFragment([
                'name' => 'Mobilisasi',
                'type' => 'charge',
                'default_qty' => 1,
                'default_unit' => 'LS',
                'default_unit_price' => 1000,
                'percent_basis' => 'product_subtotal',
                'cost_bucket' => 'overhead',
            ]);
    }

    public function test_snapshot_changes_do_not_update_catalog(): void
    {
        $user = User::factory()->create();
        $company = $this->makeCompany();
        $customer = $this->makeCustomer();
        $project = $this->makeProject($user, $company, $customer);
        $quotation = $this->makeQuotation($project, $company, $customer, $user);

        $catalog = BqLineCatalog::create([
            'name' => 'Mobilisasi',
            'type' => 'charge',
            'default_qty' => 1,
            'default_unit' => 'LS',
            'default_unit_price' => 1000,
            'percent_basis' => 'product_subtotal',
            'cost_bucket' => 'overhead',
            'is_active' => true,
        ]);

        $section = ProjectQuotationSection::create([
            'project_quotation_id' => $quotation->id,
            'name' => 'Add-ons',
            'sort_order' => 1,
        ]);

        ProjectQuotationLine::create([
            'section_id' => $section->id,
            'line_no' => '1',
            'description' => 'Mobilisasi (override)',
            'source_type' => 'item',
            'line_type' => 'charge',
            'catalog_id' => $catalog->id,
            'qty' => 1,
            'unit' => 'LS',
            'unit_price' => 1500,
            'material_total' => 1500,
            'labor_total' => 0,
            'line_total' => 1500,
        ]);

        $catalog->refresh();
        $this->assertEquals(1000, (float) $catalog->default_unit_price);
    }

    public function test_percent_basis_excludes_charge_lines(): void
    {
        $service = new ProjectQuotationTotalsService();

        $data = [
            'tax_enabled' => false,
            'tax_percent' => 0,
            'sections' => [
                [
                    'name' => 'Pekerjaan Utama',
                    'sort_order' => 1,
                    'lines' => [
                        [
                            'line_no' => '1',
                            'description' => 'Item 1',
                            'source_type' => 'item',
                            'line_type' => 'product',
                            'qty' => 1,
                            'unit' => 'LS',
                            'unit_price' => 0,
                            'material_total' => 1000,
                            'labor_total' => 500,
                        ],
                        [
                            'line_no' => '2',
                            'description' => 'Mobilisasi',
                            'source_type' => 'item',
                            'line_type' => 'charge',
                            'qty' => 1,
                            'unit' => 'LS',
                            'unit_price' => 100,
                            'material_total' => 100,
                            'labor_total' => 0,
                        ],
                        [
                            'line_no' => '3',
                            'description' => 'O/H',
                            'source_type' => 'item',
                            'line_type' => 'percent',
                            'percent_value' => 10,
                            'percent_basis' => 'product_subtotal',
                            'qty' => 1,
                            'unit' => '%',
                            'unit_price' => 0,
                            'material_total' => 0,
                            'labor_total' => 0,
                        ],
                    ],
                ],
            ],
        ];

        $computed = $service->compute($data);
        $line = $computed['sections'][0]['lines'][2];

        $this->assertEqualsWithDelta(150.0, (float) $line['computed_amount'], 0.01);
        $this->assertEqualsWithDelta(1750.0, (float) $computed['subtotal'], 0.01);
    }
}
