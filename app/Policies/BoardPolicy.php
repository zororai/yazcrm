<?php

namespace App\Policies;

use App\Models\Board;
use App\Models\User;

class BoardPolicy
{
    private function isManager(User $user): bool
    {
        return in_array($user->role, ['admin', 'director'], true);
    }

    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Board $board): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Board $board): bool
    {
        return $this->isManager($user) || $board->owner_id === $user->id;
    }

    public function delete(User $user, Board $board): bool
    {
        return $this->isManager($user) || $board->owner_id === $user->id;
    }

    // Also governs creating/renaming/reordering/archiving the board's groups.
    public function manageGroups(User $user, Board $board): bool
    {
        return $this->update($user, $board);
    }
}
