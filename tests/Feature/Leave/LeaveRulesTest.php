<?php

namespace Tests\Feature\Leave;

use App\Models\AuditLog;
use App\Models\Leave\LeaveBalance;
use App\Models\Leave\LeaveRequest;
use App\Models\Leave\LeaveType;
use App\Models\Leave\PublicHoliday;
use App\Notifications\LeaveRequestDecidedNotification;
use App\Notifications\LeaveRequestSubmittedNotification;
use App\Services\Leave\WorkingDays;
use Database\Seeders\LeaveSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class LeaveRulesTest extends TestCase
{
    use LeaveFixtures, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpLeave();
    }

    // ── Working days ─────────────────────────────────────────────────────────

    public function test_weekends_are_not_counted(): void
    {
        // Thu 5 – Tue 10 March 2026 = Thu, Fri, Mon, Tue.
        $this->assertSame(4, WorkingDays::count('2026-03-05', '2026-03-10'));
        $this->assertSame(0, WorkingDays::count('2026-03-07', '2026-03-08'));
    }

    public function test_public_holidays_are_not_counted(): void
    {
        // Mon 20 – Fri 24 April 2026, with holidays on the first and last day.
        PublicHoliday::create(['name' => 'Holiday A', 'date' => '2026-04-20']);
        PublicHoliday::create(['name' => 'Holiday B', 'date' => '2026-04-24']);

        $staff = $this->makeUser();
        $this->as($staff)->postJson(self::API.'/requests', $this->application(['start_date' => '2026-04-20', 'end_date' => '2026-04-24']))
            ->assertCreated()
            ->assertJsonPath('data.days', 3);

        $this->getJson(self::API.'/working-days?start_date=2026-04-20&end_date=2026-04-24')
            ->assertOk()
            ->assertJsonPath('days', 3)
            ->assertJsonPath('dates', ['2026-04-21', '2026-04-22', '2026-04-23']);
    }

    public function test_a_range_of_only_weekends_and_holidays_is_refused(): void
    {
        $this->as($this->makeUser())->postJson(self::API.'/requests', $this->application(['start_date' => '2026-03-07', 'end_date' => '2026-03-08']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['start_date' => 'The dates you picked are all weekends or public holidays.']);
    }

    public function test_seeded_zimbabwe_holidays_include_moveable_and_observed_days(): void
    {
        $h2026 = LeaveSeeder::zimbabweHolidays(2026);
        $this->assertSame('Good Friday', $h2026['2026-04-03']);
        $this->assertSame('Easter Monday', $h2026['2026-04-06']);
        $this->assertSame("Heroes' Day", $h2026['2026-08-10']);
        $this->assertSame('Defence Forces Day', $h2026['2026-08-11']);

        // 21 Feb 2027 is a Sunday: observed on Monday 22nd.
        $h2027 = LeaveSeeder::zimbabweHolidays(2027);
        $this->assertSame('Robert Gabriel Mugabe National Youth Day (observed)', $h2027['2027-02-22']);

        $this->seed(LeaveSeeder::class);
        $this->assertSame(6, LeaveType::whereIn('name', ['Annual', 'Sick', 'Maternity', 'Paternity', 'Compassionate', 'Unpaid'])->count());
        $this->assertTrue(PublicHoliday::whereDate('date', now()->year.'-12-25')->exists());
    }

    // ── Validation rules ─────────────────────────────────────────────────────

    public function test_end_date_before_start_date_is_refused(): void
    {
        $this->as($this->makeUser())->postJson(self::API.'/requests', $this->application(['start_date' => '2026-03-06', 'end_date' => '2026-03-02']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['end_date' => 'The end date cannot be before the start date.']);
    }

    public function test_overlapping_a_pending_or_approved_request_is_refused(): void
    {
        $staff = $this->makeUser();
        $this->pendingFor($staff); // 2–6 March

        $this->as($staff)->postJson(self::API.'/requests', $this->application(['start_date' => '2026-03-05', 'end_date' => '2026-03-10']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['start_date']);

        LeaveRequest::query()->update(['status' => LeaveRequest::APPROVED]);
        $this->postJson(self::API.'/requests', $this->application(['start_date' => '2026-03-06', 'end_date' => '2026-03-06']))
            ->assertUnprocessable();

        // Rejected and cancelled requests free the dates again.
        LeaveRequest::query()->update(['status' => LeaveRequest::REJECTED]);
        $this->postJson(self::API.'/requests', $this->application())->assertCreated();
    }

    public function test_more_days_than_the_balance_is_refused_counting_pending_requests(): void
    {
        $staff = $this->makeUser();
        LeaveBalance::create(['user_id' => $staff->id, 'leave_type_id' => $this->annual->id, 'year' => 2026, 'entitled_days' => 7, 'used_days' => 0]);

        $this->as($staff)->postJson(self::API.'/requests', $this->application())->assertCreated(); // 5 of 7

        $this->postJson(self::API.'/requests', $this->application(['start_date' => '2026-03-09', 'end_date' => '2026-03-11']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['end_date' => 'This is 3 working day(s), but you only have 2 day(s) of Annual leave left after your pending requests.']);

        $this->postJson(self::API.'/requests', $this->application(['start_date' => '2026-03-09', 'end_date' => '2026-03-10']))
            ->assertCreated();
    }

    public function test_unpaid_leave_has_no_balance_limit(): void
    {
        $unpaid = LeaveType::factory()->unpaid()->create(['name' => 'Unpaid']);

        $this->as($this->makeUser())->postJson(self::API.'/requests', $this->application([
            'leave_type_id' => $unpaid->id, 'start_date' => '2026-06-01', 'end_date' => '2026-06-30',
        ]))->assertCreated()->assertJsonPath('data.days', 22);
    }

    public function test_attachment_is_required_when_the_type_needs_one(): void
    {
        // persistentFake: Storage::fake would wipe the shared testing disk,
        // which holds a committed data-collection fixture.
        Storage::persistentFake('local');
        $sick = LeaveType::factory()->needsAttachment()->create(['name' => 'Sick']);
        $staff = $this->makeUser();

        $this->as($staff)->postJson(self::API.'/requests', $this->application(['leave_type_id' => $sick->id]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['attachment' => 'Sick leave needs a supporting document.']);

        $this->post(self::API.'/requests', $this->application([
            'leave_type_id' => $sick->id,
            'attachment'    => UploadedFile::fake()->create('note.pdf', 100, 'application/pdf'),
        ]), ['Accept' => 'application/json'])->assertCreated()->assertJsonPath('data.has_attachment', true);

        $leave = LeaveRequest::firstOrFail();
        Storage::disk('local')->assertExists($leave->attachment_path);
        $this->get(self::API."/requests/{$leave->id}/attachment")->assertOk();
        $this->as($this->makeUser())->getJson(self::API."/requests/{$leave->id}/attachment")->assertForbidden();

        Storage::disk('local')->deleteDirectory('leave-attachments');
    }

    public function test_leave_across_new_year_is_refused(): void
    {
        $this->as($this->makeUser())->postJson(self::API.'/requests', $this->application(['start_date' => '2026-12-28', 'end_date' => '2027-01-08']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['end_date']);
    }

    // ── Balance moves only on approval ───────────────────────────────────────

    public function test_approval_deducts_the_balance_and_rejection_does_not(): void
    {
        $manager = $this->approver();
        $report = $this->reportOf($manager);

        $this->as($report)->postJson(self::API.'/requests', $this->application())->assertCreated();
        $leave = LeaveRequest::firstOrFail();
        $this->assertSame(0.0, (float) LeaveBalance::where('user_id', $report->id)->value('used_days'));

        $this->as($manager)->patchJson(self::API."/requests/{$leave->id}/approve")->assertOk();
        $this->assertSame(5.0, (float) LeaveBalance::where('user_id', $report->id)->value('used_days'));

        $this->as($report)->getJson(self::API.'/balances?year=2026')
            ->assertJsonPath('balances.0.used_days', 5)
            ->assertJsonPath('balances.0.remaining_days', 17);

        // Deciding twice is refused.
        $this->as($manager)->patchJson(self::API."/requests/{$leave->id}/approve")->assertForbidden();

        $other = $this->pendingFor($report, ['start_date' => '2026-03-16', 'end_date' => '2026-03-17', 'days' => 2]);
        $this->patchJson(self::API."/requests/{$other->id}/reject", ['comment' => 'Busy'])->assertOk();
        $this->assertSame(5.0, (float) LeaveBalance::where('user_id', $report->id)->value('used_days'));
    }

    public function test_approval_is_refused_if_the_balance_ran_out_meanwhile(): void
    {
        $manager = $this->approver();
        $report = $this->reportOf($manager);
        $leave = $this->pendingFor($report);
        LeaveBalance::create(['user_id' => $report->id, 'leave_type_id' => $this->annual->id, 'year' => 2026, 'entitled_days' => 3, 'used_days' => 0]);

        $this->as($manager)->patchJson(self::API."/requests/{$leave->id}/approve")
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['days']);
        $this->assertSame('pending', $leave->fresh()->status);
        $this->assertSame(0.0, (float) LeaveBalance::where('user_id', $report->id)->value('used_days'));
    }

    // ── Admin correction ─────────────────────────────────────────────────────

    public function test_admin_correction_restores_the_balance_and_writes_an_audit_entry(): void
    {
        $admin = $this->makeUser('staff', ['leave_admin']);
        $staff = $this->makeUser();
        $leave = $this->pendingFor($staff);

        $this->as($admin)->patchJson(self::API."/requests/{$leave->id}/approve")->assertOk();
        $this->assertSame(5.0, (float) LeaveBalance::where('user_id', $staff->id)->value('used_days'));

        $this->patchJson(self::API."/requests/{$leave->id}/correct", ['status' => 'rejected'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['reason']);

        $this->patchJson(self::API."/requests/{$leave->id}/correct", [
            'status' => 'rejected', 'reason' => 'Approved by mistake; clashes with audit week', 'comment' => 'Please re-apply for April',
        ])->assertOk()
            ->assertJsonPath('data.status', 'rejected')
            ->assertJsonPath('data.correction_reason', 'Approved by mistake; clashes with audit week');

        $this->assertSame(0.0, (float) LeaveBalance::where('user_id', $staff->id)->value('used_days'));

        $audit = AuditLog::where('route_name', 'api.leave.requests.correct')->firstOrFail();
        $this->assertSame($admin->id, $audit->user_id);
        $this->assertStringContainsString("status 'approved' → 'rejected'", $audit->description);
        $this->assertStringContainsString("comment '' → 'Please re-apply for April'", $audit->description);
        $this->assertStringContainsString('Reason: Approved by mistake', $audit->description);

        // And back to approved deducts again.
        $this->patchJson(self::API."/requests/{$leave->id}/correct", ['status' => 'approved', 'reason' => 'Audit week moved'])->assertOk();
        $this->assertSame(5.0, (float) LeaveBalance::where('user_id', $staff->id)->value('used_days'));
    }

    public function test_pending_requests_cannot_be_corrected(): void
    {
        $leave = $this->pendingFor($this->makeUser());

        $this->as($this->makeUser('admin'))->patchJson(self::API."/requests/{$leave->id}/correct", ['status' => 'approved', 'reason' => 'Skip queue'])
            ->assertForbidden()
            ->assertJsonPath('message', 'Only an approved or rejected request can be corrected.');
    }

    // ── Notifications ────────────────────────────────────────────────────────

    public function test_manager_is_notified_on_submit_and_employee_on_decision(): void
    {
        Notification::fake();
        $manager = $this->approver();
        $report = $this->reportOf($manager);

        $this->as($report)->postJson(self::API.'/requests', $this->application())->assertCreated();
        Notification::assertSentTo($manager, LeaveRequestSubmittedNotification::class);

        $leave = LeaveRequest::firstOrFail();
        $this->as($manager)->patchJson(self::API."/requests/{$leave->id}/approve")->assertOk();
        Notification::assertSentTo($report, LeaveRequestDecidedNotification::class);
    }
}
