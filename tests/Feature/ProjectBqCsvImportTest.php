<?php

namespace Tests\Feature;

use App\Models\BqCsvConversion;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Item;
use App\Models\ItemLaborRate;
use App\Models\ItemVariant;
use App\Models\Project;
use App\Models\ProjectItemLaborRate;
use App\Models\ProjectQuotation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ProjectBqCsvImportTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(?string $role = null): User
    {
        $user = User::factory()->create();
        if ($role) {
            $roleRow = Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
            $user->assignRole($roleRow);
        }

        return $user;
    }

    private function makeContext(User $owner): array
    {
        $company = Company::create([
            'name' => 'Import BQ Co',
            'alias' => 'IMPBQ',
        ]);
        $customer = Customer::create([
            'name' => 'Import BQ Customer',
        ]);
        $project = Project::create([
            'company_id' => $company->id,
            'customer_id' => $customer->id,
            'code' => 'PRJ-IMPORT-001',
            'name' => 'Project Import',
            'systems_json' => ['fire_hydrant'],
            'status' => 'active',
            'sales_owner_user_id' => $owner->id,
        ]);

        return compact('company', 'customer', 'project');
    }

    private function csvFile(string $content): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('bq-import.csv', $content);
    }

    public function test_upload_requires_create_bq_permission(): void
    {
        $sales = $this->makeUser('Sales');
        $ctx = $this->makeContext($sales);

        $csv = <<<CSV
Sheet,Category,Item,Quantity,Unit,Specification,LJR
Floor 1,Pipe,Pipe MED,53.16,m,+10% waste = 58.48 m,10
CSV;

        $this->actingAs($sales)->postJson(
            route('projects.bq-csv.import.upload', $ctx['project']),
            ['file' => $this->csvFile($csv)]
        )->assertStatus(403);
    }

    public function test_admin_can_import_then_prepare_prefilled_new_bq(): void
    {
        $admin = $this->makeUser('Admin');
        $ctx = $this->makeContext($admin);

        $pipeItem = Item::create([
            'name' => 'Pipe Mapped',
            'sku' => 'PIPE-MAP-01',
            'price' => 1000,
            'list_type' => 'retail',
        ]);
        $pipeVariant = ItemVariant::create([
            'item_id' => $pipeItem->id,
            'sku' => 'PIPE-MAP-01-4IN',
            'price' => 1500,
            'attributes' => ['size' => '4"'],
            'is_active' => true,
        ]);
        $dieselItem = Item::create([
            'name' => 'Diesel Pump Mapped',
            'sku' => 'DIESEL-01',
            'price' => 8000,
            'list_type' => 'project',
        ]);

        ItemLaborRate::create([
            'item_id' => $pipeItem->id,
            'item_variant_id' => $pipeVariant->id,
            'labor_unit_cost' => 120,
        ]);
        ProjectItemLaborRate::create([
            'project_item_id' => $dieselItem->id,
            'labor_unit_cost' => 250,
        ]);

        $csv = <<<CSV
Sheet,Category,Item,Quantity,Unit,Specification,LJR
Floor 1,Pipe,Pipe MED,53.16,m,+10% waste = 58.48 m,10
Floor 1,Device,Diesel Pump,1,pcs,Pump Room,
TOTAL ALL SHEETS,Pipe,Pipe MED,999,m,Should Ignore,999
CSV;

        $upload = $this->actingAs($admin)->postJson(
            route('projects.bq-csv.import.upload', $ctx['project']),
            ['file' => $this->csvFile($csv)]
        )->assertOk();

        $token = (string) $upload->json('token');
        $this->assertNotSame('', $token);
        $this->assertNotEmpty($upload->json('missing_mappings'));

        $this->actingAs($admin)->postJson(
            route('projects.bq-csv.import.mappings', $ctx['project']),
            [
                'mappings' => [
                    [
                        'source_category' => 'Pipe',
                        'source_item' => 'Pipe MED',
                        'mapped_item' => 'Pipe Mapped',
                        'target_item_id' => $pipeItem->id,
                        'target_item_variant_id' => $pipeVariant->id,
                    ],
                    [
                        'source_category' => 'Device',
                        'source_item' => 'Diesel Pump',
                        'mapped_item' => 'Diesel Pump Mapped',
                        'target_item_id' => $dieselItem->id,
                        'target_item_variant_id' => null,
                    ],
                ],
            ]
        )->assertOk();

        $prepared = $this->actingAs($admin)->postJson(
            route('projects.bq-csv.import.prepare', $ctx['project']),
            ['token' => $token]
        )->assertOk();

        $redirectUrl = (string) $prepared->json('redirect_url');
        $this->assertStringContainsString('/projects/'.$ctx['project']->id.'/quotations/create', $redirectUrl);
        $this->assertStringContainsString('import_token=', $redirectUrl);

        $create = $this->actingAs($admin)->get($redirectUrl);
        $create->assertOk();
        $create->assertSee('Pump Room');
        $create->assertSee('Pipeline');
        $create->assertSee('Pipe Mapped');
        $create->assertSee('Diesel Pump Mapped');
        $create->assertSee('value="10"', false);
    }

    public function test_prepare_rejects_mapping_without_target_item_link(): void
    {
        $admin = $this->makeUser('Admin');
        $ctx = $this->makeContext($admin);

        BqCsvConversion::create([
            'source_category' => 'Pipe',
            'source_item' => 'Pipe MED',
            'mapped_item' => 'Pipe Mapped',
            'is_active' => true,
        ]);

        $csv = <<<CSV
Sheet,Category,Item,Quantity,Unit,Specification,LJR
Floor 1,Pipe,Pipe MED,53.16,m,+10% waste = 58.48 m,10
CSV;

        $upload = $this->actingAs($admin)->postJson(
            route('projects.bq-csv.import.upload', $ctx['project']),
            ['file' => $this->csvFile($csv)]
        )->assertOk();
        $token = (string) $upload->json('token');

        $this->actingAs($admin)->postJson(
            route('projects.bq-csv.import.prepare', $ctx['project']),
            ['token' => $token]
        )->assertStatus(422);
    }

    public function test_import_button_is_visible_on_project_quotation_pages(): void
    {
        $admin = $this->makeUser('Admin');
        $ctx = $this->makeContext($admin);

        $this->actingAs($admin)
            ->get(route('projects.show', ['project' => $ctx['project'], 'tab' => 'quotations']))
            ->assertOk()
            ->assertSee('Import CSV BQ')
            ->assertSee('New BQ');

        $this->actingAs($admin)
            ->get(route('projects.quotations.index', $ctx['project']))
            ->assertOk()
            ->assertSee('Import CSV BQ')
            ->assertSee('New BQ');
    }
}
