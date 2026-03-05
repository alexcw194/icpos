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

    public function test_admin_can_view_lead_queue_page(): void
    {
        $admin = $this->makeUserWithRole('Admin');
        $prospect = Prospect::query()->create([
            'place_id' => 'queue-place-001',
            'name' => 'Queue Prospect',
            'discovered_at' => now(),
            'status' => Prospect::STATUS_NEW,
        ]);

        ProspectAnalysis::query()->create([
            'prospect_id' => $prospect->id,
            'requested_by_user_id' => $admin->id,
            'status' => ProspectAnalysis::STATUS_RUNNING,
            'started_at' => now(),
        ]);

        LdScanRun::query()->create([
            'started_at' => now(),
            'status' => LdScanRun::STATUS_RUNNING,
            'mode' => LdScanRun::MODE_MANUAL,
            'created_by_user_id' => $admin->id,
        ]);

        $this->actingAs($admin)
            ->get(route('lead-discovery.queue.index'))
            ->assertOk()
            ->assertSeeText('Lead Queue')
            ->assertSeeText('Apollo Enrichment Queue')
            ->assertSeeText('Queue Prospect');
    }

    public function test_user_without_allowed_role_gets_403(): void
    {
        $sales = $this->makeUserWithRole('Sales');

        $this->actingAs($sales)
            ->get(route('lead-discovery.queue.index'))
            ->assertStatus(403);
    }

    public function test_cleanup_stuck_marks_old_queued_analysis_as_failed(): void
    {
        $admin = $this->makeUserWithRole('Admin');
        $prospect = Prospect::query()->create([
            'place_id' => 'queue-place-002',
            'name' => 'Queue Stuck Prospect',
            'discovered_at' => now(),
            'status' => Prospect::STATUS_NEW,
        ]);

        $analysis = ProspectAnalysis::query()->create([
            'prospect_id' => $prospect->id,
            'requested_by_user_id' => $admin->id,
            'status' => ProspectAnalysis::STATUS_QUEUED,
            'created_at' => now()->subMinutes(30),
            'updated_at' => now()->subMinutes(30),
        ]);

        $this->actingAs($admin)
            ->post(route('lead-discovery.queue.cleanup-stuck'))
            ->assertRedirect();

        $analysis->refresh();
        $this->assertSame(ProspectAnalysis::STATUS_FAILED, $analysis->status);
        $this->assertNotNull($analysis->finished_at);
    }
}
