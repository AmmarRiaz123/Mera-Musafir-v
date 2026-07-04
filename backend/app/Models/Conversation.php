<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Conversation extends Model
{
    protected $fillable = ['user_one_id', 'user_two_id', 'last_message_at'];

    protected function casts(): array
    {
        return [
            'last_message_at' => 'datetime',
        ];
    }

    public function userOne()
    {
        return $this->belongsTo(User::class, 'user_one_id');
    }

    public function userTwo()
    {
        return $this->belongsTo(User::class, 'user_two_id');
    }

    public function messages()
    {
        return $this->hasMany(ConversationMessage::class);
    }

    public function getOtherUser(int $authUserId): User
    {
        return $authUserId === $this->user_one_id ? $this->userTwo : $this->userOne;
    }

    public static function findOrCreateBetween(int $userAId, int $userBId): self
    {
        [$one, $two] = $userAId < $userBId ? [$userAId, $userBId] : [$userBId, $userAId];

        return static::firstOrCreate([
            'user_one_id' => $one,
            'user_two_id' => $two,
        ]);
    }
}
