<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PackageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                  => $this->id,
            'title'               => $this->title,
            'slug'                => $this->slug,
            'description'         => $this->description,
            'price_per_person'    => $this->price_per_person,
            'formatted_price'     => 'PKR ' . number_format($this->price_per_person),
            'max_capacity'        => $this->max_capacity,
            'booked_count'        => $this->booked_count,
            'spots_left'          => $this->spotsLeft(),
            'is_full'             => $this->isFull(),
            'start_date'          => $this->start_date->format('Y-m-d'),
            'end_date'            => $this->end_date->format('Y-m-d'),
            'duration_days'       => $this->duration_days,
            'includes'            => $this->includes ?? [],
            'itinerary_overview'  => $this->itinerary_overview,
            'cover_image'         => $this->cover_image,
            'gallery'             => $this->gallery ?? [],
            'type'                => $this->type,
            'status'              => $this->status,
            'views_count'         => $this->views_count,
            'agency'              => new AgencyResource($this->whenLoaded('agency')),
            'destination'         => new DestinationResource($this->whenLoaded('destination')),
            'trip_id'             => $this->trip?->id,
            'created_at'          => $this->created_at->toDateTimeString(),
        ];
    }
}
