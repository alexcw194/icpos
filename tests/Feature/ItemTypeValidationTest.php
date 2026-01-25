<?php

namespace Tests\Feature;

use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ItemTypeValidationTest extends TestCase
{
    use RefreshDatabase;

    public function test_item_type_project_is_rejected(): void
    {
        $role = Role::create(['name' => 'Admin', 'guard_name' => 'web']);
        $user = User::factory()->create();
        $user->assignRole($role);

        $unit = Unit::create([
            'code' => 'pcs',
            'name' => 'PCS',
            'is_active' => true,
        ]);

        $response = $this
            ->actingAs($user)
            ->postJson('/items', [
                'name' => 'Test Item',
                'price' => 1000,
                'stock' => 0,
                'unit_id' => $unit->id,
                'item_type' => 'project',
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['item_type']);
    }
}
