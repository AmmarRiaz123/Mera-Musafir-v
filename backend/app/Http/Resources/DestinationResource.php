<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DestinationResource extends JsonResource
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
            'slug' => $this->slug,
            'province' => $this->province,
            'region' => $this->region,
            'type' => $this->type,
            'description' => $this->description,
            'best_season' => $this->best_season,
            'travel_tips' => $this->travel_tips,
            'cover_image' => $this->cover_image,
            'gallery' => $this->gallery,
            'coordinates' => [
                'lat' => $this->latitude,
                'lng' => $this->longitude,
            ],
            'is_featured' => $this->is_featured,
            'is_active' => $this->is_active,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
