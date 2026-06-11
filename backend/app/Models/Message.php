<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Message extends Model
{
    use HasFactory;

    protected $fillable = ['chat_id', 'sender_id', 'body', 'type', 'metadata', 'read_by'];

    protected $casts = [
        'metadata' => 'array',
        'read_by'  => 'array',
    ];

    public function chat()
    {
        return $this->belongsTo(GroupChat::class, 'chat_id');
    }

    public function sender()
    {
        return $this->belongsTo(User::class, 'sender_id');
    }
}
