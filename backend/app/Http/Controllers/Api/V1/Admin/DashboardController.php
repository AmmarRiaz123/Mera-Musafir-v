<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\Agency;
use App\Models\Payment;
use App\Models\Report;
use App\Models\Trip;
use App\Models\User;
use Illuminate\Http\Request;

/** The operator's at-a-glance view: who's on the platform, what needs action. */
class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $now = now();

        // Revenue is only money that actually settled, and only the platform's
        // cut of it — the commission, not the gross the agencies keep.
        $paidThisMonth = Payment::where('status', 'succeeded')
            ->whereBetween('paid_at', [$now->copy()->startOfMonth(), $now->copy()->endOfMonth()]);

        return response()->json([
            'data' => [
                'users' => [
                    'total'      => User::where('type', 'traveler')->count(),
                    'new_today'  => User::whereDate('created_at', $now->toDateString())->count(),
                    'agencies'   => User::where('type', 'agency')->count(),
                ],
                'trips' => [
                    'active' => Trip::whereIn('status', ['planning', 'active'])->count(),
                ],
                'revenue' => [
                    // Commission earned this month, plus gross processed, in whole rupees.
                    'commission_month' => (int) $paidThisMonth->clone()->sum('commission'),
                    'gross_month'      => (int) $paidThisMonth->clone()->sum('amount'),
                ],
                // The two work queues, so the badge counts match the pages.
                'queues' => [
                    'open_reports'         => Report::where('status', 'pending')->count(),
                    'pending_verification' => Agency::where('is_verified', false)->count(),
                ],
            ],
        ]);
    }

    /**
     * A month-by-month revenue breakdown for the last six months, split by
     * gateway — enough to see the trend without a charting dependency.
     */
    public function revenue()
    {
        $rows = Payment::where('status', 'succeeded')
            ->where('paid_at', '>=', now()->subMonths(6)->startOfMonth())
            ->selectRaw("DATE_FORMAT(paid_at, '%Y-%m') as month, gateway,
                         SUM(amount) as gross, SUM(commission) as commission, COUNT(*) as n")
            ->groupBy('month', 'gateway')
            ->orderBy('month')
            ->get();

        return response()->json(['data' => $rows]);
    }
}
