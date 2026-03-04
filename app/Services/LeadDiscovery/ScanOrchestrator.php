<?php

namespace App\Services\LeadDiscovery;

use App\Jobs\LeadDiscovery\ScanCellKeywordJob;
use App\Models\LdGridCell;
use App\Models\LdKeyword;
use App\Models\LdScanRun;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class ScanOrchestrator
{
    public function runScan(array $options = []): LdScanRun
    {
        $mode = $options['mode'] ?? LdScanRun::MODE_MANUAL;
        $force = (bool) ($options['force'] ?? false);
        $note = $options['note'] ?? null;
        $creatorId = $this->resolveCreatorId($options['user'] ?? null);

        if (!$force) {
            $running = LdScanRun::query()
                ->where('status', LdScanRun::STATUS_RUNNING)
                ->latest('id')
                ->first();
            if ($running && $mode === LdScanRun::MODE_SCHEDULED) {
                return $running;
            }
        }

        $cellIds = $this->resolveIds($options['cell_ids'] ?? null);
        $keywordIds = $this->resolveIds($options['keyword_ids'] ?? null);

        $cellsLimit = (int) ($options['max_cells'] ?? Setting::get('lead_discovery.max_cells_per_run', 20));
        $keywordLimit = (int) ($options['max_keywords'] ?? Setting::get('lead_discovery.max_keywords_per_cell', 8));
        $cellsLimit = max(1, $cellsLimit);
        $keywordLimit = max(1, $keywordLimit);

        $cells = LdGridCell::query()
            ->where('is_active', true)
            ->when($cellIds !== [], fn ($q) => $q->whereIn('id', $cellIds))
            ->orderByRaw("COALESCE(last_scanned_at, '1970-01-01 00:00:00') asc")
            ->orderBy('id')
            ->limit($cellsLimit)
            ->get();

        $keywords = LdKeyword::query()
            ->where('is_active', true)
            ->when($keywordIds !== [], fn ($q) => $q->whereIn('id', $keywordIds))
            ->orderBy('priority')
            ->orderBy('id')
            ->limit($keywordLimit)
            ->get();

        $run = LdScanRun::query()->create([
            'started_at' => Carbon::now(),
            'status' => LdScanRun::STATUS_RUNNING,
            'mode' => in_array($mode, [LdScanRun::MODE_MANUAL, LdScanRun::MODE_SCHEDULED], true)
                ? $mode
                : LdScanRun::MODE_MANUAL,
            'note' => $note,
            'created_by_user_id' => $creatorId,
            'totals_json' => [
                'cells_selected' => $cells->count(),
                'keywords_selected' => $keywords->count(),
                'pairs_dispatched' => 0,
            ],
        ]);

        $pairs = 0;
        foreach ($cells as $cell) {
            foreach ($keywords as $keyword) {
                ScanCellKeywordJob::dispatch($run->id, $cell->id, $keyword->id);
                $pairs++;
            }
        }

        $run->totals_json = array_merge($run->totals_json ?? [], [
            'pairs_dispatched' => $pairs,
        ]);
        if ($pairs === 0) {
            $run->status = LdScanRun::STATUS_SUCCESS;
            $run->finished_at = Carbon::now();
        }
        $run->save();

        return $run;
    }

    /**
     * @return array<int>
     */
    private function resolveIds(mixed $value): array
    {
        if ($value instanceof Collection) {
            $value = $value->all();
        }

        if (is_string($value)) {
            $value = array_filter(array_map('trim', explode(',', $value)), fn ($v) => $v !== '');
        }

        if (!is_array($value)) {
            return [];
        }

        return array_values(array_unique(array_filter(array_map(static fn ($v) => (int) $v, $value), static fn ($id) => $id > 0)));
    }

    private function resolveCreatorId(mixed $user): ?int
    {
        if ($user instanceof User) {
            return (int) $user->id;
        }
        if (is_numeric($user) && (int) $user > 0) {
            return (int) $user;
        }
        return null;
    }
}
