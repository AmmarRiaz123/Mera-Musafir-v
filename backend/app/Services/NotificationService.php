<?php

namespace App\Services;

use App\Events\NotificationCreated;
use App\Models\Notification;
use App\Models\User;
use App\Support\ImageUrl;
use Illuminate\Database\Eloquent\Model;

/**
 * Creates notifications and pushes them live.
 *
 * One method the whole app calls. It snapshots the actor and the copy onto the
 * row so the feed never has to reassemble a notification from tables that may
 * have changed since — and it broadcasts the same shape the list endpoint
 * returns, so a live arrival and a page load look identical to the client.
 */
class NotificationService
{
    /**
     * @param  User        $recipient  who sees it
     * @param  string      $type       one of Notification::CATEGORY's keys
     * @param  array       $copy       ['title' => ..., 'body' => ..., 'link' => ...]
     * @param  User|null   $actor      who caused it
     * @param  Model|null  $subject    what it's about (for the polymorphic link)
     */
    public function push(
        User $recipient,
        string $type,
        array $copy,
        ?User $actor = null,
        ?Model $subject = null,
    ): ?Notification {
        // Never notify someone about their own action — liking your own post,
        // commenting on it, paying yourself. Nothing to tell you that you don't
        // already know.
        if ($actor && $actor->id === $recipient->id) {
            return null;
        }

        $notification = Notification::create([
            'user_id'      => $recipient->id,
            'actor_id'     => $actor?->id,
            'type'         => $type,
            'subject_type' => $subject?->getMorphClass(),
            'subject_id'   => $subject?->getKey(),
            'data'         => [
                'title' => $copy['title'],
                'body'  => $copy['body'] ?? null,
                'link'  => $copy['link'] ?? null,
                'actor' => $actor ? [
                    'id'     => $actor->id,
                    'name'   => $actor->name,
                    'avatar' => ImageUrl::resolve($actor->avatar),
                ] : null,
            ],
        ]);

        $payload = (new \App\Http\Resources\NotificationResource($notification))
            ->resolve();

        broadcast(new NotificationCreated($notification, $payload));

        return $notification;
    }
}
