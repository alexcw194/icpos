<?php

namespace Tests\Feature;

use App\Models\FamilyCode;
use App\Models\Item;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class FamilyCodeMasterTest extends TestCase
{
    use RefreshDatabase;

    private function makeUserWithRole(?string $roleName = null): User
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $user = User::factory()->create();
        if ($roleName) {
            $role = Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web']);
            $user->assignRole($role);
        }

        return $user;
    }

    public function test_non_admin_cannot_access_family_code_master(): void
    {
        $user = $this->makeUserWithRole();

        $this->actingAs($user)
            ->get(route('family-codes.index'))
            ->assertStatus(403);
    }

    public function test_admin_can_create_family_code_and_duplicate_is_rejected(): void
    {
        $admin = $this->makeUserWithRole('Admin');

        $this->actingAs($admin)
            ->post(route('family-codes.store'), [
                'code' => 'FIREHOSE',
            ])
            ->assertRedirect(route('family-codes.index'));

        $this->assertDatabaseHas('family_codes', ['code' => 'FIREHOSE']);

        $this->actingAs($admin)
            ->from(route('family-codes.create'))
            ->post(route('family-codes.store'), [
                'code' => 'FIREHOSE',
            ])
            ->assertRedirect(route('family-codes.create'))
            ->assertSessionHasErrors(['code']);
    }

    public function test_item_family_code_is_optional_and_must_exist_in_master(): void
    {
        $admin = $this->makeUserWithRole('Admin');

        $unit = Unit::create([
            'code' => 'pcs',
            'name' => 'PCS',
            'is_active' => true,
        ]);

        FamilyCode::create(['code' => 'FIREHOSE']);

        $this->actingAs($admin)
            ->post(route('items.store'), [
                'name' => 'Item Tanpa Family',
                'sku' => 'ITM-FAM-001',
                'price' => 1000,
                'stock' => 0,
                'unit_id' => $unit->id,
                'item_type' => 'standard',
            ])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('items', [
            'name' => 'Item Tanpa Family',
            'family_code' => null,
        ]);

        $this->actingAs($admin)
            ->post(route('items.store'), [
                'name' => 'Item Dengan Family',
                'sku' => 'ITM-FAM-002',
                'price' => 1000,
                'stock' => 0,
                'unit_id' => $unit->id,
                'item_type' => 'standard',
                'family_code' => 'FIREHOSE',
            ])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('items', [
            'name' => 'Item Dengan Family',
            'family_code' => 'FIREHOSE',
        ]);

        $this->actingAs($admin)
            ->from(route('items.create'))
            ->post(route('items.store'), [
                'name' => 'Item Family Invalid',
                'sku' => 'ITM-FAM-003',
                'price' => 1000,
                'stock' => 0,
                'unit_id' => $unit->id,
                'item_type' => 'standard',
                'family_code' => 'TIDAK-ADA',
            ])
            ->assertRedirect(route('items.create'))
            ->assertSessionHasErrors(['family_code']);
    }

    public function test_family_code_migration_backfills_from_existing_items(): void
    {
        Schema::dropIfExists('family_codes');

        $unit = Unit::create([
            'code' => 'pcs',
            'name' => 'PCS',
            'is_active' => true,
        ]);

        Item::create([
            'name' => 'Item 1',
            'sku' => 'ITM-BACKFILL-001',
            'price' => 1000,
            'stock' => 0,
            'unit_id' => $unit->id,
            'item_type' => 'standard',
            'list_type' => 'retail',
            'family_code' => 'FIREHOSE',
        ]);

        Item::create([
            'name' => 'Item 2',
            'sku' => 'ITM-BACKFILL-002',
            'price' => 1000,
            'stock' => 0,
            'unit_id' => $unit->id,
            'item_type' => 'standard',
            'list_type' => 'retail',
            'family_code' => 'firehose',
        ]);

        Item::create([
            'name' => 'Item 3',
            'sku' => 'ITM-BACKFILL-003',
            'price' => 1000,
            'stock' => 0,
            'unit_id' => $unit->id,
            'item_type' => 'standard',
            'list_type' => 'retail',
            'family_code' => 'TSHIRT',
        ]);

        $migration = require base_path('database/migrations/2026_03_12_000200_create_family_codes_table.php');
        $migration->up();

        $this->assertDatabaseHas('family_codes', ['code' => 'FIREHOSE']);
        $this->assertDatabaseHas('family_codes', ['code' => 'TSHIRT']);
        $this->assertSame(2, DB::table('family_codes')->count());
    }
}

