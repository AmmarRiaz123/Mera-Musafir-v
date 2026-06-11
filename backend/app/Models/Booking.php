<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Booking extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id', 'agency_package_id', 'travelers_count', 'total_amount',
        'status', 'payment_status', 'notes', 'confirmed_at', 'cancelled_at',
    ];

    protected $casts = [
        'travelers_count' => 'integer',
        'total_amount'    => 'integer',
        'confirmed_at'    => 'datetime',
        'cancelled_at'    => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function agencyPackage()
    {
        return $this->belongsTo(AgencyPackage::class);
    }
}
