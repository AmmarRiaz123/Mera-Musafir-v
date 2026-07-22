<?php

namespace App\Services\Payments;

use App\Models\Payment;
use Illuminate\Support\Facades\Log;

/**
 * JazzCash — Pakistan's most-used wallet, so the default once we're live.
 *
 * Written to JazzCash's HTTP form spec: the browser POSTs a signed field set to
 * their hosted page and they redirect back with a result. What's missing is the
 * merchant id, password and integrity salt, which JazzCash only issues to a
 * registered business — so isConfigured() is false and the UI hides it until
 * those land in .env. Nothing else has to change when they do.
 */
class JazzCashGateway implements PaymentGateway
{
    private array $config;

    public function __construct()
    {
        $this->config = config('payments.gateways.jazzcash');
    }

    public function name(): string
    {
        return 'jazzcash';
    }

    public function isConfigured(): bool
    {
        return filled($this->config['merchant_id'])
            && filled($this->config['password'])
            && filled($this->config['integrity_salt']);
    }

    public function charge(Payment $payment, array $options = []): GatewayResult
    {
        if (!$this->isConfigured()) {
            Log::info('JazzCash not configured — set JAZZCASH_MERCHANT_ID / PASSWORD / INTEGRITY_SALT.');

            return GatewayResult::failed('This payment method is not available yet.');
        }

        $fields = [
            'pp_Version'          => '2.0',
            'pp_TxnType'          => 'MWALLET',
            'pp_Language'         => 'EN',
            'pp_MerchantID'       => $this->config['merchant_id'],
            'pp_Password'         => $this->config['password'],
            'pp_TxnRefNo'         => $payment->reference,
            // JazzCash quotes amounts in paisa, unlike everything else here.
            'pp_Amount'           => $payment->amount * 100,
            'pp_TxnCurrency'      => 'PKR',
            'pp_TxnDateTime'      => now()->format('YmdHis'),
            'pp_TxnExpiryDateTime' => now()->addHour()->format('YmdHis'),
            'pp_BillReference'    => $payment->reference,
            'pp_Description'      => 'Mera Musafir payment',
            'pp_ReturnURL'        => route('payments.callback', ['gateway' => 'jazzcash']),
        ];

        $fields['pp_SecureHash'] = $this->secureHash($fields);

        return GatewayResult::redirect($this->config['endpoint'], $fields);
    }

    public function handleCallback(array $payload): ?GatewayResult
    {
        $received = $payload['pp_SecureHash'] ?? null;
        $expected = $this->secureHash($payload);

        // A forged callback must never be able to mark a booking paid.
        if (!$received || !hash_equals($expected, $received)) {
            Log::warning('JazzCash callback rejected — secure hash mismatch.', [
                'ref' => $payload['pp_TxnRefNo'] ?? null,
            ]);

            return null;
        }

        // "000" is success; "121" is their in-progress code.
        $code = $payload['pp_ResponseCode'] ?? null;

        if ($code === '000') {
            return GatewayResult::succeeded($payload['pp_RetreivalReferenceNo'] ?? '', $payload);
        }

        return GatewayResult::failed(
            $payload['pp_ResponseMessage'] ?? 'The payment did not go through.',
            $payload,
        );
    }

    public function refund(Payment $payment): GatewayResult
    {
        // JazzCash refunds are raised through their merchant portal, not the
        // API tier available to us — the admin marks it and settles manually.
        return GatewayResult::failed('JazzCash refunds are processed from the merchant portal.');
    }

    /**
     * HMAC-SHA256 over every non-empty pp_* field in key order, salt-prefixed.
     * Their spec is specific about all three of those things.
     */
    private function secureHash(array $fields): string
    {
        $fields = array_filter(
            $fields,
            fn ($v, $k) => str_starts_with($k, 'pp_') && $k !== 'pp_SecureHash' && $v !== '' && $v !== null,
            ARRAY_FILTER_USE_BOTH,
        );

        ksort($fields);

        $string = $this->config['integrity_salt'] . '&' . implode('&', $fields);

        return strtoupper(hash_hmac('sha256', $string, $this->config['integrity_salt']));
    }
}
