<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class ItineraryDay extends Model
{
    use HasFactory;

    protected $fillable = ['trip_id', 'day_number', 'date'];

    protected $casts = ['date' => 'date'];

    public function trip()
    {
        return $this->belongsTo(Trip::class);
    }

    public function items()
    {
        return $this->hasMany(ItineraryItem::class)->orderBy('order_index');
    }
}
