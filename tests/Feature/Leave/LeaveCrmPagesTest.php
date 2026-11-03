<?php

namespace Tests\Feature\Leave;

use App\Models\AuditLog;
use App\Models\Leave\LeaveBalance;
use App\Models\Leave\LeaveRequest;
use App\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LeaveCrmPagesTest extends TestCase
{
    use LeaveFixtures, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpLeave();
    }

    public function test_leave_permissions_can_be_ticked_on_users_and_roles(): void
    {
        $admin = $this->makeUser('admin');
        $staff = $this->makeUser('agent');

        $this->actingAs($admin)->from('/users')->put("/users/{$staff->id}", [
            'name' => $staff->name, 'email' => $staff->email, 'role' => 'agent', 'supervisor_id' => '',
            'nav_permissions' => ['leave_approve', 'leave_admin'], 'password' => '', 'password_confirmation' => '',
        ])->assertSessionHasNoErrors();
        $this->assertSame(['leave_approve', 'leave_admin'], $staff->fresh()->nav_permissions);

        $this->actingAs($admin)->from('/roles')->post('/roles', [
            'display_name' => 'Line Manager', 'nav_permissions' => ['leave_approve'],
        ])->assertSessionHasNoErrors();
        $this->assertSame(['leave_approve'], Role::where('display_name', 'Line Manager')->value('nav_permissions'));
    }

    public function test_staff_cannot_open_any_leave_page(): void
    {
        $this->actingAs($this->makeUser());

        foreach (['requests', 'types', 'holidays', 'entitlements', 'reports', 'reports/export'] as $page) {
            $this->get("/leave/{$page}")->assertForbidden();
        }
    }

    public function test_approver_sees_only_direct_reports_and_no_settings(): void
    {
        $manager = $this->approver();
        $report = $this->reportOf($manager);
        $this->pendingFor($report);
        $this->pendingFor($this->makeUser());

        $this->actingAs($manager)->get('/leave/requests?year=2026')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Leave/Requests')
                ->where('isAdmin', false)
                ->has('requests', 1)
                ->where('requests.0.user.id', $report->id)
                ->where('requests.0.can.decide', true));

        $this->get('/leave/types')->assertForbidden();
        $this->get('/leave/reports')->assertForbidden();
    }

    public function test_director_is_a_leave_admin_in_the_crm(): void
    {
        $director = $this->makeUser('director');
        $this->pendingFor($this->makeUser());

        $this->actingAs($director);
        $this->get('/leave/requests?year=2026')->assertOk()
            ->assertInertia(fn ($page) => $page->where('isAdmin', true)->has('requests', 1));
        foreach (['types', 'holidays', 'entitlements', 'reports'] as $page) {
            $this->get("/leave/{$page}")->assertOk();
        }
    }

    public function test_approving_in_the_crm_deducts_the_balance(): void
    {
        $manager = $this->approver();
        $report = $this->reportOf($manager);
        $leave = $this->pendingFor($report);

        $this->actingAs($manager)->from('/leave/requests')
            ->patch("/leave/requests/{$leave->id}/approve", ['comment' => 'OK'])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success', 'Leave approved.');

        $this->assertSame('approved', $leave->fresh()->status);
        $this->assertSame(5.0, (float) LeaveBalance::where('user_id', $report->id)->value('used_days'));
    }

    public function test_correction_in_the_crm_needs_a_reason_and_is_audit_logged_with_old_values(): void
    {
        $admin = $this->makeUser('admin');
        $staff = $this->makeUser();
        $leave = $this->pendingFor($staff, ['status' => LeaveRequest::REJECTED, 'approver_comment' => 'Too busy']);

        $this->actingAs($admin)->from('/leave/requests')
            ->patch("/leave/requests/{$leave->id}/correct", ['status' => 'approved'])
            ->assertSessionHasErrors('reason');
        $this->assertSame('rejected', $leave->fresh()->status);

        $this->actingAs($admin)->from('/leave/requests')
            ->patch("/leave/requests/{$leave->id}/correct", ['status' => 'approved', 'reason' => 'Manager agreed after all', 'comment' => 'Approved on review'])
            ->assertSessionHasNoErrors();

        $leave->refresh();
        $this->assertSame('approved', $leave->status);
        $this->assertSame($admin->id, $leave->corrected_by);
        $this->assertSame(5.0, (float) LeaveBalance::where('user_id', $staff->id)->value('used_days'));

        $audit = AuditLog::where('route_name', 'leave.requests.correct')->where('status_code', 302)->latest('id')->firstOrFail();
        $this->assertStringContainsString("status 'rejected' → 'approved'", $audit->description);
        $this->assertStringContainsString("comment 'Too busy' → 'Approved on review'", $audit->description);
        $this->assertStringContainsString('Reason: Manager agreed after all', $audit->description);
    }

    public function test_admin_manages_settings_from_the_crm(): void
    {
        $admin = $this->makeUser('staff', ['leave_admin']);
        $staff = $this->makeUser();

        $this->actingAs($admin)->from('/leave/types')
            ->post('/leave/types', ['name' => 'Study', 'default_days' => 5, 'is_paid' => true, 'requires_attachment' => false, 'is_active' => true])
            ->assertSessionHasNoErrors();

        $this->from('/leave/holidays')->post('/leave/holidays', ['name' => 'Founders Day', 'date' => '2026-07-07'])
            ->assertSessionHasNoErrors();

        $this->from('/leave/entitlements')->post('/leave/entitlements', [
            'user_id' => $staff->id, 'leave_type_id' => $this->annual->id, 'year' => 2026, 'entitled_days' => 30,
        ])->assertSessionHasNoErrors();

        $this->get('/leave/entitlements?year=2026&user_id='.$staff->id)->assertOk()
            ->assertInertia(fn ($page) => $page->component('Leave/Entitlements')->where('rows.0.entitled_days', 30));

        $this->get('/leave/reports/export?year=2026')->assertOk();
    }
}
