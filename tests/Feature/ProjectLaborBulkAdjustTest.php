<?php

namespace Tests\Feature;

use App\Models\Item;
use App\Models\ItemLaborRate;
use App\Models\ItemVariant;
use App\Models\ProjectItemLaborRate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ProjectLaborBulkAdjustTest extends TestCase
{
    use RefreshDatabase;

    private function makeUserWithRole(string $roleName): User
    {
        $role = Role::create(['name' => $roleName, 'guard_name' => 'web']);
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    private function makeItem(string $name, string $sku, ?string $listType = 'retail'): Item
    {
        return Item::create([
            'name' => $name,
            'sku' => $sku,
            'price' => 0,
            'list_type' => $listType,
        ]);
    }

    public function test_finance_can_bulk_increase_item_labor_rates_with_variant(): void
    {
        $user = $this->makeUserWithRole('Finance');

        $itemA = $this->makeItem('Item A', 'ITM-A-001', 'retail');
        $itemB = $this->makeItem('Item B', 'ITM-B-001', 'retail');

        $variant = ItemVariant::create([
            'item_id' => $itemB->id,
            'sku' => 'ITM-B-001-L',
            'price' => 0,
            'stock' => 0,
            'attributes' => ['size' => 'L'],
            'is_active' => true,
        ]);

        $rateA = ItemLaborRate::create([
            'item_id' => $itemA->id,
            'labor_unit_cost' => 100,
        ]);

        $rateB = ItemLaborRate::create([
            'item_id' => $itemB->id,
            'item_variant_id' => $variant->id,
            'labor_unit_cost' => 200,
        ]);

        $response = $this->actingAs($user)->post(route('projects.labor.bulk-adjust'), [
            'type' => 'item',
            'operation' => 'increase',
            'percent' => '10',
            'selected' => [
                $itemA->id.':0',
                $itemB->id.':'.$variant->id,
            ],
            'q' => 'abc',
            'page' => 2,
        ]);

        $response->assertSessionHasNoErrors();

        $rateA->refresh();
        $rateB->refresh();

        $this->assertEqualsWithDelta(110.0, (float) $rateA->labor_unit_cost, 0.01);
        $this->assertEqualsWithDelta(220.0, (float) $rateB->labor_unit_cost, 0.01);
        $this->assertSame($user->id, $rateA->updated_by);
        $this->assertSame($user->id, $rateB->updated_by);
    }

    public function test_user_without_role_cannot_bulk_adjust(): void
    {
        $user = User::factory()->create();
        $item = $this->makeItem('Item A', 'ITM-A-002', 'retail');

        $this->actingAs($user)
            ->post(route('projects.labor.bulk-adjust'), [
                'type' => 'item',
                'operation' => 'increase',
                'percent' => '10',
                'selected' => [$item->id.':0'],
            ])
            ->assertStatus(403);
    }

    public function test_bulk_adjust_requires_selected_items(): void
    {
        $user = $this->makeUserWithRole('Finance');

        $response = $this->actingAs($user)->from(route('projects.labor.index'))
            ->post(route('projects.labor.bulk-adjust'), [
                'type' => 'item',
                'operation' => 'increase',
                'percent' => '10',
            ]);

        $response->assertRedirect(route('projects.labor.index'));
        $response->assertSessionHasErrors('selected');
    }

    public function test_bulk_adjust_rejects_negative_percent(): void
    {
        $user = $this->makeUserWithRole('Finance');
        $item = $this->makeItem('Item A', 'ITM-A-003', 'retail');

        $rate = ItemLaborRate::create([
            'item_id' => $item->id,
            'labor_unit_cost' => 100,
        ]);

        $response = $this->actingAs($user)->from(route('projects.labor.index'))
            ->post(route('projects.labor.bulk-adjust'), [
                'type' => 'item',
                'operation' => 'increase',
                'percent' => '-1',
                'selected' => [$item->id.':0'],
            ]);

        $response->assertRedirect(route('projects.labor.index'));
        $response->assertSessionHasErrors('percent');

        $rate->refresh();
        $this->assertEqualsWithDelta(100.0, (float) $rate->labor_unit_cost, 0.01);
    }

    public function test_percent_above_100_is_allowed_for_increase(): void
    {
        $user = $this->makeUserWithRole('Finance');
        $item = $this->makeItem('Item A', 'ITM-A-004', 'retail');

        $rate = ItemLaborRate::create([
            'item_id' => $item->id,
            'labor_unit_cost' => 100,
        ]);

        $this->actingAs($user)
            ->post(route('projects.labor.bulk-adjust'), [
                'type' => 'item',
                'operation' => 'increase',
                'percent' => '150',
                'selected' => [$item->id.':0'],
            ])
            ->assertSessionHasNoErrors();

        $rate->refresh();
        $this->assertEqualsWithDelta(250.0, (float) $rate->labor_unit_cost, 0.01);
    }

    public function test_decrease_that_makes_negative_is_rejected_without_partial_updates(): void
    {
        $user = $this->makeUserWithRole('Finance');
        $itemA = $this->makeItem('Item A', 'ITM-A-005', 'retail');
        $itemB = $this->makeItem('Item B', 'ITM-B-005', 'retail');

        $rateA = ItemLaborRate::create([
            'item_id' => $itemA->id,
            'labor_unit_cost' => 100,
        ]);

        $rateB = ItemLaborRate::create([
            'item_id' => $itemB->id,
            'labor_unit_cost' => 80,
        ]);

        $response = $this->actingAs($user)->from(route('projects.labor.index'))
            ->post(route('projects.labor.bulk-adjust'), [
                'type' => 'item',
                'operation' => 'decrease',
                'percent' => '150',
                'selected' => [
                    $itemA->id.':0',
                    $itemB->id.':0',
                ],
            ]);

        $response->assertRedirect(route('projects.labor.index'));
        $response->assertSessionHasErrors('percent');

        $rateA->refresh();
        $rateB->refresh();

        $this->assertEqualsWithDelta(100.0, (float) $rateA->labor_unit_cost, 0.01);
        $this->assertEqualsWithDelta(80.0, (float) $rateB->labor_unit_cost, 0.01);
    }

    public function test_pm_can_bulk_adjust_project_item_rates(): void
    {
        $user = $this->makeUserWithRole('PM');
        $projectItem = $this->makeItem('Project Item', 'PRJ-ITM-001', 'project');

        $rate = ProjectItemLaborRate::create([
            'project_item_id' => $projectItem->id,
            'labor_unit_cost' => 50,
        ]);

        $this->actingAs($user)
            ->post(route('projects.labor.bulk-adjust'), [
                'type' => 'project',
                'operation' => 'increase',
                'percent' => '10',
                'selected' => [$projectItem->id.':0'],
            ])
            ->assertSessionHasNoErrors();

        $rate->refresh();
        $this->assertEqualsWithDelta(55.0, (float) $rate->labor_unit_cost, 0.01);
    }
}
