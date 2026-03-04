<?php

namespace Tests\Feature;

use App\Models\LdGridCell;
use App\Models\Prospect;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class LeadDiscoveryConfigLocationOptionsTest extends TestCase
{
    use RefreshDatabase;

    private function makeUserWithRole(string $roleName): User
    {
        $role = Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web']);
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    public function test_config_page_provides_grid_location_options_from_grid_cells_and_prospects(): void
    {
        $admin = $this->makeUserWithRole('Admin');

        LdGridCell::query()->create([
            'name' => 'Surabaya-01',
            'center_lat' => -7.2574719,
            'center_lng' => 112.7520883,
            'radius_m' => 12000,
            'city' => 'Surabaya',
            'province' => 'Jawa Timur',
            'is_active' => true,
        ]);

        Prospect::query()->create([
            'place_id' => 'place-config-bali',
            'name' => 'Prospect Bali',
            'city' => 'Denpasar',
            'province' => 'Bali',
            'discovered_at' => now(),
            'status' => Prospect::STATUS_NEW,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.lead-discovery.config', ['tab' => 'cells']))
            ->assertOk()
            ->assertViewHas('gridProvinceOptions', function ($options) {
                return in_array('Jawa Timur', $options, true)
                    && in_array('Bali', $options, true);
            })
            ->assertViewHas('gridCityOptionsAll', function ($options) {
                return in_array('Surabaya', $options, true)
                    && in_array('Denpasar', $options, true);
            })
            ->assertViewHas('gridCityOptionsByProvince', function ($map) {
                return isset($map['Jawa Timur'], $map['Bali'])
                    && in_array('Surabaya', $map['Jawa Timur'], true)
                    && in_array('Denpasar', $map['Bali'], true);
            });
    }
}
