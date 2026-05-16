<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class IncomingCallEvent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public readonly array $callData)
    {
    }

    public function broadcastOn(): array
    {
        return [new Channel('calls')];
    }

    public function broadcastAs(): string
    {
        return 'incoming-call';
    }

    public function broadcastWith(): array
    {
        return $this->callData;
    }
}
