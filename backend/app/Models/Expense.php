<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Expense extends Model
{
    use HasFactory;

    protected $fillable = ['trip_id', 'paid_by_id', 'amount', 'description', 'split_type'];

    protected $casts = ['amount' => 'integer'];

    public function trip()
    {
        return $this->belongsTo(Trip::class);
    }

    public function paidBy()
    {
        return $this->belongsTo(User::class, 'paid_by_id');
    }

    public function shares()
    {
        return $this->hasMany(ExpenseShare::class);
    }
}
