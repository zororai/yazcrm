<?php

namespace Tests\Feature;

use App\Models\ProgressReport;
use App\Models\SuccessStory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

// Attaching Success Stories (from the Success Stories section) to a monthly
// progress report.
class ProgressReportStoriesTest extends TestCase
{
    use RefreshDatabase;

    private User $agent;
    private User $other;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setDate(2026, 10, 8));
        $this->agent = $this->user('agent', 'Agent One');
        $this->other = $this->user('agent', 'Agent Two');
    }

    private function user(string $role, string $name): User
    {
        return User::create(['name' => $name, 'email' => str($name)->slug().'@example.test', 'password' => bcrypt('x'), 'role' => $role]);
    }

    private function story(User $user, string $title): SuccessStory
    {
        $ticket = DB::table('tickets')->insertGetId(['subject' => 'Case', 'agent_id' => $user->id, 'created_at' => now(), 'updated_at' => now()]);

        return SuccessStory::create(['ticket_id' => $ticket, 'user_id' => $user->id, 'title' => $title, 'story' => "Story text for {$title}"]);
    }

    private function save(User $user, array $storyIds): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs($user)->from('/progress-reports')->post('/progress-reports', [
            'month' => '2026-10-01', 'job_title' => 'Counsellor', 'attached_story_ids' => $storyIds,
        ]);
    }

    public function test_user_can_attach_and_detach_their_own_stories(): void
    {
        $a = $this->story($this->agent, 'Caller found a job');
        $b = $this->story($this->agent, 'Returned to school');

        $this->save($this->agent, [$a->id, $b->id])->assertRedirect('/progress-reports')->assertSessionHas('success');
        $report = ProgressReport::where('user_id', $this->agent->id)->firstOrFail();
        $this->assertEqualsCanonicalizing([$a->id, $b->id], $report->attachedStories()->pluck('success_stories.id')->all());

        // Saving again with one unticked removes it.
        $this->save($this->agent, [$b->id])->assertRedirect('/progress-reports')->assertSessionHas('success');
        $this->assertSame([$b->id], $report->attachedStories()->pluck('success_stories.id')->all());
        $this->assertSame(1, ProgressReport::count()); // same month updates, never duplicates

        $this->save($this->agent, [])->assertRedirect('/progress-reports')->assertSessionHas('success');
        $this->assertSame(0, $report->attachedStories()->count());
    }

    public function test_cannot_attach_someone_elses_story(): void
    {
        $mine = $this->story($this->agent, 'Mine');
        $theirs = $this->story($this->other, 'Theirs');

        $this->save($this->agent, [$mine->id, $theirs->id])
            ->assertSessionHasErrors(['attached_story_ids.1' => 'You can only attach your own success stories.']);
        $this->assertSame(0, DB::table('progress_report_success_story')->count());
    }

    public function test_form_lists_only_own_stories_and_current_attachments(): void
    {
        $mine = $this->story($this->agent, 'Mine');
        $this->story($this->other, 'Theirs');
        $this->save($this->agent, [$mine->id]);

        $this->withoutVite()->actingAs($this->agent)->get('/progress-reports?month=2026-10-01')
            ->assertInertia(fn ($page) => $page
                ->has('myStories', 1)
                ->where('myStories.0.title', 'Mine')
                ->where('current.attached_story_ids', [$mine->id])
            );
    }

    public function test_manager_sees_attached_stories_in_full_on_the_report(): void
    {
        $story = $this->story($this->agent, 'Caller found a job');
        $this->save($this->agent, [$story->id]);
        $report = ProgressReport::firstOrFail();
        $manager = $this->user('helpline_manager', 'Manager');

        $this->withoutVite()->actingAs($manager)->get("/progress-reports/{$report->id}")
            ->assertInertia(fn ($page) => $page
                ->where('report.attached_stories.0.title', 'Caller found a job')
                ->where('report.attached_stories.0.story', 'Story text for Caller found a job')
            );

        $this->actingAs($manager)->get("/progress-reports/{$report->id}/export-pdf")->assertOk();
    }
}
