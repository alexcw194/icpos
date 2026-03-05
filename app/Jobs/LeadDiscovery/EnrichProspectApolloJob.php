<?php

namespace App\Jobs\LeadDiscovery;

use App\Models\ProspectApolloEnrichment;
use App\Services\LeadDiscovery\ApolloEnrichmentService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Throwable;

class EnrichProspectApolloJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public function __construct(public int $enrichmentId)
    {
    }

    public function handle(ApolloEnrichmentService $service): void
    {
        $enrichment = ProspectApolloEnrichment::query()
            ->with('prospect')
            ->find($this->enrichmentId);

        if (!$enrichment) {
            return;
        }

        if (!in_array($enrichment->status, [
            ProspectApolloEnrichment::STATUS_QUEUED,
            ProspectApolloEnrichment::STATUS_RUNNING,
        ], true)) {
            return;
        }

        $enrichment->status = ProspectApolloEnrichment::STATUS_RUNNING;
        $enrichment->started_at = $enrichment->started_at ?: Carbon::now();
        $enrichment->error_message = null;
        $enrichment->save();

        try {
            $result = $service->enrich($enrichment->prospect);
            $enrichment->fill($result);
            $enrichment->status = ProspectApolloEnrichment::STATUS_SUCCESS;
            $enrichment->finished_at = Carbon::now();
            $enrichment->save();

            $service->mergeProspectFillEmpty($enrichment->prospect, $enrichment);
        } catch (Throwable $e) {
            $enrichment->status = ProspectApolloEnrichment::STATUS_FAILED;
            $enrichment->error_message = mb_substr($e->getMessage(), 0, 1000);
            $enrichment->finished_at = Carbon::now();
            $enrichment->save();
        }
    }
}
