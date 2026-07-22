<?php

namespace App\Http\Resources;

use App\Support\ImageUrl;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CommentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $authUser = auth('sanctum')->user();

        return [
            'id'         => $this->id,
            'body'       => $this->body,
            'media_url'  => ImageUrl::resolve($this->media_url),
            'media_type' => $this->media_type,
            'created_at' => $this->created_at?->toDateTimeString(),
            'can_delete' => $authUser
                ? ($authUser->id === $this->user_id || $authUser->id === $this->post?->user_id)
                : false,
            'author'     => [
                'id'          => $this->author?->id,
                'name'        => $this->author?->name,
                'avatar'      => ImageUrl::resolve($this->author?->avatar),
                'is_verified' => (bool) $this->author?->is_verified,
            ],
        ];
    }
}
