<?php

namespace App\Services\Payments;

use App\Models\Payment;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Stripe — for cards and anyone paying from outside Pakistan.
 *
 * Talks to Stripe's REST API directly rather than pulling in their SDK: this
 * needs three endpoints, and the SDK would be the largest dependency in the
 * project for that. Unlike the two wallets, Stripe test keys are self-serve —
 * put STRIPE_SECRET in .env and this works immediately.
 */
class StripeGateway implements PaymentGateway
{
    private array $config;

    public function __construct()
    {
        $this->config = config('payments.gateways.stripe');
    }

    public function name(): string
    {
        return 'stripe';
    }

    public function isConfigured(): bool
    {
        return filled($this->config['secret']);
    }

    public function charge(Payment $payment, array $options = []): GatewayResult
    {
        if (!$this->isConfigured()) {
            Log::info('Stripe not configured — set STRIPE_SECRET in .env.');

            return GatewayResult::failed('This payment method is not available yet.');
        }

        $response = Http::withToken($this->config['secret'])
            ->asForm()
            ->post('https://api.stripe.com/v1/payment_intents', [
                // Stripe works in the smallest currency unit.
                'amount'   => $payment->amount * 100,
                'currency' => 'pkr',
                'metadata[payment_reference]' => $payment->reference,
                'automatic_payment_methods[enabled]' => 'true',
            ]);

        if (!$response->successful()) {
            return GatewayResult::failed(
                $response->json('error.message') ?? 'Card payment could not be started.',
                $response->json() ?? [],
            );
        }

        // The client secret goes to the browser, which completes the card
        // details with Stripe directly — card numbers never touch our server.
        return new GatewayResult(
            status: 'pending',
            reference: $response->json('id'),
            payload: ['client_secret' => $response->json('client_secret')],
        );
    }

    public function handleCallback(array $payload): ?GatewayResult
    {
        // Signature verification happens in the controller, which has the raw
        // body — re-encoding the parsed array would change the bytes and the
        // signature would never match.
        $type   = $payload['type'] ?? null;
        $intent = $payload['data']['object'] ?? [];

        return match ($type) {
            'payment_intent.succeeded' => GatewayResult::succeeded($intent['id'] ?? '', $payload),
            'payment_intent.payment_failed' => GatewayResult::failed(
                $intent['last_payment_error']['message'] ?? 'The card was declined.',
                $payload,
            ),
            default => null,
        };
    }

    public function refund(Payment $payment): GatewayResult
    {
        if (!$this->isConfigured() || !$payment->gateway_reference) {
            return GatewayResult::failed('This payment cannot be refunded automatically.');
        }

        $response = Http::withToken($this->config['secret'])
            ->asForm()
            ->post('https://api.stripe.com/v1/refunds', [
                'payment_intent' => $payment->gateway_reference,
            ]);

        return $response->successful()
            ? GatewayResult::succeeded($response->json('id'), $response->json())
            : GatewayResult::failed($response->json('error.message') ?? 'Refund failed.');
    }

    /** Stripe signs webhooks; an unverified body must never settle a payment. */
    public function verifyWebhook(string $rawBody, ?string $signatureHeader): bool
    {
        $secret = $this->config['webhook_secret'];

        if (!$secret || !$signatureHeader) {
            return false;
        }

        preg_match('/t=(\d+)/', $signatureHeader, $t);
        preg_match('/v1=([a-f0-9]+)/', $signatureHeader, $v);

        if (empty($t[1]) || empty($v[1])) {
            return false;
        }

        $expected = hash_hmac('sha256', $t[1] . '.' . $rawBody, $secret);

        return hash_equals($expected, $v[1]);
    }
}
