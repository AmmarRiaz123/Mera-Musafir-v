<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'type' => $this->type,
            'avatar' => $this->avatar,
            'bio' => $this->bio,
            'city' => $this->city,
            'gender' => $this->gender,
            'reputation_score' => $this->reputation_score,
            'is_verified' => $this->is_verified,
            'is_blocked' => $this->is_blocked,
            'preferences' => $this->preferences,
            'roles' => $this->whenLoaded('roles', function () {
                return $this->roles->pluck('name');
            }),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
