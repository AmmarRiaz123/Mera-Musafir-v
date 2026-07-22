<?php

namespace App\Services;

use App\Models\BlockedUser;
use App\Models\CommunityPost;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

/**
 * Ranks the community feed.
 *
 * The brief asks for "recent + destination-relevant + followed agencies". Rather
 * than sorting purely by date, each post gets a score and the feed is ordered by
 * it. Scoring happens in SQL so the database can paginate — scoring in PHP would
 * mean loading every post in the table to rank them.
 */
class FeedService
{
    /** Points awarded per signal. */
    private const W_FOLLOWED_AUTHOR = 40;
    private const W_FOLLOWED_AGENCY = 35;
    private const W_DESTINATION     = 25;
    private const W_ENGAGEMENT      = 2;   // per like + comment, capped below
    private const ENGAGEMENT_CAP    = 20;

    public function feed(?User $user, array $filters = [], int $perPage = 10): LengthAwarePaginator
    {
        $query = CommunityPost::query()
            ->visible()
            ->with(['author', 'destination']);

        // A specific place's feed (destination detail page).
        if (!empty($filters['destination_id'])) {
            $query->where('destination_id', $filters['destination_id']);
        }

        if (!empty($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        if (!empty($filters['user_id'])) {
            $query->where('user_id', $filters['user_id']);
        }

        if ($user) {
            // Never show posts by someone in a block relationship, either way.
            $blockedIds = BlockedUser::relatedIds($user->id);
            if ($blockedIds->isNotEmpty()) {
                $query->whereNotIn('user_id', $blockedIds);
            }
        }

        $query->select('community_posts.*');

        // "Recommended" is the ranked feed; the others are plain, predictable
        // orders for people who'd rather browse than be curated.
        match ($filters['sort'] ?? 'recommended') {
            'new' => $query->orderByDesc('created_at'),

            'top' => $query
                ->orderByDesc('likes_count')
                ->orderByDesc('comments_count')
                ->orderByDesc('created_at'),

            'discussed' => $query
                ->orderByDesc('comments_count')
                ->orderByDesc('created_at'),

            default => $query
                ->selectRaw($this->scoreExpression($user) . ' as feed_score')
                ->orderByDesc('feed_score')
                ->orderByDesc('created_at'),
        };

        return $query->paginate($perPage);
    }

    /**
     * Builds the ranking expression.
     *
     * Recency is the base: a post loses points as it ages, so a great old post
     * eventually yields to fresh ones. Everything else is additive on top.
     */
    private function scoreExpression(?User $user): string
    {
        // Recency: 60 points, decaying to 0 over ~14 days.
        $parts = ['GREATEST(0, 60 - (TIMESTAMPDIFF(HOUR, community_posts.created_at, NOW()) / 5.6))'];

        // Engagement, capped so one viral post can't dominate forever.
        $engagement = self::W_ENGAGEMENT;
        $cap        = self::ENGAGEMENT_CAP;
        $parts[]    = "LEAST({$cap}, (community_posts.likes_count + community_posts.comments_count) * {$engagement})";

        if (!$user) {
            return implode(' + ', $parts);
        }

        $userId = (int) $user->id;

        // People you follow.
        $w = self::W_FOLLOWED_AUTHOR;
        $parts[] = "(CASE WHEN community_posts.user_id IN (
                        SELECT following_id FROM user_follows WHERE follower_id = {$userId}
                    ) THEN {$w} ELSE 0 END)";

        // Agencies you follow (the post's author owns the agency).
        $w = self::W_FOLLOWED_AGENCY;
        $parts[] = "(CASE WHEN community_posts.user_id IN (
                        SELECT a.user_id FROM agencies a
                        JOIN agency_followers af ON af.agency_id = a.id
                        WHERE af.user_id = {$userId}
                    ) THEN {$w} ELSE 0 END)";

        // Destinations you've actually travelled to.
        $w = self::W_DESTINATION;
        $parts[] = "(CASE WHEN community_posts.destination_id IN (
                        SELECT t.destination_id FROM trips t
                        JOIN trip_members tm ON tm.trip_id = t.id
                        WHERE tm.user_id = {$userId} AND tm.status = 'joined'
                    ) THEN {$w} ELSE 0 END)";

        return implode(' + ', $parts);
    }

    /** Keep the denormalised counters honest after a like/comment changes. */
    public function syncCounters(CommunityPost $post): void
    {
        $post->forceFill([
            'likes_count'    => DB::table('post_likes')->where('community_post_id', $post->id)->count(),
            'comments_count' => DB::table('post_comments')
                ->where('community_post_id', $post->id)
                ->whereNull('deleted_at')
                ->count(),
        ])->save();
    }
}
