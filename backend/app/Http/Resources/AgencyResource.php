<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AgencyResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'             => $this->id,
            'business_name'  => $this->business_name,
            'slug'           => $this->slug,
            'description'    => $this->description,
            'logo'           => $this->logo,
            'cover_image'    => $this->cover_image,
            'tier'           => $this->tier,
            'is_verified'    => $this->is_verified,
            'verified_at'    => $this->verified_at?->toDateTimeString(),
            'contact_phone'  => $this->contact_phone,
            'contact_email'  => $this->contact_email,
            'follower_count' => $this->follower_count,
            'total_trips'    => $this->total_trips,
            'packages_count' => $this->whenCounted('packages'),
            'user'           => new UserResource($this->whenLoaded('user')),
            'is_following'   => auth()->check()
                ? $this->followers()->where('user_id', auth()->id())->exists()
                : false,
            'created_at'     => $this->created_at->toDateTimeString(),
        ];
    }
}
