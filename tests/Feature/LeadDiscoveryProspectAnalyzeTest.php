<?php

namespace Tests\Feature;

use App\Jobs\LeadDiscovery\AnalyzeProspectJob;
use App\Models\Prospect;
use App\Models\ProspectAnalysis;
use App\Models\User;
use App\Services\LeadDiscovery\ProspectAnalyzerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use RuntimeException;
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

    public function test_authorized_user_can_trigger_analyze_and_dispatch_job(): void
    {
        Bus::fake();
        $sales = $this->makeUserWithRole('Sales');
        $prospect = $this->makeProspect();

        $this->actingAs($sales)
            ->from(route('lead-discovery.prospects.show', $prospect))
            ->post(route('lead-discovery.prospects.analyze', $prospect))
            ->assertRedirect(route('lead-discovery.prospects.show', $prospect));

        $this->assertDatabaseHas('prospect_analyses', [
            'prospect_id' => $prospect->id,
            'status' => ProspectAnalysis::STATUS_QUEUED,
            'requested_by_user_id' => $sales->id,
        ]);
        Bus::assertDispatched(AnalyzeProspectJob::class);
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
        Bus::fake();
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
        Bus::assertNotDispatched(AnalyzeProspectJob::class);
    }

    public function test_job_marks_success_with_low_score_when_website_is_missing(): void
    {
        $prospect = $this->makeProspect([
            'website' => null,
            'formatted_address' => null,
            'short_address' => null,
            'city' => null,
            'province' => null,
        ]);
        $analysis = ProspectAnalysis::query()->create([
            'prospect_id' => $prospect->id,
            'status' => ProspectAnalysis::STATUS_QUEUED,
        ]);

        $job = new AnalyzeProspectJob($analysis->id);
        $job->handle(app(ProspectAnalyzerService::class));

        $analysis->refresh();
        $this->assertSame(ProspectAnalysis::STATUS_SUCCESS, $analysis->status);
        $this->assertSame(0, (int) $analysis->pages_crawled);
        $this->assertFalse((bool) data_get($analysis->checklist_json, 'website_present'));
        $this->assertLessThanOrEqual(35, (int) $analysis->score);
    }

    public function test_job_extracts_linkedin_email_and_business_type_from_website(): void
    {
        $prospect = $this->makeProspect([
            'name' => 'PT Pabrik Gula Maju',
            'website' => 'https://example.com',
            'formatted_address' => 'Jl. Industri No. 1, Surabaya',
            'city' => 'Surabaya',
            'province' => 'Jawa Timur',
        ]);
        $analysis = ProspectAnalysis::query()->create([
            'prospect_id' => $prospect->id,
            'status' => ProspectAnalysis::STATUS_QUEUED,
        ]);

        Http::fake([
            'https://example.com*' => Http::sequence()
                ->push(
                    '<html><body>PT Pabrik Gula Maju info@gulamaju.co.id <a href="https://www.linkedin.com/company/gula-maju">Company</a><a href="/about">About</a></body></html>',
                    200,
                    ['Content-Type' => 'text/html']
                )
                ->push(
                    '<html><body><a href="https://www.linkedin.com/in/joko-prasetyo/">Joko</a> Phone +62 812 3456 7890</body></html>',
                    200,
                    ['Content-Type' => 'text/html']
                ),
        ]);

        $job = new AnalyzeProspectJob($analysis->id);
        $job->handle(app(ProspectAnalyzerService::class));

        $analysis->refresh();
        $this->assertSame(ProspectAnalysis::STATUS_SUCCESS, $analysis->status);
        $this->assertTrue((bool) $analysis->website_reachable);
        $this->assertNotEmpty($analysis->linkedin_company_url);
        $this->assertNotEmpty($analysis->linkedin_people_json);
        $this->assertContains('info@gulamaju.co.id', $analysis->emails_json ?? []);
        $this->assertContains($analysis->business_type, ['food_processing', 'general_manufacturing']);
        $this->assertSame(ProspectAnalysis::ADDRESS_CLEAR, $analysis->address_clarity);
    }

    public function test_job_marks_failed_when_analyzer_throws(): void
    {
        $prospect = $this->makeProspect();
        $analysis = ProspectAnalysis::query()->create([
            'prospect_id' => $prospect->id,
            'status' => ProspectAnalysis::STATUS_QUEUED,
        ]);

        $job = new AnalyzeProspectJob($analysis->id);
        $job->handle(new class extends ProspectAnalyzerService {
            public function analyze(Prospect $prospect): array
            {
                throw new RuntimeException('Analyzer boom');
            }
        });

        $analysis->refresh();
        $this->assertSame(ProspectAnalysis::STATUS_FAILED, $analysis->status);
        $this->assertStringContainsString('Analyzer boom', (string) $analysis->error_message);
    }

    public function test_ssrf_private_host_is_blocked_and_not_crawled(): void
    {
        Http::fake();
        $prospect = $this->makeProspect([
            'website' => 'http://127.0.0.1/internal',
        ]);
        $analysis = ProspectAnalysis::query()->create([
            'prospect_id' => $prospect->id,
            'status' => ProspectAnalysis::STATUS_QUEUED,
        ]);

        $job = new AnalyzeProspectJob($analysis->id);
        $job->handle(app(ProspectAnalyzerService::class));

        $analysis->refresh();
        $this->assertSame(ProspectAnalysis::STATUS_SUCCESS, $analysis->status);
        $this->assertSame(0, (int) $analysis->pages_crawled);
        $this->assertFalse((bool) $analysis->website_reachable);
        Http::assertNothingSent();
    }
}
