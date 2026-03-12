<?php

namespace Tests\Feature;

use App\Models\FamilyCode;
use App\Models\Item;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class ItemBulkFamilyCodeUpdateTest extends TestCase
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

    private function makeItem(string $name, string $listType = 'retail', ?string $familyCode = null): Item
    {
        return Item::create([
            'name' => $name,
            'sku' => 'ITM-BULK-' . strtoupper(substr(md5($name . microtime(true)), 0, 8)),
            'price' => 1000,
            'stock' => 0,
            'list_type' => $listType,
            'family_code' => $familyCode,
        ]);
    }

    public function test_non_admin_cannot_bulk_update_family_code(): void
    {
        $user = $this->makeUserWithRole();
        $item = $this->makeItem('Retail Item A');
        FamilyCode::create(['code' => 'FIREHOSE']);

        $this->actingAs($user)
            ->post(route('items.bulk-family-code'), [
                'item_ids' => [$item->id],
                'family_code' => 'FIREHOSE',
            ])
            ->assertStatus(403);
    }

    public function test_admin_bulk_update_validates_payload(): void
    {
        $admin = $this->makeUserWithRole('Admin');
        $item = $this->makeItem('Retail Item Validation');
        FamilyCode::create(['code' => 'FIREHOSE']);

        $this->actingAs($admin)
            ->from(route('items.index'))
            ->post(route('items.bulk-family-code'), [
                'item_ids' => [],
                'family_code' => 'FIREHOSE',
            ])
            ->assertRedirect(route('items.index'))
            ->assertSessionHasErrors(['item_ids']);

        $this->actingAs($admin)
            ->from(route('items.index'))
            ->post(route('items.bulk-family-code'), [
                'item_ids' => [$item->id],
                'family_code' => 'TIDAK-ADA',
            ])
            ->assertRedirect(route('items.index'))
            ->assertSessionHasErrors(['family_code']);

        $this->actingAs($admin)
            ->from(route('items.index'))
            ->post(route('items.bulk-family-code'), [
                'item_ids' => [999999],
                'family_code' => 'FIREHOSE',
            ])
            ->assertRedirect(route('items.index'))
            ->assertSessionHasErrors(['item_ids.0']);
    }

    public function test_admin_bulk_update_sets_and_clears_family_code_for_retail_scope_only(): void
    {
        $admin = $this->makeUserWithRole('Admin');
        FamilyCode::create(['code' => 'FIREHOSE']);

        $retailA = $this->makeItem('Retail Item A', 'retail', null);
        $retailB = $this->makeItem('Retail Item B', 'retail', null);
        $projectA = $this->makeItem('Project Item A', 'project', null);

        $this->actingAs($admin)
            ->post(route('items.bulk-family-code'), [
                'r' => '/items?view=flat&sort=name_asc',
                'item_ids' => [$retailA->id, $retailB->id, $projectA->id, $retailA->id],
                'family_code' => 'FIREHOSE',
            ])
            ->assertRedirect('/items?view=flat&sort=name_asc');

        $this->assertDatabaseHas('items', ['id' => $retailA->id, 'family_code' => 'FIREHOSE']);
        $this->assertDatabaseHas('items', ['id' => $retailB->id, 'family_code' => 'FIREHOSE']);
        $this->assertDatabaseHas('items', ['id' => $projectA->id, 'family_code' => null]);

        $this->actingAs($admin)
            ->post(route('items.bulk-family-code'), [
                'item_ids' => [$retailA->id, $retailB->id],
                'family_code' => '',
            ])
            ->assertRedirect(route('items.index'));

        $this->assertDatabaseHas('items', ['id' => $retailA->id, 'family_code' => null]);
        $this->assertDatabaseHas('items', ['id' => $retailB->id, 'family_code' => null]);
    }

    public function test_project_items_bulk_update_only_touches_project_items(): void
    {
        $admin = $this->makeUserWithRole('SuperAdmin');
        FamilyCode::create(['code' => 'PROJECT']);

        $retail = $this->makeItem('Retail Item Scope', 'retail', null);
        $project = $this->makeItem('Project Item Scope', 'project', null);

        $this->actingAs($admin)
            ->post(route('project-items.bulk-family-code'), [
                'item_ids' => [$retail->id, $project->id],
                'family_code' => 'PROJECT',
            ])
            ->assertRedirect(route('project-items.index'));

        $this->assertDatabaseHas('items', ['id' => $project->id, 'family_code' => 'PROJECT']);
        $this->assertDatabaseHas('items', ['id' => $retail->id, 'family_code' => null]);
    }
}

