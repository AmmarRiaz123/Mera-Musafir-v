<?php

namespace App\Services\Payments;

use InvalidArgumentException;

/** Resolves gateway drivers by name and reports which ones are usable. */
class GatewayManager
{
    private const DRIVERS = [
        'sandbox'   => SandboxGateway::class,
        'jazzcash'  => JazzCashGateway::class,
        'easypaisa' => EasyPaisaGateway::class,
        'stripe'    => StripeGateway::class,
    ];

    /** Labels and blurbs live here so every screen names them identically. */
    private const META = [
        'jazzcash'  => ['label' => 'JazzCash',  'blurb' => 'Pay from your JazzCash wallet'],
        'easypaisa' => ['label' => 'EasyPaisa', 'blurb' => 'Pay from your EasyPaisa wallet'],
        'stripe'    => ['label' => 'Card',      'blurb' => 'Debit or credit card'],
        'sandbox'   => ['label' => 'Test payment', 'blurb' => 'Settles instantly — no real money moves'],
    ];

    public function driver(?string $name = null): PaymentGateway
    {
        $name ??= config('payments.default');

        if (!isset(self::DRIVERS[$name])) {
            throw new InvalidArgumentException("Unknown payment gateway [{$name}].");
        }

        return app(self::DRIVERS[$name]);
    }

    /**
     * The methods a user can actually pick right now.
     *
     * An unconfigured gateway is left out rather than shown and then failing —
     * offering JazzCash before the merchant account exists would just produce a
     * dead end at the worst possible moment.
     */
    public function available(): array
    {
        return collect(self::DRIVERS)
            ->map(fn ($class, $name) => $this->driver($name))
            ->filter(fn (PaymentGateway $g) => $g->isConfigured())
            ->map(fn (PaymentGateway $g) => [
                'name'  => $g->name(),
                'label' => self::META[$g->name()]['label'],
                'blurb' => self::META[$g->name()]['blurb'],
            ])
            ->values()
            ->all();
    }
}
