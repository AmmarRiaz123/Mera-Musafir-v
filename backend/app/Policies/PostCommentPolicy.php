<?php

namespace App\Policies;

use App\Models\PostComment;
use App\Models\User;

class PostCommentPolicy
{
    public function before(User $user): ?bool
    {
        return $user->type === 'admin' ? true : null;
    }

    /** A comment can be removed by its author or by the post's author. */
    public function delete(User $user, PostComment $comment): bool
    {
        return $user->id === $comment->user_id
            || $user->id === $comment->post?->user_id;
    }
}
