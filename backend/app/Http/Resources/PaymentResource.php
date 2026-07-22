<?php

namespace App\Http\Resources;

use App\Models\AgencySubscription;
use App\Models\Booking;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PaymentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'            => $this->id,
            'reference'     => $this->reference,
            'gateway'       => $this->gateway,
            'amount'        => $this->amount,
            'commission'    => $this->commission,
            'net_amount'    => $this->net_amount,
            'status'        => $this->status,
            'failure_reason' => $this->failure_reason,
            'paid_at'       => $this->paid_at?->toDateTimeString(),
            'refunded_at'   => $this->refunded_at?->toDateTimeString(),
            'created_at'    => $this->created_at->toDateTimeString(),
            'for'           => $this->describePayable(),
        ];
    }

    /** One line naming what was bought, so history reads without extra lookups. */
    private function describePayable(): array
    {
        $payable = $this->payable;

        if ($payable instanceof Booking) {
            return [
                'type'  => 'booking',
                'title' => $payable->agencyPackage?->title ?? 'Package booking',
                'slug'  => $payable->agencyPackage?->slug,
                'meta'  => $payable->travelers_count . ' ' .
                    ($payable->travelers_count === 1 ? 'traveller' : 'travellers'),
            ];
        }

        if ($payable instanceof AgencySubscription) {
            return [
                'type'  => 'subscription',
                'title' => ucfirst($payable->tier) . ' plan',
                'slug'  => null,
                'meta'  => ucfirst($payable->period),
            ];
        }

        return ['type' => 'unknown', 'title' => 'Payment', 'slug' => null, 'meta' => null];
    }
}
