<?php

namespace Tests\Feature;

use App\Jobs\LeadDiscovery\EnrichProspectApolloJob;
use App\Models\Prospect;
use App\Models\ProspectApolloEnrichment;
use App\Services\LeadDiscovery\ApolloEnrichmentService;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Config;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class LeadDiscoveryApolloEnrichmentTest extends TestCase
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
            'name' => 'Prospect Apollo',
            'discovered_at' => now(),
            'status' => Prospect::STATUS_NEW,
        ], $override));
    }

    public function test_admin_can_trigger_apollo_enrichment_and_queue_job(): void
    {
        Bus::fake();
        $admin = $this->makeUserWithRole('Admin');
        $prospect = $this->makeProspect();

        $this->actingAs($admin)
            ->from(route('lead-discovery.prospects.show', $prospect))
            ->post(route('lead-discovery.prospects.enrich-apollo', $prospect))
            ->assertRedirect(route('lead-discovery.prospects.show', $prospect));

        $this->assertDatabaseHas('prospect_apollo_enrichments', [
            'prospect_id' => $prospect->id,
            'status' => ProspectApolloEnrichment::STATUS_QUEUED,
            'requested_by_user_id' => $admin->id,
        ]);

        Bus::assertDispatched(EnrichProspectApolloJob::class);
    }

    public function test_enrichment_trigger_blocks_when_active_run_exists(): void
    {
        $admin = $this->makeUserWithRole('Admin');
        $prospect = $this->makeProspect();
        ProspectApolloEnrichment::query()->create([
            'prospect_id' => $prospect->id,
            'requested_by_user_id' => $admin->id,
            'status' => ProspectApolloEnrichment::STATUS_RUNNING,
        ]);

        $this->actingAs($admin)
            ->from(route('lead-discovery.prospects.show', $prospect))
            ->post(route('lead-discovery.prospects.enrich-apollo', $prospect))
            ->assertRedirect(route('lead-discovery.prospects.show', $prospect))
            ->assertSessionHas('warning');

        $this->assertSame(1, ProspectApolloEnrichment::query()->count());
    }

    public function test_sales_cannot_trigger_apollo_enrichment(): void
    {
        $sales = $this->makeUserWithRole('Sales');
        $prospect = $this->makeProspect();

        $this->actingAs($sales)
            ->post(route('lead-discovery.prospects.enrich-apollo', $prospect))
            ->assertStatus(403);
    }

    public function test_job_marks_failed_when_apollo_key_missing(): void
    {
        Config::set('services.apollo.key', '');

        $admin = $this->makeUserWithRole('Admin');
        $prospect = $this->makeProspect();
        $enrichment = ProspectApolloEnrichment::query()->create([
            'prospect_id' => $prospect->id,
            'requested_by_user_id' => $admin->id,
            'status' => ProspectApolloEnrichment::STATUS_QUEUED,
        ]);

        $job = new EnrichProspectApolloJob($enrichment->id);
        $job->handle(app(ApolloEnrichmentService::class));

        $enrichment->refresh();
        $this->assertSame(ProspectApolloEnrichment::STATUS_FAILED, $enrichment->status);
        $this->assertNotNull($enrichment->error_message);
    }
}
