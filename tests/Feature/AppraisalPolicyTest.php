<?php

namespace Tests\Feature;

use App\Models\Appraisal;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AppraisalPolicyTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(array $attributes = []): User
    {
        static $n = 0;
        $n++;

        return User::create(array_merge([
            'name'     => "User {$n}",
            'email'    => "user{$n}@example.test",
            'password' => bcrypt('password'),
            'role'     => 'staff',
        ], $attributes));
    }

    public function test_employee_can_create_and_edit_own_draft(): void
    {
        $employee = $this->makeUser();

        $response = $this->actingAs($employee)->post('/appraisals', ['user_id' => $employee->id]);
        $response->assertRedirect();

        $appraisal = Appraisal::firstOrFail();
        $this->assertSame($employee->id, $appraisal->user_id);
        $this->assertSame('draft', $appraisal->status);

        $this->actingAs($employee)
            ->put("/appraisals/{$appraisal->id}", ['job_title' => 'Engineer'])
            ->assertRedirect();

        $this->assertSame('Engineer', $appraisal->fresh()->job_title);
    }

    public function test_employee_cannot_edit_after_submitting(): void
    {
        $employee  = $this->makeUser();
        $appraisal = Appraisal::create(['user_id' => $employee->id, 'status' => 'draft']);

        // An empty self-assessment can't be submitted…
        $this->actingAs($employee)->post("/appraisals/{$appraisal->id}/submit")
            ->assertSessionHas('error', 'Fill in and save your self-assessment before submitting it.');
        $this->assertSame('draft', $appraisal->fresh()->status);

        // …once answers are saved it can.
        $this->actingAs($employee)->put("/appraisals/{$appraisal->id}", ['employee_responses' => ['past_activities' => 'Outreach']]);
        $this->actingAs($employee)->post("/appraisals/{$appraisal->id}/submit")->assertRedirect();
        $this->assertSame('submitted', $appraisal->fresh()->status);
        $this->assertSame('Outreach', $appraisal->fresh()->employee_responses['past_activities']);

        $this->actingAs($employee)
            ->put("/appraisals/{$appraisal->id}", ['job_title' => 'Blocked'])
            ->assertForbidden();
    }

    public function test_employee_cannot_act_on_another_employees_appraisal(): void
    {
        $owner   = $this->makeUser();
        $other   = $this->makeUser();
        $appraisal = Appraisal::create(['user_id' => $owner->id, 'status' => 'draft']);

        $this->actingAs($other)->get("/appraisals/{$appraisal->id}")->assertForbidden();
        $this->actingAs($other)->put("/appraisals/{$appraisal->id}", ['job_title' => 'X'])->assertForbidden();
    }

    public function test_supervisor_can_review_only_assigned_appraisal(): void
    {
        $supervisor      = $this->makeUser();
        $otherSupervisor = $this->makeUser();
        $employee        = $this->makeUser(['supervisor_id' => $supervisor->id]);

        $appraisal = Appraisal::create([
            'user_id'       => $employee->id,
            'supervisor_id' => $supervisor->id,
            'status'        => 'submitted',
        ]);

        $this->actingAs($supervisor)
            ->put("/appraisals/{$appraisal->id}/review", ['overall_rating' => 4])
            ->assertRedirect();
        $this->assertSame(4, $appraisal->fresh()->overall_rating);

        $this->actingAs($otherSupervisor)
            ->put("/appraisals/{$appraisal->id}/review", ['overall_rating' => 1])
            ->assertForbidden();
    }

    public function test_reviewer_sees_what_the_employee_submitted(): void
    {
        $supervisor = $this->makeUser();
        $employee   = $this->makeUser(['supervisor_id' => $supervisor->id]);
        $appraisal  = Appraisal::create([
            'user_id'            => $employee->id,
            'supervisor_id'      => $supervisor->id,
            'status'             => 'submitted',
            'job_title'          => 'Counsellor',
            'employee_responses' => ['past_activities' => 'Ran 40 counselling sessions', 'overall_comments' => 'Good year'],
        ]);

        $this->withoutVite()->actingAs($supervisor)->get("/appraisals/{$appraisal->id}/review")->assertOk()
            ->assertInertia(fn ($page) => $page->component('Appraisals/Review')
                ->where('appraisal.job_title', 'Counsellor')
                ->where('appraisal.employee_responses.past_activities', 'Ran 40 counselling sessions')
                ->where('appraisal.employee_responses.overall_comments', 'Good year'));
    }

    // The full flow as the buttons now run it: Submit = save the form, then
    // submit; Complete = save the review, then complete.
    public function test_answers_and_review_are_saved_through_submit_and_complete(): void
    {
        $supervisor = $this->makeUser(['name' => 'Tatenda Songore']);
        $employee   = $this->makeUser(['name' => 'Talent Mubonesi', 'supervisor_id' => $supervisor->id]);

        $this->actingAs($employee)->post('/appraisals', ['user_id' => $employee->id]);
        $appraisal = Appraisal::where('user_id', $employee->id)->firstOrFail();
        $appraisal->update(['supervisor_id' => $supervisor->id]);

        // Employee clicks Submit: the page saves first…
        $this->actingAs($employee)->put("/appraisals/{$appraisal->id}", [
            'job_title'          => 'Counsellor',
            'department'         => 'Helpline',
            'employee_responses' => [
                'job_description'  => 'Answer calls, refer clients',
                'past_activities'  => 'Handled 1,200 calls',
                'overall_comments' => 'A busy but good year',
            ],
        ])->assertSessionHas('success', 'Saved.');
        // …then submits.
        $this->actingAs($employee)->post("/appraisals/{$appraisal->id}/submit")
            ->assertSessionHas('success', 'Appraisal submitted to your supervisor.');

        $saved = $appraisal->fresh();
        $this->assertSame('submitted', $saved->status);
        $this->assertSame('Counsellor', $saved->job_title);
        $this->assertSame('Handled 1,200 calls', $saved->employee_responses['past_activities']);
        $this->assertSame('A busy but good year', $saved->employee_responses['overall_comments']);

        // Supervisor opens the review and sees the submission.
        $this->withoutVite()->actingAs($supervisor)->get("/appraisals/{$appraisal->id}/review")
            ->assertInertia(fn ($page) => $page
                ->where('appraisal.job_title', 'Counsellor')
                ->where('appraisal.employee_responses.past_activities', 'Handled 1,200 calls'));

        // Supervisor clicks Complete: the page saves the review first…
        $this->actingAs($supervisor)->put("/appraisals/{$appraisal->id}/review", [
            'overall_rating'       => 4,
            'supervisor_responses' => [
                'reviewer_summary' => 'Strong performer',
                'key_strengths'    => ['text' => 'Calm with callers', 'rating' => 5],
                'training_needs'   => 'Advanced counselling course',
            ],
        ])->assertSessionHas('success', 'Saved.');
        // …then completes.
        $this->actingAs($supervisor)->post("/appraisals/{$appraisal->id}/complete")->assertSessionHasNoErrors();

        $done = $appraisal->fresh();
        $this->assertSame('completed', $done->status);
        $this->assertSame(4, $done->overall_rating);
        $this->assertSame('Strong performer', $done->supervisor_responses['reviewer_summary']);
        $this->assertSame('Calm with callers', $done->supervisor_responses['key_strengths']['text']);
        // The employee's answers survived the whole process.
        $this->assertSame('Handled 1,200 calls', $done->employee_responses['past_activities']);
    }

    public function test_manager_can_reopen_with_reason_but_not_without(): void
    {
        $manager   = $this->makeUser(['role' => 'admin']);
        $employee  = $this->makeUser();
        $appraisal = Appraisal::create([
            'user_id' => $employee->id,
            'status'  => 'submitted',
        ]);

        $this->actingAs($manager)
            ->post("/appraisals/{$appraisal->id}/reopen", [])
            ->assertSessionHasErrors('reason');
        $this->assertSame('submitted', $appraisal->fresh()->status);

        $this->actingAs($manager)
            ->post("/appraisals/{$appraisal->id}/reopen", ['reason' => 'Needs correction'])
            ->assertRedirect();
        $this->assertSame('draft', $appraisal->fresh()->status);
    }

    public function test_non_manager_cannot_reopen(): void
    {
        $employee  = $this->makeUser();
        $appraisal = Appraisal::create(['user_id' => $employee->id, 'status' => 'submitted']);

        $this->actingAs($employee)
            ->post("/appraisals/{$appraisal->id}/reopen", ['reason' => 'test'])
            ->assertForbidden();
    }

    public function test_only_admin_can_delete(): void
    {
        $director  = $this->makeUser(['role' => 'director']);
        $admin     = $this->makeUser(['role' => 'admin']);
        $employee  = $this->makeUser();
        $appraisal = Appraisal::create(['user_id' => $employee->id, 'status' => 'draft']);

        $this->actingAs($director)->delete("/appraisals/{$appraisal->id}")->assertForbidden();
        $this->assertNotNull($appraisal->fresh());

        $this->actingAs($admin)->delete("/appraisals/{$appraisal->id}")->assertRedirect();
        $this->assertNull($appraisal->fresh());
    }
}
