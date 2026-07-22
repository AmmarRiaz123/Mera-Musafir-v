<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    protected $fillable = [
        'user_id', 'payable_type', 'payable_id', 'gateway', 'reference',
        'gateway_reference', 'amount', 'commission', 'net_amount',
        'commission_rate', 'status', 'failure_reason', 'gateway_payload',
        'paid_at', 'refunded_at',
    ];

    protected $casts = [
        'amount'          => 'integer',
        'commission'      => 'integer',
        'net_amount'      => 'integer',
        'commission_rate' => 'float',
        'gateway_payload' => 'array',
        'paid_at'         => 'datetime',
        'refunded_at'     => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function payable()
    {
        return $this->morphTo();
    }

    public function isSettled(): bool
    {
        return $this->status === 'succeeded';
    }
}
