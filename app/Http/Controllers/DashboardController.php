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
        $hasRole = function (string $role) use ($user): bool {
            if (!$user) {
                return false;
            }
            if (method_exists($user, 'hasAnyRole')) {
                return $user->hasAnyRole([$role]);
            }
            if (method_exists($user, 'hasRole')) {
                return $user->hasRole($role);
            }
            return false;
        };

        if ($hasRole('Logistic')) {
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

        if ($hasRole('Finance')) {
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

            $ttPendingCount = (clone $invBase)
                ->where('status', 'posted')
                ->whereNull('receipt_path')
                ->count();

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

            $ttPendingInvoices = (clone $invBase)
                ->where('status', 'posted')
                ->whereNull('receipt_path')
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
                'ttPendingCount',
                'mtdCollectedAmount',
                'overdueInvoices',
                'dueSoonInvoices',
                'ttPendingInvoices',
                'mtdPaidInvoices',
                'npwpLockedSoCount'
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
        $sentCount = (clone $mtdBase)->where('status', Quotation::STATUS_SENT)->count();
        $wonCount = (clone $mtdBase)->where('status', Quotation::STATUS_WON)->count();
        $wonRevenue = (clone $mtdBase)->where('status', Quotation::STATUS_WON)->sum('total');
        $sentPipeline = (clone $mtdBase)->where('status', Quotation::STATUS_SENT)->sum('total');

        $cutoff = $now->copy()->subDays(7)->startOfDay();
        $workQueue = (clone $qBase)
            ->where('status', Quotation::STATUS_SENT)
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
