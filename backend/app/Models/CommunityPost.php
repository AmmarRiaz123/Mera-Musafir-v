<?php

namespace App\Models;

use App\Support\ImageUrl;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CommunityPost extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'user_id', 'destination_id', 'type', 'body',
        'media_url', 'media_type', 'gallery', 'audio',
        'is_flagged', 'is_hidden',
    ];

    protected function casts(): array
    {
        return [
            'is_flagged' => 'boolean',
            'is_hidden'  => 'boolean',
            'audio'      => 'array',
            'gallery'    => 'array',
        ];
    }

    public function author()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function destination()
    {
        return $this->belongsTo(Destination::class);
    }

    public function likes()
    {
        return $this->hasMany(PostLike::class);
    }

    public function comments()
    {
        return $this->hasMany(PostComment::class)->whereNull('post_comments.deleted_at');
    }

    /** Everything a traveller is allowed to see. */
    public function scopeVisible(Builder $query): Builder
    {
        // A suspended author's posts drop out everywhere at once — this scope
        // backs the feed, the community page, destination pages and profiles.
        return $query->where('is_hidden', false)
            ->whereHas('author', fn ($q) => $q->where('is_blocked', false));
    }

    public function isLikedBy(?int $userId): bool
    {
        return $userId
            ? $this->likes()->where('user_id', $userId)->exists()
            : false;
    }

    // Uploaded media is stored as a relative path; an external GIF URL is kept
    // as-is (see ImageUrl::toPath).
    public function setMediaUrlAttribute($value): void
    {
        $this->attributes['media_url'] = ImageUrl::toPath($value);
    }
}
