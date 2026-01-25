<?php

namespace Tests\Feature;

use App\Models\Item;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ItemListTypeTest extends TestCase
{
    use RefreshDatabase;

    private function makeAdminUser(): User
    {
        $role = Role::create(['name' => 'Admin', 'guard_name' => 'web']);
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    private function makeUnit(): Unit
    {
        return Unit::create([
            'code' => 'pcs',
            'name' => 'PCS',
            'is_active' => true,
        ]);
    }

    public function test_items_index_only_shows_retail_list_type(): void
    {
        $user = User::factory()->create();

        Item::create([
            'name' => 'Retail Item',
            'sku' => 'RET-ITEM-1',
            'price' => 1000,
            'list_type' => 'retail',
        ]);

        Item::create([
            'name' => 'Project Item',
            'sku' => 'PRJ-ITEM-1',
            'price' => 1000,
            'list_type' => 'project',
        ]);

        $response = $this->actingAs($user)->get('/items');

        $response->assertOk();
        $response->assertSee('Retail Item');
        $response->assertDontSee('Project Item');
    }

    public function test_project_items_index_only_shows_project_list_type(): void
    {
        $user = User::factory()->create();

        Item::create([
            'name' => 'Retail Item',
            'sku' => 'RET-ITEM-2',
            'price' => 1000,
            'list_type' => 'retail',
        ]);

        Item::create([
            'name' => 'Project Item',
            'sku' => 'PRJ-ITEM-2',
            'price' => 1000,
            'list_type' => 'project',
        ]);

        $response = $this->actingAs($user)->get('/project-items');

        $response->assertOk();
        $response->assertSee('Project Item');
        $response->assertDontSee('Retail Item');
    }

    public function test_store_forces_retail_list_type(): void
    {
        $admin = $this->makeAdminUser();
        $unit = $this->makeUnit();

        $response = $this->actingAs($admin)->post('/items', [
            'name' => 'Payload Retail',
            'sku' => 'RET-PAYLOAD-1',
            'price' => 1000,
            'stock' => 0,
            'unit_id' => $unit->id,
            'item_type' => 'standard',
            'list_type' => 'project',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('items', [
            'sku' => 'RET-PAYLOAD-1',
            'list_type' => 'retail',
        ]);
    }

    public function test_store_forces_project_list_type(): void
    {
        $admin = $this->makeAdminUser();
        $unit = $this->makeUnit();

        $response = $this->actingAs($admin)->post('/project-items', [
            'name' => 'Payload Project',
            'sku' => 'PRJ-PAYLOAD-1',
            'price' => 1000,
            'stock' => 0,
            'unit_id' => $unit->id,
            'item_type' => 'standard',
            'list_type' => 'retail',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('items', [
            'sku' => 'PRJ-PAYLOAD-1',
            'list_type' => 'project',
        ]);
    }

    public function test_cross_edit_guard_returns_404(): void
    {
        $admin = $this->makeAdminUser();

        $item = Item::create([
            'name' => 'Retail Item',
            'sku' => 'RET-ITEM-3',
            'price' => 1000,
            'list_type' => 'retail',
        ]);

        $response = $this->actingAs($admin)->get("/project-items/{$item->id}/edit");

        $response->assertStatus(404);
    }
}
