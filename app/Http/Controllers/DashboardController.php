<?php

namespace App\Http\Controllers;

use App\Models\Quotation;
use App\Models\SalesOrder;
use Carbon\Carbon;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
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
