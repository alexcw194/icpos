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
        $job->handle(app(PlacesLegacyClient::class), app(ProspectNormalizer::class));

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
}
