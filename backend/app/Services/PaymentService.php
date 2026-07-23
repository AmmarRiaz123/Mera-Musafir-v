<?php

namespace App\Services;

use App\Models\AgencySubscription;
use App\Models\Booking;
use App\Models\Payment;
use App\Models\User;
use App\Services\Payments\GatewayManager;
use App\Services\Payments\GatewayResult;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Owns the money rules.
 *
 * Controllers decide who may pay for what; everything about how much the
 * platform keeps, when a booking becomes confirmed and what a settled payment
 * does to the thing it paid for lives here, so the two payable types can't
 * drift apart.
 */
class PaymentService
{
    public function __construct(
        private GatewayManager $gateways,
        private BookingService $bookings,
    ) {}

    /**
     * Create a pending payment and hand it to a gateway.
     *
     * Reuses an existing pending payment for the same thing rather than opening
     * a second one — someone who backs out of the gateway page and tries again
     * shouldn't end up with two rows and, later, two charges.
     */
    public function initiate(User $user, Model $payable, string $gateway, array $options = []): Payment
    {
        $driver = $this->gateways->driver($gateway);

        $payment = Payment::where('payable_type', $payable->getMorphClass())
            ->where('payable_id', $payable->getKey())
            ->where('status', 'pending')
            ->first();

        $amount = $this->amountFor($payable);

        if (!$payment) {
            // Commission applies to agency bookings, not to what an agency pays
            // us for its own subscription — that's already all ours.
            $rate = $payable instanceof Booking ? (float) config('payments.commission_rate') : 0.0;
            $commission = (int) round($amount * $rate);

            $payment = Payment::create([
                'user_id'         => $user->id,
                'payable_type'    => $payable->getMorphClass(),
                'payable_id'      => $payable->getKey(),
                'gateway'         => $driver->name(),
                'reference'       => $this->newReference(),
                'amount'          => $amount,
                'commission'      => $commission,
                'net_amount'      => $amount - $commission,
                'commission_rate' => $rate,
                'status'          => 'pending',
            ]);
        } else {
            // They came back and chose a different method.
            $payment->update(['gateway' => $driver->name()]);
        }

        $result = $driver->charge($payment, $options);
        $this->apply($payment, $result);

        return $payment->fresh();
    }

    /**
     * Move a payment to whatever the gateway said, and fan out the consequences.
     *
     * Idempotent on purpose: gateways retry webhooks, and a second "succeeded"
     * for an already-settled payment must not confirm a booking twice or extend
     * a subscription by another month.
     */
    public function apply(Payment $payment, GatewayResult $result): Payment
    {
        if ($payment->status === 'succeeded' && $result->status === 'succeeded') {
            return $payment;
        }

        return DB::transaction(function () use ($payment, $result) {
            $payment->fill([
                'gateway_reference' => $result->reference ?: $payment->gateway_reference,
                'gateway_payload'   => $result->payload ?: $payment->gateway_payload,
            ]);

            if ($result->status === 'succeeded') {
                $payment->status  = 'succeeded';
                $payment->paid_at = now();
                $payment->failure_reason = null;
                $payment->save();

                $this->settle($payment);

                return $payment;
            }

            if ($result->status === 'failed') {
                $payment->status = 'failed';
                $payment->failure_reason = $result->failureReason;
            }

            $payment->save();

            return $payment;
        });
    }

    /** What a settled payment does to the thing it paid for. */
    private function settle(Payment $payment): void
    {
        $payable = $payment->payable;

        if ($payable instanceof Booking) {
            $payable->update(['payment_status' => 'paid']);

            // Paying is confirmation — the seat is sold. Going through the
            // service also seats them on the departure's group trip, which is
            // the whole reason a package booking is worth having.
            $this->bookings->confirm($payable);

            $package = $payable->agencyPackage;
            if ($package?->agency?->user) {
                app(\App\Services\NotificationService::class)->push(
                    recipient: $package->agency->user,
                    type: 'booking_paid',
                    copy: [
                        'title' => $payable->user->name . ' paid for ' . $package->title,
                        'body'  => 'PKR ' . number_format($payment->net_amount) . ' after platform fee',
                        'link'  => '/agencies/' . $package->agency->slug . '/dashboard',
                    ],
                    actor: $payable->user,
                    subject: $payable,
                );
            }

            return;
        }

        if ($payable instanceof AgencySubscription) {
            $months = $payable->period === 'yearly' ? 12 : 1;

            // Extend from whatever's left rather than from today, so paying
            // early doesn't cost the agency the remainder of the current term.
            $agency = $payable->agency;
            $start  = $agency->subscription_expires_at?->isFuture()
                ? $agency->subscription_expires_at
                : now();

            $payable->update([
                'status'    => 'active',
                'starts_at' => now(),
                'ends_at'   => $start->copy()->addMonths($months),
            ]);

            $agency->update([
                'tier'                    => $payable->tier,
                'subscription_expires_at' => $payable->ends_at,
            ]);
        }
    }

    /** Reverse a settled payment. */
    public function refund(Payment $payment): Payment
    {
        if ($payment->status !== 'succeeded') {
            return $payment;
        }

        $result = $this->gateways->driver($payment->gateway)->refund($payment);

        if ($result->status !== 'succeeded') {
            Log::warning('Refund not completed by gateway', [
                'payment' => $payment->reference,
                'reason'  => $result->failureReason,
            ]);

            return $payment;
        }

        return DB::transaction(function () use ($payment) {
            $payment->update([
                'status'      => 'refunded',
                'refunded_at' => now(),
            ]);

            $payable = $payment->payable;

            if ($payable instanceof Booking) {
                // Cancel through the service so the package seats, the trip
                // headcount and the traveller's place all come back — setting
                // the status alone would strand them.
                $this->bookings->release($payable);
                $payable->update(['payment_status' => 'refunded']);
            }

            return $payment;
        });
    }

    private function amountFor(Model $payable): int
    {
        return match (true) {
            $payable instanceof Booking            => (int) $payable->total_amount,
            $payable instanceof AgencySubscription => (int) $payable->amount,
            default => throw new \InvalidArgumentException('That cannot be paid for.'),
        };
    }

    /** Short, unambiguous, and safe to read down a phone line. */
    private function newReference(): string
    {
        do {
            $ref = 'MM' . now()->format('ymd') . strtoupper(bin2hex(random_bytes(3)));
        } while (Payment::where('reference', $ref)->exists());

        return $ref;
    }
}
