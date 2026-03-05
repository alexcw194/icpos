<?php

namespace App\Jobs\LeadDiscovery;

use App\Models\ProspectAnalysis;
use App\Services\LeadDiscovery\LeadDiscoveryAiClassifierService;
use App\Services\LeadDiscovery\ProspectAnalyzerService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Throwable;

class AnalyzeProspectJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public function __construct(public int $analysisId)
    {
    }

    public function handle(
        ProspectAnalyzerService $analyzer,
        LeadDiscoveryAiClassifierService $aiClassifier
    ): void
    {
        $analysis = ProspectAnalysis::query()
            ->with('prospect.keyword:id,keyword,category_label')
            ->find($this->analysisId);
        if (!$analysis) {
            return;
        }

        if (!in_array($analysis->status, [ProspectAnalysis::STATUS_QUEUED, ProspectAnalysis::STATUS_RUNNING], true)) {
            return;
        }

        $analysis->status = ProspectAnalysis::STATUS_RUNNING;
        $analysis->started_at = $analysis->started_at ?: Carbon::now();
        $analysis->error_message = null;
        $analysis->save();

        try {
            $result = $analyzer->analyze($analysis->prospect);
            $aiResult = $aiClassifier->classify($analysis->prospect, $result);
            $analysis->fill(array_merge($result, $aiResult));
            $analysis->status = ProspectAnalysis::STATUS_SUCCESS;
            $analysis->finished_at = Carbon::now();
            $analysis->save();
        } catch (Throwable $e) {
            $analysis->status = ProspectAnalysis::STATUS_FAILED;
            $analysis->error_message = mb_substr($e->getMessage(), 0, 1000);
            $analysis->finished_at = Carbon::now();
            $analysis->save();
        }
    }
}
