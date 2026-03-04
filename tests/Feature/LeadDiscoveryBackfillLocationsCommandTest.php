<?php

namespace Tests\Feature;

use App\Models\LdGridCell;
use App\Models\Prospect;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LeadDiscoveryBackfillLocationsCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_backfill_locations_updates_missing_city_and_province_from_grid_cell(): void
    {
        $cell = LdGridCell::query()->create([
            'name' => 'Surabaya-Cell',
            'center_lat' => -7.2574,
            'center_lng' => 112.7520,
            'radius_m' => 12000,
            'city' => 'Surabaya',
            'province' => 'Jawa Timur',
            'is_active' => true,
        ]);

        $prospect = Prospect::query()->create([
            'place_id' => 'place-backfill-1',
            'name' => 'Prospect Backfill',
            'grid_cell_id' => $cell->id,
            'city' => null,
            'province' => null,
            'discovered_at' => now(),
            'status' => Prospect::STATUS_NEW,
        ]);

        $this->artisan('lead-discovery:backfill-locations')
            ->assertExitCode(0);

        $prospect->refresh();
        $this->assertSame('Surabaya', $prospect->city);
        $this->assertSame('Jawa Timur', $prospect->province);
    }

    public function test_backfill_locations_dry_run_does_not_change_data(): void
    {
        $cell = LdGridCell::query()->create([
            'name' => 'Bali-Cell',
            'center_lat' => -8.6500,
            'center_lng' => 115.2167,
            'radius_m' => 12000,
            'city' => 'Denpasar',
            'province' => 'Bali',
            'is_active' => true,
        ]);

        $prospect = Prospect::query()->create([
            'place_id' => 'place-backfill-2',
            'name' => 'Prospect Dry Run',
            'grid_cell_id' => $cell->id,
            'city' => null,
            'province' => null,
            'discovered_at' => now(),
            'status' => Prospect::STATUS_NEW,
        ]);

        $this->artisan('lead-discovery:backfill-locations', ['--dry-run' => true])
            ->assertExitCode(0);

        $prospect->refresh();
        $this->assertNull($prospect->city);
        $this->assertNull($prospect->province);
    }
}
