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
        'user_id', 'destination_id', 'type', 'body', 'image',
        'is_flagged', 'is_hidden',
    ];

    protected function casts(): array
    {
        return [
            'is_flagged' => 'boolean',
            'is_hidden'  => 'boolean',
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
        return $query->where('is_hidden', false);
    }

    public function isLikedBy(?int $userId): bool
    {
        return $userId
            ? $this->likes()->where('user_id', $userId)->exists()
            : false;
    }

    // Images are stored as relative paths (see ImageUrl::toPath).
    public function setImageAttribute($value): void
    {
        $this->attributes['image'] = ImageUrl::toPath($value);
    }
}
