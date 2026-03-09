<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class AuditStockConsistency extends Command
{
    protected $signature = 'stock:audit';
    protected $description = 'Audit consistency between stock_ledgers, stock_summaries, and item_stocks per variant scope';

    public function handle(): int
    {
        $this->info('Auditing stock consistency...');
        $epsilon = 0.0001;

        $ledgerBalances = DB::table('stock_ledgers')
            ->select(
                'company_id',
                'warehouse_id',
                'item_id',
                DB::raw('COALESCE(item_variant_id, 0) as variant_scope'),
                DB::raw('SUM(qty_change) as calc_balance')
            )
            ->groupBy('company_id', 'warehouse_id', 'item_id', DB::raw('COALESCE(item_variant_id, 0)'))
            ->get()
            ->keyBy(fn ($x) => implode('-', [
                $x->company_id,
                $x->warehouse_id,
                $x->item_id,
                $x->variant_scope,
            ]));

        $summaryBalances = DB::table('stock_summaries')
            ->select(
                'company_id',
                'warehouse_id',
                'item_id',
                DB::raw('COALESCE(variant_id, 0) as variant_scope'),
                DB::raw('SUM(qty_balance) as qty_balance')
            )
            ->groupBy('company_id', 'warehouse_id', 'item_id', DB::raw('COALESCE(variant_id, 0)'))
            ->get()
            ->keyBy(fn ($x) => implode('-', [
                $x->company_id,
                $x->warehouse_id,
                $x->item_id,
                $x->variant_scope,
            ]));

        $itemStockBalances = DB::table('item_stocks')
            ->select(
                'company_id',
                'warehouse_id',
                'item_id',
                DB::raw('COALESCE(item_variant_id, 0) as variant_scope'),
                DB::raw('SUM(qty_on_hand) as qty_on_hand')
            )
            ->groupBy('company_id', 'warehouse_id', 'item_id', DB::raw('COALESCE(item_variant_id, 0)'))
            ->get()
            ->keyBy(fn ($x) => implode('-', [
                $x->company_id,
                $x->warehouse_id,
                $x->item_id,
                $x->variant_scope,
            ]));

        $allKeys = collect()
            ->merge($ledgerBalances->keys())
            ->merge($summaryBalances->keys())
            ->merge($itemStockBalances->keys())
            ->unique()
            ->values();

        $diffs = [];

        foreach ($allKeys as $key) {
            $ledgerBal = (float) ($ledgerBalances->get($key)->calc_balance ?? 0);
            $summaryBal = (float) ($summaryBalances->get($key)->qty_balance ?? 0);
            $itemStockBal = (float) ($itemStockBalances->get($key)->qty_on_hand ?? 0);

            $ledgerVsSummary = $ledgerBal - $summaryBal;
            $summaryVsStock = $summaryBal - $itemStockBal;

            if (abs($ledgerVsSummary) > $epsilon || abs($summaryVsStock) > $epsilon) {
                $diffs[] = [
                    'key' => $key,
                    'ledger' => $ledgerBal,
                    'summary' => $summaryBal,
                    'item_stock' => $itemStockBal,
                    'ledger_vs_summary' => $ledgerVsSummary,
                    'summary_vs_item_stock' => $summaryVsStock,
                ];
            }
        }

        if (empty($diffs)) {
            $this->info('All stock balances are consistent.');
            return Command::SUCCESS;
        }

        $this->warn(count($diffs).' mismatch(es) found!');

        foreach ($diffs as $row) {
            $parts = explode('-', $row['key']);
            $variantLabel = ((int) ($parts[3] ?? 0)) > 0 ? $parts[3] : 'NULL';

            $this->line(sprintf(
                'Company:%s | Warehouse:%s | Item:%s | Variant:%s -> Ledger=%.4f, Summary=%.4f, ItemStock=%.4f, L-S=%.4f, S-I=%.4f',
                $parts[0] ?? '-',
                $parts[1] ?? '-',
                $parts[2] ?? '-',
                $variantLabel,
                $row['ledger'],
                $row['summary'],
                $row['item_stock'],
                $row['ledger_vs_summary'],
                $row['summary_vs_item_stock']
            ));
        }

        return Command::FAILURE;
    }
}
