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

        $post(['priority' => 'high'])->assertSessionHasErrors([
            'follow_up_date' => 'A follow-up date is required for High and Urgent tickets.',
            'log_note'       => 'A log note is required for High and Urgent tickets.',
        ]);
        $this->assertFalse(Ticket::where('subject', 'Serious case')->exists());

        $post(['priority' => 'high', 'follow_up_date' => '2026-10-25', 'log_note' => '  Referred to clinic, will call Monday '])
            ->assertSessionHasNoErrors();
        $ticket = Ticket::with('notes')->where('subject', 'Serious case')->firstOrFail();
        $this->assertCount(1, $ticket->notes);
        $this->assertSame('Referred to clinic, will call Monday', $ticket->notes[0]->body);
        $this->assertSame($this->agentA->id, $ticket->notes[0]->user_id);

        // Low tickets need neither.
        $post(['priority' => 'low', 'subject' => 'Minor case'])->assertSessionHasNoErrors();

        // Raising an existing ticket with no notes and no follow-up date to Urgent needs both.
        $id  = $this->ticket($this->agentA->id, ['priority' => 'low']);
        $put = fn (array $data) => $this->actingAs($this->agentA)->from("/tickets/{$id}")->put("/tickets/{$id}", $data + ['subject' => 'Case']);
        $put(['priority' => 'urgent'])->assertSessionHasErrors(['follow_up_date', 'log_note']);
        $put(['priority' => 'urgent', 'follow_up_date' => '2026-10-26', 'log_note' => 'Escalated'])->assertSessionHasNoErrors();
        $this->assertSame('urgent', Ticket::find($id)->priority);
        $this->assertSame('Escalated', Ticket::find($id)->notes()->value('body'));

        // Once it is Urgent with a date, later edits don't ask for another note.
        $put(['priority' => 'urgent', 'description' => 'Updated'])->assertSessionHasNoErrors();
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
