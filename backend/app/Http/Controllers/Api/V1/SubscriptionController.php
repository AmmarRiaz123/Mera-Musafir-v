<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\AgencySubscription;
use Illuminate\Http\Request;

class SubscriptionController extends Controller
{
    /** GET /subscriptions/plans — what an agency can buy, and where it stands. */
    public function plans(Request $request)
    {
        $plans  = config('payments.plans');
        $agency = $request->user()->agency;

        return response()->json([
            'data' => [
                'current' => $agency ? [
                    'tier'       => $agency->tier,
                    'expires_at' => $agency->subscription_expires_at?->toDateTimeString(),
                    'is_active'  => $agency->tier !== 'basic'
                        && $agency->subscription_expires_at?->isFuture(),
                ] : null,
                'plans' => collect($plans)->map(fn ($prices, $tier) => [
                    'tier'    => $tier,
                    'monthly' => $prices['monthly'],
                    'yearly'  => $prices['yearly'],
                    // Two months free on an annual plan, per the business plan.
                    'yearly_saving' => ($prices['monthly'] * 12) - $prices['yearly'],
                ])->values(),
            ],
        ]);
    }

    /**
     * POST /subscriptions — reserve a subscription, then pay for it.
     *
     * The row is created pending and only becomes active when the payment
     * settles, so an abandoned checkout never upgrades anyone's tier.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'tier'   => 'required|in:pro,premium',
            'period' => 'required|in:monthly,yearly',
        ]);

        $agency = $request->user()->agency;

        if (!$agency) {
            return response()->json(['message' => 'Only agencies have subscriptions.'], 403);
        }

        $amount = config("payments.plans.{$validated['tier']}.{$validated['period']}");

        // Reuse an unpaid attempt rather than littering the table with them.
        $subscription = AgencySubscription::updateOrCreate(
            ['agency_id' => $agency->id, 'status' => 'pending'],
            [
                'tier'   => $validated['tier'],
                'period' => $validated['period'],
                'amount' => $amount,
            ],
        );

        return response()->json([
            'message' => 'Subscription reserved — pay to activate it.',
            'data'    => [
                'id'     => $subscription->id,
                'tier'   => $subscription->tier,
                'period' => $subscription->period,
                'amount' => $subscription->amount,
            ],
        ], 201);
    }

    /** GET /subscriptions/history — an agency's own billing record. */
    public function history(Request $request)
    {
        $agency = $request->user()->agency;

        if (!$agency) {
            return response()->json(['data' => []]);
        }

        $subs = AgencySubscription::where('agency_id', $agency->id)
            ->latest()
            ->get()
            ->map(fn ($s) => [
                'id'        => $s->id,
                'tier'      => $s->tier,
                'period'    => $s->period,
                'amount'    => $s->amount,
                'status'    => $s->status,
                'starts_at' => $s->starts_at?->toDateString(),
                'ends_at'   => $s->ends_at?->toDateString(),
            ]);

        return response()->json(['data' => $subs]);
    }
}
