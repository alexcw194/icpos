<?php

namespace Tests\Feature;

use App\Models\Labor;
use App\Models\LaborCost;
use App\Models\SubContractor;
use App\Models\User;
use App\Services\ProjectQuotationTotalsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class LaborCostTest extends TestCase
{
    use RefreshDatabase;

    private function makeAdminUser(): User
    {
        $role = Role::create(['name' => 'Admin', 'guard_name' => 'web']);
        $user = User::factory()->create();
        $user->assignRole($role);
        return $user;
    }

    public function test_admin_can_create_sub_contractor(): void
    {
        $admin = $this->makeAdminUser();

        $response = $this->actingAs($admin)->post('/sub-contractors', [
            'name' => 'PT Sub A',
            'is_active' => 1,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('sub_contractors', [
            'name' => 'PT Sub A',
            'is_active' => 1,
        ]);
    }

    public function test_admin_can_set_and_fetch_labor_cost(): void
    {
        $admin = $this->makeAdminUser();

        $sub = SubContractor::create([
            'name' => 'Sub A',
            'is_active' => true,
        ]);

        $labor = Labor::create([
            'code' => 'INST',
            'name' => 'Installation',
            'unit' => 'LS',
            'is_active' => true,
        ]);

        $response = $this->actingAs($admin)->post("/labors/{$labor->id}/cost", [
            'sub_contractor_id' => $sub->id,
            'cost_amount' => '1.000,00',
            'is_active' => 1,
            'set_default' => 1,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('labor_costs', [
            'labor_id' => $labor->id,
            'sub_contractor_id' => $sub->id,
        ]);

        $labor->refresh();
        $this->assertEquals($sub->id, $labor->default_sub_contractor_id);

        $payload = $this->actingAs($admin)
            ->getJson(route('labors.cost.show', $labor, ['sub_contractor_id' => $sub->id]))
            ->assertOk()
            ->json();

        $this->assertTrue($payload['exists']);
        $this->assertEqualsWithDelta(1000.0, (float) $payload['cost_amount'], 0.01);
    }

    public function test_non_admin_cannot_access_cost_endpoints(): void
    {
        $user = User::factory()->create();

        $sub = SubContractor::create([
            'name' => 'Sub B',
            'is_active' => true,
        ]);

        $labor = Labor::create([
            'code' => 'TC',
            'name' => 'Testing',
            'unit' => 'LS',
            'is_active' => true,
        ]);

        $this->actingAs($user)
            ->getJson(route('labors.cost.show', $labor, ['sub_contractor_id' => $sub->id]))
            ->assertStatus(403);

        $this->actingAs($user)
            ->post(route('labors.cost.store', $labor), [
                'sub_contractor_id' => $sub->id,
                'cost_amount' => '1000',
            ])
            ->assertStatus(403);
    }

    public function test_bq_snapshot_uses_default_sub_contractor_cost(): void
    {
        $sub = SubContractor::create([
            'name' => 'Sub C',
            'is_active' => true,
        ]);

        $labor = Labor::create([
            'code' => 'INS',
            'name' => 'Install',
            'unit' => 'LS',
            'is_active' => true,
            'default_sub_contractor_id' => $sub->id,
        ]);

        LaborCost::create([
            'labor_id' => $labor->id,
            'sub_contractor_id' => $sub->id,
            'cost_amount' => 2500,
            'is_active' => true,
        ]);

        $service = new ProjectQuotationTotalsService();
        $data = [
            'sections' => [
                [
                    'name' => 'Main',
                    'sort_order' => 1,
                    'lines' => [
                        [
                            'line_no' => '1',
                            'description' => 'Item 1',
                            'line_type' => 'product',
                            'qty' => 1,
                            'unit' => 'LS',
                            'unit_price' => 0,
                            'material_total' => 1000,
                            'labor_total' => 500,
                            'labor_id' => $labor->id,
                        ],
                    ],
                ],
            ],
        ];

        $computed = $service->compute($data);
        $line = $computed['sections'][0]['lines'][0];

        $this->assertEqualsWithDelta(2500.0, (float) $line['labor_cost_amount'], 0.01);
        $this->assertEquals('sub_contractor', $line['labor_cost_source']);
    }
}
