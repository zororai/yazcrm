<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\LsjpCheckup;
use App\Models\LsjpParticipant;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class LsjpRegisterTest extends TestCase
{
    use RefreshDatabase;

    private User $officer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(CarbonImmutable::create(2026, 6, 15, 10));
        Storage::fake('public');
        $this->officer = $this->user('programs', ['lsjp']);
    }

    private function user(string $role, array $perms = []): User
    {
        static $n = 0;
        $n++;

        return User::create(['name' => "LSJP User {$n}", 'email' => "lsjp{$n}@example.test", 'password' => bcrypt('x'), 'role' => $role, 'nav_permissions' => $perms]);
    }

    private function person(array $attrs = []): LsjpParticipant
    {
        return LsjpParticipant::create($attrs + [
            'full_name' => 'Tendai Moyo', 'training_completed_on' => '2026-05-01', 'created_by' => $this->officer->id,
        ]);
    }

    private function photo(string $name = 'shop.jpg'): UploadedFile
    {
        return UploadedFile::fake()->image($name, 40, 40);
    }

    public function test_register_requires_the_lsjp_permission(): void
    {
        $this->actingAs($this->user('agent', ['calls']))->get('/lsjp')->assertForbidden();
        $this->actingAs($this->user('agent', ['calls']))->post('/lsjp', ['full_name' => 'X', 'training_completed_on' => '2026-05-01'])->assertForbidden();
        $this->withoutVite()->actingAs($this->officer)->get('/lsjp')->assertOk();
        $this->withoutVite()->actingAs($this->user('admin'))->get('/lsjp')->assertOk();
    }

    public function test_adding_a_person_records_all_fields_and_validates(): void
    {
        $this->actingAs($this->officer)->post('/lsjp', [
            'full_name' => 'Rudo Chikomo', 'id_number' => '63-123456A78', 'age' => 22, 'sex' => 'female',
            'phone' => '0771000000', 'province' => 'Harare', 'district' => 'Epworth', 'location' => 'Ward 7',
            'key_population' => 'Young women', 'current_activity' => 'Selling vegetables',
            'skill_trained' => 'Tailoring', 'project' => 'UNICEF', 'training_completed_on' => '2026-05-01',
        ])->assertRedirect();

        $person = LsjpParticipant::where('full_name', 'Rudo Chikomo')->firstOrFail();
        $this->assertSame('63-123456A78', $person->id_number);
        $this->assertSame('Young women', $person->key_population);
        $this->assertSame('Selling vegetables', $person->current_activity);
        $this->assertSame($this->officer->id, $person->created_by);
        $this->assertStringContainsString('Added Rudo Chikomo to the LSJP register', AuditLog::latest('id')->value('description'));

        // Same ID twice, a future training date and an unknown province are refused.
        $this->actingAs($this->officer)->from('/lsjp')->post('/lsjp', [
            'full_name' => 'Someone Else', 'id_number' => '63-123456A78', 'province' => 'Atlantis', 'training_completed_on' => '2026-07-01',
        ])->assertSessionHasErrors(['id_number', 'province', 'training_completed_on']);
        $this->assertSame(1, LsjpParticipant::count());
    }

    public function test_checkups_fall_due_at_one_three_and_six_months(): void
    {
        $schedule = collect($this->person(['training_completed_on' => '2026-05-01'])->load('checkups')->schedule())->keyBy('month');
        $this->assertSame(['2026-06-01', 'overdue'], [$schedule[1]['due_date'], $schedule[1]['state']]);
        $this->assertSame(['2026-08-01', 'upcoming'], [$schedule[3]['due_date'], $schedule[3]['state']]);
        $this->assertSame(['2026-11-01', 'upcoming'], [$schedule[6]['due_date'], $schedule[6]['state']]);

        // 3-month check-up due in 5 days → "due soon".
        $soon = collect($this->person(['full_name' => 'B', 'training_completed_on' => '2026-03-20'])->load('checkups')->schedule())->keyBy('month');
        $this->assertSame('due_soon', $soon[3]['state']);

        // End-of-month training doesn't overflow (31 Jan + 1 month = 28 Feb).
        $eom = collect($this->person(['full_name' => 'C', 'training_completed_on' => '2026-01-31'])->load('checkups')->schedule())->keyBy('month');
        $this->assertSame('2026-02-28', $eom[1]['due_date']);
    }

    public function test_recording_a_checkup_with_challenges_comment_referral_and_photos(): void
    {
        $person = $this->person();

        $this->actingAs($this->officer)->from("/lsjp/{$person->id}")->post("/lsjp/{$person->id}/checkups/1", [
            'conducted_on' => '2026-06-10', 'progress' => 'struggling', 'activity_status' => 'Tailoring from home',
            'challenges' => 'No sewing machine', 'comment' => 'Motivated', 'referred_to' => 'Youth fund',
            'referral_notes' => 'Ref #44', 'photos' => [$this->photo('a.jpg'), $this->photo('b.jpg')],
        ])->assertSessionHasNoErrors();

        $checkup = LsjpCheckup::with('photos')->firstOrFail();
        $this->assertSame(1, $checkup->month);
        $this->assertSame('No sewing machine', $checkup->challenges);
        $this->assertSame('Youth fund', $checkup->referred_to);
        $this->assertSame($this->officer->id, $checkup->conducted_by);
        $this->assertCount(2, $checkup->photos);
        Storage::disk('public')->assertExists($checkup->photos[0]->path);
        $this->assertSame('done', collect($person->fresh()->load('checkups')->schedule())->firstWhere('month', 1)['state']);
        $this->assertSame('Recorded 1 month check-up for Tendai Moyo: Struggling · referred to Youth fund', AuditLog::latest('id')->value('description'));

        // Saving the same month again updates it (no duplicate) and adds photos.
        $this->actingAs($this->officer)->from("/lsjp/{$person->id}")->post("/lsjp/{$person->id}/checkups/1", [
            'conducted_on' => '2026-06-10', 'progress' => 'progressing', 'photos' => [$this->photo()],
        ])->assertSessionHasNoErrors();
        $this->assertSame(1, LsjpCheckup::count());
        $this->assertSame('progressing', $checkup->fresh()->progress);
        $this->assertCount(3, $checkup->fresh()->photos);
        $this->assertStringStartsWith('Updated 1 month check-up', AuditLog::latest('id')->value('description'));
    }

    public function test_checkup_validation(): void
    {
        $person = $this->person();
        $post = fn (array $data, int $month = 1) => $this->actingAs($this->officer)->from("/lsjp/{$person->id}")->post("/lsjp/{$person->id}/checkups/{$month}", $data);

        $post(['conducted_on' => '2026-04-01', 'progress' => 'great'])->assertSessionHasErrors(['conducted_on', 'progress']);
        $post(['conducted_on' => '2026-07-01', 'progress' => 'thriving'])->assertSessionHasErrors('conducted_on'); // future
        $post(['conducted_on' => '2026-06-10', 'progress' => 'thriving'], 2)->assertNotFound();                  // only 1/3/6
        $post(['conducted_on' => '2026-06-10', 'progress' => 'thriving', 'photos' => array_map(fn ($i) => $this->photo("p{$i}.jpg"), range(1, 7))])
            ->assertSessionHasErrors('photos');
        $this->assertSame(0, LsjpCheckup::count());
    }

    public function test_index_summary_and_follow_up_filter(): void
    {
        $overdue = $this->person(['full_name' => 'Overdue Person', 'training_completed_on' => '2026-05-01']);
        $this->person(['full_name' => 'Due Soon Person', 'training_completed_on' => '2026-03-20']);
        $complete = $this->person(['full_name' => 'Complete Person', 'training_completed_on' => '2025-11-01']);
        foreach ([1, 3, 6] as $m) {
            LsjpCheckup::create(['lsjp_participant_id' => $complete->id, 'month' => $m, 'conducted_on' => '2026-05-02', 'progress' => 'thriving']);
        }

        $this->withoutVite()->actingAs($this->officer)->get('/lsjp')->assertInertia(fn ($page) => $page
            ->component('Lsjp/Index')
            ->where('summary.people', 3)
            ->where('summary.complete', 1)
            ->where('summary.done', 3)
            ->where('summary.overdue', 2)   // Overdue Person 1m + Due Soon Person 1m
            ->where('summary.due_soon', 1)  // Due Soon Person 3m
            ->has('people', 3)
        );

        $this->withoutVite()->actingAs($this->officer)->get('/lsjp?follow_up=due_soon')
            ->assertInertia(fn ($page) => $page->has('people', 1)->where('people.0.full_name', 'Due Soon Person'));
        $this->withoutVite()->actingAs($this->officer)->get('/lsjp?follow_up=complete')
            ->assertInertia(fn ($page) => $page->has('people', 1)->where('people.0.full_name', 'Complete Person'));
        $this->withoutVite()->actingAs($this->officer)->get('/lsjp?follow_up=overdue')
            ->assertInertia(fn ($page) => $page->has('people', 2));
        $this->withoutVite()->actingAs($this->officer)->get('/lsjp?search=overdue')
            ->assertInertia(fn ($page) => $page->has('people', 1)->where('people.0.id', $overdue->id));
    }

    public function test_only_admin_or_director_can_remove_a_person_and_photos_can_be_removed(): void
    {
        $person = $this->person();
        $this->actingAs($this->officer)->post("/lsjp/{$person->id}/checkups/1", [
            'conducted_on' => '2026-06-10', 'progress' => 'thriving', 'photos' => [$this->photo()],
        ]);
        $photo = LsjpCheckup::first()->photos()->first();

        $this->actingAs($this->officer)->from("/lsjp/{$person->id}")->delete("/lsjp/photos/{$photo->id}")->assertSessionHas('success');
        Storage::disk('public')->assertMissing($photo->path);

        $this->actingAs($this->officer)->delete("/lsjp/{$person->id}", ['reason' => 'x'])->assertForbidden();

        $director = $this->user('director', ['lsjp']);
        $this->actingAs($director)->from("/lsjp/{$person->id}")->delete("/lsjp/{$person->id}")->assertSessionHasErrors('reason');
        $this->actingAs($director)->delete("/lsjp/{$person->id}", ['reason' => 'Registered twice'])->assertRedirect('/lsjp');
        $this->assertSoftDeleted($person);
        $this->assertStringContainsString('Reason: Registered twice', AuditLog::latest('id')->value('description'));
    }
}
