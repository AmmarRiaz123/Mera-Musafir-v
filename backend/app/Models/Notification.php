<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Notification extends Model
{
    protected $fillable = [
        'user_id', 'actor_id', 'type', 'subject_type', 'subject_id', 'data', 'read_at',
    ];

    protected $casts = [
        'data'    => 'array',
        'read_at' => 'datetime',
    ];

    /**
     * Which sidebar tab a red dot belongs on. Kept next to the types it maps so
     * the frontend and backend can't disagree about where a follow shows up.
     */
    public const CATEGORY = [
        'follow'           => 'profile',
        'comment'          => 'community',
        'like'             => 'community',
        'message'          => 'messages',
        'booking_request'  => 'agency',
        'booking_paid'     => 'agency',
        'booking_approved' => 'bookings',
        'trip_join'        => 'trips',
        'announcement'     => 'other',
    ];

    public function actor()
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    public function category(): string
    {
        return self::CATEGORY[$this->type] ?? 'other';
    }
}
