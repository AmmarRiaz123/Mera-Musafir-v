<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TripResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'             => $this->id,
            'title'          => $this->title,
            'description'    => $this->description,
            'cover_image'    => $this->cover_image,
            'start_date'     => $this->start_date->format('Y-m-d'),
            'end_date'       => $this->end_date->format('Y-m-d'),
            'max_travelers'  => $this->max_travelers,
            'current_count'  => $this->current_count,
            'spots_left'     => $this->max_travelers - $this->current_count,
            'is_full'        => $this->isFull(),
            'budget_min'     => $this->budget_min,
            'budget_max'     => $this->budget_max,
            'type'           => $this->type,
            'visibility'     => $this->visibility,
            'status'         => $this->status,
            'creator'        => new UserResource($this->whenLoaded('creator')),
            'destination'    => new DestinationResource($this->whenLoaded('destination')),
            'members'        => UserResource::collection($this->whenLoaded('joinedMembers')),
            'members_count'  => $this->current_count,
            'created_at'     => $this->created_at->toDateTimeString(),
        ];
    }
}