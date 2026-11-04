<?php

namespace Tests\Feature;

use App\Models\Ticket;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

// Tickets → follow-up views (awaiting uptake / follow-up due / uptake overdue)
// and the one-click "Confirm uptake" action.
class TicketFollowUpTest extends TestCase
{
    use RefreshDatabase;

    private User $agentA;
    private User $agentB;
    private User $manager;
    private array $t = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(CarbonImmutable::create(2026, 10, 23, 10));

        $this->agentA  = $this->user('Agent A', 'agent', ['tickets']);
        $this->agentB  = $this->user('Agent B', 'agent', ['tickets']);
        $this->manager = $this->user('Manager', 'helpline_manager', ['tickets', 'ticket_management']);

        $a = $this->agentA->id;
        $this->t = [
            'awaiting'       => $this->ticket($a, ['referred_to' => 'Clinic']),
            'na_referral'    => $this->ticket($a, ['referred_to' => 'N/A']),
            'confirmed'      => $this->ticket($a, ['referred_to' => 'Clinic', 'uptake_confirmed' => 1]),
            'due'            => $this->ticket($a, ['follow_up_date' => '2026-10-22']),
            'due_resolved'   => $this->ticket($a, ['follow_up_date' => '2026-10-22', 'status' => 'resolved']),
            'due_tomorrow'   => $this->ticket($a, ['follow_up_date' => '2026-10-24']),
            'uptake_overdue' => $this->ticket($a, ['referred_to' => 'Youth Centre', 'referral_uptake_date' => '2026-10-20']),
            'other_agent'    => $this->ticket($this->agentB->id, ['referred_to' => 'Clinic']),
        ];
    }

    private function user(string $name, string $role, array $perms): User
    {
        return User::create(['name' => $name, 'email' => str($name)->slug().'@example.test', 'password' => bcrypt('x'), 'role' => $role, 'nav_permissions' => $perms]);
    }

    private function ticket(int $agentId, array $attrs): int
    {
        return DB::table('tickets')->insertGetId($attrs + [
            'subject' => 'Case', 'agent_id' => $agentId, 'status' => 'ongoing', 'priority' => 'medium',
            'uptake_confirmed' => 0, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function ids($page): array
    {
        return collect($page->toArray()['props']['tickets']['data'])->pluck('id')->sort()->values()->all();
    }

    public function test_agent_counts_cover_only_their_own_tickets(): void
    {
        $this->withoutVite()->actingAs($this->agentA)->get('/tickets')->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('followUpCounts.awaiting_uptake', 2) // awaiting + uptake_overdue (N/A and confirmed excluded)
                ->where('followUpCounts.follow_up_due', 1)   // due only (resolved / tomorrow excluded)
                ->where('followUpCounts.uptake_overdue', 1)
            );

        // A manager with Ticket Management sees everyone's.
        $this->withoutVite()->actingAs($this->manager)->get('/tickets')
            ->assertInertia(fn ($page) => $page->where('followUpCounts.awaiting_uptake', 3));
    }

    public function test_each_view_lists_the_right_tickets(): void
    {
        $get = fn (string $view) => $this->withoutVite()->actingAs($this->agentA)->get("/tickets?follow_up={$view}");

        $get('awaiting_uptake')->assertInertia(fn ($page) => $page->where('filters.follow_up', 'awaiting_uptake')
            ->where('tickets.data', fn ($rows) => collect($rows)->pluck('id')->sort()->values()->all()
                === collect([$this->t['awaiting'], $this->t['uptake_overdue']])->sort()->values()->all()));
        $get('follow_up_due')->assertInertia(fn ($page) => $page->has('tickets.data', 1)->where('tickets.data.0.id', $this->t['due']));
        $get('uptake_overdue')->assertInertia(fn ($page) => $page->has('tickets.data', 1)->where('tickets.data.0.id', $this->t['uptake_overdue']));
    }

    public function test_confirm_uptake_marks_the_ticket_and_adds_a_note(): void
    {
        $this->actingAs($this->agentA)->from('/tickets')->post("/tickets/{$this->t['awaiting']}/confirm-uptake")
            ->assertSessionHas('success', "Uptake confirmed for ticket #{$this->t['awaiting']}.");

        $ticket = Ticket::with('notes')->findOrFail($this->t['awaiting']);
        $this->assertTrue((bool) $ticket->uptake_confirmed);
        $this->assertSame('Service uptake confirmed (referred to Clinic).', $ticket->notes->first()->body);
        $this->assertStringContainsString("Confirmed service uptake on ticket #{$ticket->id}", \App\Models\AuditLog::latest('id')->value('description'));

        // It drops out of the awaiting view.
        $this->withoutVite()->actingAs($this->agentA)->get('/tickets')
            ->assertInertia(fn ($page) => $page->where('followUpCounts.awaiting_uptake', 1));
    }

    public function test_agents_cannot_confirm_someone_elses_ticket_but_managers_can(): void
    {
        $this->actingAs($this->agentA)->post("/tickets/{$this->t['other_agent']}/confirm-uptake")->assertForbidden();
        $this->assertFalse((bool) Ticket::find($this->t['other_agent'])->uptake_confirmed);

        $this->actingAs($this->manager)->from('/tickets')->post("/tickets/{$this->t['other_agent']}/confirm-uptake", ['note' => 'Client attended on Monday'])
            ->assertSessionHas('success');
        $ticket = Ticket::with('notes')->find($this->t['other_agent']);
        $this->assertTrue((bool) $ticket->uptake_confirmed);
        $this->assertSame('Service uptake confirmed (referred to Clinic): Client attended on Monday', $ticket->notes->first()->body);
    }

    public function test_new_tickets_have_no_medium_priority(): void
    {
        $post = fn (array $data) => $this->actingAs($this->agentA)->from('/tickets')
            ->post('/tickets', $data + ['subject' => 'New case', 'contact_number' => '0771000000']);

        $post(['priority' => 'medium'])->assertSessionHasErrors(['priority' => 'Choose a priority: Low, High or Urgent.']);

        $post([])->assertSessionHasNoErrors();
        $this->assertSame('low', Ticket::where('subject', 'New case')->latest('id')->value('priority')); // default is now Low

        $post(['priority' => 'urgent', 'follow_up_date' => '2026-10-30', 'log_note' => 'Called back'])->assertSessionHasNoErrors();
        $this->assertSame('urgent', Ticket::where('subject', 'New case')->latest('id')->value('priority'));

        // Existing Medium tickets can still be edited.
        $this->actingAs($this->agentA)->from("/tickets/{$this->t['due']}")
            ->put("/tickets/{$this->t['due']}", ['priority' => 'medium', 'subject' => 'Case'])
            ->assertSessionHasNoErrors();
    }

    public function test_high_and_urgent_tickets_need_a_follow_up_date_and_log_note(): void
    {
        $post = fn (array $data) => $this->actingAs($this->agentA)->from('/tickets')
            ->post('/tickets', $data + ['subject' => 'Serious case', 'contact_number' => '0771000000']);

        // The follow-up date is filled in automatically; the log note is not.
        $post(['priority' => 'high'])
            ->assertSessionHasErrors(['log_note' => 'A log note is required for High and Urgent tickets.'])
            ->assertSessionDoesntHaveErrors('follow_up_date');
        $this->assertFalse(Ticket::where('subject', 'Serious case')->exists());

        $post(['priority' => 'high', 'follow_up_date' => '2026-10-25', 'log_note' => '  Referred to clinic, will call Monday '])
            ->assertSessionHasNoErrors();
        $ticket = Ticket::with('notes')->where('subject', 'Serious case')->firstOrFail();
        $this->assertCount(1, $ticket->notes);
        $this->assertSame('Referred to clinic, will call Monday', $ticket->notes[0]->body);
        $this->assertSame($this->agentA->id, $ticket->notes[0]->user_id);

        // Low tickets need neither.
        $post(['priority' => 'low', 'subject' => 'Minor case'])->assertSessionHasNoErrors();

        // Raising an existing ticket with no notes to Urgent needs a log note.
        $id  = $this->ticket($this->agentA->id, ['priority' => 'low']);
        $put = fn (array $data) => $this->actingAs($this->agentA)->from("/tickets/{$id}")->put("/tickets/{$id}", $data + ['subject' => 'Case']);
        $put(['priority' => 'urgent'])->assertSessionHasErrors(['log_note'])->assertSessionDoesntHaveErrors('follow_up_date');
        $put(['priority' => 'urgent', 'follow_up_date' => '2026-10-26', 'log_note' => 'Escalated'])->assertSessionHasNoErrors();
        $this->assertSame('urgent', Ticket::find($id)->priority);
        $this->assertSame('Escalated', Ticket::find($id)->notes()->value('body'));

        // Once it is Urgent with a date, later edits don't ask for another note.
        $put(['priority' => 'urgent', 'description' => 'Updated'])->assertSessionHasNoErrors();
    }

    public function test_follow_up_and_uptake_dates_are_set_from_the_priority(): void
    {
        // setUp's clock: 2026-10-23.
        $post = fn (array $data) => $this->actingAs($this->agentA)->from('/tickets')
            ->post('/tickets', $data + ['contact_number' => '0771000000'])->assertSessionHasNoErrors();
        $dates = fn (string $subject) => Ticket::where('subject', $subject)->get(['follow_up_date', 'referral_uptake_date'])
            ->map(fn ($t) => [substr((string) $t->follow_up_date, 0, 10), $t->referral_uptake_date ? substr((string) $t->referral_uptake_date, 0, 10) : null])
            ->first();

        $post(['subject' => 'Urgent one', 'priority' => 'urgent', 'log_note' => 'x']);
        $post(['subject' => 'High one', 'priority' => 'high', 'log_note' => 'x']);
        $post(['subject' => 'Low one', 'priority' => 'low']);
        $post(['subject' => 'Default one']);
        $post(['subject' => 'Chosen', 'priority' => 'high', 'log_note' => 'x', 'follow_up_date' => '2026-10-24', 'referral_uptake_date' => '2026-10-30']);

        $this->assertSame(['2026-10-25', '2026-10-27'], $dates('Urgent one')); // 48 h / 96 h
        $this->assertSame(['2026-10-25', '2026-10-27'], $dates('High one'));
        $this->assertSame(['2026-10-30', null], $dates('Low one'));            // 7 days, no uptake deadline
        $this->assertSame(['2026-10-30', null], $dates('Default one'));
        $this->assertSame(['2026-10-24', '2026-10-30'], $dates('Chosen'));    // the agent's own dates are kept

        // Raising a Low ticket to Urgent restarts the clock from the day it is raised.
        $this->travelTo(CarbonImmutable::create(2026, 10, 26, 9));
        $low = Ticket::where('subject', 'Low one')->first();
        $this->actingAs($this->agentA)->from("/tickets/{$low->id}")->put("/tickets/{$low->id}", [
            'subject' => 'Low one', 'priority' => 'urgent', 'log_note' => 'Escalated',
            'follow_up_date' => '2026-10-30', // unchanged from the form
        ])->assertSessionHasNoErrors();
        $this->assertSame(['2026-10-28', '2026-10-30'], $dates('Low one'));
    }

    public function test_the_follow_up_pop_up_lists_the_agents_own_due_cases(): void
    {
        // setUp's clock: 2026-10-23. Agent A has 'due' (follow-up yesterday) and
        // 'uptake_overdue' (uptake 2026-10-20) due; the rest are not.
        $uptakeToday = $this->ticket($this->agentA->id, ['subject' => 'Uptake today', 'priority' => 'urgent', 'referral_uptake_date' => '2026-10-23']);
        $this->ticket($this->agentA->id, ['referral_uptake_date' => '2026-10-23', 'uptake_confirmed' => 1]);

        $res = $this->actingAs($this->agentA)->getJson('/tickets/follow-ups-due')->assertOk()
            ->assertJsonPath('count', 3)
            ->assertJsonPath('today', '2026-10-23')
            ->assertJsonPath('tickets.0.id', $uptakeToday) // urgent first
            ->assertJsonPath('tickets.0.uptake_due', '2026-10-23')
            ->assertJsonPath('tickets.0.follow_up_due', null);
        $this->assertEqualsCanonicalizing([$uptakeToday, $this->t['due'], $this->t['uptake_overdue']], array_column($res->json('tickets'), 'id'));

        // Only ever the agent's own cases, even for a manager.
        $this->actingAs($this->agentB)->getJson('/tickets/follow-ups-due')->assertJsonPath('count', 0);
        $this->actingAs($this->manager)->getJson('/tickets/follow-ups-due')->assertJsonPath('count', 0);
    }

    public function test_a_still_ongoing_case_carries_on_until_it_is_resolved(): void
    {
        // setUp's clock: 2026-10-23. An Urgent case due today, uptake due today.
        $id = $this->ticket($this->agentA->id, ['subject' => 'Shelter', 'priority' => 'urgent', 'referred_to' => 'Shelter',
            'follow_up_date' => '2026-10-23', 'referral_uptake_date' => '2026-10-23']);
        $followUp = fn (array $data) => $this->actingAs($this->agentA)->postJson("/tickets/{$id}/follow-up", $data);

        $followUp([])->assertStatus(422)->assertJsonValidationErrors(['status' => 'Choose the case status.']);

        // Still ongoing → next follow-up in 48 h, uptake in 96 h; off today's list.
        $followUp(['status' => 'ongoing', 'note' => 'Called; client still waiting for a bed'])->assertOk()
            ->assertJsonPath('follow_up_date', '2026-10-25')
            ->assertJsonPath('referral_uptake_date', '2026-10-27')
            ->assertJsonPath('message', 'Case still ongoing: next follow-up 25 Oct 2026, referral uptake now expected by 27 Oct 2026.');
        $this->assertSame("Follow-up done. Case status: Ongoing.\nCalled; client still waiting for a bed\nCase still ongoing: next follow-up 25 Oct 2026, referral uptake now expected by 27 Oct 2026.",
            Ticket::find($id)->notes()->value('body'));
        $this->assertNotContains($id, array_column($this->actingAs($this->agentA)->getJson('/tickets/follow-ups-due')->json('tickets'), 'id'));

        // Two days later it pops up again; uptake confirmed this time, still ongoing.
        $this->travelTo(CarbonImmutable::create(2026, 10, 25, 9));
        $this->assertContains($id, array_column($this->actingAs($this->agentA)->getJson('/tickets/follow-ups-due')->json('tickets'), 'id'));
        $followUp(['status' => 'ongoing', 'uptake_confirmed' => true])->assertOk()
            ->assertJsonPath('follow_up_date', '2026-10-27')
            ->assertJsonPath('referral_uptake_date', '2026-10-27'); // confirmed uptake isn't moved
        $this->assertTrue(Ticket::find($id)->uptake_confirmed);

        // Resolved → the cycle ends.
        $this->travelTo(CarbonImmutable::create(2026, 10, 27, 9));
        $followUp(['status' => 'resolved'])->assertOk()->assertJsonPath('follow_up_date', '2026-10-27')
            ->assertJsonPath('message', 'Follow-up recorded. Case resolved.');
        $this->assertNotNull(Ticket::find($id)->resolved_at);
        $this->actingAs($this->agentA)->getJson('/tickets/follow-ups-due')->assertJsonMissing(['id' => $id]);

        // Someone else's case can't be followed up by an agent.
        $this->actingAs($this->agentB)->postJson("/tickets/{$id}/follow-up", ['status' => 'ongoing'])->assertForbidden();
    }

    public function test_saving_a_due_low_case_as_ongoing_from_the_edit_form_books_the_next_follow_up(): void
    {
        // 'due' (Medium, follow-up 2026-10-22) and a Low one due today.
        $low = $this->ticket($this->agentA->id, ['subject' => 'Low due', 'priority' => 'low', 'follow_up_date' => '2026-10-23']);
        $this->actingAs($this->agentA)->from("/tickets/{$low}")
            ->put("/tickets/{$low}", ['subject' => 'Low due', 'status' => 'ongoing', 'follow_up_date' => '2026-10-23'])
            ->assertSessionHas('success', 'Ticket updated. Case still ongoing: next follow-up 30 Oct 2026.');
        $this->assertSame('2026-10-30', substr((string) Ticket::find($low)->follow_up_date, 0, 10));

        // An agent who picks a later date themselves keeps it.
        $id = $this->t['due'];
        $this->actingAs($this->agentA)->put("/tickets/{$id}", ['subject' => 'Case', 'status' => 'ongoing', 'follow_up_date' => '2026-11-02']);
        $this->assertSame('2026-11-02', substr((string) Ticket::find($id)->follow_up_date, 0, 10));
    }

    public function test_high_and_urgent_tickets_go_on_the_urgent_cases_board(): void
    {
        $post = fn (array $data) => $this->actingAs($this->agentA)->from('/tickets')
            ->post('/tickets', $data + ['contact_number' => '0771000000']);

        $post(['subject' => 'High one', 'priority' => 'high', 'follow_up_date' => '2026-10-25', 'log_note' => 'x']);
        $post(['subject' => 'Low one', 'priority' => 'low']);
        $this->assertSame('open', \App\Models\UrgentCase::where('subject', 'High one')->value('status'));
        $this->assertFalse(\App\Models\UrgentCase::where('subject', 'Low one')->exists());

        // Raising a ticket adds it once; saving again doesn't duplicate it.
        $id = Ticket::where('subject', 'Low one')->value('id');
        foreach ([1, 2] as $_) {
            $this->actingAs($this->agentA)->put("/tickets/{$id}", ['subject' => 'Low one', 'priority' => 'urgent', 'follow_up_date' => '2026-10-25', 'log_note' => 'Escalated']);
        }
        $this->assertSame(1, \App\Models\UrgentCase::where('source_ticket_id', $id)->count());

        // Existing tickets are picked up by the sync command (resolved ones go on as resolved).
        $old  = $this->ticket($this->agentA->id, ['subject' => 'Old high', 'priority' => 'high']);
        $done = $this->ticket($this->agentA->id, ['subject' => 'Old done', 'priority' => 'urgent', 'status' => 'resolved']);
        $this->artisan('tickets:sync-urgent-cases')->assertSuccessful();
        $this->assertSame('open', \App\Models\UrgentCase::where('source_ticket_id', $old)->value('status'));
        $this->assertSame('resolved', \App\Models\UrgentCase::where('source_ticket_id', $done)->value('status'));
        $this->artisan('tickets:sync-urgent-cases')->expectsOutputToContain('Nothing to add')->assertSuccessful();

        $this->withoutVite()->actingAs($this->manager)->get('/urgent-cases')->assertOk()
            ->assertInertia(fn ($page) => $page->has('cases.data', 4));
    }

    public function test_a_log_note_can_be_emailed(): void
    {
        \Illuminate\Support\Facades\Mail::fake();
        $id   = $this->t['due'];
        $note = fn (array $data) => $this->actingAs($this->agentA)->from("/tickets/{$id}")->post("/tickets/{$id}/notes", $data);

        // Plain note: nothing sent.
        $note(['body' => 'Just a note'])->assertSessionHas('success', 'Note added.');
        \Illuminate\Support\Facades\Mail::assertNothingSent();

        // A bad address is rejected before anything is saved.
        $note(['body' => 'Emailed', 'email_to' => 'not-an-email'])->assertSessionHasErrors(['email_to' => 'Not a valid email address: not-an-email']);
        $this->assertFalse(\App\Models\TicketNote::where('body', 'like', 'Emailed%')->exists());

        $note(['body' => 'Emailed', 'email_user_ids' => [$this->manager->id], 'email_to' => 'partner@clinic.org, MANAGER@example.test'])
            ->assertSessionHas('success', 'Note added and emailed to 2 recipient(s).');

        \Illuminate\Support\Facades\Mail::assertSent(\App\Mail\TicketLogNoteMail::class, fn ($m) => $m->hasTo('manager@example.test') && $m->hasTo('partner@clinic.org')
            && str_starts_with($m->note->body, 'Emailed') && str_contains($m->render(), 'Open the ticket'));
        $this->assertSame("Emailed\n\nEmailed to: manager@example.test, partner@clinic.org",
            \App\Models\TicketNote::where('body', 'like', 'Emailed%')->value('body'));
    }

    public function test_agents_get_one_reminder_on_the_follow_up_date(): void
    {
        // setUp's clock is 2026-10-23: only open tickets due that day count.
        $today    = $this->ticket($this->agentA->id, ['subject' => 'Call back', 'follow_up_date' => '2026-10-23', 'priority' => 'high']);
        $this->ticket($this->agentA->id, ['follow_up_date' => '2026-10-23', 'status' => 'resolved']);
        $this->ticket($this->agentB->id, ['follow_up_date' => '2026-10-23']);

        $this->artisan('tickets:follow-up-reminders')->expectsOutput('Sent 2 follow-up reminder(s).')->assertSuccessful();
        $this->artisan('tickets:follow-up-reminders')->expectsOutput('Sent 0 follow-up reminder(s).'); // no repeats

        $n = $this->agentA->notifications()->first();
        $this->assertSame(1, $this->agentA->notifications()->count());
        $this->assertSame("/tickets/{$today}", $n->data['url']);
        $this->assertSame("Follow-up due today: ticket #{$today} \"Call back\" (High).", $n->data['message']);

        // Moving the follow-up to a new date earns a new reminder on that day.
        Ticket::whereKey($today)->update(['follow_up_date' => '2026-10-24']);
        $this->travelTo(CarbonImmutable::create(2026, 10, 24, 8));
        $this->artisan('tickets:follow-up-reminders')->expectsOutput('Sent 2 follow-up reminder(s).'); // + setUp's due_tomorrow
        $this->assertSame(3, $this->agentA->notifications()->count());
        $this->assertSame(1, $this->agentA->notifications()->where('data', 'like', "%\"ticket_id\":{$today},\"follow_up_date\":\"2026-10-24\"%")->count());
    }

    public function test_other_service_needs_a_specified_detail(): void
    {
        $post = fn (array $data) => $this->actingAs($this->agentA)->from('/tickets')
            ->post('/tickets', $data + ['subject' => 'Other case', 'contact_number' => '0771000000']);

        $post(['services_requested' => 'Other (Specify):'])
            ->assertSessionHasErrors(['services_requested_other' => 'Please specify the other service.']);

        $post([
            'services_requested_before' => 'Other (Specify):', 'services_requested_before_other' => ' Legal aid ',
            'services_requested'        => 'PSS',              'services_requested_other'        => 'stale text',
        ])->assertSessionHasNoErrors();

        $ticket = Ticket::where('subject', 'Other case')->firstOrFail();
        $this->assertSame('Legal aid', $ticket->services_requested_before_other);
        $this->assertNull($ticket->services_requested_other); // not "Other", so the detail is dropped
        $this->assertSame('Other (Specify): Legal aid', $ticket->serviceWithDetail('services_requested_before'));
        $this->assertSame('PSS', $ticket->serviceWithDetail('services_requested'));
    }

    public function test_export_follows_the_selected_view(): void
    {
        $res = $this->actingAs($this->agentA)->get('/tickets/export?follow_up=follow_up_due')->assertOk();
        $csv = $res->baseResponse instanceof \Symfony\Component\HttpFoundation\StreamedResponse ? $res->streamedContent() : $res->getContent();
        $rows = array_values(array_filter(explode("\n", trim($csv))));
        $this->assertCount(2, $rows); // header + the one due ticket
    }
}
