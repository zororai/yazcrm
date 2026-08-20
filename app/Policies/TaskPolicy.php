<?php

namespace App\Policies;

use App\Models\Task;
use App\Models\User;

class TaskPolicy
{
    private function isManager(User $user): bool
    {
        return in_array($user->role, ['admin', 'director'], true);
    }

    private function isAssignee(User $user, Task $task): bool
    {
        return $task->assignees()->where('users.id', $user->id)->exists();
    }

    private function isWatcher(User $user, Task $task): bool
    {
        return $task->watchers()->where('users.id', $user->id)->exists();
    }

    private function isSupervisorOfAssignee(User $user, Task $task): bool
    {
        return $task->assignees()->where('users.supervisor_id', $user->id)->exists();
    }

    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Task $task): bool
    {
        return $this->isManager($user)
            || $task->created_by === $user->id
            || $task->board->owner_id === $user->id
            || $this->isAssignee($user, $task)
            || $this->isWatcher($user, $task)
            || $this->isSupervisorOfAssignee($user, $task);
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Task $task): bool
    {
        return $this->isManager($user)
            || $task->created_by === $user->id
            || $this->isAssignee($user, $task);
    }

    public function assign(User $user, Task $task): bool
    {
        return $this->isManager($user)
            || $task->board->owner_id === $user->id
            || $task->created_by === $user->id;
    }

    public function changeStatus(User $user, Task $task): bool
    {
        return $this->update($user, $task);
    }

    public function changePriority(User $user, Task $task): bool
    {
        return $this->update($user, $task);
    }

    public function comment(User $user, Task $task): bool
    {
        return $this->view($user, $task);
    }

    public function delete(User $user, Task $task): bool
    {
        return $user->role === 'admin';
    }

    public function archive(User $user, Task $task): bool
    {
        return $this->update($user, $task);
    }

    public function restore(User $user, Task $task): bool
    {
        return $this->update($user, $task);
    }

    public function manage(User $user, Task $task): bool
    {
        return $this->isManager($user);
    }
}
