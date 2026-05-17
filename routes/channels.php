<?php

use Illuminate\Support\Facades\Broadcast;

// Each agent can only subscribe to their own private channel
Broadcast::channel('agent.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});
