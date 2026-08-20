<?php

namespace App\Services;

use App\Models\Task;
use App\Models\TaskActivityLog;
use App\Models\TaskComment;
use App\Models\User;
use App\Notifications\TaskAssignedNotification;
use App\Support\Tasks\TaskStatus;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class TaskWorkflowService
{
    public function create(User $actor, array $attributes): Task
    {
        return DB::transaction(function () use ($actor, $attributes) {
            $task = Task::create($attributes + [
                'created_by' => $actor->id,
                'status'     => TaskStatus::NOT_STARTED,
            ]);

            $this->log($task, $actor, 'created', newStatus: $task->status);

            return $task;
        });
    }

    public function update(Task $task, User $actor, array $data): Task
    {
        return DB::transaction(function () use ($task, $actor, $data) {
            $task->update($data);

            $this->log($task, $actor, 'updated', changedFields: array_keys($data));

            return $task;
        });
    }

    public function assign(Task $task, User $actor, array $userIds): Task
    {
        return DB::transaction(function () use ($task, $actor, $userIds) {
            $existing = $task->assignees()->pluck('users.id')->all();

            $task->assignees()->sync(collect($userIds)->mapWithKeys(fn ($id) => [
                $id => ['assigned_by' => $actor->id],
            ]));

            $added   = array_diff($userIds, $existing);
            $removed = array_diff($existing, $userIds);

            if ($added) {
                $this->log($task, $actor, 'assigned', changedFields: array_values($added));
            }
            if ($removed) {
                $this->log($task, $actor, 'unassigned', changedFields: array_values($removed));
            }

            $task = $task->fresh();

            if ($added) {
                User::whereIn('id', $added)->get()->each(
                    fn (User $u) => $u->notify(new TaskAssignedNotification($task, $actor))
                );
            }

            return $task;
        });
    }

    public function changeStatus(Task $task, User $actor, string $status): Task
    {
        if (! in_array($status, TaskStatus::ALL, true)) {
            throw new RuntimeException("Unknown task status '{$status}'.");
        }

        if (! TaskStatus::canTransition($task->status, $status)) {
            throw new RuntimeException("Cannot move a task from '{$task->status}' to '{$status}'.");
        }

        return DB::transaction(function () use ($task, $actor, $status) {
            $old = $task->status;

            $task->update([
                'status'       => $status,
                'completed_at' => $status === TaskStatus::COMPLETED ? now() : null,
            ]);

            $this->log($task, $actor, $status === TaskStatus::COMPLETED ? 'completed' : 'status_changed', oldStatus: $old, newStatus: $status);

            return $task;
        });
    }

    public function changePriority(Task $task, User $actor, string $priority): Task
    {
        return DB::transaction(function () use ($task, $actor, $priority) {
            $old = $task->priority;

            $task->update(['priority' => $priority]);

            $this->log($task, $actor, 'priority_changed', oldPriority: $old, newPriority: $priority);

            return $task;
        });
    }

    public function addComment(Task $task, User $actor, string $comment): TaskComment
    {
        return DB::transaction(function () use ($task, $actor, $comment) {
            $taskComment = TaskComment::create([
                'task_id' => $task->id,
                'user_id' => $actor->id,
                'comment' => $comment,
            ]);

            $this->log($task, $actor, 'commented');

            return $taskComment;
        });
    }

    public function complete(Task $task, User $actor): Task
    {
        return $this->changeStatus($task, $actor, TaskStatus::COMPLETED);
    }

    public function reopen(Task $task, User $actor, string $reason): Task
    {
        if ($task->status !== TaskStatus::COMPLETED) {
            throw new RuntimeException('Only a completed task can be reopened.');
        }

        return DB::transaction(function () use ($task, $actor, $reason) {
            $task->update(['status' => TaskStatus::IN_PROGRESS, 'completed_at' => null]);

            $this->log($task, $actor, 'reopened', oldStatus: TaskStatus::COMPLETED, newStatus: TaskStatus::IN_PROGRESS, reason: $reason);

            return $task;
        });
    }

    public function archive(Task $task, User $actor): Task
    {
        return DB::transaction(function () use ($task, $actor) {
            $task->update(['is_archived' => true]);

            $this->log($task, $actor, 'archived');

            return $task;
        });
    }

    public function restore(Task $task, User $actor): Task
    {
        return DB::transaction(function () use ($task, $actor) {
            $task->update(['is_archived' => false]);

            $this->log($task, $actor, 'restored');

            return $task;
        });
    }

    private function log(
        Task $task,
        User $actor,
        string $action,
        ?string $oldStatus = null,
        ?string $newStatus = null,
        ?string $oldPriority = null,
        ?string $newPriority = null,
        ?array $changedFields = null,
        ?string $reason = null
    ): void {
        TaskActivityLog::create([
            'task_id'        => $task->id,
            'user_id'        => $actor->id,
            'action'         => $action,
            'old_status'     => $oldStatus,
            'new_status'     => $newStatus,
            'old_priority'   => $oldPriority,
            'new_priority'   => $newPriority,
            'changed_fields' => $changedFields,
            'reason'         => $reason,
        ]);
    }
}
