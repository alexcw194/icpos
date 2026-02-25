<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Customer;
use App\Models\Project;
use App\Models\ProjectQuotation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ProjectQuotationBqCsvExportTest extends TestCase
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
            'name' => 'BQ CSV Co',
            'alias' => 'BQCSV',
        ]);
        $customer = Customer::create([
            'name' => 'BQ CSV Customer',
        ]);
        $project = Project::create([
            'company_id' => $company->id,
            'customer_id' => $customer->id,
            'code' => 'PRJ-BQCSV-001',
            'name' => 'Project CSV',
            'systems_json' => ['fire_hydrant'],
            'status' => 'active',
            'sales_owner_user_id' => $owner->id,
        ]);
        $quotation = ProjectQuotation::create([
            'project_id' => $project->id,
            'company_id' => $company->id,
            'customer_id' => $customer->id,
            'number' => 'BQ-CSV-001',
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
            'sales_owner_user_id' => $owner->id,
        ]);

        return compact('company', 'customer', 'project', 'quotation');
    }

    private function csvFile(string $content): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('bq-source.csv', $content);
    }

    /**
     * @return array{0: array<int, string>, 1: array<int, array<string, string>>}
     */
    private function parseCsvContent(string $content): array
    {
        $lines = preg_split('/\r\n|\r|\n/', trim($content));
        $rows = [];
        foreach ($lines as $line) {
            if (trim((string) $line) === '') {
                continue;
            }
            $rows[] = str_getcsv($line);
        }

        $header = $rows[0] ?? [];
        $dataRows = [];
        foreach (array_slice($rows, 1) as $row) {
            $dataRows[] = array_combine($header, $row);
        }

        return [$header, $dataRows];
    }

    public function test_upload_parses_header_and_breakdown_flag(): void
    {
        $admin = $this->makeUser('Admin');
        $ctx = $this->makeContext($admin);

        $csvOneSheet = <<<CSV
Sheet,Category,Item,Quantity,Unit,Specification,LJR
Floor 1,Pipe,Pipe MED,53.16,m,+10% waste = 58.48 m,10
TOTAL ALL SHEETS,Pipe,Pipe MED,53.16,m,+10% waste = 58.48 m,10
CSV;

        $response = $this->actingAs($admin)->postJson(
            route('projects.quotations.bq-csv.upload', [$ctx['project'], $ctx['quotation']]),
            ['file' => $this->csvFile($csvOneSheet)]
        );

        $response->assertOk()
            ->assertJson([
                'sheet_count' => 1,
                'can_breakdown' => false,
            ]);
        $this->assertNotEmpty($response->json('token'));
        $this->assertNotEmpty($response->json('missing_mappings'));

        $csvTwoSheets = <<<CSV
Sheet,Category,Item,Quantity,Unit,Specification,LJR
Floor 1,Pipe,Pipe MED,53.16,m,+10% waste = 58.48 m,10
Floor 2,Fitting,Elbow 90,2,pcs,,
CSV;

        $responseTwo = $this->actingAs($admin)->postJson(
            route('projects.quotations.bq-csv.upload', [$ctx['project'], $ctx['quotation']]),
            ['file' => $this->csvFile($csvTwoSheets)]
        );

        $responseTwo->assertOk()
            ->assertJson([
                'sheet_count' => 2,
                'can_breakdown' => true,
            ]);
    }

    public function test_upload_rejects_invalid_header(): void
    {
        $admin = $this->makeUser('Admin');
        $ctx = $this->makeContext($admin);

        $invalidCsv = <<<CSV
Sheet,Category,Item,Quantity,Unit,Specification
Floor 1,Pipe,Pipe MED,53.16,m,+10% waste = 58.48 m
CSV;

        $this->actingAs($admin)->postJson(
            route('projects.quotations.bq-csv.upload', [$ctx['project'], $ctx['quotation']]),
            ['file' => $this->csvFile($invalidCsv)]
        )->assertStatus(422);
    }

    public function test_admin_can_save_mapping_then_export_csv(): void
    {
        $admin = $this->makeUser('Admin');
        $ctx = $this->makeContext($admin);

        $csv = <<<CSV
Sheet,Category,Item,Quantity,Unit,Specification,LJR
Floor 1,Pipe,Pipe MED,53.16,m,+10% waste = 58.48 m,10
Floor 2,Pipe,Pipe MED,26.58,m,+10% waste = 29.24 m,5
Floor 1,Fitting,Elbow 90,2,pcs,,
Floor 1,Device,Hydrant Pillar,16,pcs,Fire Hydrant - EQUIPMENT,
Floor 1,Device,Diesel Pump,1,pcs,,
Floor 1,Valves,PRV 3,2,pcs,Fire Hydrant - VALVES,
TOTAL ALL SHEETS,Pipe,Pipe MED,999,m,SHOULD IGNORE,999
CSV;

        $upload = $this->actingAs($admin)->postJson(
            route('projects.quotations.bq-csv.upload', [$ctx['project'], $ctx['quotation']]),
            ['file' => $this->csvFile($csv)]
        )->assertOk();

        $token = (string) $upload->json('token');
        $this->assertNotSame('', $token);

        $this->actingAs($admin)->get(
            route('projects.quotations.bq-csv.export', [$ctx['project'], $ctx['quotation'], 'token' => $token, 'breakdown' => 0])
        )->assertStatus(422);

        $this->actingAs($admin)->postJson(
            route('projects.quotations.bq-csv.mappings', [$ctx['project'], $ctx['quotation']]),
            [
                'mappings' => [
                    ['source_category' => 'Pipe', 'source_item' => 'Pipe MED', 'mapped_item' => 'Pipe MED'],
                    ['source_category' => 'Fitting', 'source_item' => 'Elbow 90', 'mapped_item' => 'Elbow 90'],
                    ['source_category' => 'Device', 'source_item' => 'Hydrant Pillar', 'mapped_item' => 'Hydrant Pillar'],
                    ['source_category' => 'Device', 'source_item' => 'Diesel Pump', 'mapped_item' => 'Diesel Pump'],
                    ['source_category' => 'Valves', 'source_item' => 'PRV 3', 'mapped_item' => 'PRV 3'],
                ],
            ]
        )->assertOk();

        $download = $this->actingAs($admin)->get(
            route('projects.quotations.bq-csv.export', [$ctx['project'], $ctx['quotation'], 'token' => $token, 'breakdown' => 0])
        );
        $download->assertOk();

        [$header, $rows] = $this->parseCsvContent($download->streamedContent());
        $this->assertSame(['Sheet', 'Category', 'Item', 'Quantity', 'Unit', 'Specification', 'LJR'], $header);
        $this->assertNotEmpty($rows);

        foreach ($rows as $row) {
            $this->assertSame('TOTAL ALL SHEETS', $row['Sheet']);
        }

        $this->assertTrue(collect($rows)->contains(function ($row) {
            return ($row['Item'] ?? '') === 'Pipe MED'
                && ($row['Quantity'] ?? '') === '15';
        }));
        $this->assertFalse(collect($rows)->contains(function ($row) {
            return ($row['Specification'] ?? '') === 'SHOULD IGNORE';
        }));

        $pipeRowIndex = null;
        $pumpRowIndex = null;
        $equipmentRowIndex = null;
        $valveRowIndex = null;
        foreach ($rows as $idx => $row) {
            if (($row['Item'] ?? '') === 'Pipe MED') {
                $pipeRowIndex = $idx;
                $this->assertSame('m', $row['Unit']);
            }
            if (($row['Item'] ?? '') === 'Diesel Pump') {
                $pumpRowIndex = $idx;
            }
            if (($row['Item'] ?? '') === 'PRV 3') {
                $valveRowIndex = $idx;
            }
            if (($row['Item'] ?? '') === 'Hydrant Pillar') {
                $equipmentRowIndex = $idx;
            }
        }

        $this->assertNotNull($pipeRowIndex);
        $this->assertNotNull($pumpRowIndex);
        $this->assertNotNull($valveRowIndex);
        $this->assertNotNull($equipmentRowIndex);
        $this->assertTrue($pumpRowIndex < $pipeRowIndex);
        $this->assertTrue($pumpRowIndex < $valveRowIndex);
        $this->assertTrue($valveRowIndex < $equipmentRowIndex);
        $this->assertTrue($pipeRowIndex < $equipmentRowIndex);

        $downloadBreakdown = $this->actingAs($admin)->get(
            route('projects.quotations.bq-csv.export', [$ctx['project'], $ctx['quotation'], 'token' => $token, 'breakdown' => 1])
        );
        $downloadBreakdown->assertOk();
        [, $rowsBreakdown] = $this->parseCsvContent($downloadBreakdown->streamedContent());
        $this->assertTrue(collect($rowsBreakdown)->contains(fn ($row) => ($row['Sheet'] ?? '') === 'Floor 1'));
        $this->assertTrue(collect($rowsBreakdown)->contains(fn ($row) => ($row['Sheet'] ?? '') === 'Floor 2'));
        $this->assertTrue(collect($rowsBreakdown)->contains(fn ($row) => ($row['Sheet'] ?? '') === 'TOTAL ALL SHEETS'));
    }

    public function test_non_admin_cannot_store_mappings(): void
    {
        $owner = $this->makeUser();
        $ctx = $this->makeContext($owner);

        $csv = <<<CSV
Sheet,Category,Item,Quantity,Unit,Specification,LJR
Floor 1,Pipe,Pipe MED,53.16,m,+10% waste = 58.48 m,10
CSV;

        $this->actingAs($owner)->postJson(
            route('projects.quotations.bq-csv.upload', [$ctx['project'], $ctx['quotation']]),
            ['file' => $this->csvFile($csv)]
        )->assertOk();

        $this->actingAs($owner)->postJson(
            route('projects.quotations.bq-csv.mappings', [$ctx['project'], $ctx['quotation']]),
            [
                'mappings' => [
                    ['source_category' => 'Pipe', 'source_item' => 'Pipe MED', 'mapped_item' => 'Pipe MED'],
                ],
            ]
        )->assertStatus(403);
    }
}
