<?php

namespace App\Policies;

use App\Models\CommunityPost;
use App\Models\User;

class CommunityPostPolicy
{
    /** Admins can moderate anything. */
    public function before(User $user): ?bool
    {
        return $user->type === 'admin' ? true : null;
    }

    public function update(User $user, CommunityPost $post): bool
    {
        return $user->id === $post->user_id;
    }

    public function delete(User $user, CommunityPost $post): bool
    {
        return $user->id === $post->user_id;
    }
}
