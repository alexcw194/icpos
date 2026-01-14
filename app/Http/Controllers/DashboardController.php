<?php

namespace App\Http\Controllers;

use App\Models\Quotation;
use App\Models\SalesOrder;
use App\Models\Invoice;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $isFinance = false;
        if ($user) {
            if (method_exists($user, 'hasAnyRole')) {
                $isFinance = $user->hasAnyRole(['Finance']);
            } elseif (method_exists($user, 'hasRole')) {
                $isFinance = $user->hasRole('Finance');
            }
        }

        if ($isFinance) {
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

        return view('dashboard', compact(
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
