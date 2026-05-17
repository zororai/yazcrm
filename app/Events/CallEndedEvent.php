<?php

namespace App\Events;

use App\Models\Call;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use App\Models\Extension;

class CallEndedEvent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public readonly Call $call) {}

    public function broadcastOn(): array
    {
        // Find the agent assigned to the extension that handled this call
        $userId = Extension::where('extension_number', $this->call->extension_number)
            ->value('user_id');

        // No assigned agent — skip broadcast entirely
        if (!$userId) return [];

        return [new PrivateChannel('agent.' . $userId)];
    }

    public function broadcastAs(): string
    {
        return 'call-ended';
    }

    public function broadcastWith(): array
    {
        return [
            'call_id'   => $this->call->id,
            'caller'    => $this->call->caller,
            'callee'    => $this->call->callee,
            'duration'  => $this->call->duration,
            'direction' => $this->call->direction,
            'client'    => $this->call->client
                ? $this->call->client->only(['id', 'name', 'phone'])
                : null,
        ];
    }
}
