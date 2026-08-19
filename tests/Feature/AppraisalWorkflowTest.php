<?php

namespace Tests\Feature;

use App\Models\Appraisal;
use App\Models\AppraisalActivityLog;
use App\Models\User;
use App\Services\AppraisalWorkflowService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class AppraisalWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(array $attributes = []): User
    {
        static $n = 0;
        $n++;

        return User::create(array_merge([
            'name'     => "User {$n}",
            'email'    => "wf-user{$n}@example.test",
            'password' => bcrypt('password'),
            'role'     => 'staff',
        ], $attributes));
    }

    public function test_invalid_transitions_are_rejected(): void
    {
        $service   = app(AppraisalWorkflowService::class);
        $employee  = $this->makeUser();
        $appraisal = Appraisal::create(['user_id' => $employee->id, 'status' => 'draft']);

        $this->expectException(RuntimeException::class);

        $service->complete($appraisal, $employee);
    }

    public function test_activity_log_created_for_submit_complete_reopen_update(): void
    {
        $service   = app(AppraisalWorkflowService::class);
        $employee  = $this->makeUser();
        $supervisor = $this->makeUser();
        $manager   = $this->makeUser(['role' => 'admin']);

        $appraisal = $service->create($employee, [
            'user_id'       => $employee->id,
            'supervisor_id' => $supervisor->id,
        ]);
        $this->assertSame(1, AppraisalActivityLog::where('action', 'created')->count());

        $service->update($appraisal, $employee, ['job_title' => 'Engineer'], 'updated');
        $this->assertSame(1, AppraisalActivityLog::where('action', 'updated')->count());

        $service->submit($appraisal, $employee);
        $this->assertSame('submitted', $appraisal->fresh()->status);
        $this->assertSame(1, AppraisalActivityLog::where('action', 'submitted')->count());

        $service->update($appraisal, $supervisor, ['overall_rating' => 5], 'review_updated');
        $this->assertSame(1, AppraisalActivityLog::where('action', 'review_updated')->count());

        $service->complete($appraisal, $supervisor);
        $this->assertSame('completed', $appraisal->fresh()->status);
        $this->assertSame(1, AppraisalActivityLog::where('action', 'completed')->count());

        $service->reopen($appraisal, $manager, 'Rating needs revisiting');
        $this->assertSame('submitted', $appraisal->fresh()->status);
        $log = AppraisalActivityLog::where('action', 'reopened')->firstOrFail();
        $this->assertSame('Rating needs revisiting', $log->reason);
        $this->assertSame('completed', $log->old_status);
        $this->assertSame('submitted', $log->new_status);
        $this->assertSame($manager->id, $log->user_id);
    }
}
