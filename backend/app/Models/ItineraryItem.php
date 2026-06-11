<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class ItineraryItem extends Model
{
    use HasFactory;

    protected $fillable = ['itinerary_day_id', 'title', 'time', 'location', 'latitude', 'longitude', 'notes', 'order_index', 'created_by'];

    protected $casts = [
        'order_index' => 'integer',
        'latitude'    => 'float',
        'longitude'   => 'float',
    ];

    public function day()
    {
        return $this->belongsTo(ItineraryDay::class, 'itinerary_day_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
