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
use App\Models\Unit;
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
            'unit_id' => Unit::create(['code' => 'btg', 'name' => 'Batang', 'is_active' => true])->id,
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
            'unit_id' => Unit::create(['code' => 'set', 'name' => 'Set', 'is_active' => true])->id,
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
        $create->assertSee('value="btg"', false);
        $create->assertSee('value="set"', false);
        $create->assertDontSee('+10% waste');
        $create->assertDontSee('Fire Hydrant - EQUIPMENT');
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

    public function test_upload_parses_item_name_with_double_quote_character_using_comma_split(): void
    {
        $admin = $this->makeUser('Admin');
        $ctx = $this->makeContext($admin);

        $csv = <<<CSV
Sheet,Category,Item,Quantity,Unit,Specification,LJR
Floor 1,Fitting,Elbow 90 4",1,pcs,,
CSV;

        $response = $this->actingAs($admin)->postJson(
            route('projects.bq-csv.import.upload', $ctx['project']),
            ['file' => $this->csvFile($csv)]
        )->assertOk();

        $missing = collect($response->json('missing_mappings'));
        $this->assertTrue($missing->contains(function ($row) {
            return (string) ($row['source_item'] ?? '') === 'Elbow 90 4"';
        }));
    }

    public function test_prepare_supports_mixed_mapping_and_ignored_rows(): void
    {
        $admin = $this->makeUser('Admin');
        $ctx = $this->makeContext($admin);

        $pipeItem = Item::create([
            'name' => 'Pipe Mapped',
            'sku' => 'PIPE-MAP-02',
            'price' => 1000,
            'list_type' => 'retail',
        ]);

        $csv = <<<CSV
Sheet,Category,Item,Quantity,Unit,Specification,LJR
Floor 1,Pipe,Pipe MED,53.16,m,+10% waste = 58.48 m,10
Floor 1,Device,Indoor Hydrant Box,12,pcs,Fire Hydrant - EQUIPMENT,
CSV;

        $upload = $this->actingAs($admin)->postJson(
            route('projects.bq-csv.import.upload', $ctx['project']),
            ['file' => $this->csvFile($csv)]
        )->assertOk();
        $token = (string) $upload->json('token');

        $this->actingAs($admin)->postJson(
            route('projects.bq-csv.import.mappings', $ctx['project']),
            [
                'mappings' => [
                    [
                        'source_category' => 'Pipe',
                        'source_item' => 'Pipe MED',
                        'mapped_item' => 'Pipe Mapped',
                        'target_item_id' => $pipeItem->id,
                        'target_item_variant_id' => null,
                    ],
                ],
            ]
        )->assertOk();

        $prepared = $this->actingAs($admin)->postJson(
            route('projects.bq-csv.import.prepare', $ctx['project']),
            [
                'token' => $token,
                'ignored' => [
                    ['source_category' => 'Device', 'source_item' => 'Indoor Hydrant Box'],
                ],
            ]
        )->assertOk();

        $redirectUrl = (string) $prepared->json('redirect_url');
        $this->assertStringContainsString('/projects/'.$ctx['project']->id.'/quotations/create', $redirectUrl);

        $create = $this->actingAs($admin)->get($redirectUrl);
        $create->assertOk();
        $create->assertSee('Pipe Mapped');
        $create->assertSee('value="10"', false);
        $create->assertDontSee('Indoor Hydrant Box');

        $this->assertDatabaseHas('bq_csv_conversions', [
            'source_category_norm' => 'pipe',
            'source_item_norm' => 'pipe med',
            'target_item_id' => $pipeItem->id,
        ]);
        $this->assertDatabaseMissing('bq_csv_conversions', [
            'source_category_norm' => 'device',
            'source_item_norm' => 'indoor hydrant box',
        ]);
    }

    public function test_prepare_allows_all_rows_ignored_and_redirects_to_create(): void
    {
        $admin = $this->makeUser('Admin');
        $ctx = $this->makeContext($admin);

        $csv = <<<CSV
Sheet,Category,Item,Quantity,Unit,Specification,LJR
Floor 1,Pipe,Pipe MED,53.16,m,+10% waste = 58.48 m,10
Floor 1,Device,Indoor Hydrant Box,12,pcs,Fire Hydrant - EQUIPMENT,
CSV;

        $upload = $this->actingAs($admin)->postJson(
            route('projects.bq-csv.import.upload', $ctx['project']),
            ['file' => $this->csvFile($csv)]
        )->assertOk();
        $token = (string) $upload->json('token');

        $prepared = $this->actingAs($admin)->postJson(
            route('projects.bq-csv.import.prepare', $ctx['project']),
            [
                'token' => $token,
                'ignored' => [
                    ['source_category' => 'Pipe', 'source_item' => 'Pipe MED'],
                    ['source_category' => 'Device', 'source_item' => 'Indoor Hydrant Box'],
                ],
            ]
        )->assertOk();

        $redirectUrl = (string) $prepared->json('redirect_url');
        $this->assertStringContainsString('import_token=', $redirectUrl);

        $create = $this->actingAs($admin)->get($redirectUrl);
        $create->assertOk();
        $create->assertDontSee('Pipe MED');
        $create->assertDontSee('Indoor Hydrant Box');
    }

    public function test_ignored_rows_are_not_persisted_and_reappear_on_next_upload(): void
    {
        $admin = $this->makeUser('Admin');
        $ctx = $this->makeContext($admin);

        $csv = <<<CSV
Sheet,Category,Item,Quantity,Unit,Specification,LJR
Floor 1,Device,Indoor Hydrant Box,12,pcs,Fire Hydrant - EQUIPMENT,
CSV;

        $uploadFirst = $this->actingAs($admin)->postJson(
            route('projects.bq-csv.import.upload', $ctx['project']),
            ['file' => $this->csvFile($csv)]
        )->assertOk();
        $firstToken = (string) $uploadFirst->json('token');

        $this->actingAs($admin)->postJson(
            route('projects.bq-csv.import.prepare', $ctx['project']),
            [
                'token' => $firstToken,
                'ignored' => [
                    ['source_category' => 'Device', 'source_item' => 'Indoor Hydrant Box'],
                ],
            ]
        )->assertOk();

        $this->assertDatabaseMissing('bq_csv_conversions', [
            'source_category_norm' => 'device',
            'source_item_norm' => 'indoor hydrant box',
        ]);

        $uploadSecond = $this->actingAs($admin)->postJson(
            route('projects.bq-csv.import.upload', $ctx['project']),
            ['file' => $this->csvFile($csv)]
        )->assertOk();

        $missing = collect($uploadSecond->json('missing_mappings'));
        $this->assertTrue($missing->contains(function ($row) {
            return (string) ($row['source_category'] ?? '') === 'Device'
                && (string) ($row['source_item'] ?? '') === 'Indoor Hydrant Box';
        }));
    }

    public function test_prefill_uses_master_item_or_variant_name_not_csv_name(): void
    {
        $admin = $this->makeUser('Admin');
        $ctx = $this->makeContext($admin);

        $item = Item::create([
            'name' => 'Master Pipe Name',
            'sku' => 'MASTER-PIPE-01',
            'price' => 1200,
            'list_type' => 'retail',
            'variant_type' => 'size',
            'name_template' => '{name} {size}',
        ]);
        $variant = ItemVariant::create([
            'item_id' => $item->id,
            'sku' => 'MASTER-PIPE-01-4IN',
            'price' => 1300,
            'attributes' => ['size' => '4"'],
            'is_active' => true,
        ]);

        BqCsvConversion::create([
            'source_category' => 'Pipe',
            'source_item' => 'PIPE CSV NAME',
            'mapped_item' => 'PIPE CSV NAME',
            'target_source_type' => 'item',
            'target_item_id' => $item->id,
            'target_item_variant_id' => $variant->id,
            'is_active' => true,
        ]);

        $csv = <<<CSV
Sheet,Category,Item,Quantity,Unit,Specification,LJR
Floor 1,Pipe,PIPE CSV NAME,53.16,m,+10% waste = 58.48 m,10
CSV;

        $upload = $this->actingAs($admin)->postJson(
            route('projects.bq-csv.import.upload', $ctx['project']),
            ['file' => $this->csvFile($csv)]
        )->assertOk();
        $token = (string) $upload->json('token');

        $prepared = $this->actingAs($admin)->postJson(
            route('projects.bq-csv.import.prepare', $ctx['project']),
            ['token' => $token]
        )->assertOk();

        $redirectUrl = (string) $prepared->json('redirect_url');
        $create = $this->actingAs($admin)->get($redirectUrl);
        $create->assertOk();
        $create->assertSee('Master Pipe Name');
        $create->assertDontSee('PIPE CSV NAME');
    }

    public function test_prefill_variant_label_falls_back_to_attribute_when_variant_template_equals_parent(): void
    {
        $admin = $this->makeUser('Admin');
        $ctx = $this->makeContext($admin);

        $item = Item::create([
            'name' => 'Elbow',
            'sku' => 'ELBOW-PARENT',
            'price' => 15000,
            'list_type' => 'project',
            'variant_type' => 'none',
            'name_template' => '{name}',
        ]);
        $variant = ItemVariant::create([
            'item_id' => $item->id,
            'sku' => 'ELBOW-90A-3IN',
            'price' => 17000,
            'attributes' => ['size' => '3"'],
            'is_active' => true,
        ]);

        BqCsvConversion::create([
            'source_category' => 'Fitting',
            'source_item' => 'ELBOW CSV',
            'mapped_item' => 'ELBOW CSV',
            'target_source_type' => 'project',
            'target_item_id' => $item->id,
            'target_item_variant_id' => $variant->id,
            'is_active' => true,
        ]);

        $csv = <<<CSV
Sheet,Category,Item,Quantity,Unit,Specification,LJR
Floor 1,Fitting,ELBOW CSV,2,pcs,,0
CSV;

        $upload = $this->actingAs($admin)->postJson(
            route('projects.bq-csv.import.upload', $ctx['project']),
            ['file' => $this->csvFile($csv)]
        )->assertOk();
        $token = (string) $upload->json('token');

        $prepared = $this->actingAs($admin)->postJson(
            route('projects.bq-csv.import.prepare', $ctx['project']),
            ['token' => $token]
        )->assertOk();

        $redirectUrl = (string) $prepared->json('redirect_url');
        $create = $this->actingAs($admin)->get($redirectUrl);
        $create->assertOk();
        $create->assertSee('value="Elbow - 3&quot;"', false);
        $create->assertDontSee('ELBOW CSV');
    }
}
