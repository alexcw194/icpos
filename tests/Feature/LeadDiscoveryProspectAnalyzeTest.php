<?php

namespace Tests\Feature;

use App\Models\Prospect;
use App\Models\ProspectAnalysis;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class LeadDiscoveryProspectAnalyzeTest extends TestCase
{
    use RefreshDatabase;

    private function makeUserWithRole(string $roleName): User
    {
        $role = Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web']);
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    private function makeProspect(array $override = []): Prospect
    {
        return Prospect::query()->create(array_merge([
            'place_id' => 'place-' . uniqid(),
            'name' => 'Prospect Analyze',
            'formatted_address' => 'Surabaya',
            'city' => 'Surabaya',
            'province' => 'Jawa Timur',
            'country' => 'Indonesia',
            'discovered_at' => now(),
            'status' => Prospect::STATUS_NEW,
        ], $override));
    }

    public function test_authorized_user_can_trigger_analyze_and_process_immediately(): void
    {
        $sales = $this->makeUserWithRole('Sales');
        $prospect = $this->makeProspect();

        $this->actingAs($sales)
            ->from(route('lead-discovery.prospects.show', $prospect))
            ->post(route('lead-discovery.prospects.analyze', $prospect))
            ->assertRedirect(route('lead-discovery.prospects.show', $prospect));

        $this->assertDatabaseHas('prospect_analyses', [
            'prospect_id' => $prospect->id,
            'status' => ProspectAnalysis::STATUS_SUCCESS,
            'requested_by_user_id' => $sales->id,
        ]);
    }

    public function test_user_without_role_gets_403_on_analyze_trigger(): void
    {
        $user = User::factory()->create();
        $prospect = $this->makeProspect();

        $this->actingAs($user)
            ->post(route('lead-discovery.prospects.analyze', $prospect))
            ->assertStatus(403);
    }

    public function test_analyze_trigger_skips_when_active_run_exists(): void
    {
        $admin = $this->makeUserWithRole('Admin');
        $prospect = $this->makeProspect();
        ProspectAnalysis::query()->create([
            'prospect_id' => $prospect->id,
            'status' => ProspectAnalysis::STATUS_RUNNING,
            'requested_by_user_id' => $admin->id,
        ]);

        $this->actingAs($admin)
            ->from(route('lead-discovery.prospects.show', $prospect))
            ->post(route('lead-discovery.prospects.analyze', $prospect))
            ->assertRedirect(route('lead-discovery.prospects.show', $prospect))
            ->assertSessionHas('warning');

        $this->assertSame(1, ProspectAnalysis::query()->count());
    }
}
