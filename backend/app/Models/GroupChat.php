<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class GroupChat extends Model
{
    use HasFactory;

    protected $fillable = ['trip_id', 'name'];

    public function trip()
    {
        return $this->belongsTo(Trip::class);
    }

    public function messages()
    {
        return $this->hasMany(Message::class, 'chat_id');
    }

    public function members()
    {
        return $this->trip->joinedMembers();
    }
}
