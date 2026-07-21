<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Support\ImageUrl;

class AgencyPackage extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'agency_id', 'destination_id', 'title', 'slug', 'description',
        'price_per_person', 'max_capacity', 'booked_count', 'start_date', 'end_date',
        'duration_days', 'includes', 'itinerary_overview', 'cover_image', 'gallery',
        'type', 'status', 'views_count',
    ];

    protected $casts = [
        'price_per_person'    => 'integer',
        'max_capacity'        => 'integer',
        'booked_count'        => 'integer',
        'duration_days'       => 'integer',
        'views_count'         => 'integer',
        'includes'            => 'array',
        'itinerary_overview'  => 'array',
        'gallery'             => 'array',
        'start_date'          => 'date',
        'end_date'            => 'date',
    ];

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function agency()
    {
        return $this->belongsTo(Agency::class);
    }

    public function destination()
    {
        return $this->belongsTo(Destination::class);
    }

    public function bookings()
    {
        return $this->hasMany(Booking::class);
    }

    public function trip()
    {
        return $this->hasOne(Trip::class, 'package_id');
    }

    public function isFull(): bool
    {
        return $this->booked_count >= $this->max_capacity;
    }

    public function spotsLeft(): int
    {
        return max(0, $this->max_capacity - $this->booked_count);
    }

    // Persist image columns as relative paths (see ImageUrl::toPath).
    public function setCoverImageAttribute($value): void
    {
        $this->attributes['cover_image'] = ImageUrl::toPath($value);
    }
}
