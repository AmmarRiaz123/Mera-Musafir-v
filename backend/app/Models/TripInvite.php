<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TripInvite extends Model
{
    protected $fillable = [
        'trip_id',
        'inviter_id',
        'invitee_id',
        'conversation_message_id',
        'status',
    ];

    public function trip()
    {
        return $this->belongsTo(Trip::class);
    }

    public function inviter()
    {
        return $this->belongsTo(User::class, 'inviter_id');
    }

    public function invitee()
    {
        return $this->belongsTo(User::class, 'invitee_id');
    }

    public function conversationMessage()
    {
        return $this->belongsTo(ConversationMessage::class, 'conversation_message_id');
    }
}
