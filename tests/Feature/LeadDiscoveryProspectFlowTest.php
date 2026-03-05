<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Jenis;
use App\Models\LdGridCell;
use App\Models\Prospect;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class LeadDiscoveryProspectFlowTest extends TestCase
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
        return Jenis::create([
            'name' => 'Hotel',
            'slug' => 'hotel',
            'is_active' => true,
        ]);
    }

    private function makeProspect(array $override = []): Prospect
    {
        return Prospect::create(array_merge([
            'place_id' => 'place-' . uniqid(),
            'name' => 'Prospect Company',
            'formatted_address' => 'Surabaya',
            'city' => 'Surabaya',
            'province' => 'Jawa Timur',
            'country' => 'Indonesia',
            'discovered_at' => now(),
            'status' => Prospect::STATUS_NEW,
        ], $override));
    }

    public function test_only_admin_and_superadmin_can_access_prospects_index(): void
    {
        foreach (['Admin', 'SuperAdmin'] as $roleName) {
            $user = $this->makeUserWithRole($roleName);
            $this->actingAs($user)
                ->get(route('lead-discovery.prospects.index'))
                ->assertOk();
            auth()->logout();
        }

        foreach (['Sales', 'Finance'] as $roleName) {
            $user = $this->makeUserWithRole($roleName);
            $this->actingAs($user)
                ->get(route('lead-discovery.prospects.index'))
                ->assertStatus(403);
            auth()->logout();
        }
    }

    public function test_user_without_authorized_role_gets_403_on_prospects_index(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)
            ->get(route('lead-discovery.prospects.index'))
            ->assertStatus(403);
    }

    public function test_prospects_index_excludes_ignored_by_default(): void
    {
        $admin = $this->makeUserWithRole('Admin');
        $active = $this->makeProspect(['name' => 'Prospect Active', 'status' => Prospect::STATUS_NEW]);
        $ignored = $this->makeProspect(['name' => 'Prospect Ignored', 'status' => Prospect::STATUS_IGNORED]);

        $this->actingAs($admin)
            ->get(route('lead-discovery.prospects.index'))
            ->assertOk()
            ->assertSee($active->name)
            ->assertDontSee($ignored->name);
    }

    public function test_prospects_index_can_show_only_ignored_when_status_filter_ignored(): void
    {
        $admin = $this->makeUserWithRole('Admin');
        $active = $this->makeProspect(['name' => 'Prospect Active', 'status' => Prospect::STATUS_NEW]);
        $ignored = $this->makeProspect(['name' => 'Prospect Ignored', 'status' => Prospect::STATUS_IGNORED]);

        $this->actingAs($admin)
            ->get(route('lead-discovery.prospects.index', ['status' => Prospect::STATUS_IGNORED]))
            ->assertOk()
            ->assertSee($ignored->name)
            ->assertDontSee($active->name);
    }

    public function test_admin_only_config_route_returns_403_for_sales(): void
    {
        $sales = $this->makeUserWithRole('Sales');

        $this->actingAs($sales)
            ->get(route('admin.lead-discovery.config'))
            ->assertStatus(403);
    }

    public function test_prospects_index_provides_distinct_province_and_city_options(): void
    {
        $admin = $this->makeUserWithRole('Admin');

        $this->makeProspect([
            'name' => 'Prospect A',
            'province' => 'Jawa Timur',
            'city' => 'Surabaya',
        ]);
        $this->makeProspect([
            'name' => 'Prospect B',
            'province' => 'Bali',
            'city' => 'Denpasar',
        ]);
        $this->makeProspect([
            'name' => 'Prospect C',
            'province' => null,
            'city' => null,
        ]);

        $this->actingAs($admin)
            ->get(route('lead-discovery.prospects.index'))
            ->assertOk()
            ->assertViewHas('provinceOptions', function ($options) {
                return in_array('Jawa Timur', $options, true)
                    && in_array('Bali', $options, true);
            })
            ->assertViewHas('cityOptions', function ($options) {
                return in_array('Surabaya', $options, true)
                    && in_array('Denpasar', $options, true);
            });
    }

    public function test_prospects_city_options_follow_selected_province(): void
    {
        $admin = $this->makeUserWithRole('Admin');

        $this->makeProspect([
            'name' => 'Prospect Jatim 1',
            'province' => 'Jawa Timur',
            'city' => 'Surabaya',
        ]);
        $this->makeProspect([
            'name' => 'Prospect Jatim 2',
            'province' => 'Jawa Timur',
            'city' => 'Malang',
        ]);
        $this->makeProspect([
            'name' => 'Prospect Bali',
            'province' => 'Bali',
            'city' => 'Denpasar',
        ]);

        $this->actingAs($admin)
            ->get(route('lead-discovery.prospects.index', [
                'province' => 'Jawa Timur',
                'city' => 'Denpasar',
            ]))
            ->assertOk()
            ->assertViewHas('cityOptions', function ($options) {
                return in_array('Surabaya', $options, true)
                    && in_array('Malang', $options, true)
                    && !in_array('Denpasar', $options, true);
            })
            ->assertViewHas('selectedCity', '');
    }

    public function test_prospects_filter_uses_grid_cell_fallback_when_prospect_location_is_empty(): void
    {
        $admin = $this->makeUserWithRole('Admin');
        $cell = LdGridCell::query()->create([
            'name' => 'Bali-Cell',
            'center_lat' => -8.65,
            'center_lng' => 115.21,
            'radius_m' => 12000,
            'city' => 'Denpasar',
            'province' => 'Bali',
            'is_active' => true,
        ]);

        $prospect = $this->makeProspect([
            'name' => 'Prospect No Location',
            'city' => null,
            'province' => null,
            'grid_cell_id' => $cell->id,
        ]);

        $this->actingAs($admin)
            ->get(route('lead-discovery.prospects.index', [
                'province' => 'Bali',
                'city' => 'Denpasar',
            ]))
            ->assertOk()
            ->assertSee($prospect->name);
    }

    public function test_prospects_location_options_include_grid_cell_values_when_prospect_values_missing(): void
    {
        $admin = $this->makeUserWithRole('Admin');

        LdGridCell::query()->create([
            'name' => 'Cell-Only-Location',
            'center_lat' => -8.65,
            'center_lng' => 115.21,
            'radius_m' => 12000,
            'city' => 'Badung',
            'province' => 'Bali',
            'is_active' => true,
        ]);

        $this->makeProspect([
            'name' => 'Prospect No Location',
            'city' => null,
            'province' => null,
        ]);

        $this->actingAs($admin)
            ->get(route('lead-discovery.prospects.index'))
            ->assertOk()
            ->assertViewHas('provinceOptions', fn ($options) => in_array('Bali', $options, true))
            ->assertViewHas('cityOptions', fn ($options) => in_array('Badung', $options, true));
    }

    public function test_convert_requires_category_and_owner(): void
    {
        $admin = $this->makeUserWithRole('Admin');
        $prospect = $this->makeProspect();

        $response = $this->actingAs($admin)
            ->from(route('lead-discovery.prospects.show', $prospect))
            ->post(route('lead-discovery.prospects.convert', $prospect), []);

        $response->assertRedirect(route('lead-discovery.prospects.show', $prospect));
        $response->assertSessionHasErrors(['jenis_id', 'sales_user_id']);
    }

    public function test_convert_creates_customer_contact_and_marks_prospect_converted(): void
    {
        $admin = $this->makeUserWithRole('Admin');
        $sales = $this->makeUserWithRole('Sales');
        $jenis = $this->makeJenis();

        $prospect = $this->makeProspect([
            'name' => 'excelindo mitra sentosa pt',
            'phone' => '08123456789',
            'website' => 'https://example.com',
            'formatted_address' => 'Jl. Raya Surabaya',
        ]);

        $this->actingAs($admin)
            ->post(route('lead-discovery.prospects.convert', $prospect), [
                'jenis_id' => $jenis->id,
                'sales_user_id' => $sales->id,
            ])
            ->assertRedirect(route('lead-discovery.prospects.show', $prospect));

        $this->assertDatabaseCount('customers', 1);
        $customer = Customer::query()->first();
        $this->assertNotNull($customer);
        $this->assertSame('Excelindo Mitra Sentosa PT', $customer->name);
        $this->assertSame($jenis->id, (int) $customer->jenis_id);
        $this->assertSame($sales->id, (int) $customer->sales_user_id);
        $this->assertStringContainsString((string) $prospect->place_id, (string) $customer->notes);

        $prospect->refresh();
        $this->assertSame(Prospect::STATUS_CONVERTED, $prospect->status);
        $this->assertNotNull($prospect->converted_customer_id);

        $this->assertDatabaseHas('contacts', [
            'customer_id' => $customer->id,
            'first_name' => 'General',
            'position' => 'Frontdesk/General',
            'phone' => '08123456789',
        ]);
    }

    public function test_convert_reuses_existing_customer_if_name_key_matches(): void
    {
        $admin = $this->makeUserWithRole('Admin');
        $sales = $this->makeUserWithRole('Sales');
        $jenis = $this->makeJenis();

        $existing = Customer::create([
            'name' => 'PT Excelindo Mitra Sentosa',
            'jenis_id' => $jenis->id,
            'sales_user_id' => $sales->id,
        ]);

        $prospect = $this->makeProspect([
            'name' => 'excelindo mitra sentosa',
            'phone' => null,
        ]);

        $this->actingAs($admin)
            ->post(route('lead-discovery.prospects.convert', $prospect), [
                'jenis_id' => $jenis->id,
                'sales_user_id' => $sales->id,
            ])
            ->assertRedirect(route('lead-discovery.prospects.show', $prospect));

        $this->assertDatabaseCount('customers', 1);
        $prospect->refresh();
        $this->assertSame($existing->id, (int) $prospect->converted_customer_id);
    }
}
