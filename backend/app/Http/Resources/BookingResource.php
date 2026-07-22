<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BookingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'              => $this->id,
            'travelers_count' => $this->travelers_count,
            'total_amount'    => $this->total_amount,
            'status'          => $this->status,
            'payment_status'  => $this->payment_status,
            'approved_at'     => $this->approved_at?->toDateTimeString(),
            'payment_due_at'  => $this->payment_due_at?->toDateTimeString(),
            // The one thing the UI actually branches on: is there a bill to pay?
            'awaiting_payment' => $this->status === 'approved' && $this->payment_status === 'unpaid',
            'notes'           => $this->notes,
            'confirmed_at'    => $this->confirmed_at?->toDateTimeString(),
            'cancelled_at'    => $this->cancelled_at?->toDateTimeString(),
            'created_at'      => $this->created_at->toDateTimeString(),
            'package'         => new PackageResource($this->whenLoaded('agencyPackage')),
            'user'            => $this->when($this->relationLoaded('user'), fn() => [
                'id'    => $this->user->id,
                'name'  => $this->user->name,
                'email' => $this->user->email,
                'phone' => $this->user->phone,
            ]),
        ];
    }
}
