<?php

namespace Tests\Feature;

use App\Jobs\LeadDiscovery\ScanCellKeywordJob;
use App\Models\LdGridCell;
use App\Models\LdKeyword;
use App\Models\LdScanRun;
use App\Models\Prospect;
use App\Models\Setting;
use App\Services\LeadDiscovery\PlacesLegacyClient;
use App\Services\LeadDiscovery\ProspectNormalizer;
use App\Services\LeadDiscovery\ProspectResultFilter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class LeadDiscoveryScanCellKeywordJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_scan_job_reads_up_to_three_pages_and_deduplicates_by_place_id(): void
    {
        Setting::setMany([
            'lead_discovery.max_pages_per_query' => '3',
            'lead_discovery.page_token_delay_ms' => '0',
            'lead_discovery.request_timeout_sec' => '20',
            'lead_discovery.retry_max' => '0',
        ]);

        $keyword = LdKeyword::create([
            'keyword' => 'factory',
            'category_label' => 'Manufacturing',
            'is_active' => true,
            'priority' => 10,
        ]);

        $cell = LdGridCell::create([
            'name' => 'Surabaya-01',
            'center_lat' => -7.2574719,
            'center_lng' => 112.7520883,
            'radius_m' => 12000,
            'city' => 'Surabaya',
            'province' => 'Jawa Timur',
            'is_active' => true,
        ]);

        $run = LdScanRun::create([
            'started_at' => now(),
            'status' => LdScanRun::STATUS_RUNNING,
            'mode' => LdScanRun::MODE_MANUAL,
            'totals_json' => [
                'pairs_dispatched' => 1,
            ],
        ]);

        Prospect::create([
            'place_id' => 'place-A',
            'name' => 'Old Name',
            'discovered_at' => now()->subDay(),
            'last_seen_at' => now()->subDay(),
            'status' => Prospect::STATUS_NEW,
        ]);

        Http::fake([
            'maps.googleapis.com/maps/api/place/nearbysearch/json*' => Http::sequence()
                ->push([
                    'status' => 'OK',
                    'results' => [
                        [
                            'place_id' => 'place-A',
                            'name' => 'Company A',
                            'vicinity' => 'Surabaya',
                            'types' => ['point_of_interest'],
                            'geometry' => ['location' => ['lat' => -7.2574, 'lng' => 112.7520]],
                        ],
                    ],
                    'next_page_token' => 'token-2',
                ], 200)
                ->push([
                    'status' => 'OK',
                    'results' => [
                        [
                            'place_id' => 'place-B',
                            'name' => 'Company B',
                            'formatted_address' => 'Malang, Jawa Timur',
                            'types' => ['establishment'],
                            'geometry' => ['location' => ['lat' => -7.9, 'lng' => 112.6]],
                        ],
                    ],
                    'next_page_token' => 'token-3',
                ], 200)
                ->push([
                    'status' => 'ZERO_RESULTS',
                    'results' => [],
                ], 200),
        ]);

        $job = new ScanCellKeywordJob($run->id, $cell->id, $keyword->id);
        $job->handle(
            app(PlacesLegacyClient::class),
            app(ProspectNormalizer::class),
            app(ProspectResultFilter::class)
        );

        $this->assertSame(2, Prospect::count());
        $this->assertDatabaseHas('prospects', [
            'place_id' => 'place-A',
            'name' => 'Company A',
        ]);
        $this->assertDatabaseHas('prospects', [
            'place_id' => 'place-B',
            'name' => 'Company B',
        ]);
        $this->assertDatabaseCount('ld_scan_logs', 3);

        $run->refresh();
        $this->assertSame(LdScanRun::STATUS_SUCCESS, $run->status);
        $this->assertSame(3, (int) ($run->totals_json['requests_total'] ?? 0));

        $recorded = Http::recorded();
        $this->assertCount(3, $recorded);

        $secondRequest = $recorded[1][0];
        parse_str((string) parse_url($secondRequest->url(), PHP_URL_QUERY), $query2);
        $this->assertSame('token-2', $query2['pagetoken'] ?? null);
        $this->assertArrayNotHasKey('location', $query2);
        $this->assertArrayNotHasKey('radius', $query2);
        $this->assertArrayNotHasKey('keyword', $query2);
    }

    public function test_scan_job_allows_shopping_mall_but_ignores_retail_types_and_keeps_converted_status(): void
    {
        Setting::setMany([
            'lead_discovery.max_pages_per_query' => '1',
            'lead_discovery.page_token_delay_ms' => '0',
            'lead_discovery.request_timeout_sec' => '20',
            'lead_discovery.retry_max' => '0',
        ]);

        $keyword = LdKeyword::create([
            'keyword' => 'factory',
            'category_label' => 'Manufacturing',
            'is_active' => true,
            'priority' => 10,
        ]);

        $cell = LdGridCell::create([
            'name' => 'Bali-01',
            'center_lat' => -8.6500,
            'center_lng' => 115.2167,
            'radius_m' => 12000,
            'city' => 'Denpasar',
            'province' => 'Bali',
            'is_active' => true,
        ]);

        $run = LdScanRun::create([
            'started_at' => now(),
            'status' => LdScanRun::STATUS_RUNNING,
            'mode' => LdScanRun::MODE_MANUAL,
            'totals_json' => [
                'pairs_dispatched' => 1,
            ],
        ]);

        Prospect::create([
            'place_id' => 'place-converted-retail',
            'name' => 'Converted Tenant',
            'primary_type' => 'store',
            'types_json' => ['store'],
            'discovered_at' => now()->subDay(),
            'last_seen_at' => now()->subDay(),
            'status' => Prospect::STATUS_CONVERTED,
        ]);

        Http::fake([
            'maps.googleapis.com/maps/api/place/nearbysearch/json*' => Http::response([
                'status' => 'OK',
                'results' => [
                    [
                        'place_id' => 'place-mall-1',
                        'name' => 'Mall Bali Galeria',
                        'formatted_address' => 'Jl. By Pass Ngurah Rai, Bali',
                        'types' => ['shopping_mall', 'department_store'],
                        'geometry' => ['location' => ['lat' => -8.70, 'lng' => 115.18]],
                    ],
                    [
                        'place_id' => 'place-tenant-1',
                        'name' => 'Sports Station Level 2 Mall Bali',
                        'formatted_address' => 'Level 2 Mall Bali Galeria',
                        'types' => ['clothing_store', 'store'],
                        'geometry' => ['location' => ['lat' => -8.71, 'lng' => 115.19]],
                    ],
                    [
                        'place_id' => 'place-lancome-1',
                        'name' => 'LANCOME BOUTIQUE - BEACHWALK MALL BALI',
                        'formatted_address' => 'Beachwalk Mall Bali',
                        'types' => ['shopping_mall'],
                        'geometry' => ['location' => ['lat' => -8.72, 'lng' => 115.16]],
                    ],
                    [
                        'place_id' => 'place-parking-1',
                        'name' => 'Parkir Level 21 Mall',
                        'formatted_address' => 'Level 3 Mall Bali',
                        'types' => ['parking'],
                        'geometry' => ['location' => ['lat' => -8.73, 'lng' => 115.17]],
                    ],
                    [
                        'place_id' => 'place-converted-retail',
                        'name' => 'Converted Tenant Updated',
                        'formatted_address' => 'Unit 12 Mall XYZ',
                        'types' => ['store'],
                        'geometry' => ['location' => ['lat' => -8.72, 'lng' => 115.20]],
                    ],
                ],
            ], 200),
        ]);

        $job = new ScanCellKeywordJob($run->id, $cell->id, $keyword->id);
        $job->handle(
            app(PlacesLegacyClient::class),
            app(ProspectNormalizer::class),
            app(ProspectResultFilter::class)
        );

        $mall = Prospect::query()->where('place_id', 'place-mall-1')->first();
        $this->assertNotNull($mall);
        $this->assertNotSame(Prospect::STATUS_IGNORED, $mall->status);

        $tenant = Prospect::query()->where('place_id', 'place-tenant-1')->first();
        $this->assertNotNull($tenant);
        $this->assertSame(Prospect::STATUS_IGNORED, $tenant->status);

        $lancome = Prospect::query()->where('place_id', 'place-lancome-1')->first();
        $this->assertNotNull($lancome);
        $this->assertSame(Prospect::STATUS_IGNORED, $lancome->status);

        $parking = Prospect::query()->where('place_id', 'place-parking-1')->first();
        $this->assertNotNull($parking);
        $this->assertSame(Prospect::STATUS_IGNORED, $parking->status);

        $converted = Prospect::query()->where('place_id', 'place-converted-retail')->first();
        $this->assertNotNull($converted);
        $this->assertSame(Prospect::STATUS_CONVERTED, $converted->status);
    }

    public function test_scan_job_backfills_city_and_province_from_grid_cell_when_place_has_no_location_text(): void
    {
        Setting::setMany([
            'lead_discovery.max_pages_per_query' => '1',
            'lead_discovery.page_token_delay_ms' => '0',
            'lead_discovery.request_timeout_sec' => '20',
            'lead_discovery.retry_max' => '0',
        ]);

        $keyword = LdKeyword::create([
            'keyword' => 'factory',
            'category_label' => 'Manufacturing',
            'is_active' => true,
            'priority' => 10,
        ]);

        $cell = LdGridCell::create([
            'name' => 'Surabaya-02',
            'center_lat' => -7.2574,
            'center_lng' => 112.7520,
            'radius_m' => 12000,
            'city' => 'Surabaya',
            'province' => 'Jawa Timur',
            'is_active' => true,
        ]);

        $run = LdScanRun::create([
            'started_at' => now(),
            'status' => LdScanRun::STATUS_RUNNING,
            'mode' => LdScanRun::MODE_MANUAL,
            'totals_json' => [
                'pairs_dispatched' => 1,
            ],
        ]);

        Http::fake([
            'maps.googleapis.com/maps/api/place/nearbysearch/json*' => Http::response([
                'status' => 'OK',
                'results' => [
                    [
                        'place_id' => 'place-no-address',
                        'name' => 'Company No Address',
                        'types' => ['establishment'],
                        'geometry' => ['location' => ['lat' => -7.25, 'lng' => 112.75]],
                    ],
                ],
            ], 200),
        ]);

        $job = new ScanCellKeywordJob($run->id, $cell->id, $keyword->id);
        $job->handle(
            app(PlacesLegacyClient::class),
            app(ProspectNormalizer::class),
            app(ProspectResultFilter::class)
        );

        $this->assertDatabaseHas('prospects', [
            'place_id' => 'place-no-address',
            'city' => 'Surabaya',
            'province' => 'Jawa Timur',
        ]);
    }
}
