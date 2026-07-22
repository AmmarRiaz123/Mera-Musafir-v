<?php

namespace App\Services\Payments;

use App\Models\Payment;
use Illuminate\Support\Facades\Log;

/**
 * EasyPaisa — the second wallet most Pakistanis have.
 *
 * Same shape as JazzCash: a signed form POST to their hosted checkout. Needs a
 * store id and hash key that EasyPaisa issue to registered merchants, so this
 * reports itself unconfigured until those exist.
 */
class EasyPaisaGateway implements PaymentGateway
{
    private array $config;

    public function __construct()
    {
        $this->config = config('payments.gateways.easypaisa');
    }

    public function name(): string
    {
        return 'easypaisa';
    }

    public function isConfigured(): bool
    {
        return filled($this->config['store_id']) && filled($this->config['hash_key']);
    }

    public function charge(Payment $payment, array $options = []): GatewayResult
    {
        if (!$this->isConfigured()) {
            Log::info('EasyPaisa not configured — set EASYPAISA_STORE_ID / HASH_KEY.');

            return GatewayResult::failed('This payment method is not available yet.');
        }

        $fields = [
            'storeId'            => $this->config['store_id'],
            'amount'             => number_format($payment->amount, 1, '.', ''),
            'postBackURL'        => route('payments.callback', ['gateway' => 'easypaisa']),
            'orderRefNum'        => $payment->reference,
            'expiryDate'         => now()->addHour()->format('Ymd His'),
            'merchantHashedReq'  => '',
            'autoRedirect'       => '1',
            'paymentMethod'      => 'MA_PAYMENT_METHOD',
            'emailAddr'          => $payment->user->email,
            'mobileNum'          => $payment->user->phone ?? '',
        ];

        $fields['merchantHashedReq'] = $this->hash($fields);

        return GatewayResult::redirect($this->config['endpoint'], $fields);
    }

    public function handleCallback(array $payload): ?GatewayResult
    {
        $received = $payload['merchantHashedReq'] ?? null;

        if (!$received || !hash_equals($this->hash($payload), $received)) {
            Log::warning('EasyPaisa callback rejected — hash mismatch.', [
                'ref' => $payload['orderRefNum'] ?? null,
            ]);

            return null;
        }

        // 0000 is their success code.
        if (($payload['status'] ?? null) === '0000') {
            return GatewayResult::succeeded($payload['transactionId'] ?? '', $payload);
        }

        return GatewayResult::failed(
            $payload['desc'] ?? 'The payment did not go through.',
            $payload,
        );
    }

    public function refund(Payment $payment): GatewayResult
    {
        return GatewayResult::failed('EasyPaisa refunds are processed from the merchant portal.');
    }

    private function hash(array $fields): string
    {
        unset($fields['merchantHashedReq']);
        ksort($fields);

        $string = implode('&', array_map(
            fn ($k, $v) => "{$k}={$v}",
            array_keys($fields),
            $fields,
        ));

        return base64_encode(openssl_encrypt(
            $string,
            'AES-128-ECB',
            $this->config['hash_key'],
            OPENSSL_RAW_DATA,
        ));
    }
}
