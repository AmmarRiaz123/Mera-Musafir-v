<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BlockedUser extends Model
{
    protected $fillable = ['blocker_id', 'blocked_id'];

    public function blocker()
    {
        return $this->belongsTo(User::class, 'blocker_id');
    }

    public function blocked()
    {
        return $this->belongsTo(User::class, 'blocked_id');
    }

    /**
     * All user IDs in a block relationship with $userId, in EITHER direction
     * (people they blocked + people who blocked them).
     */
    public static function relatedIds(int $userId)
    {
        return static::where('blocker_id', $userId)->pluck('blocked_id')
            ->merge(static::where('blocked_id', $userId)->pluck('blocker_id'))
            ->unique()
            ->values();
    }

    /**
     * True if either user has blocked the other.
     */
    public static function blockExistsBetween(int $a, int $b): bool
    {
        return static::where(function ($q) use ($a, $b) {
            $q->where('blocker_id', $a)->where('blocked_id', $b);
        })->orWhere(function ($q) use ($a, $b) {
            $q->where('blocker_id', $b)->where('blocked_id', $a);
        })->exists();
    }
}
