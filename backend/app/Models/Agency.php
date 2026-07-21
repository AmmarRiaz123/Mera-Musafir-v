<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Support\ImageUrl;

class Agency extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id', 'business_name', 'slug', 'description', 'logo', 'cover_image',
        'tier', 'is_verified', 'verified_at', 'license_number', 'contact_phone',
        'contact_email', 'follower_count', 'total_trips', 'subscription_expires_at',
    ];

    protected $casts = [
        'is_verified'             => 'boolean',
        'follower_count'          => 'integer',
        'total_trips'             => 'integer',
        'subscription_expires_at' => 'datetime',
        'verified_at'             => 'datetime',
    ];

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function packages()
    {
        return $this->hasMany(AgencyPackage::class);
    }

    public function followers()
    {
        return $this->belongsToMany(User::class, 'agency_followers', 'agency_id', 'user_id')
                    ->withTimestamps();
    }

    // Returns true if agency tier meets or exceeds the requested level
    public function hasTier(string $tier): bool
    {
        $levels = ['basic' => 1, 'pro' => 2, 'premium' => 3];
        return ($levels[$this->tier] ?? 0) >= ($levels[$tier] ?? 0);
    }

    // Basic is always active; pro/premium require a valid subscription_expires_at
    public function isSubscriptionActive(): bool
    {
        if ($this->tier === 'basic') return true;
        return $this->subscription_expires_at !== null && $this->subscription_expires_at->isFuture();
    }

    // Persist image columns as relative paths (see ImageUrl::toPath).
    public function setLogoAttribute($value): void
    {
        $this->attributes['logo'] = ImageUrl::toPath($value);
    }

    // Persist image columns as relative paths (see ImageUrl::toPath).
    public function setCoverImageAttribute($value): void
    {
        $this->attributes['cover_image'] = ImageUrl::toPath($value);
    }
}
