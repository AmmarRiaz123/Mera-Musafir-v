<?php

namespace App\Events;

use App\Models\ConversationMessage;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ConversationMessageSent implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public ConversationMessage $message)
    {
        $this->message->loadMissing('sender');
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('conversation.' . $this->message->conversation_id),
        ];
    }

    public function broadcastAs(): string
    {
        return 'message.sent';
    }

    public function broadcastWith(): array
    {
        return [
            'id'              => $this->message->id,
            'body'            => $this->message->body,
            'type'            => $this->message->type,
            'metadata'        => $this->message->metadata,
            'read_at'         => $this->message->read_at,
            'conversation_id' => $this->message->conversation_id,
            'sender'          => [
                'id'          => $this->message->sender->id,
                'name'        => $this->message->sender->name,
                'avatar'      => $this->message->sender->avatar,
                'is_verified' => (bool) $this->message->sender->is_verified,
            ],
            'created_at'      => $this->message->created_at,
        ];
    }
}
