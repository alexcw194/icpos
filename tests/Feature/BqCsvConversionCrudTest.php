<?php

namespace Tests\Feature;

use App\Models\Item;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class BqCsvConversionCrudTest extends TestCase
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

    private function makeItem(string $name, string $sku, string $listType = 'retail'): Item
    {
        return Item::create([
            'name' => $name,
            'sku' => $sku,
            'price' => 10000,
            'list_type' => $listType,
        ]);
    }

    public function test_admin_can_create_conversion_and_unique_norm_is_enforced(): void
    {
        $admin = $this->makeUser('Admin');
        $item = $this->makeItem('Pipe Item', 'PIPE-ITEM-01');

        $this->actingAs($admin)->post(route('bq-csv-conversions.store'), [
            'source_category' => 'Pipe',
            'source_item' => 'Pipe MED',
            'mapped_item' => 'Pipe Medium',
            'target_item_id' => $item->id,
            'target_item_variant_id' => null,
            'is_active' => 1,
        ])->assertRedirect(route('bq-csv-conversions.index'));

        $this->assertDatabaseHas('bq_csv_conversions', [
            'source_category_norm' => 'pipe',
            'source_item_norm' => 'pipe med',
            'mapped_item' => 'Pipe Medium',
            'target_source_type' => 'item',
            'target_item_id' => $item->id,
        ]);

        $this->actingAs($admin)->post(route('bq-csv-conversions.store'), [
            'source_category' => '  pipe ',
            'source_item' => ' PIPE   MED ',
            'mapped_item' => 'Another',
            'target_item_id' => $item->id,
            'target_item_variant_id' => null,
            'is_active' => 1,
        ])->assertSessionHasErrors('source_item');
    }

    public function test_non_admin_cannot_access_conversion_master(): void
    {
        $user = $this->makeUser();

        $this->actingAs($user)
            ->get(route('bq-csv-conversions.index'))
            ->assertStatus(403);
    }
}
