<?php

namespace App\Services\Payments;

use App\Models\Payment;

/**
 * A gateway that settles locally.
 *
 * The three real providers all need merchant credentials that only exist once
 * the business is registered with them, which would leave the entire payment
 * flow untestable until then. This driver implements the same contract and
 * settles immediately, so bookings, commission, receipts and history can all be
 * exercised end to end. It refuses to load when the app is in production.
 */
class SandboxGateway implements PaymentGateway
{
    public function name(): string
    {
        return 'sandbox';
    }

    public function isConfigured(): bool
    {
        return !app()->isProduction();
    }

    public function charge(Payment $payment, array $options = []): GatewayResult
    {
        // A deliberate way to exercise the failure path — the UI offers it as
        // "simulate a decline" rather than leaving failures untestable.
        if (($options['simulate'] ?? null) === 'fail') {
            return GatewayResult::failed('The bank declined this payment.', [
                'simulated' => true,
            ]);
        }

        return GatewayResult::succeeded('SBX-' . strtoupper(bin2hex(random_bytes(6))), [
            'simulated' => true,
            'charged_at' => now()->toIso8601String(),
        ]);
    }

    public function handleCallback(array $payload): ?GatewayResult
    {
        return null; // nothing calls back — it settled inline
    }

    public function refund(Payment $payment): GatewayResult
    {
        return GatewayResult::succeeded($payment->gateway_reference ?? $payment->reference, [
            'refunded' => true,
        ]);
    }
}
