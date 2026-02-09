<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\Delivery;
use App\Models\Invoice;
use App\Models\Quotation;
use App\Models\SalesOrder;
use App\Models\StockAdjustment;
use App\Models\StockSummary;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $hasAnyRole = function (array $roles) use ($user): bool {
            if (!$user) {
                return false;
            }
            if (method_exists($user, 'hasAnyRole')) {
                return $user->hasAnyRole($roles);
            }
            if (method_exists($user, 'hasRole')) {
                foreach ($roles as $role) {
                    if ($user->hasRole($role)) {
                        return true;
                    }
                }
            }
            return false;
        };

        if ($hasAnyRole(['Admin', 'SuperAdmin'])) {
            $today = Carbon::now()->startOfDay();
            $mtdStart = Carbon::now()->startOfMonth()->startOfDay();
            $mtdEnd = Carbon::now();
            $ytdStart = Carbon::now()->startOfYear()->startOfDay();
            $ytdEnd = Carbon::now();
            $companyId = $request->filled('company_id') ? (int) $request->company_id : null;

            $companies = Company::query()
                ->orderBy('name')
                ->get(['id', 'alias', 'name']);

            $hasCompanyQuotation = Schema::hasColumn('quotations', 'company_id');
            $hasCompanySo = Schema::hasColumn('sales_orders', 'company_id');
            $hasSoCancelledAt = Schema::hasColumn('sales_orders', 'cancelled_at');
            $hasSoStatus = Schema::hasColumn('sales_orders', 'status');
            $hasCompanyInvoice = Schema::hasColumn('invoices', 'company_id');
            $hasCompanySummary = Schema::hasColumn('stock_summaries', 'company_id');

            $qBase = Quotation::query()
                ->with(['customer:id,name', 'company:id,alias,name'])
                ->when($companyId && $hasCompanyQuotation, fn($q) => $q->where('company_id', $companyId));

            $mtdBase = (clone $qBase)->whereBetween('date', [$mtdStart, $mtdEnd]);
            $qDraftMtdCount = (clone $mtdBase)->where('status', Quotation::STATUS_DRAFT)->count();
            $qSentMtdCount = (clone $mtdBase)->where('status', Quotation::STATUS_DRAFT)->count();
            $qWonMtdCount = (clone $mtdBase)->where('status', Quotation::STATUS_WON)->count();
            $qWonMtdAmount = (clone $mtdBase)->where('status', Quotation::STATUS_WON)->sum('total');
            $qSentPipelineMtdAmount = (clone $mtdBase)->where('status', Quotation::STATUS_DRAFT)->sum('total');

            $hasSentAt = Schema::hasColumn('quotations', 'sent_at');
            $sentAgingCutoff = Carbon::now()->subDays(7)->startOfDay();
            $qSentAging7dCount = $hasSentAt
                ? (clone $qBase)->where('status', Quotation::STATUS_DRAFT)->whereNotNull('sent_at')->where('sent_at', '<=', $sentAgingCutoff)->count()
                : 0;

            $sentAgingQuotes = collect();
            if ($hasSentAt) {
                $sentAgingQuotes = (clone $qBase)
                    ->where('status', Quotation::STATUS_DRAFT)
                    ->whereNotNull('sent_at')
                    ->where('sent_at', '<=', $sentAgingCutoff)
                    ->orderBy('sent_at')
                    ->limit(25)
                    ->get();
                $sentAgingQuotes->each(function ($q) {
                    $q->age_days = $q->sent_at ? $q->sent_at->diffInDays(Carbon::now()) : null;
                });
            }

            $soOpenStatuses = ['open', 'partial_delivered'];
            $soHasDeadline = Schema::hasColumn('sales_orders', 'deadline');
            $soBase = SalesOrder::query()
                ->with(['customer:id,name', 'company:id,alias,name'])
                ->when($companyId && $hasCompanySo, fn($q) => $q->where('company_id', $companyId));

            $soOpenCount = (clone $soBase)->whereIn('status', $soOpenStatuses)->count();
            $soDue7Count = $soHasDeadline
                ? (clone $soBase)->whereIn('status', $soOpenStatuses)->whereNotNull('deadline')->whereBetween('deadline', [$today, $today->copy()->addDays(7)])->count()
                : 0;

            $soOverdue = collect();
            $soDueSoon = collect();
            $soRecentOpen = collect();
            if ($soHasDeadline) {
                $soOverdue = (clone $soBase)
                    ->whereIn('status', $soOpenStatuses)
                    ->whereNotNull('deadline')
                    ->where('deadline', '<', $today)
                    ->orderBy('deadline')
                    ->limit(20)
                    ->get();
                $soDueSoon = (clone $soBase)
                    ->whereIn('status', $soOpenStatuses)
                    ->whereNotNull('deadline')
                    ->whereBetween('deadline', [$today, $today->copy()->addDays(7)])
                    ->orderBy('deadline')
                    ->limit(20)
                    ->get();
            } else {
                $soRecentOpen = (clone $soBase)
                    ->whereIn('status', $soOpenStatuses)
                    ->orderByDesc('order_date')
                    ->limit(20)
                    ->get();
            }

            $soRevenueYtd = SalesOrder::query()
                ->when($companyId && $hasCompanySo, fn($q) => $q->where('company_id', $companyId))
                ->whereBetween('created_at', [$ytdStart, $ytdEnd])
                ->when($hasSoCancelledAt, fn($q) => $q->whereNull('cancelled_at'))
                ->when($hasSoStatus, fn($q) => $q->where('status', '!=', 'cancelled'))
                ->sum('total');

            $invBase = Invoice::query()
                ->with(['customer:id,name', 'company:id,alias,name'])
                ->when($companyId && $hasCompanyInvoice, fn($q) => $q->where('company_id', $companyId));

            $arOutstandingAmount = (clone $invBase)
                ->where('status', 'posted')
                ->whereNull('paid_at')
                ->sum('total');

            $overdueInvoiceCount = 0;
            $hasDueDate = Schema::hasColumn('invoices', 'due_date');
            if ($hasDueDate) {
                $overdueInvoiceCount = (clone $invBase)
                    ->where('status', 'posted')
                    ->whereNull('paid_at')
                    ->whereNotNull('due_date')
                    ->where('due_date', '<', $today)
                    ->count();
            }

            $unpaidCount = (clone $invBase)
                ->where('status', 'posted')
                ->whereNull('paid_at')
                ->count();

            $overdueInvoices = collect();
            if ($hasDueDate) {
                $overdueInvoices = (clone $invBase)
                    ->where('status', 'posted')
                    ->whereNull('paid_at')
                    ->whereNotNull('due_date')
                    ->where('due_date', '<', $today)
                    ->orderBy('due_date')
                    ->limit(20)
                    ->get();
            }

            $unpaidInvoices = (clone $invBase)
                ->where('status', 'posted')
                ->whereNull('paid_at')
                ->orderByDesc('date')
                ->limit(20)
                ->get();

            $summaryBase = StockSummary::query()
                ->when($companyId && $hasCompanySummary, fn($q) => $q->where('company_id', $companyId));

            $negativeStockCount = (clone $summaryBase)->where('qty_balance', '<', 0)->count();
            $negativeStockRows = (clone $summaryBase)
                ->where('qty_balance', '<', 0)
                ->with([
                    'item:id,name,sku',
                    'variant:id,item_id,sku,attributes',
                    'variant.item:id,name,variant_type,name_template',
                    'warehouse:id,name'
                ])
                ->orderBy('qty_balance')
                ->limit(20)
                ->get();

            $companyStats = collect();
            if ($companies->count() > 1 && !$companyId) {
                $companyStats = $companies->map(function ($co) use ($mtdStart, $mtdEnd, $today, $soOpenStatuses, $hasCompanyQuotation, $hasCompanySo, $hasCompanyInvoice, $hasCompanySummary, $hasDueDate) {
                    $qid = $co->id;

                    $wonMtdAmount = Quotation::query()
                        ->when($hasCompanyQuotation, fn($q) => $q->where('company_id', $qid))
                        ->where('status', Quotation::STATUS_WON)
                        ->whereBetween('date', [$mtdStart, $mtdEnd])
                        ->sum('total');

                    $sentPipelineAmount = Quotation::query()
                        ->when($hasCompanyQuotation, fn($q) => $q->where('company_id', $qid))
                        ->where('status', Quotation::STATUS_DRAFT)
                        ->whereBetween('date', [$mtdStart, $mtdEnd])
                        ->sum('total');

                    $arOutstanding = Invoice::query()
                        ->when($hasCompanyInvoice, fn($q) => $q->where('company_id', $qid))
                        ->where('status', 'posted')
                        ->whereNull('paid_at')
                        ->sum('total');

                    $overdueCount = 0;
                    if ($hasDueDate) {
                        $overdueCount = Invoice::query()
                            ->when($hasCompanyInvoice, fn($q) => $q->where('company_id', $qid))
                            ->where('status', 'posted')
                            ->whereNull('paid_at')
                            ->whereNotNull('due_date')
                            ->where('due_date', '<', $today)
                            ->count();
                    }

                    $soOpenCount = SalesOrder::query()
                        ->when($hasCompanySo, fn($q) => $q->where('company_id', $qid))
                        ->whereIn('status', $soOpenStatuses)
                        ->count();

                    $negativeStock = StockSummary::query()
                        ->when($hasCompanySummary, fn($q) => $q->where('company_id', $qid))
                        ->where('qty_balance', '<', 0)
                        ->count();

                    return (object) [
                        'company' => $co,
                        'won_mtd_amount' => $wonMtdAmount,
                        'sent_pipeline_amount' => $sentPipelineAmount,
                        'ar_outstanding_amount' => $arOutstanding,
                        'overdue_count' => $overdueCount,
                        'so_open_count' => $soOpenCount,
                        'negative_stock_count' => $negativeStock,
                    ];
                });
            }

            return view('dashboards.admin', compact(
                'companies',
                'companyId',
                'qDraftMtdCount',
                'qSentMtdCount',
                'qWonMtdCount',
                'qWonMtdAmount',
                'qSentPipelineMtdAmount',
                'qSentAging7dCount',
                'soOpenCount',
                'soDue7Count',
                'soRevenueYtd',
                'arOutstandingAmount',
                'overdueInvoiceCount',
                'unpaidCount',
                'negativeStockCount',
                'sentAgingQuotes',
                'soHasDeadline',
                'soOverdue',
                'soDueSoon',
                'soRecentOpen',
                'overdueInvoices',
                'negativeStockRows',
                'unpaidInvoices',
                'companyStats'
            ));
        }

        if ($hasAnyRole(['Finance'])) {
            $today = Carbon::now()->startOfDay();
            $mtdStart = Carbon::now()->startOfMonth()->startOfDay();
            $now = Carbon::now();

            $invBase = Invoice::query()
                ->with(['customer:id,name', 'company:id,alias,name'])
                ->latest();

            $arOutstandingAmount = (clone $invBase)
                ->where('status', 'posted')
                ->whereNull('paid_at')
                ->sum('total');

            $arOutstandingCount = (clone $invBase)
                ->where('status', 'posted')
                ->whereNull('paid_at')
                ->count();

            $overdueCount = (clone $invBase)
                ->where('status', 'posted')
                ->whereNull('paid_at')
                ->whereNotNull('due_date')
                ->where('due_date', '<', $today)
                ->count();

            $dueSoonCount = (clone $invBase)
                ->where('status', 'posted')
                ->whereNull('paid_at')
                ->whereNotNull('due_date')
                ->whereBetween('due_date', [$today, $today->copy()->addDays(7)])
                ->count();

            $unpaidCount = (int) $arOutstandingCount;

            $mtdCollectedAmount = (clone $invBase)
                ->where('status', 'paid')
                ->whereBetween('paid_at', [$mtdStart, $now])
                ->sum('paid_amount');

            $overdueInvoices = (clone $invBase)
                ->where('status', 'posted')
                ->whereNull('paid_at')
                ->whereNotNull('due_date')
                ->where('due_date', '<', $today)
                ->orderBy('due_date')
                ->limit(25)
                ->get();

            $dueSoonInvoices = (clone $invBase)
                ->where('status', 'posted')
                ->whereNull('paid_at')
                ->whereNotNull('due_date')
                ->whereBetween('due_date', [$today, $today->copy()->addDays(7)])
                ->orderBy('due_date')
                ->limit(25)
                ->get();

            $unpaidInvoices = (clone $invBase)
                ->where('status', 'posted')
                ->whereNull('paid_at')
                ->orderByDesc('date')
                ->limit(25)
                ->get();

            $mtdPaidInvoices = (clone $invBase)
                ->where('status', 'paid')
                ->whereBetween('paid_at', [$mtdStart, $now])
                ->orderByDesc('paid_at')
                ->limit(25)
                ->get();

            $npwpLockedSoCount = null;
            if (Schema::hasColumn('sales_orders', 'npwp_required') && Schema::hasColumn('sales_orders', 'npwp_status')) {
                $npwpLockedSoCount = SalesOrder::query()
                    ->where('npwp_required', true)
                    ->where('npwp_status', 'missing')
                    ->count();
            }

            return view('dashboards.finance', compact(
                'arOutstandingAmount',
                'arOutstandingCount',
                'overdueCount',
                'dueSoonCount',
                'unpaidCount',
                'mtdCollectedAmount',
                'overdueInvoices',
                'dueSoonInvoices',
                'unpaidInvoices',
                'mtdPaidInvoices',
                'npwpLockedSoCount'
            ));
        }

        if ($hasAnyRole(['Logistic'])) {
            $today = Carbon::now()->startOfDay();
            $now = Carbon::now();
            $companyId = (int) ($request->company_id ?? Company::where('is_default', true)->value('id'));

            $companies = Company::query()
                ->orderBy('name')
                ->get(['id', 'alias', 'name']);

            $soCompanyScoped = Schema::hasColumn('sales_orders', 'company_id') && $companyId;
            $soHasDeadline = Schema::hasColumn('sales_orders', 'deadline');

            $soOpenStatuses = ['open', 'partial_delivered'];
            $soOpenBase = SalesOrder::query()
                ->when($soCompanyScoped, fn($q) => $q->where('company_id', $companyId))
                ->whereIn('status', $soOpenStatuses);

            $soOpenCount = (clone $soOpenBase)->count();
            $soPartialCount = (clone $soOpenBase)->where('status', 'partial_delivered')->count();

            $soDue7Count = 0;
            $soOverdueCount = 0;
            if ($soHasDeadline) {
                $soDue7Count = (clone $soOpenBase)
                    ->whereNotNull('deadline')
                    ->whereBetween('deadline', [$today, $today->copy()->addDays(7)])
                    ->count();
                $soOverdueCount = (clone $soOpenBase)
                    ->whereNotNull('deadline')
                    ->where('deadline', '<', $today)
                    ->count();
            }

            $soDueSoon = (clone $soOpenBase)
                ->with(['customer:id,name'])
                ->when($soHasDeadline, fn($q) => $q->whereNotNull('deadline')->whereBetween('deadline', [$today, $today->copy()->addDays(7)]))
                ->orderBy($soHasDeadline ? 'deadline' : 'order_date')
                ->limit(20)
                ->get();

            $deliveryCompanyScoped = Schema::hasColumn('deliveries', 'company_id') && $companyId;
            $deliveryDraftStatus = defined(Delivery::class.'::STATUS_DRAFT') ? Delivery::STATUS_DRAFT : 'draft';
            $deliveryPostedStatus = defined(Delivery::class.'::STATUS_POSTED') ? Delivery::STATUS_POSTED : 'posted';
            $deliveryCancelledStatus = defined(Delivery::class.'::STATUS_CANCELLED') ? Delivery::STATUS_CANCELLED : 'cancelled';
            $deliveryHasPostedAt = Schema::hasColumn('deliveries', 'posted_at');
            $deliveryHasCancelledAt = Schema::hasColumn('deliveries', 'cancelled_at');

            $deliveryBase = Delivery::query()
                ->when($deliveryCompanyScoped, fn($q) => $q->where('company_id', $companyId));

            $deliveryDraftCount = (clone $deliveryBase)
                ->where('status', $deliveryDraftStatus)
                ->count();

            $deliveryPostedTodayCount = (clone $deliveryBase)
                ->where('status', $deliveryPostedStatus)
                ->when($deliveryHasPostedAt, fn($q) => $q->where('posted_at', '>=', $today), fn($q) => $q->whereDate('date', $today))
                ->count();

            $deliveryCancelled30dCount = 0;
            if ($deliveryHasCancelledAt) {
                $deliveryCancelled30dCount = (clone $deliveryBase)
                    ->where('status', $deliveryCancelledStatus)
                    ->where('cancelled_at', '>=', $today->copy()->subDays(30))
                    ->count();
            }

            $deliveryQueue = (clone $deliveryBase)
                ->where('status', $deliveryDraftStatus)
                ->with(['salesOrder:id,so_number', 'invoice:id,number', 'warehouse:id,name'])
                ->orderByDesc('created_at')
                ->limit(20)
                ->get();

            $summaryCompanyScoped = Schema::hasColumn('stock_summaries', 'company_id') && $companyId;
            $summaryBase = StockSummary::query()
                ->when($summaryCompanyScoped, fn($q) => $q->where('company_id', $companyId));

            $negativeStockCount = (clone $summaryBase)->where('qty_balance', '<', 0)->count();
            $lowStockCount = (clone $summaryBase)->where('qty_balance', '<=', 0)->count();

            $inventoryExceptions = (clone $summaryBase)
                ->where('qty_balance', '<', 0)
                ->with([
                    'item:id,name,sku',
                    'variant:id,item_id,sku,attributes',
                    'variant.item:id,name,variant_type,name_template',
                    'warehouse:id,name'
                ])
                ->orderBy('qty_balance')
                ->limit(20)
                ->get();

            $adjustmentCompanyScoped = Schema::hasColumn('stock_adjustments', 'company_id') && $companyId;
            $recentAdjustments = StockAdjustment::query()
                ->when($adjustmentCompanyScoped, fn($q) => $q->where('company_id', $companyId))
                ->where('created_at', '>=', $today->copy()->subDays(7))
                ->with(['item:id,name,sku', 'variant:id,item_id,sku,attributes', 'variant.item:id,name,variant_type,name_template', 'warehouse:id,name'])
                ->orderByDesc('created_at')
                ->limit(20)
                ->get();

            return view('dashboards.logistic', compact(
                'companies',
                'companyId',
                'soOpenCount',
                'soDue7Count',
                'soOverdueCount',
                'soPartialCount',
                'deliveryDraftCount',
                'deliveryPostedTodayCount',
                'deliveryCancelled30dCount',
                'negativeStockCount',
                'lowStockCount',
                'soDueSoon',
                'soHasDeadline',
                'deliveryQueue',
                'inventoryExceptions',
                'recentAdjustments'
            ));
        }

        $now = Carbon::now();
        $start = $now->copy()->startOfMonth()->startOfDay();
        $end = $now->copy()->endOfDay();

        $qBase = Quotation::query()
            ->visibleTo($user)
            ->with(['customer:id,name', 'company:id,alias,name']);

        $mtdBase = (clone $qBase)->whereBetween('date', [$start, $end]);

        $draftCount = (clone $mtdBase)->where('status', Quotation::STATUS_DRAFT)->count();
        $sentCount = (clone $mtdBase)->where('status', Quotation::STATUS_DRAFT)->count();
        $wonCount = (clone $mtdBase)->where('status', Quotation::STATUS_WON)->count();
        $wonRevenue = (clone $mtdBase)->where('status', Quotation::STATUS_WON)->sum('total');
        $sentPipeline = (clone $mtdBase)->where('status', Quotation::STATUS_DRAFT)->sum('total');

        $cutoff = $now->copy()->subDays(7)->startOfDay();
        $workQueue = (clone $qBase)
            ->where('status', Quotation::STATUS_DRAFT)
            ->whereNotNull('sent_at')
            ->where('sent_at', '<=', $cutoff)
            ->orderBy('sent_at')
            ->limit(25)
            ->get();

        $workQueue->each(function ($q) use ($now) {
            $q->age_days = $q->sent_at ? $q->sent_at->diffInDays($now) : null;
        });

        $recent = (clone $qBase)
            ->latest('date')
            ->latest('id')
            ->limit(10)
            ->get();

        $soTotalCount = SalesOrder::query()
            ->visibleTo($user)
            ->count();

        return view('dashboards.sales', compact(
            'draftCount',
            'sentCount',
            'wonCount',
            'wonRevenue',
            'sentPipeline',
            'soTotalCount',
            'workQueue',
            'recent'
        ));
    }
}
