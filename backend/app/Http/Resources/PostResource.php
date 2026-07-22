<?php

namespace App\Http\Resources;

use App\Support\ImageUrl;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PostResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        // Community routes are public, so resolve the optional bearer token
        // through sanctum — auth() alone is never authenticated here.
        $authUser = auth('sanctum')->user();

        return [
            'id'             => $this->id,
            'type'           => $this->type,
            'body'           => $this->body,
            'media_url'      => ImageUrl::resolve($this->media_url),
            'media_type'     => $this->media_type,
            'audio'          => $this->audio,
            'likes_count'    => (int) $this->likes_count,
            'comments_count' => (int) $this->comments_count,
            'is_liked'       => $authUser ? $this->isLikedBy($authUser->id) : false,
            'is_mine'        => $authUser ? $authUser->id === $this->user_id : false,
            'is_flagged'     => (bool) $this->is_flagged,
            'author'         => [
                'id'          => $this->author?->id,
                'name'        => $this->author?->name,
                'avatar'      => ImageUrl::resolve($this->author?->avatar),
                'is_verified' => (bool) $this->author?->is_verified,
                // Agency posts are styled differently in the feed.
                'is_agency'   => $this->author?->type === 'agency',
                'agency_name' => $this->author?->agency?->business_name,
                'agency_slug' => $this->author?->agency?->slug,
            ],
            'destination'    => $this->whenLoaded('destination', fn () => $this->destination ? [
                'id'   => $this->destination->id,
                'name' => $this->destination->name,
                'slug' => $this->destination->slug,
            ] : null),
            'created_at'     => $this->created_at?->toDateTimeString(),
        ];
    }
}
