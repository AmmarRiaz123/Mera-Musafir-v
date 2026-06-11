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
