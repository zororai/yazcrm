<?php

namespace Tests\Feature;

use App\Models\ProgressReport;
use App\Models\SuccessStory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

// The helpline manager sees every progress report (all months, not just the
// current one) and every success story, and can open each of them.
class ManagerReportsAccessTest extends TestCase
{
    use RefreshDatabase;

    private User $manager;
    private User $agentA;
    private User $agentB;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setDate(2026, 10, 7));

        $this->manager = $this->user('helpline_manager', 'Mercy');
        $this->agentA  = $this->user('agent', 'Agent A');
        $this->agentB  = $this->user('agent', 'Agent B');

        $this->report($this->agentA, '2026-08-01', 'approved');
        $this->report($this->agentA, '2026-09-01', 'pending');
        $this->report($this->agentB, '2026-09-01', 'needs_revision');

        foreach ([$this->agentA, $this->agentB] as $agent) {
            $ticket = DB::table('tickets')->insertGetId(['subject' => 'Case', 'agent_id' => $agent->id, 'created_at' => now(), 'updated_at' => now()]);
            SuccessStory::create(['ticket_id' => $ticket, 'user_id' => $agent->id, 'title' => "Story by {$agent->name}", 'story' => 'Helped a caller']);
        }
    }

    private function user(string $role, string $name): User
    {
        return User::create(['name' => $name, 'email' => str($name)->slug().'@example.test', 'password' => bcrypt('x'), 'role' => $role]);
    }

    private function report(User $user, string $month, string $status): ProgressReport
    {
        return ProgressReport::forceCreate(['user_id' => $user->id, 'month' => $month, 'status' => $status, 'date_submitted' => $month]);
    }

    public function test_team_reports_show_every_month_by_default(): void
    {
        $this->withoutVite()->actingAs($this->manager)->get('/progress-reports/team')->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('ProgressReports/Team')
                ->where('month', 'all')
                ->has('reports', 3)
                ->where('reports.0.month', '2026-09-01') // newest first
                ->where('availableMonths', [['month' => '2026-09-01', 'count' => 2], ['month' => '2026-08-01', 'count' => 1]])
                ->has('notSubmitted', 0)
            );
    }

    public function test_team_reports_filter_by_month_status_and_person(): void
    {
        $get = fn (string $q) => $this->withoutVite()->actingAs($this->manager)->get("/progress-reports/team{$q}");

        $get('?month=2026-09-01')->assertInertia(fn ($page) => $page
            ->has('reports', 2)
            ->where('notSubmitted', fn ($u) => collect($u)->pluck('name')->contains('Mercy')) // Mercy filed nothing for September
        );
        $get('?status=approved')->assertInertia(fn ($page) => $page->has('reports', 1)->where('reports.0.month', '2026-08-01'));
        $get("?user_id={$this->agentB->id}")->assertInertia(fn ($page) => $page->has('reports', 1)->where('reports.0.user.name', 'Agent B'));
    }

    public function test_manager_can_open_anyones_report_and_story(): void
    {
        $report = ProgressReport::where('user_id', $this->agentB->id)->firstOrFail();
        $this->withoutVite()->actingAs($this->manager)->get("/progress-reports/{$report->id}")->assertOk();

        $this->withoutVite()->actingAs($this->manager)->get('/success-stories')
            ->assertInertia(fn ($page) => $page->where('isManager', true)->where('stories.total', 2));

        $story = SuccessStory::where('user_id', $this->agentB->id)->firstOrFail();
        $this->withoutVite()->actingAs($this->manager)->get("/success-stories/{$story->id}")->assertOk();
    }

    public function test_team_reports_permission_can_view_all_but_not_review(): void
    {
        $viewer = User::create(['name' => 'Viewer', 'email' => 'viewer@example.test', 'password' => bcrypt('x'), 'role' => 'programs', 'nav_permissions' => ['team_reports']]);
        $report = ProgressReport::where('user_id', $this->agentB->id)->firstOrFail();

        $this->withoutVite()->actingAs($viewer)->get('/progress-reports/team')->assertOk()
            ->assertInertia(fn ($page) => $page->has('reports', 3));
        $this->withoutVite()->actingAs($viewer)->get("/progress-reports/{$report->id}")->assertOk();
        $this->withoutVite()->actingAs($viewer)->get('/progress-reports')
            ->assertInertia(fn ($page) => $page->where('canViewTeam', true)->where('isManager', false));

        $this->actingAs($viewer)->post("/progress-reports/{$report->id}/status", ['status' => 'approved'])->assertForbidden();
    }

    public function test_agents_only_see_their_own(): void
    {
        $this->actingAs($this->agentA)->get('/progress-reports/team')->assertForbidden();

        $other = ProgressReport::where('user_id', $this->agentB->id)->firstOrFail();
        $this->actingAs($this->agentA)->get("/progress-reports/{$other->id}")->assertForbidden();

        $this->withoutVite()->actingAs($this->agentA)->get('/success-stories')
            ->assertInertia(fn ($page) => $page->where('stories.total', 1)->where('stories.data.0.user.name', 'Agent A'));
    }
}
