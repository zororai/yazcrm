<?php

namespace Tests\Feature;

use App\Models\Board;
use App\Models\Task;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TaskPolicyTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(array $attributes = []): User
    {
        static $n = 0;
        $n++;

        return User::create(array_merge([
            'name'     => "TaskUser {$n}",
            'email'    => "task-user{$n}@example.test",
            'password' => bcrypt('password'),
            'role'     => 'staff',
        ], $attributes));
    }

    private function makeBoard(User $owner): Board
    {
        $workspace = Workspace::create(['owner_id' => $owner->id, 'name' => 'WS']);

        return Board::create(['workspace_id' => $workspace->id, 'owner_id' => $owner->id, 'name' => 'Board']);
    }

    public function test_manager_creator_assignee_can_view_but_unrelated_cannot(): void
    {
        $manager  = $this->makeUser(['role' => 'admin']);
        $creator  = $this->makeUser();
        $assignee = $this->makeUser();
        $stranger = $this->makeUser();

        $board = $this->makeBoard($creator);
        $task  = Task::create(['board_id' => $board->id, 'created_by' => $creator->id, 'title' => 'T']);
        $task->assignees()->attach($assignee->id, ['assigned_by' => $creator->id]);

        $this->actingAs($manager)->get("/tasks/{$task->id}")->assertOk();
        $this->actingAs($creator)->get("/tasks/{$task->id}")->assertOk();
        $this->actingAs($assignee)->get("/tasks/{$task->id}")->assertOk();
        $this->actingAs($stranger)->get("/tasks/{$task->id}")->assertForbidden();
    }

    public function test_creator_and_assignee_can_update_but_unrelated_cannot(): void
    {
        $creator  = $this->makeUser();
        $assignee = $this->makeUser();
        $stranger = $this->makeUser();

        $board = $this->makeBoard($creator);
        $task  = Task::create(['board_id' => $board->id, 'created_by' => $creator->id, 'title' => 'T']);
        $task->assignees()->attach($assignee->id, ['assigned_by' => $creator->id]);

        $this->actingAs($creator)->put("/tasks/{$task->id}", ['title' => 'Updated by creator'])->assertRedirect();
        $this->assertSame('Updated by creator', $task->fresh()->title);

        $this->actingAs($assignee)->put("/tasks/{$task->id}", ['title' => 'Updated by assignee'])->assertRedirect();
        $this->assertSame('Updated by assignee', $task->fresh()->title);

        $this->actingAs($stranger)->put("/tasks/{$task->id}", ['title' => 'Blocked'])->assertForbidden();
    }

    public function test_only_admin_can_delete(): void
    {
        $director = $this->makeUser(['role' => 'director']);
        $admin    = $this->makeUser(['role' => 'admin']);
        $creator  = $this->makeUser();

        $board = $this->makeBoard($creator);
        $task  = Task::create(['board_id' => $board->id, 'created_by' => $creator->id, 'title' => 'T']);

        $this->actingAs($director)->delete("/tasks/{$task->id}")->assertForbidden();
        $this->assertNotNull($task->fresh());

        $this->actingAs($admin)->delete("/tasks/{$task->id}")->assertRedirect();
        $this->assertNull($task->fresh());
    }
}
