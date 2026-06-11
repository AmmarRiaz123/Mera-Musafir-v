<?php

namespace App\Events;

use App\Models\Trip;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ExpenseUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        private Trip $trip,
        private array $expenses,
        private array $settlement
    ) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('trip.' . $this->trip->id)];
    }

    public function broadcastAs(): string
    {
        return 'expense.updated';
    }

    public function broadcastWith(): array
    {
        return [
            'expenses'   => $this->expenses,
            'settlement' => $this->settlement,
        ];
    }
}
