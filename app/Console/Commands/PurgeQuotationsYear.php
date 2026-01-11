<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class PurgeQuotationsYear extends Command
{
    protected $signature = 'icpos:purge-quotations
        {year : Tahun yang akan dibuang (contoh: 2025)}
        {--company= : Company ID spesifik. Jika kosong = semua company}
        {--with-related : Ikut hapus data turunan yang terhubung (invoice/sales order) jika ada}
        {--force : Tanpa prompt konfirmasi}';

    protected $description = 'Hapus quotations pada tahun tertentu dan reset document counter untuk quotation pada tahun tsb';

    public function handle(): int
    {
        $year = (int) $this->argument('year');
        $companyId = $this->option('company') ? (int) $this->option('company') : null;
        $withRelated = (bool) $this->option('with-related');
        $force = (bool) $this->option('force');

        if ($year < 2000 || $year > 2100) {
            $this->error("Year tidak valid.");
            return self::FAILURE;
        }

        // Filter quotations by year (+ optional company)
        $qBase = DB::table('quotations')
            ->when($companyId, fn($q) => $q->where('company_id', $companyId))
            ->whereYear('date', $year);

        $count = (clone $qBase)->count();

        if ($count === 0) {
            $this->info("Tidak ada quotation untuk year={$year}" . ($companyId ? " company_id={$companyId}" : "") . ".");
            // Tetap reset counter supaya clean slate
            $this->resetCounters($year, $companyId);
            return self::SUCCESS;
        }

        if (!$force) {
            $msg = "Akan menghapus {$count} quotation year={$year}" . ($companyId ? " company_id={$companyId}" : " (SEMUA COMPANY)") . ". Lanjut?";
            if (!$this->confirm($msg)) {
                $this->warn("Dibatalkan.");
                return self::SUCCESS;
            }
        }

        // Ambil IDs supaya bisa delete bertahap dan aman untuk FK
        $quotationIds = (clone $qBase)->pluck('id')->all();

        // Guard: cek relasi yang bisa bikin FK error
        $invoiceCount = DB::table('invoices')->whereIn('quotation_id', $quotationIds)->count();
        $soCount      = DB::table('sales_orders')->whereIn('quotation_id', $quotationIds)->count();

        if (($invoiceCount > 0 || $soCount > 0) && !$withRelated) {
            $this->error("STOP: Ditemukan relasi:");
            $this->line("- invoices terkait: {$invoiceCount}");
            $this->line("- sales_orders terkait: {$soCount}");
            $this->line("Jalankan ulang dengan --with-related jika memang mau dibuang juga.");
            return self::FAILURE;
        }

        DB::transaction(function () use ($quotationIds, $year, $companyId, $withRelated) {
            // (Optional) Hapus turunan yang refer ke quotation_id (jika ada)
            if ($withRelated) {
                // invoice_lines bisa punya quotation_id / quotation_line_id di beberapa implementasi, jadi amankan dua-duanya
                if (DB::getSchemaBuilder()->hasTable('invoice_lines')) {
                    DB::table('invoice_lines')->whereIn('quotation_id', $quotationIds)->delete();
                }
                if (DB::getSchemaBuilder()->hasTable('invoices')) {
                    DB::table('invoices')->whereIn('quotation_id', $quotationIds)->delete();
                }

                if (DB::getSchemaBuilder()->hasTable('sales_order_lines')) {
                    $soIds = DB::table('sales_orders')->whereIn('quotation_id', $quotationIds)->pluck('id')->all();
                    if (!empty($soIds)) {
                        DB::table('sales_order_lines')->whereIn('sales_order_id', $soIds)->delete();
                        DB::table('sales_orders')->whereIn('id', $soIds)->delete();
                    }
                } else {
                    // minimal: hapus SO header jika table lines tidak ada
                    if (DB::getSchemaBuilder()->hasTable('sales_orders')) {
                        DB::table('sales_orders')->whereIn('quotation_id', $quotationIds)->delete();
                    }
                }
            }

            // Hapus quotation_lines dulu (kalau FK cascade belum diset)
            if (DB::getSchemaBuilder()->hasTable('quotation_lines')) {
                DB::table('quotation_lines')->whereIn('quotation_id', $quotationIds)->delete();
            }

            // Hapus quotations
            DB::table('quotations')->whereIn('id', $quotationIds)->delete();

            // Reset counter untuk year tsb
            $this->resetCounters($year, $companyId);
        });

        $this->info("OK: Quotations year={$year} dibuang + document counter quotation di-reset.");
        return self::SUCCESS;
    }

    private function resetCounters(int $year, ?int $companyId): void
    {
        // DocNumberService pakai table document_counters: company_id + doc_type + year, last_seq :contentReference[oaicite:3]{index=3}
        if (!DB::getSchemaBuilder()->hasTable('document_counters')) {
            return;
        }

        DB::table('document_counters')
            ->when($companyId, fn($q) => $q->where('company_id', $companyId))
            ->where('doc_type', 'quotation')
            ->where('year', $year)
            ->update(['last_seq' => 0, 'updated_at' => now()]);
    }
}
