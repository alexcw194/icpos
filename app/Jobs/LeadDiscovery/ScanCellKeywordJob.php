<?php

namespace App\Jobs\LeadDiscovery;

use App\Models\LdGridCell;
use App\Models\LdKeyword;
use App\Models\LdScanLog;
use App\Models\LdScanRun;
use App\Models\Prospect;
use App\Models\Setting;
use App\Services\LeadDiscovery\PlacesLegacyClient;
use App\Services\LeadDiscovery\ProspectNormalizer;
use App\Services\LeadDiscovery\ProspectResultFilter;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Throwable;

class ScanCellKeywordJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(
        public int $scanRunId,
        public int $gridCellId,
        public int $keywordId
    ) {
    }

    public function handle(
        PlacesLegacyClient $client,
        ProspectNormalizer $normalizer,
        ProspectResultFilter $resultFilter
    ): void
    {
        $run = LdScanRun::query()->find($this->scanRunId);
        $cell = LdGridCell::query()->find($this->gridCellId);
        $keyword = LdKeyword::query()->find($this->keywordId);
        if (!$run || !$cell || !$keyword) {
            return;
        }

        $maxPages = max(1, (int) Setting::get('lead_discovery.max_pages_per_query', 3));
        $pageDelayMs = max(0, (int) Setting::get('lead_discovery.page_token_delay_ms', 2200));

        $requests = 0;
        $errors = 0;
        $overLimit = 0;
        $upserted = 0;
        $newRows = 0;

        $pageToken = null;
        $now = Carbon::now();

        try {
            for ($page = 1; $page <= $maxPages; $page++) {
                $requests++;
                $response = $client->nearbySearch(
                    (float) $cell->center_lat,
                    (float) $cell->center_lng,
                    (int) $cell->radius_m,
                    (string) $keyword->keyword,
                    $pageToken
                );

                $status = (string) ($response['status'] ?? 'UNKNOWN');
                if ($status === 'OVER_QUERY_LIMIT') {
                    $overLimit++;
                }
                if (!in_array($status, ['OK', 'ZERO_RESULTS'], true)) {
                    $errors++;
                }

                $results = is_array($response['results'] ?? null) ? $response['results'] : [];
                $nextToken = $response['next_page_token'] ?? null;

                LdScanLog::query()->updateOrCreate(
                    [
                        'scan_run_id' => $run->id,
                        'grid_cell_id' => $cell->id,
                        'keyword_id' => $keyword->id,
                        'page_index' => $page,
                    ],
                    [
                        'request_url' => $response['request_url'] ?? null,
                        'request_payload' => $response['request_payload'] ?? null,
                        'response_status' => $status,
                        'results_count' => count($results),
                        'next_page_count' => $nextToken ? 1 : 0,
                        'error_message' => in_array($status, ['OK', 'ZERO_RESULTS'], true) ? null : (string)($status),
                    ]
                );

                foreach ($results as $place) {
                    if (!is_array($place) || empty($place['place_id'])) {
                        continue;
                    }

                    $ignoreResult = $resultFilter->shouldIgnore($place);
                    $ignoreReason = $resultFilter->reason($place);
                    $payload = $normalizer->normalizePlaceToProspectPayload($place, $keyword->id, $cell->id);
                    $payload['raw_json'] = $this->withFilterMetadata(
                        $payload['raw_json'] ?? [],
                        $ignoreResult,
                        $ignoreReason,
                        $now
                    );
                    if ($ignoreResult) {
                        $payload['status'] = Prospect::STATUS_IGNORED;
                    }
                    $prospect = Prospect::query()->firstOrNew([
                        'place_id' => $payload['place_id'],
                    ]);

                    if (!$prospect->exists) {
                        $prospect->fill($payload);
                        $prospect->save();
                        $upserted++;
                        $newRows++;
                        continue;
                    }

                    $prospect->fill([
                        'name' => $payload['name'] ?: $prospect->name,
                        'formatted_address' => $payload['formatted_address'] ?: $prospect->formatted_address,
                        'short_address' => $payload['short_address'] ?: $prospect->short_address,
                        'city' => $payload['city'] ?: $prospect->city,
                        'province' => $payload['province'] ?: $prospect->province,
                        'country' => $payload['country'] ?: $prospect->country,
                        'lat' => $payload['lat'] ?? $prospect->lat,
                        'lng' => $payload['lng'] ?? $prospect->lng,
                        'phone' => $payload['phone'] ?: $prospect->phone,
                        'website' => $payload['website'] ?: $prospect->website,
                        'google_maps_url' => $payload['google_maps_url'] ?: $prospect->google_maps_url,
                        'primary_type' => $payload['primary_type'] ?: $prospect->primary_type,
                        'types_json' => $payload['types_json'] ?: $prospect->types_json,
                        'last_seen_at' => $now,
                        'raw_json' => $payload['raw_json'] ?? $prospect->raw_json,
                    ]);

                    if ($ignoreResult && $prospect->status !== Prospect::STATUS_CONVERTED) {
                        $prospect->status = Prospect::STATUS_IGNORED;
                    }

                    if (!$prospect->keyword_id) {
                        $prospect->keyword_id = $keyword->id;
                    }
                    if (!$prospect->grid_cell_id) {
                        $prospect->grid_cell_id = $cell->id;
                    }
                    $prospect->save();
                    $upserted++;
                }

                if (!$nextToken || $page >= $maxPages) {
                    break;
                }

                usleep($pageDelayMs * 1000);
                $pageToken = $nextToken;
            }

            $cell->last_scanned_at = $now;
            $cell->save();

            $this->syncRunTotals($requests, $upserted, $newRows, $errors, $overLimit, true);
        } catch (Throwable $e) {
            LdScanLog::query()->create([
                'scan_run_id' => $run->id,
                'grid_cell_id' => $cell->id,
                'keyword_id' => $keyword->id,
                'page_index' => 1,
                'response_status' => 'EXCEPTION',
                'error_message' => mb_substr($e->getMessage(), 0, 1000),
            ]);

            $this->syncRunTotals($requests, $upserted, $newRows, $errors + 1, $overLimit, true);
            throw $e;
        }
    }

    private function syncRunTotals(
        int $requests,
        int $upserted,
        int $newRows,
        int $errors,
        int $overLimit,
        bool $markPairDone
    ): void {
        $pairKey = "ld_scan_pair_done:{$this->scanRunId}:{$this->gridCellId}:{$this->keywordId}";
        $pairDone = !$markPairDone || Cache::add($pairKey, 1, now()->addDay());

        DB::transaction(function () use ($requests, $upserted, $newRows, $errors, $overLimit, $pairDone) {
            $run = LdScanRun::query()->lockForUpdate()->find($this->scanRunId);
            if (!$run) {
                return;
            }
            $totals = $run->totals_json ?? [];

            $totals['requests_total'] = (int) ($totals['requests_total'] ?? 0) + $requests;
            $totals['prospects_upserted'] = (int) ($totals['prospects_upserted'] ?? 0) + $upserted;
            $totals['prospects_new'] = (int) ($totals['prospects_new'] ?? 0) + $newRows;
            $totals['errors_count'] = (int) ($totals['errors_count'] ?? 0) + $errors;
            $totals['over_query_limit_count'] = (int) ($totals['over_query_limit_count'] ?? 0) + $overLimit;

            if ($pairDone) {
                $totals['pairs_completed'] = (int) ($totals['pairs_completed'] ?? 0) + 1;
            }

            $run->totals_json = $totals;

            $pairsDispatched = (int) ($totals['pairs_dispatched'] ?? 0);
            $pairsCompleted = (int) ($totals['pairs_completed'] ?? 0);
            if ($pairsDispatched > 0 && $pairsCompleted >= $pairsDispatched && $run->status === LdScanRun::STATUS_RUNNING) {
                $run->status = LdScanRun::STATUS_SUCCESS;
                $run->finished_at = Carbon::now();
            }

            $run->save();
        });
    }

    public function failed(Throwable $exception): void
    {
        DB::transaction(function () {
            $run = LdScanRun::query()->lockForUpdate()->find($this->scanRunId);
            if (!$run || $run->status !== LdScanRun::STATUS_RUNNING) {
                return;
            }
            $run->status = LdScanRun::STATUS_FAILED;
            $run->finished_at = Carbon::now();
            $run->save();
        });
    }

    private function withFilterMetadata(array $rawJson, bool $ignored, string $reason, Carbon $scannedAt): array
    {
        $rawJson['_lead_discovery_filter'] = [
            'ignored' => $ignored,
            'reason' => $reason,
            'scanned_at' => $scannedAt->toIso8601String(),
        ];

        return $rawJson;
    }
}
