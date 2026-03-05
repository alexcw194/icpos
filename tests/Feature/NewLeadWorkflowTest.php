<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Jenis;
use App\Models\Prospect;
use App\Models\ProspectAssignmentLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class NewLeadWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private function makeUserWithRole(string $roleName): User
    {
        $role = Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web']);
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    private function makeJenis(): Jenis
    {
        return Jenis::query()->create([
            'name' => 'Hotel',
            'slug' => 'hotel',
            'is_active' => true,
        ]);
    }

    private function makeProspect(array $override = []): Prospect
    {
        return Prospect::query()->create(array_merge([
            'place_id' => 'place-' . uniqid(),
            'name' => 'Prospect New Lead',
            'formatted_address' => 'Surabaya',
            'city' => 'Surabaya',
            'province' => 'Jawa Timur',
            'country' => 'Indonesia',
            'discovered_at' => now(),
            'status' => Prospect::STATUS_NEW,
        ], $override));
    }

    public function test_sales_cannot_access_discovery_but_can_access_own_new_leads(): void
    {
        $sales = $this->makeUserWithRole('Sales');
        $otherSales = $this->makeUserWithRole('Sales');

        $ownLead = $this->makeProspect([
            'name' => 'Lead Own Sales',
            'status' => Prospect::STATUS_ASSIGNED,
            'owner_user_id' => $sales->id,
            'assigned_at' => now(),
        ]);
        $otherLead = $this->makeProspect([
            'name' => 'Lead Other Sales',
            'status' => Prospect::STATUS_ASSIGNED,
            'owner_user_id' => $otherSales->id,
            'assigned_at' => now(),
        ]);

        $this->actingAs($sales)
            ->get(route('lead-discovery.prospects.index'))
            ->assertStatus(403);

        $this->actingAs($sales)
            ->get(route('lead-discovery.queue.index'))
            ->assertStatus(403);

        $this->actingAs($sales)
            ->get(route('crm.new-leads.index'))
            ->assertOk()
            ->assertSee($ownLead->name)
            ->assertDontSee($otherLead->name);

        $this->actingAs($sales)
            ->get(route('crm.new-leads.show', $otherLead))
            ->assertStatus(403);
    }

    public function test_admin_assign_from_discovery_sets_assigned_fields_and_log(): void
    {
        $admin = $this->makeUserWithRole('Admin');
        $sales = $this->makeUserWithRole('Sales');
        $prospect = $this->makeProspect();

        $this->actingAs($admin)
            ->post(route('lead-discovery.prospects.assign', $prospect), [
                'owner_user_id' => $sales->id,
            ])
            ->assertRedirect();

        $prospect->refresh();
        $this->assertSame(Prospect::STATUS_ASSIGNED, $prospect->status);
        $this->assertSame((int) $sales->id, (int) $prospect->owner_user_id);
        $this->assertSame((int) $admin->id, (int) $prospect->assigned_by_user_id);
        $this->assertNotNull($prospect->assigned_at);

        $this->assertDatabaseHas('prospect_assignment_logs', [
            'prospect_id' => $prospect->id,
            'action' => ProspectAssignmentLog::ACTION_ASSIGNED,
            'to_user_id' => $sales->id,
            'acted_by_user_id' => $admin->id,
        ]);
    }

    public function test_sales_can_reject_own_assigned_lead(): void
    {
        $sales = $this->makeUserWithRole('Sales');
        $prospect = $this->makeProspect([
            'status' => Prospect::STATUS_ASSIGNED,
            'owner_user_id' => $sales->id,
            'assigned_at' => now(),
        ]);

        $this->actingAs($sales)
            ->post(route('crm.new-leads.reject', $prospect), [
                'reason' => 'No response from lead',
            ])
            ->assertRedirect();

        $prospect->refresh();
        $this->assertSame(Prospect::STATUS_REJECTED, $prospect->status);
        $this->assertSame((int) $sales->id, (int) $prospect->rejected_by_user_id);
        $this->assertSame('No response from lead', $prospect->reject_reason);

        $this->assertDatabaseHas('prospect_assignment_logs', [
            'prospect_id' => $prospect->id,
            'action' => ProspectAssignmentLog::ACTION_REJECTED,
            'from_user_id' => $sales->id,
            'acted_by_user_id' => $sales->id,
        ]);
    }

    public function test_admin_can_reassign_rejected_and_previous_sales_loses_access(): void
    {
        $admin = $this->makeUserWithRole('Admin');
        $salesA = $this->makeUserWithRole('Sales');
        $salesB = $this->makeUserWithRole('Sales');

        $prospect = $this->makeProspect([
            'status' => Prospect::STATUS_REJECTED,
            'owner_user_id' => $salesA->id,
            'assigned_at' => now()->subDay(),
            'rejected_at' => now()->subHour(),
            'rejected_by_user_id' => $salesA->id,
            'reject_reason' => 'Rejected by Sales A',
        ]);

        $this->actingAs($admin)
            ->post(route('crm.new-leads.reassign', $prospect), [
                'owner_user_id' => $salesB->id,
            ])
            ->assertRedirect();

        $prospect->refresh();
        $this->assertSame(Prospect::STATUS_ASSIGNED, $prospect->status);
        $this->assertSame((int) $salesB->id, (int) $prospect->owner_user_id);
        $this->assertNull($prospect->rejected_at);
        $this->assertNull($prospect->rejected_by_user_id);
        $this->assertNull($prospect->reject_reason);

        $this->assertDatabaseHas('prospect_assignment_logs', [
            'prospect_id' => $prospect->id,
            'action' => ProspectAssignmentLog::ACTION_REASSIGNED,
            'from_user_id' => $salesA->id,
            'to_user_id' => $salesB->id,
            'acted_by_user_id' => $admin->id,
        ]);

        $this->actingAs($salesA)
            ->get(route('crm.new-leads.show', $prospect))
            ->assertStatus(403);

        $this->actingAs($salesB)
            ->get(route('crm.new-leads.show', $prospect))
            ->assertOk();
    }

    public function test_sales_add_as_customer_forces_sales_owner_to_actor(): void
    {
        $sales = $this->makeUserWithRole('Sales');
        $otherSales = $this->makeUserWithRole('Sales');
        $jenis = $this->makeJenis();

        $prospect = $this->makeProspect([
            'name' => 'pt diana tina ayu',
            'phone' => '0811111111',
            'status' => Prospect::STATUS_ASSIGNED,
            'owner_user_id' => $sales->id,
            'assigned_at' => now(),
        ]);

        $this->actingAs($sales)
            ->post(route('crm.new-leads.add-customer', $prospect), [
                'jenis_id' => $jenis->id,
                'sales_user_id' => $otherSales->id,
            ])
            ->assertRedirect(route('crm.new-leads.show', $prospect));

        $prospect->refresh();
        $customer = Customer::query()->findOrFail($prospect->converted_customer_id);

        $this->assertSame(Prospect::STATUS_CONVERTED, $prospect->status);
        $this->assertSame((int) $sales->id, (int) $prospect->owner_user_id);
        $this->assertSame((int) $sales->id, (int) $customer->sales_user_id);

        $this->assertDatabaseHas('prospect_assignment_logs', [
            'prospect_id' => $prospect->id,
            'action' => ProspectAssignmentLog::ACTION_CONVERTED,
            'to_user_id' => $sales->id,
            'acted_by_user_id' => $sales->id,
        ]);
    }
}
