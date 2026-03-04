<?php

namespace Tests\Feature;

use App\Models\Prospect;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LeadDiscoveryCleanupRetailCommandTest extends TestCase
{
    use RefreshDatabase;

    private function makeProspect(array $override = []): Prospect
    {
        return Prospect::query()->create(array_merge([
            'place_id' => 'place-' . uniqid(),
            'name' => 'Prospect Sample',
            'formatted_address' => 'Surabaya',
            'city' => 'Surabaya',
            'province' => 'Jawa Timur',
            'country' => 'Indonesia',
            'discovered_at' => now(),
            'status' => Prospect::STATUS_NEW,
            'primary_type' => 'point_of_interest',
            'types_json' => ['point_of_interest'],
        ], $override));
    }

    public function test_cleanup_retail_marks_retail_candidates_ignored_and_skips_converted(): void
    {
        $retail = $this->makeProspect([
            'name' => 'Store Level 2 Mall',
            'primary_type' => 'store',
            'types_json' => ['store'],
            'status' => Prospect::STATUS_NEW,
        ]);

        $mall = $this->makeProspect([
            'name' => 'Grand Shopping Mall',
            'primary_type' => 'shopping_mall',
            'types_json' => ['shopping_mall'],
            'status' => Prospect::STATUS_NEW,
        ]);

        $convertedRetail = $this->makeProspect([
            'name' => 'Converted Store',
            'primary_type' => 'store',
            'types_json' => ['store'],
            'status' => Prospect::STATUS_CONVERTED,
        ]);

        $this->artisan('lead-discovery:cleanup-retail')
            ->assertExitCode(0);

        $retail->refresh();
        $mall->refresh();
        $convertedRetail->refresh();

        $this->assertSame(Prospect::STATUS_IGNORED, $retail->status);
        $this->assertSame(Prospect::STATUS_NEW, $mall->status);
        $this->assertSame(Prospect::STATUS_CONVERTED, $convertedRetail->status);
    }

    public function test_cleanup_retail_dry_run_does_not_change_data(): void
    {
        $retail = $this->makeProspect([
            'name' => 'Retail Unit 10',
            'formatted_address' => 'Unit 10 Mall A',
            'primary_type' => 'clothing_store',
            'types_json' => ['clothing_store', 'store'],
            'status' => Prospect::STATUS_NEW,
        ]);

        $this->artisan('lead-discovery:cleanup-retail', ['--dry-run' => true])
            ->assertExitCode(0);

        $retail->refresh();
        $this->assertSame(Prospect::STATUS_NEW, $retail->status);
    }
}
