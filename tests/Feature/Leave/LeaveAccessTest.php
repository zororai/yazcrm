<?php

namespace Tests\Feature\Leave;

use App\Models\Leave\LeaveRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

// The "who can do what" table, row by row: each role's allowed actions
// succeed and the rest are refused with a 403 and a readable message.
class LeaveAccessTest extends TestCase
{
    use LeaveFixtures, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpLeave();
    }

    // ── Any active staff member ───────────────────────────────────────────────

    public function test_staff_can_apply_and_see_their_own_balances_and_requests(): void
    {
        $staff = $this->makeUser();

        $this->as($staff)->postJson(self::API.'/requests', $this->application())
            ->assertCreated()
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.days', 5);

        $this->getJson(self::API.'/balances?year=2026')
            ->assertOk()
            ->assertJsonPath('balances.0.leave_type.name', 'Annual')
            ->assertJsonPath('balances.0.entitled_days', 22)
            ->assertJsonPath('balances.0.pending_days', 5)
            ->assertJsonPath('balances.0.available_days', 17);

        $this->getJson(self::API.'/requests')->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_staff_cannot_approve_see_the_calendar_reports_or_change_settings(): void
    {
        $staff = $this->makeUser();
        $colleague = $this->makeUser();
        $leave = $this->pendingFor($colleague);

        $this->as($staff);
        $this->patchJson(self::API."/requests/{$leave->id}/approve")->assertForbidden()
            ->assertJsonPath('message', 'You can only approve or reject leave for people who report to you.');
        $this->patchJson(self::API."/requests/{$leave->id}/reject", ['comment' => 'No'])->assertForbidden();
        $this->getJson(self::API.'/pending')->assertOk()->assertJsonCount(0, 'data');
        $this->getJson(self::API.'/calendar')->assertForbidden()->assertJsonStructure(['message']);
        $this->getJson(self::API.'/reports')->assertForbidden();
        $this->getJson(self::API.'/entitlements')->assertForbidden();
        $this->postJson(self::API.'/types', ['name' => 'Study', 'default_days' => 5])->assertForbidden()
            ->assertJsonPath('message', 'Only leave admins can change leave settings.');
        $this->postJson(self::API.'/holidays', ['name' => 'X', 'date' => '2026-07-01'])->assertForbidden();
    }

    public function test_staff_only_ever_see_their_own_requests(): void
    {
        $staff = $this->makeUser();
        $other = $this->makeUser();
        $mine = $this->pendingFor($staff);
        $theirs = $this->pendingFor($other, ['start_date' => '2026-04-06', 'end_date' => '2026-04-07', 'days' => 2]);

        $this->as($staff)->getJson(self::API.'/requests')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $mine->id);

        $this->getJson(self::API."/requests/{$theirs->id}")->assertForbidden();
        $this->getJson(self::API."/requests/{$mine->id}")->assertOk()->assertJsonPath('data.can.cancel', true);
    }

    public function test_owner_can_cancel_only_while_pending_and_nobody_else_can(): void
    {
        $staff = $this->makeUser();
        $other = $this->makeUser();
        $leave = $this->pendingFor($staff);

        $this->as($other)->patchJson(self::API."/requests/{$leave->id}/cancel")->assertForbidden()
            ->assertJsonPath('message', 'Only the person who applied can cancel this request.');

        $this->as($staff)->patchJson(self::API."/requests/{$leave->id}/cancel")
            ->assertOk()
            ->assertJsonPath('data.status', 'cancelled');

        $approved = $this->pendingFor($staff, ['start_date' => '2026-05-04', 'end_date' => '2026-05-05', 'days' => 2, 'status' => 'approved']);
        $this->patchJson(self::API."/requests/{$approved->id}/cancel")->assertForbidden()
            ->assertJsonPath('message', 'Only a pending request can be cancelled.');
    }

    // ── Approver ─────────────────────────────────────────────────────────────

    public function test_approver_sees_and_decides_only_their_direct_reports(): void
    {
        $manager = $this->approver();
        $report = $this->reportOf($manager);
        $stranger = $this->makeUser();

        $mine = $this->pendingFor($report);
        $notMine = $this->pendingFor($stranger);

        $this->as($manager)->getJson(self::API.'/pending')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $mine->id)
            ->assertJsonPath('data.0.can.decide', true);

        $this->getJson(self::API."/requests/{$mine->id}")->assertOk();
        $this->getJson(self::API."/requests/{$notMine->id}")->assertForbidden();

        $this->patchJson(self::API."/requests/{$notMine->id}/approve")->assertForbidden();
        $this->patchJson(self::API."/requests/{$mine->id}/approve", ['comment' => 'Enjoy'])
            ->assertOk()
            ->assertJsonPath('data.status', 'approved')
            ->assertJsonPath('data.approver.id', $manager->id);
    }

    public function test_approver_can_reject_with_a_comment(): void
    {
        $manager = $this->approver();
        $leave = $this->pendingFor($this->reportOf($manager));

        $this->as($manager)->patchJson(self::API."/requests/{$leave->id}/reject")
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['comment' => 'Say why you are rejecting this request.']);

        $this->patchJson(self::API."/requests/{$leave->id}/reject", ['comment' => 'Month-end close'])
            ->assertOk()
            ->assertJsonPath('data.status', 'rejected')
            ->assertJsonPath('data.approver_comment', 'Month-end close');
    }

    public function test_line_manager_without_the_permission_cannot_approve(): void
    {
        $manager = $this->makeUser();
        $leave = $this->pendingFor($this->reportOf($manager));

        $this->as($manager)->getJson(self::API.'/pending')->assertOk()->assertJsonCount(0, 'data');
        $this->patchJson(self::API."/requests/{$leave->id}/approve")->assertForbidden();
        $this->getJson(self::API.'/calendar')->assertForbidden();
    }

    public function test_approver_calendar_shows_only_their_team(): void
    {
        $manager = $this->approver();
        $report = $this->reportOf($manager);
        $this->pendingFor($report);
        $this->pendingFor($this->makeUser());

        $this->as($manager)->getJson(self::API.'/calendar?from=2026-03-01&to=2026-03-31')
            ->assertOk()
            ->assertJsonCount(1, 'requests')
            ->assertJsonPath('requests.0.user.id', $report->id);
    }

    public function test_approver_cannot_correct_or_manage_settings(): void
    {
        $manager = $this->approver();
        $leave = $this->pendingFor($this->reportOf($manager), ['status' => 'approved']);

        $this->as($manager)->patchJson(self::API."/requests/{$leave->id}/correct", ['status' => 'rejected', 'reason' => 'Wrong dates'])
            ->assertForbidden()
            ->assertJsonPath('message', 'Only leave admins can correct a decision.');
        $this->getJson(self::API.'/reports')->assertForbidden();
    }

    // ── Leave admin ──────────────────────────────────────────────────────────

    public function test_admin_director_and_leave_admin_permission_all_count_as_leave_admin(): void
    {
        foreach ([$this->makeUser('admin'), $this->makeUser('director'), $this->makeUser('staff', ['leave_admin'])] as $admin) {
            $leave = $this->pendingFor($this->makeUser());

            $this->as($admin);
            $this->getJson(self::API.'/pending')->assertOk()->assertJsonFragment(['id' => $leave->id]);
            $this->getJson(self::API."/requests/{$leave->id}")->assertOk();
            $this->getJson(self::API.'/calendar?from=2026-03-01&to=2026-03-31')->assertOk();
            $this->getJson(self::API.'/reports?year=2026')->assertOk()->assertJsonStructure(['by_type', 'by_status', 'rows']);
            $this->getJson(self::API.'/entitlements?year=2026')->assertOk();
            $this->patchJson(self::API."/requests/{$leave->id}/approve")->assertOk();

            $leave->delete();
        }
    }

    public function test_leave_admin_manages_types_holidays_and_entitlements(): void
    {
        $admin = $this->makeUser('staff', ['leave_admin']);
        $staff = $this->makeUser();

        $this->as($admin);
        $type = $this->postJson(self::API.'/types', ['name' => 'Study', 'default_days' => 5, 'is_paid' => true])
            ->assertCreated()->json('data.id');
        $this->putJson(self::API."/types/{$type}", ['name' => 'Study', 'default_days' => 8])->assertOk()
            ->assertJsonPath('data.default_days', 8);
        $this->deleteJson(self::API."/types/{$type}")->assertOk();

        $holiday = $this->postJson(self::API.'/holidays', ['name' => 'Founders Day', 'date' => '2026-07-07'])
            ->assertCreated()->json('data.id');
        $this->postJson(self::API.'/holidays', ['name' => 'Twice', 'date' => '2026-07-07'])->assertUnprocessable();
        $this->deleteJson(self::API."/holidays/{$holiday}")->assertOk();

        $this->postJson(self::API.'/entitlements', [
            'user_id' => $staff->id, 'leave_type_id' => $this->annual->id, 'year' => 2026, 'entitled_days' => 25,
        ])->assertSuccessful()->assertJsonPath('data.entitled_days', 25);

        $this->as($staff)->getJson(self::API.'/balances?year=2026')->assertJsonPath('balances.0.entitled_days', 25);
    }

    public function test_report_exports_csv(): void
    {
        $this->makeUser();

        $response = $this->as($this->makeUser('admin'))->get(self::API.'/reports?year=2026&format=csv');

        $response->assertOk();
        $this->assertStringContainsString('text/csv', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('"Staff member","Leave type",Year,Entitled,Used,Pending,Remaining', $response->streamedContent());
    }

    // ── Nobody decides their own leave ───────────────────────────────────────

    public function test_nobody_can_approve_reject_or_correct_their_own_request(): void
    {
        $admin = $this->makeUser('admin', [], ['supervisor_id' => null]);
        $own = $this->pendingFor($admin);

        $this->as($admin);
        $this->patchJson(self::API."/requests/{$own->id}/approve")->assertForbidden()
            ->assertJsonPath('message', 'You cannot approve or reject your own leave request.');
        $this->patchJson(self::API."/requests/{$own->id}/reject", ['comment' => 'x'])->assertForbidden();
        $this->getJson(self::API.'/pending')->assertJsonMissing(['id' => $own->id]);

        $own->update(['status' => LeaveRequest::APPROVED]);
        $this->patchJson(self::API."/requests/{$own->id}/correct", ['status' => 'cancelled', 'reason' => 'My own mistake'])
            ->assertForbidden();

        // An approver who is (oddly) their own supervisor still can't.
        $loop = $this->approver();
        $loop->update(['supervisor_id' => $loop->id]);
        $loopLeave = $this->pendingFor($loop);
        $this->as($loop)->patchJson(self::API."/requests/{$loopLeave->id}/approve")->assertForbidden();
        $this->getJson(self::API.'/pending')->assertJsonCount(0, 'data');
    }

    // ── Deactivated user ─────────────────────────────────────────────────────

    public function test_deactivated_user_is_blocked_and_their_token_revoked(): void
    {
        $staff = $this->makeUser();
        $token = $staff->createToken('api-token')->plainTextToken;

        $this->withToken($token)->getJson(self::API.'/requests')->assertOk();

        $staff->update(['is_active' => false]);
        $this->app['auth']->forgetGuards();

        $this->withToken($token)->getJson(self::API.'/requests')
            ->assertUnauthorized()
            ->assertJsonPath('code', 'account_disabled');
        $this->assertSame(0, $staff->tokens()->count());

        $this->postJson('/api/auth/login', ['email' => $staff->email, 'password' => 'password'])
            ->assertForbidden()
            ->assertJsonPath('message', 'Account is disabled.');
    }

    public function test_deactivating_in_the_crm_revokes_app_tokens(): void
    {
        $admin = $this->makeUser('admin');
        $staff = $this->makeUser();
        $staff->createToken('api-token');

        $this->actingAs($admin)->post("/users/{$staff->id}/toggle-active")->assertRedirect();

        $this->assertFalse($staff->fresh()->is_active);
        $this->assertSame(0, $staff->tokens()->count());
    }
}
