<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Support\ImageUrl;

class Trip extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'creator_id',
        'destination_id',
        'package_id',
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

    // A trip may originate from an agency package booking
    public function package()
    {
        return $this->belongsTo(AgencyPackage::class);
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

    // People waiting for the host to approve their join request.
    public function pendingMembers()
    {
        return $this->belongsToMany(User::class, 'trip_members')
                    ->withPivot('status', 'role', 'joined_at')
                    ->wherePivot('status', 'pending')
                    ->withTimestamps();
    }

    // Check if trip is full
    public function isFull(): bool
    {
        return $this->current_count >= $this->max_travelers;
    }

    public function chat()
    {
        return $this->hasOne(GroupChat::class);
    }

    public function itineraryDays()
    {
        return $this->hasMany(ItineraryDay::class)->orderBy('day_number');
    }

    public function expenses()
    {
        return $this->hasMany(Expense::class);
    }

    public function checklistItems()
    {
        return $this->hasMany(ChecklistItem::class)->orderBy('order_index');
    }

    // Check if a user is already a member
    /**
     * Only counts *active* membership. A row with status 'left' or 'declined'
     * is history — it must not block the user from joining again.
     */
    public function hasMember(int $userId): bool
    {
        return $this->members()
                    ->where('user_id', $userId)
                    ->wherePivotIn('status', ['joined', 'pending'])
                    ->exists();
    }

    // Persist image columns as relative paths (see ImageUrl::toPath).
    public function setCoverImageAttribute($value): void
    {
        $this->attributes['cover_image'] = ImageUrl::toPath($value);
    }
}
