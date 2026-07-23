<?php

namespace App\Events;

use App\Models\CommunityPost;
use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * A new community post exists. Broadcasts a lightweight signal on a public
 * channel — just the id and author — so open feeds can show a "new posts" pill
 * and re-fetch on click. Deliberately doesn't carry the post body: re-fetching
 * re-applies every per-viewer filter (blocks, suspensions, ranking) that a raw
 * broadcast payload couldn't.
 */
class PostCreated implements ShouldBroadcastNow
{
    use Dispatchable, SerializesModels;

    public function __construct(public CommunityPost $post)
    {
    }

    public function broadcastOn(): array
    {
        return [new Channel('community')];
    }

    public function broadcastAs(): string
    {
        return 'post.created';
    }

    public function broadcastWith(): array
    {
        return [
            'id'        => $this->post->id,
            'author_id' => $this->post->user_id,
        ];
    }
}
