<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class LeadDiscoverySidebarTest extends TestCase
{
    use RefreshDatabase;

    public function test_sidebar_shows_new_leads_for_sales_and_hides_admin_lead_menu(): void
    {
        $role = Role::firstOrCreate(['name' => 'Sales', 'guard_name' => 'web']);
        $user = User::factory()->create();
        $user->assignRole($role);

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk();
        $response->assertSee('CRM', false);
        $response->assertSee('New Leads', false);
        $response->assertDontSee('Lead Discovery', false);
        $response->assertDontSee('Lead Queue', false);
        $response->assertSee('data-group-key="sales"', false);
        $response->assertSee('data-group-key="crm"', false);
        $response->assertSee('id="sidebar-accordion"', false);
        $response->assertSee("defaultKey = activeGroup", false);
        $response->assertSee("'sales'", false);
    }

    public function test_sidebar_shows_lead_discovery_and_queue_for_admin(): void
    {
        $role = Role::firstOrCreate(['name' => 'Admin', 'guard_name' => 'web']);
        $user = User::factory()->create();
        $user->assignRole($role);

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk();
        $response->assertSee('CRM', false);
        $response->assertSee('Lead Discovery', false);
        $response->assertSee('Lead Queue', false);
        $response->assertSee('New Leads', false);
    }
}
