<?php

namespace Tests\Feature;

use App\Models\Prospect;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class LeadDiscoveryProspectWebsiteUpdateTest extends TestCase
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
            'name' => 'Prospect Website',
            'discovered_at' => now(),
            'status' => Prospect::STATUS_NEW,
        ], $override));
    }

    public function test_admin_can_update_website_and_it_is_normalized(): void
    {
        $admin = $this->makeUserWithRole('Admin');
        $prospect = $this->makeProspect();

        $this->actingAs($admin)
            ->post(route('lead-discovery.prospects.website.update', $prospect), [
                'website' => 'Example.COM/path/',
            ])
            ->assertRedirect();

        $prospect->refresh();
        $this->assertSame('https://example.com/path', $prospect->website);
    }

    public function test_admin_can_clear_website(): void
    {
        $admin = $this->makeUserWithRole('Admin');
        $prospect = $this->makeProspect([
            'website' => 'https://example.com',
        ]);

        $this->actingAs($admin)
            ->post(route('lead-discovery.prospects.website.update', $prospect), [
                'website' => '',
            ])
            ->assertRedirect();

        $prospect->refresh();
        $this->assertNull($prospect->website);
    }

    public function test_invalid_website_is_rejected(): void
    {
        $admin = $this->makeUserWithRole('Admin');
        $prospect = $this->makeProspect();

        $this->actingAs($admin)
            ->from(route('lead-discovery.prospects.show', $prospect))
            ->post(route('lead-discovery.prospects.website.update', $prospect), [
                'website' => '://not-valid',
            ])
            ->assertRedirect(route('lead-discovery.prospects.show', $prospect))
            ->assertSessionHasErrors('website');
    }

    public function test_sales_cannot_update_website(): void
    {
        $sales = $this->makeUserWithRole('Sales');
        $prospect = $this->makeProspect();

        $this->actingAs($sales)
            ->post(route('lead-discovery.prospects.website.update', $prospect), [
                'website' => 'https://example.com',
            ])
            ->assertStatus(403);
    }
}
