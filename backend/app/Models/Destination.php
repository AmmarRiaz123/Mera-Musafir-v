<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Destination extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'province',
        'region',
        'type',
        'description',
        'best_season',
        'travel_tips',
        'cover_image',
        'gallery',
        'latitude',
        'longitude',
        'is_featured',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'gallery' => 'array',
            'is_featured' => 'boolean',
            'is_active' => 'boolean',
            'latitude' => 'decimal:8',
            'longitude' => 'decimal:8',
        ];
    }

    public function getRouteKeyName()
    {
        return 'slug';
    }
}
