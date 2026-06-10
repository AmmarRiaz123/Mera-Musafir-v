<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Trip extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'creator_id',
        'destination_id',
        'title',
        'description',
        'cover_image',
        'start_date',
        'end_date',
        'max_travelers',
        'current_count',
        'budget_min',
        'budget_max',
        'type',
        'visibility',
        'status',
    ];

    protected $casts = [
        'start_date'   => 'date',
        'end_date'     => 'date',
        'max_travelers' => 'integer',
        'current_count' => 'integer',
        'budget_min'   => 'integer',
        'budget_max'   => 'integer',
    ];

    // A trip belongs to the user who created it
    public function creator()
    {
        return $this->belongsTo(User::class, 'creator_id');
    }

    // A trip belongs to one destination
    public function destination()
    {
        return $this->belongsTo(Destination::class);
    }

    // A trip has many members through trip_members
    public function members()
    {
        return $this->belongsToMany(User::class, 'trip_members')
                    ->withPivot('status', 'role', 'joined_at')
                    ->withTimestamps();
    }

    // Only confirmed members (status = joined)
    public function joinedMembers()
    {
        return $this->belongsToMany(User::class, 'trip_members')
                    ->withPivot('status', 'role', 'joined_at')
                    ->wherePivot('status', 'joined')
                    ->withTimestamps();
    }

    // Check if trip is full
    public function isFull(): bool
    {
        return $this->current_count >= $this->max_travelers;
    }

    // Check if a user is already a member
    public function hasMember(int $userId): bool
    {
        return $this->members()->where('user_id', $userId)->exists();
    }
}