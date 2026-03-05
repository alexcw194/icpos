<?php

namespace Tests\Feature;

use App\Models\LdScanRun;
use App\Models\Prospect;
use App\Models\ProspectAnalysis;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class LeadDiscoveryQueuePageTest extends TestCase
{
    use RefreshDatabase;

    private function makeUserWithRole(string $roleName): User
    {
        $role = Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web']);
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    public function test_authorized_user_can_view_lead_queue_page(): void
    {
        $sales = $this->makeUserWithRole('Sales');
        $prospect = Prospect::query()->create([
            'place_id' => 'queue-place-001',
            'name' => 'Queue Prospect',
            'discovered_at' => now(),
            'status' => Prospect::STATUS_NEW,
        ]);

        ProspectAnalysis::query()->create([
            'prospect_id' => $prospect->id,
            'requested_by_user_id' => $sales->id,
            'status' => ProspectAnalysis::STATUS_RUNNING,
            'started_at' => now(),
        ]);

        LdScanRun::query()->create([
            'started_at' => now(),
            'status' => LdScanRun::STATUS_RUNNING,
            'mode' => LdScanRun::MODE_MANUAL,
            'created_by_user_id' => $sales->id,
        ]);

        $this->actingAs($sales)
            ->get(route('lead-discovery.queue.index'))
            ->assertOk()
            ->assertSeeText('Lead Queue')
            ->assertSeeText('Queue Prospect');
    }

    public function test_user_without_allowed_role_gets_403(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('lead-discovery.queue.index'))
            ->assertStatus(403);
    }
}

