<?php

namespace Tests\Feature\DataCollection;

use App\Models\DataCollectionRejection;
use App\Models\DataCollectionSubmission;
use App\Models\DataCollectionSubmissionAudit;
use App\Models\DataCollectionSubmissionValue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class SubmissionIngestionTest extends TestCase
{
    use RefreshDatabase, BuildsDataCollectionForms;

    public function test_a_valid_submission_is_accepted_and_stored(): void
    {
        $form = $this->deployedHouseholdForm();
        $enumerator = $this->makeUser();
        $this->assign($form, $enumerator);
        $item = $this->validItem($form, [], ['submitted_by' => 999]); // ignored: never taken from the payload

        $result = $this->batch($enumerator, [$item])->assertOk()->json('results.0');

        $this->assertSame('accepted', $result['status']);
        $this->assertSame($item['client_uuid'], $result['client_uuid']);
        $this->assertNull($result['reason']);
        $this->assertSame([], $result['errors']);

        $submission = DataCollectionSubmission::findOrFail($result['server_id']);
        $this->assertSame($enumerator->id, $submission->submitted_by);
        $this->assertSame($form->current_version_id, $submission->form_version_id);
        $this->assertSame('pending', $submission->status);
        $this->assertSame('2026-10-30 09:15:00', $submission->collected_at->format('Y-m-d H:i:s'));
        $this->assertSame(['farming', 'business'], $submission->data['income_sources']);
        $this->assertSame(-17.82, $submission->lat);
        $this->assertNotNull($submission->processed_at, 'the queued job ran (sync queue in tests)');

        // Flattened analytics rows: one per select_multiple option, repeat
        // children keyed by "repeat/child" with their instance.
        $this->assertSame(2, DataCollectionSubmissionValue::where('submission_id', $submission->id)->where('field', 'income_sources')->count());
        $this->assertEquals(12, DataCollectionSubmissionValue::where('submission_id', $submission->id)
            ->where('field', 'members/member_age')->where('instance', 1)->value('value_num'));
        $this->assertSame('received', DataCollectionSubmissionAudit::where('submission_id', $submission->id)->value('action'));
    }

    public function test_duplicate_send_returns_duplicate_with_the_original_id(): void
    {
        $form = $this->deployedHouseholdForm();
        $enumerator = $this->makeUser();
        $this->assign($form, $enumerator);
        $item = $this->validItem($form);

        $first = $this->batch($enumerator, [$item])->json('results.0');
        $second = $this->batch($enumerator, [$item, $item])->json('results');

        $this->assertSame('accepted', $first['status']);
        $this->assertSame('duplicate', $second[0]['status']);
        $this->assertSame('duplicate', $second[1]['status']);
        $this->assertSame($first['server_id'], $second[0]['server_id']);
        $this->assertSame(1, DataCollectionSubmission::where('client_uuid', $item['client_uuid'])->count());
    }

    public function test_concurrent_duplicate_is_caught_by_the_unique_index(): void
    {
        $form = $this->deployedHouseholdForm();
        $enumerator = $this->makeUser();
        $this->assign($form, $enumerator);
        $item = $this->validItem($form);

        // Simulate a parallel request committing the same client_uuid after
        // our duplicate check (the form is loaded right after it) and before
        // our insert.
        $winnerId = null;
        \App\Models\DataCollectionForm::retrieved(function ($loaded) use (&$winnerId, $item, $form, $enumerator) {
            if ($winnerId === null && $loaded->id === $form->id) {
                $winnerId = DB::table('data_collection_submissions')->insertGetId([
                    'client_uuid' => $item['client_uuid'], 'form_id' => $form->id, 'form_version_id' => $form->current_version_id,
                    'submitted_by' => $enumerator->id, 'data' => '{}', 'status' => 'pending',
                    'created_at' => now(), 'updated_at' => now(),
                ]);
            }
        });

        $result = $this->batch($enumerator, [$item])->assertOk()->json('results.0');

        $this->assertSame('duplicate', $result['status']);
        $this->assertSame($winnerId, $result['server_id']);
        $this->assertSame(1, DataCollectionSubmission::where('client_uuid', $item['client_uuid'])->count());
    }

    public function test_one_bad_item_never_fails_the_batch(): void
    {
        $form = $this->deployedHouseholdForm();
        $enumerator = $this->makeUser();
        $this->assign($form, $enumerator);

        $good = $this->validItem($form);
        $badChoice = $this->validItem($form, ['ward' => 'ward_99', 'respondent_age' => 7]);
        $noUuid = $this->validItem($form, [], ['client_uuid' => null]);

        $results = $this->batch($enumerator, [$good, $badChoice, 'not an object', $noUuid])->assertOk()->json('results');

        $this->assertSame(['accepted', 'rejected', 'rejected', 'rejected'], array_column($results, 'status'));
        $this->assertSame('Some answers do not match the form.', $results[1]['reason']);
        $this->assertEqualsCanonicalizing(
            [['field' => 'ward', 'message' => "'ward_99' is not one of the choices."], ['field' => 'respondent_age', 'message' => 'Age must be between 10 and 100.']],
            $results[1]['errors'],
        );
        $this->assertSame('client_uuid', $results[3]['errors'][0]['field']);
        $this->assertSame(1, DataCollectionSubmission::count());

        // Rejections are counted for the dashboard, without the answers.
        $rejection = DataCollectionRejection::where('client_uuid', $badChoice['client_uuid'])->firstOrFail();
        $this->assertSame($form->id, $rejection->form_id);
        $this->assertStringNotContainsString('Rudo', json_encode($rejection->toArray()));
    }

    public function test_type_and_shape_problems_are_rejected_per_field(): void
    {
        $form = $this->deployedHouseholdForm();
        $enumerator = $this->makeUser();
        $this->assign($form, $enumerator);

        $item = $this->validItem($form, [
            'respondent_age'     => '19',                 // integer must be an int
            'interview_date'     => '30/10/2026',
            'needs'              => 'food',               // select_multiple must be an array
            'household_location' => ['lat' => 120, 'lng' => 31],
            'house_photo'        => 'not-listed.jpg',
            'members'            => [['member_name' => 'A', 'member_age' => 200, 'member_gender' => 'female']],
        ]);

        $errors = collect($this->batch($enumerator, [$item])->json('results.0.errors'))->pluck('message', 'field');

        $this->assertSame('Must be a whole number.', $errors['respondent_age']);
        $this->assertSame('Must be a date as YYYY-MM-DD.', $errors['interview_date']);
        $this->assertSame('Must be a list of choice names.', $errors['needs']);
        $this->assertStringStartsWith('Must be a location', $errors['household_location']);
        $this->assertSame("'not-listed.jpg' is not listed in media[] for this field.", $errors['house_photo']);
        $this->assertSame('Age must be between 0 and 120.', $errors['members[0].member_age']);
    }

    public function test_fields_hidden_by_skip_logic_are_not_required(): void
    {
        $form = $this->deployedHouseholdForm();
        $enumerator = $this->makeUser();
        $this->assign($form, $enumerator);

        // No income → income_sources (required) is hidden and omitted.
        // A 45-year-old member → in_school (required) is hidden.
        $noIncome = $this->validItem($form, ['has_income' => 'no']);
        unset($noIncome['data']['income_sources'], $noIncome['data']['monthly_income']);

        // With income the same omission is an error; "other" need requires the detail.
        $withIncome = $this->validItem($form, ['needs' => ['other']]);
        unset($withIncome['data']['income_sources']);

        $results = $this->batch($enumerator, [$noIncome, $withIncome])->json('results');

        $this->assertSame('accepted', $results[0]['status']);
        $this->assertSame('rejected', $results[1]['status']);
        $this->assertEqualsCanonicalizing(['income_sources', 'needs_other'], array_column($results[1]['errors'], 'field'));
    }

    public function test_required_inside_a_repeat_uses_the_same_instance(): void
    {
        $form = $this->deployedHouseholdForm();
        $enumerator = $this->makeUser();
        $this->assign($form, $enumerator);

        $item = $this->validItem($form, ['members' => [
            ['member_name' => 'Old', 'member_age' => 60, 'member_gender' => 'male'],       // in_school hidden
            ['member_name' => 'Young', 'member_age' => 15, 'member_gender' => 'female'],   // in_school required
        ]]);

        $result = $this->batch($enumerator, [$item])->json('results.0');

        $this->assertSame([['field' => 'members[1].in_school', 'message' => 'This field is required.']], $result['errors']);
    }

    public function test_an_older_form_version_is_still_accepted(): void
    {
        $form = $this->deployedHouseholdForm();
        $enumerator = $this->makeUser();
        $this->assign($form, $enumerator);
        $v1 = $form->currentVersion;

        $this->newVersion($form);
        $this->assertSame(2, $form->fresh('currentVersion')->currentVersion->version_number);

        $item = $this->validItem($form, [], ['form_version' => '1']); // the app sends it as a string
        $result = $this->batch($enumerator, [$item])->json('results.0');

        $this->assertSame('accepted', $result['status']);
        $this->assertSame($v1->id, DataCollectionSubmission::find($result['server_id'])->form_version_id);

        $missing = $this->validItem($form, [], ['form_version' => 9]);
        $this->assertSame('Version 9 of this form does not exist on the server.', $this->batch($enumerator, [$missing])->json('results.0.reason'));
    }

    public function test_unassigned_users_and_unknown_forms_are_rejected(): void
    {
        $form = $this->deployedHouseholdForm();
        $viewer = $this->makeUser();
        $this->assign($form, $viewer, ['view']);

        $results = $this->batch($viewer, [
            $this->validItem($form),
            $this->validItem($form, [], ['form_id' => 424242]),
        ])->json('results');

        $this->assertSame('You are not assigned to collect data for this form.', $results[0]['reason']);
        $this->assertSame('This form does not exist on the server.', $results[1]['reason']);
        $this->assertSame(0, DataCollectionSubmission::count());
    }

    public function test_malformed_envelope_is_the_only_422(): void
    {
        $user = $this->makeUser();

        $this->actingAs($user, 'sanctum')->postJson('/api/v1/submissions/batch', ['submissions' => 'nope'])->assertStatus(422);
        $this->actingAs($user, 'sanctum')->postJson('/api/v1/submissions/batch', ['submissions' => ['a' => 1]])->assertStatus(422);
        $this->actingAs($user, 'sanctum')->postJson('/api/v1/submissions/batch', [])->assertStatus(422);
        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/submissions/batch', ['submissions' => array_fill(0, 51, ['client_uuid' => (string) Str::uuid()])])
            ->assertStatus(422);

        // 50 bad items is still a 200 with 50 results.
        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/submissions/batch', ['submissions' => array_fill(0, 50, ['client_uuid' => 'x'])])
            ->assertOk()->assertJsonCount(50, 'results');
    }

    public function test_media_upload_before_its_submission_is_404_then_stored_once(): void
    {
        Storage::fake('local');
        $form = $this->deployedHouseholdForm();
        $enumerator = $this->makeUser();
        $this->assign($form, $enumerator);
        $item = $this->validItem($form);
        $photo = UploadedFile::fake()->image('abc.jpg', 40, 30);

        $url = "/api/v1/submissions/{$item['client_uuid']}/media";

        $this->actingAs($enumerator, 'sanctum')->post($url, ['field_name' => 'house_photo', 'file' => $photo], ['Accept' => 'application/json'])
            ->assertNotFound()->assertJsonPath('code', 'submission_not_found');

        $this->batch($enumerator, [$item])->assertJsonPath('results.0.status', 'accepted');

        // The app sends the field as "field"; the contract says field_name. Both work.
        $stored = $this->actingAs($enumerator, 'sanctum')
            ->post($url, ['field' => 'house_photo', 'filename' => 'abc.jpg', 'file' => $photo], ['Accept' => 'application/json'])
            ->assertCreated()->assertJsonPath('status', 'stored')->json();

        $this->actingAs($enumerator, 'sanctum')
            ->post($url, ['field_name' => 'house_photo', 'filename' => 'abc.jpg', 'file' => $photo], ['Accept' => 'application/json'])
            ->assertOk()->assertJsonPath('status', 'duplicate')->assertJsonPath('id', $stored['id']);

        $media = DataCollectionSubmission::where('client_uuid', $item['client_uuid'])->first()->media()->sole();
        $this->assertSame('abc.jpg', $media->original_name);
        $this->assertStringNotContainsString('abc', $media->path, 'the stored name is generated server-side');
        $this->assertStringEndsWith('.jpg', $media->path);
        Storage::disk('local')->assertExists($media->path);

        // Another user can't attach to it; non-photo fields and non-images are refused.
        $this->actingAs($this->makeUser(), 'sanctum')
            ->post($url, ['field_name' => 'house_photo', 'file' => $photo], ['Accept' => 'application/json'])->assertNotFound();
        $this->actingAs($enumerator, 'sanctum')
            ->post($url, ['field_name' => 'respondent_name', 'file' => $photo], ['Accept' => 'application/json'])->assertStatus(422);
        // A script renamed .jpg and sent as image/jpeg: the type comes from
        // the bytes, so it's refused.
        $script = tempnam(sys_get_temp_dir(), 'evil');
        file_put_contents($script, '<?php echo "pwned"; ?>');
        $this->actingAs($enumerator, 'sanctum')
            ->post($url, ['field_name' => 'house_photo', 'filename' => 'evil.jpg', 'file' => new UploadedFile($script, 'evil.jpg', 'image/jpeg', null, true)], ['Accept' => 'application/json'])
            ->assertStatus(422);
    }

    public function test_oversized_media_is_refused(): void
    {
        Storage::fake('local');
        config(['data_collection.media_max_kb' => 10]);
        $form = $this->deployedHouseholdForm();
        $enumerator = $this->makeUser();
        $this->assign($form, $enumerator);
        $item = $this->validItem($form);
        $this->batch($enumerator, [$item]);

        $this->actingAs($enumerator, 'sanctum')
            ->post("/api/v1/submissions/{$item['client_uuid']}/media",
                ['field_name' => 'house_photo', 'file' => UploadedFile::fake()->image('big.jpg')->size(50)],
                ['Accept' => 'application/json'])
            ->assertStatus(422);
    }
}
