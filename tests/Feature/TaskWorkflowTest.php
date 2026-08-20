<?php

namespace Tests\Feature;

use App\Models\Board;
use App\Models\Task;
use App\Models\TaskActivityLog;
use App\Models\User;
use App\Models\Workspace;
use App\Services\TaskWorkflowService;
use App\Support\Tasks\TaskStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class TaskWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(array $attributes = []): User
    {
        static $n = 0;
        $n++;

        return User::create(array_merge([
            'name'     => "WfUser {$n}",
            'email'    => "task-wf-user{$n}@example.test",
            'password' => bcrypt('password'),
            'role'     => 'staff',
        ], $attributes));
    }

    private function makeBoard(User $owner): Board
    {
        $workspace = Workspace::create(['owner_id' => $owner->id, 'name' => 'WS']);

        return Board::create(['workspace_id' => $workspace->id, 'owner_id' => $owner->id, 'name' => 'Board']);
    }

    public function test_invalid_transition_is_rejected(): void
    {
        $service = app(TaskWorkflowService::class);
        $user    = $this->makeUser();
        $board   = $this->makeBoard($user);
        $task    = Task::create(['board_id' => $board->id, 'created_by' => $user->id, 'title' => 'T']);

        $this->expectException(RuntimeException::class);

        $service->changeStatus($task, $user, TaskStatus::COMPLETED);
    }

    public function test_full_lifecycle_creates_activity_logs(): void
    {
        $service  = app(TaskWorkflowService::class);
        $creator  = $this->makeUser();
        $assignee = $this->makeUser();
        $board    = $this->makeBoard($creator);

        $task = $service->create($creator, ['board_id' => $board->id, 'title' => 'Prepare report']);
        $this->assertSame(1, TaskActivityLog::where('action', 'created')->count());

        $service->update($task, $creator, ['description' => 'Details']);
        $this->assertSame(1, TaskActivityLog::where('action', 'updated')->count());

        $service->assign($task, $creator, [$assignee->id]);
        $this->assertSame(1, TaskActivityLog::where('action', 'assigned')->count());
        $this->assertTrue($task->fresh()->assignees->pluck('id')->contains($assignee->id));

        $service->changeStatus($task, $assignee, TaskStatus::IN_PROGRESS);
        $this->assertSame(TaskStatus::IN_PROGRESS, $task->fresh()->status);
        $this->assertSame(1, TaskActivityLog::where('action', 'status_changed')->count());

        $service->changePriority($task, $assignee, 'high');
        $this->assertSame('high', $task->fresh()->priority);
        $this->assertSame(1, TaskActivityLog::where('action', 'priority_changed')->count());

        $service->addComment($task, $assignee, 'Working on it');
        $this->assertSame(1, TaskActivityLog::where('action', 'commented')->count());

        $service->complete($task, $assignee);
        $this->assertSame(TaskStatus::COMPLETED, $task->fresh()->status);
        $this->assertNotNull($task->fresh()->completed_at);
        $this->assertSame(1, TaskActivityLog::where('action', 'completed')->count());

        $service->reopen($task, $creator, 'Needs more work');
        $this->assertSame(TaskStatus::IN_PROGRESS, $task->fresh()->status);
        $this->assertNull($task->fresh()->completed_at);
        $log = TaskActivityLog::where('action', 'reopened')->firstOrFail();
        $this->assertSame('Needs more work', $log->reason);

        $service->archive($task, $creator);
        $this->assertTrue($task->fresh()->is_archived);
        $this->assertSame(1, TaskActivityLog::where('action', 'archived')->count());

        $service->restore($task, $creator);
        $this->assertFalse($task->fresh()->is_archived);
        $this->assertSame(1, TaskActivityLog::where('action', 'restored')->count());
    }

    public function test_subtask_progress_is_calculated_from_children(): void
    {
        $user  = $this->makeUser();
        $board = $this->makeBoard($user);
        $parent = Task::create(['board_id' => $board->id, 'created_by' => $user->id, 'title' => 'Parent']);

        Task::create(['board_id' => $board->id, 'parent_id' => $parent->id, 'created_by' => $user->id, 'title' => 'Sub 1', 'status' => TaskStatus::COMPLETED]);
        Task::create(['board_id' => $board->id, 'parent_id' => $parent->id, 'created_by' => $user->id, 'title' => 'Sub 2', 'status' => TaskStatus::COMPLETED]);
        Task::create(['board_id' => $board->id, 'parent_id' => $parent->id, 'created_by' => $user->id, 'title' => 'Sub 3']);
        Task::create(['board_id' => $board->id, 'parent_id' => $parent->id, 'created_by' => $user->id, 'title' => 'Sub 4']);

        $this->assertSame(50, $parent->fresh()->progress());
    }
}
