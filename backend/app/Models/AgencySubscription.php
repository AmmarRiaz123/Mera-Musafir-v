<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class AgencySubscription extends Model
{
    protected $fillable = [
        'agency_id', 'tier', 'period', 'amount', 'status', 'starts_at', 'ends_at',
    ];

    protected $casts = [
        'amount'    => 'integer',
        'starts_at' => 'datetime',
        'ends_at'   => 'datetime',
    ];

    public function agency()
    {
        return $this->belongsTo(Agency::class);
    }

    public function payments(): MorphMany
    {
        return $this->morphMany(Payment::class, 'payable');
    }
}
