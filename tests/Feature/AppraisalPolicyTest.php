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

        $this->actingAs($employee)->post("/appraisals/{$appraisal->id}/submit")->assertRedirect();
        $this->assertSame('submitted', $appraisal->fresh()->status);

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
