<?php

namespace App\Models;

use App\Support\ImageUrl;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PostComment extends Model
{
    use SoftDeletes;

    protected $fillable = ['community_post_id', 'user_id', 'body', 'media_url', 'media_type', 'is_hidden'];

    protected function casts(): array
    {
        return ['is_hidden' => 'boolean'];
    }

    // Uploaded media stores a relative path; a Giphy URL is kept as-is.
    public function setMediaUrlAttribute($value): void
    {
        $this->attributes['media_url'] = ImageUrl::toPath($value);
    }

    public function post()
    {
        return $this->belongsTo(CommunityPost::class, 'community_post_id');
    }

    public function author()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
