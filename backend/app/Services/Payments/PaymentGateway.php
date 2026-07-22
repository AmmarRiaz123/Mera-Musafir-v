<?php

namespace App\Services\Payments;

use App\Models\Payment;

interface PaymentGateway
{
    /** Machine name — matches the key in config/payments.php. */
    public function name(): string;

    /** False when credentials are missing, so the UI can hide it rather than fail. */
    public function isConfigured(): bool;

    /** Start a payment. */
    public function charge(Payment $payment, array $options = []): GatewayResult;

    /**
     * Interpret a callback/webhook body.
     *
     * Returns null when the payload can't be trusted — an unverified signature
     * must never be able to mark a payment paid.
     */
    public function handleCallback(array $payload): ?GatewayResult;

    /** Refund a settled payment. */
    public function refund(Payment $payment): GatewayResult;
}
