<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class NotificationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        // Almost everything the card shows was snapshotted into `data` at
        // creation, so it renders from one row and survives the subject being
        // deleted later.
        $data = $this->data ?? [];

        return [
            'id'         => $this->id,
            'type'       => $this->type,
            'category'   => $this->category(),
            'title'      => $data['title'] ?? '',
            'body'       => $data['body'] ?? null,
            'link'       => $data['link'] ?? null,
            'actor'      => $data['actor'] ?? null,       // {id, name, avatar}
            'is_read'    => $this->read_at !== null,
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}
