<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class LeadDiscoverySidebarTest extends TestCase
{
    use RefreshDatabase;

    public function test_sidebar_contains_crm_group_and_default_group_state_script(): void
    {
        $role = Role::firstOrCreate(['name' => 'Sales', 'guard_name' => 'web']);
        $user = User::factory()->create();
        $user->assignRole($role);

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk();
        $response->assertSee('CRM', false);
        $response->assertSee('Lead Discovery', false);
        $response->assertSee('data-group-key="sales"', false);
        $response->assertSee('data-group-key="crm"', false);
        $response->assertSee('sales: false', false);
        $response->assertSee('crm: true', false);
    }
}
