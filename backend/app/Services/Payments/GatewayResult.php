<?php

namespace App\Services\Payments;

/**
 * What a gateway tells us after we hand it a payment.
 *
 * Gateways disagree about almost everything — some redirect the browser, some
 * settle inline, some answer later on a webhook. This flattens all three into
 * one shape so PaymentService and the frontend don't branch per provider.
 */
class GatewayResult
{
    public function __construct(
        public readonly string $status,          // pending|succeeded|failed
        public readonly ?string $reference = null,
        public readonly ?string $redirectUrl = null,
        public readonly ?array $formFields = null,
        public readonly ?string $failureReason = null,
        public readonly array $payload = [],
    ) {}

    public static function succeeded(string $reference, array $payload = []): self
    {
        return new self(status: 'succeeded', reference: $reference, payload: $payload);
    }

    public static function failed(string $reason, array $payload = []): self
    {
        return new self(status: 'failed', failureReason: $reason, payload: $payload);
    }

    /** The browser has to go somewhere (or POST a form) before we know anything. */
    public static function redirect(string $url, ?array $formFields = null, array $payload = []): self
    {
        return new self(
            status: 'pending',
            redirectUrl: $url,
            formFields: $formFields,
            payload: $payload,
        );
    }
}
