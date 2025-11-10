<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Models\StockLedger;
use App\Models\StockSummary;

class AuditStockConsistency extends Command
{
    protected $signature = 'stock:audit';
    protected $description = 'Audit consistency between stock_ledgers and stock_summaries';

    public function handle(): int
    {
        $this->info('Auditing stock consistency...');

        // Ambil saldo dari ledger
        $ledgerBalances = DB::table('stock_ledgers')
            ->select(
                'company_id',
                'warehouse_id',
                'item_id',
                DB::raw('SUM(qty_change) as calc_balance')
            )
            ->groupBy('company_id', 'warehouse_id', 'item_id')
            ->get()
            ->keyBy(fn($x) => implode('-', [
                $x->company_id,
                $x->warehouse_id,
                $x->item_id
            ]));

        // Ambil saldo dari summary
        $stockBalances = StockSummary::select(
                'company_id',
                'warehouse_id',
                'item_id',
                'qty_balance'
            )
            ->get()
            ->keyBy(fn($x) => implode('-', [
                $x->company_id,
                $x->warehouse_id,
                $x->item_id
            ]));

        // Bandingkan hasilnya
        $diffs = [];

        foreach ($ledgerBalances as $key => $ledger) {
            $stock = $stockBalances->get($key);
            $ledgerBal = (float) $ledger->calc_balance;
            $stockBal = (float) ($stock->qty_balance ?? 0);
            $diff = $ledgerBal - $stockBal;

            if (abs($diff) > 0.0001) {
                $diffs[] = [
                    'key' => $key,
                    'ledger' => $ledgerBal,
                    'stock' => $stockBal,
                    'diff' => $diff,
                ];
            }
        }

        if (empty($diffs)) {
            $this->info('All stock balances are consistent ✅');
            return Command::SUCCESS;
        }

        $this->warn(count($diffs) . ' mismatch(es) found!');
        foreach ($diffs as $row) {
            $parts = explode('-', $row['key']);
            $this->line(sprintf(
                'Company:%s | Warehouse:%s | Item:%s → Ledger=%.4f, Stock=%.4f, Diff=%.4f',
                $parts[0],
                $parts[1],
                $parts[2],
                $row['ledger'],
                $row['stock'],
                $row['diff']
            ));
        }

        return Command::FAILURE;
    }
}
