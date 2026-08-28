<?php

namespace App\Policies;

use App\Models\Call;
use App\Models\Extension;
use App\Models\User;

class CallPolicy
{
    private function isManager(User $user): bool
    {
        return in_array($user->role, ['admin', 'director', 'helpline_manager'], true);
    }

    private function ownsExtension(User $user, Call $call): bool
    {
        if (! $call->extension_number) {
            return false;
        }

        return Extension::where('user_id', $user->id)
            ->where('extension_number', $call->extension_number)
            ->exists();
    }

    public function view(User $user, Call $call): bool
    {
        return $this->isManager($user) || $this->ownsExtension($user, $call);
    }

    public function viewRecording(User $user, Call $call): bool
    {
        return $this->view($user, $call);
    }

    public function viewTranscript(User $user, Call $call): bool
    {
        return $this->view($user, $call);
    }

    public function transcribe(User $user, Call $call): bool
    {
        return $this->isManager($user);
    }

    public function exportTranscript(User $user, Call $call): bool
    {
        return $this->isManager($user);
    }
}
