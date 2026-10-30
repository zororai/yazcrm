<?php

namespace Tests\Feature\DataCollection;

use App\Models\DataCollectionExport;
use App\Models\DataCollectionFormVersion;
use App\Models\DataCollectionSubmission;
use App\Models\DataCollectionSubmissionAudit;
use App\Services\DataCollection\SubmissionAnalytics;
use App\Services\DataCollection\XlsForm\XlsFormSamples;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

// The CRM side: form setup, per-form access, PII masking, data table,
// summary/dashboard maths, edits and status changes, exports and the feed.
class CrmScreensTest extends TestCase
{
    use RefreshDatabase, BuildsDataCollectionForms;

    private function seedSubmissions($form, $enumerator): array
    {
        $items = [
            $this->validItem($form, ['respondent_age' => 18, 'ward' => 'ward_1', 'respondent_gender' => 'female', 'needs' => ['food']]),
            $this->validItem($form, ['respondent_age' => 20, 'ward' => 'ward_1', 'respondent_gender' => 'male', 'needs' => ['food', 'health'], 'respondent_name' => 'Tapiwa Ncube']),
            $this->validItem($form, ['respondent_age' => 40, 'ward' => 'ward_2', 'respondent_gender' => 'female', 'needs' => ['skills'],
                'members' => [['member_name' => 'A', 'member_age' => 8, 'member_gender' => 'male', 'in_school' => 'yes']]]),
        ];
        $results = $this->batch($enumerator, $items)->json('results');
        $this->assertSame(['accepted', 'accepted', 'accepted'], array_column($results, 'status'));

        return array_column($results, 'server_id');
    }

    public function test_designer_uploads_a_bad_xlsform_and_sees_errors_then_deploys_a_good_one(): void
    {
        $admin = $this->makeUser(['role' => 'admin']);
        $this->actingAs($admin)->post('/data-collection/projects', ['code' => 'P1', 'name' => 'Project'])->assertRedirect();
        $project = \App\Models\DataCollectionProject::firstOrFail();
        $this->actingAs($admin)->post("/data-collection/projects/{$project->id}/forms", ['code' => 'F1', 'name' => 'Survey']);
        $form = \App\Models\DataCollectionForm::firstOrFail();

        $bad = XlsFormSamples::build([['type' => 'select_one nope', 'name' => 'q', 'label' => 'Q'], ['type' => 'begin group', 'name' => 'g']], [], ['form_title' => 'Bad']);
        $this->actingAs($admin)->post("/data-collection/forms/{$form->id}/xlsform", ['xlsform' => $this->xlsUpload($bad)])
            ->assertSessionHas('error');
        $draft = DataCollectionFormVersion::where('form_id', $form->id)->sole();
        $this->assertNotEmpty($draft->errors);

        $this->actingAs($admin)->post("/data-collection/forms/{$form->id}/versions/{$draft->id}/deploy")->assertSessionHas('error');
        $this->assertSame('draft', $draft->fresh()->status);

        // A fixed upload replaces the draft (still v1) and deploys.
        $this->actingAs($admin)->post("/data-collection/forms/{$form->id}/xlsform", ['xlsform' => $this->xlsUpload()])->assertSessionHas('success');
        $draft = DataCollectionFormVersion::where('form_id', $form->id)->sole();
        $this->assertSame(1, $draft->version_number);
        $this->assertNull($draft->errors);
        $this->actingAs($admin)->post("/data-collection/forms/{$form->id}/versions/{$draft->id}/deploy")->assertSessionHas('success');
        $this->assertSame('deployed', $form->fresh()->status);

        $this->withoutVite()->actingAs($admin)->get("/data-collection/forms/{$form->id}")->assertOk()
            ->assertInertia(fn ($page) => $page->component('DataCollection/Forms/Show')->where('versions.0.status', 'deployed'));
    }

    public function test_template_and_sample_download(): void
    {
        $user = $this->makeUser();
        $this->actingAs($user)->get('/data-collection/xlsform-template.xlsx')->assertOk()->assertDownload('xlsform-template.xlsx');
        $this->actingAs($user)->get('/data-collection/xlsform-sample.xlsx')->assertOk()->assertDownload('household-survey-sample.xlsx');
    }

    public function test_only_designers_change_forms_and_access_is_per_form(): void
    {
        $form = $this->deployedHouseholdForm();
        $enumerator = $this->makeUser();
        $viewer = $this->makeUser();
        $stranger = $this->makeUser();
        $this->assign($form, $enumerator, ['collect']);
        $this->assign($form, $viewer, ['view']);

        $this->actingAs($viewer)->post("/data-collection/forms/{$form->id}/xlsform", ['xlsform' => $this->xlsUpload()])->assertForbidden();
        $this->actingAs($viewer)->post("/data-collection/forms/{$form->id}/assignments", ['user_ids' => [$stranger->id], 'permissions' => ['view']])->assertForbidden();

        $this->withoutVite()->actingAs($viewer)->get("/data-collection/forms/{$form->id}/data")->assertOk();
        $this->withoutVite()->actingAs($enumerator)->get("/data-collection/forms/{$form->id}/data")->assertForbidden();
        $this->withoutVite()->actingAs($stranger)->get("/data-collection/forms/{$form->id}")->assertForbidden();
        $this->withoutVite()->actingAs($viewer)->get("/data-collection/forms/{$form->id}/mapping")->assertForbidden();

        // A form designer (nav permission) can manage any form.
        $designer = $this->makeUser(['nav_permissions' => ['data_collection', 'data_collection_design']]);
        $this->actingAs($designer)->post("/data-collection/forms/{$form->id}/assignments", ['user_ids' => [$stranger->id], 'permissions' => ['view', 'export']])
            ->assertSessionHas('success');
        $this->withoutVite()->actingAs($stranger)->get("/data-collection/forms/{$form->id}/data")->assertOk();
    }

    public function test_pii_is_masked_in_the_table_search_submission_page_and_exports(): void
    {
        Storage::fake('local');
        $form = $this->deployedHouseholdForm();
        $enumerator = $this->makeUser();
        $analyst = $this->makeUser();
        $piiAnalyst = $this->makeUser(['nav_permissions' => ['data_collection', 'data_collection_pii']]);
        $this->assign($form, $enumerator);
        $this->assign($form, $analyst, ['view', 'export']);
        $this->assign($form, $piiAnalyst, ['view', 'export']);
        [$id] = $this->seedSubmissions($form, $enumerator);

        $this->withoutVite()->actingAs($analyst)->get("/data-collection/forms/{$form->id}/data")
            ->assertInertia(fn ($page) => $page->where('rows.data.0.data.respondent_name', '•••')
                ->where('rows.data.0.data.members.0.member_name', fn ($v) => $v === '•••' || $v === null)
                ->where('rows.data.0.data.ward', fn ($v) => str_starts_with($v, 'ward_')));

        // Searching a hidden field must not reveal who is in the data.
        $this->withoutVite()->actingAs($analyst)->get("/data-collection/forms/{$form->id}/data?q=Tapiwa")
            ->assertInertia(fn ($page) => $page->where('rows.total', 0));
        $this->withoutVite()->actingAs($piiAnalyst)->get("/data-collection/forms/{$form->id}/data?q=Tapiwa")
            ->assertInertia(fn ($page) => $page->where('rows.total', 1)->where('rows.data.0.data.respondent_name', 'Tapiwa Ncube'));

        $this->withoutVite()->actingAs($analyst)->get("/data-collection/submissions/{$id}")
            ->assertInertia(fn ($page) => $page->where('submission.data.respondent_name', '•••')->where('can.pii', false));

        $this->actingAs($analyst)->post("/data-collection/forms/{$form->id}/exports", ['format' => 'csv'])->assertSessionHas('success');
        $export = DataCollectionExport::latest('id')->firstOrFail();
        $this->assertSame('ready', $export->status);
        $csv = Storage::disk('local')->get($export->path);
        $this->assertStringNotContainsString('Rudo Moyo', $csv);
        $this->assertStringContainsString('•••', $csv);
        $this->assertSame(1, $analyst->notifications()->count(), 'the requester gets a "ready to download" notification');

        $this->actingAs($analyst)->get("/data-collection/exports/{$export->id}/download")->assertOk();
        $this->actingAs($piiAnalyst)->get("/data-collection/exports/{$export->id}/download")->assertForbidden();
    }

    public function test_filters_sort_and_bulk_status(): void
    {
        $form = $this->deployedHouseholdForm();
        $enumerator = $this->makeUser();
        $analyst = $this->makeUser();
        $this->assign($form, $enumerator);
        $this->assign($form, $analyst, ['view', 'edit']);
        $ids = $this->seedSubmissions($form, $enumerator);

        $this->withoutVite()->actingAs($analyst)->get("/data-collection/forms/{$form->id}/data?answers[ward]=ward_1")
            ->assertInertia(fn ($page) => $page->where('rows.total', 2));
        $this->withoutVite()->actingAs($analyst)->get("/data-collection/forms/{$form->id}/data?answers[needs]=food&sort=respondent_age&dir=asc")
            ->assertInertia(fn ($page) => $page->where('rows.total', 2)->where('rows.data.0.data.respondent_age', 18));

        $this->actingAs($analyst)->post("/data-collection/forms/{$form->id}/data/status", ['ids' => [$ids[0], $ids[1]], 'status' => 'on_hold', 'note' => 'Check ward'])
            ->assertSessionHas('success');
        $this->assertSame('on_hold', DataCollectionSubmission::find($ids[0])->status);
        $this->assertSame(2, DataCollectionSubmissionAudit::where('action', 'status_changed')->count());

        $this->withoutVite()->actingAs($analyst)->get("/data-collection/forms/{$form->id}/data?status=on_hold")
            ->assertInertia(fn ($page) => $page->where('rows.total', 2));
    }

    public function test_summary_statistics_group_by_and_crosstab(): void
    {
        $form = $this->deployedHouseholdForm();
        $enumerator = $this->makeUser();
        $this->assign($form, $enumerator);
        $this->seedSubmissions($form, $enumerator);

        $analytics = SubmissionAnalytics::make($form, [], true);
        $summary = $analytics->summary('respondent_gender');
        $fields = collect($summary['fields'])->keyBy('path');

        $this->assertSame(3, $summary['total']);
        $age = $fields['respondent_age'];
        $this->assertSame('numeric', $age['kind']);
        $this->assertEquals(26, $age['mean']);
        $this->assertEquals(20, $age['median']);
        $this->assertEquals(18, $age['min']);
        $this->assertEquals(40, $age['max']);
        $this->assertEquals(12.17, $age['stddev']);
        $this->assertEquals(['female' => ['count' => 2, 'mean' => 29.0, 'median' => 29.0, 'min' => 18.0, 'max' => 40.0], 'male' => ['count' => 1, 'mean' => 20.0, 'median' => 20.0, 'min' => 20.0, 'max' => 20.0]],
            $age['grouped']);

        $ward = collect($fields['ward']['rows'])->keyBy('value');
        $this->assertSame(2, $ward['ward_1']['count']);
        $this->assertEquals(66.7, $ward['ward_1']['pct']);
        $this->assertSame('Ward 1', $ward['ward_1']['label']);
        $this->assertSame(0, $ward['ward_5']['count'], 'every choice is listed, even with no answers');

        $needs = collect($fields['needs']['rows'])->keyBy('value');
        $this->assertSame(2, $needs['food']['count']);

        // Repeat child: counted per entry (2+2+1 members).
        $this->assertSame(5, $fields['members/member_gender']['answered']);
        $this->assertSame(['submissions' => 3, 'instances' => 5], array_intersect_key($summary['repeats'][0], array_flip(['submissions', 'instances'])));

        $cross = $analytics->crosstab('ward', 'respondent_gender');
        $this->assertSame(1, $cross['cells']['ward_1']['female']);
        $this->assertSame(1, $cross['cells']['ward_1']['male']);
        $this->assertSame(1, $cross['cells']['ward_2']['female']);

        // Filters apply to the summary too.
        $this->assertSame(1, SubmissionAnalytics::make($form, ['answers' => ['ward' => 'ward_2']], true)->summary()['total']);
    }

    public function test_dashboard_and_map(): void
    {
        $form = $this->deployedHouseholdForm();
        $enumerator = $this->makeUser(['name' => 'Enumerator One']);
        $this->assign($form, $enumerator);
        $this->seedSubmissions($form, $enumerator);
        $this->batch($enumerator, [$this->validItem($form, ['ward' => 'nowhere'])]);

        $dashboard = SubmissionAnalytics::make($form, [], true)->dashboard();
        $this->assertSame(3, $dashboard['total']);
        $this->assertSame(1, $dashboard['rejected']);
        $this->assertSame('Enumerator One', $dashboard['per_enumerator'][0]['name']);
        $this->assertSame(['day' => '2026-10-30', 'count' => 3], $dashboard['per_day'][0]);
        $this->assertSame(3, $dashboard['by_status']['pending']);

        $map = SubmissionAnalytics::make($form, [], true)->points('household_location', 'ward');
        $this->assertCount(3, $map['points']);
        $this->assertEquals(-17.82, $map['points'][0]['lat']);
        $this->assertArrayHasKey('ward_1', $map['legend']);
    }

    public function test_editing_a_submission_is_validated_and_audited_and_delete_is_soft(): void
    {
        $form = $this->deployedHouseholdForm();
        $enumerator = $this->makeUser();
        $analyst = $this->makeUser(['nav_permissions' => ['data_collection_pii']]);
        $this->assign($form, $enumerator);
        $this->assign($form, $analyst, ['view', 'edit']);
        [$id] = $this->seedSubmissions($form, $enumerator);

        $this->actingAs($analyst)->put("/data-collection/submissions/{$id}", ['changes' => ['respondent_age' => 5]])
            ->assertSessionHasErrors('changes.respondent_age');

        $this->actingAs($analyst)->put("/data-collection/submissions/{$id}", ['changes' => ['respondent_age' => 21, 'ward' => 'ward_3'], 'note' => 'Corrected on call-back'])
            ->assertSessionHas('success');
        $submission = DataCollectionSubmission::find($id);
        $this->assertSame(21, $submission->data['respondent_age']);
        $audit = DataCollectionSubmissionAudit::where('submission_id', $id)->where('action', 'edited')->sole();
        $this->assertSame(['respondent_age' => 18, 'ward' => 'ward_1'], $audit->old);
        $this->assertSame('Corrected on call-back', $audit->note);
        $this->assertSame('ward_3', \App\Models\DataCollectionSubmissionValue::where('submission_id', $id)->where('field', 'ward')->value('value'), 'analytics rows are rebuilt');

        $this->actingAs($analyst)->post("/data-collection/submissions/{$id}/status", ['status' => 'approved', 'note' => 'Verified'])->assertSessionHas('success');
        $this->assertSame('approved', $submission->fresh()->status);

        // Delete: designers only, soft, audited, restorable.
        $this->actingAs($analyst)->delete("/data-collection/submissions/{$id}", ['reason' => 'dup'])->assertForbidden();
        $admin = \App\Models\User::where('role', 'admin')->first();
        $this->actingAs($admin)->delete("/data-collection/submissions/{$id}", ['reason' => 'Duplicate household'])->assertSessionHas('success');
        $this->assertSoftDeleted('data_collection_submissions', ['id' => $id]);
        $this->assertSame('deleted', DataCollectionSubmissionAudit::where('submission_id', $id)->latest('id')->value('action'));
        $this->actingAs($admin)->post("/data-collection/submissions/{$id}/restore")->assertSessionHas('success');
        $this->assertNotSoftDeleted('data_collection_submissions', ['id' => $id]);
    }

    public function test_media_is_only_served_to_people_who_can_view_the_form(): void
    {
        Storage::fake('local');
        $form = $this->deployedHouseholdForm();
        $enumerator = $this->makeUser();
        $viewer = $this->makeUser();
        $this->assign($form, $enumerator);
        $this->assign($form, $viewer, ['view']);
        $item = $this->validItem($form);
        $this->batch($enumerator, [$item]);
        $mediaId = $this->actingAs($enumerator, 'sanctum')
            ->post("/api/v1/submissions/{$item['client_uuid']}/media", ['field_name' => 'house_photo', 'filename' => 'abc.jpg',
                'file' => \Illuminate\Http\UploadedFile::fake()->image('abc.jpg')], ['Accept' => 'application/json'])
            ->json('id');

        $this->actingAs($viewer)->get("/data-collection/media/{$mediaId}")->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->actingAs($this->makeUser())->get("/data-collection/media/{$mediaId}")->assertForbidden();
    }

    public function test_xlsx_geojson_and_pdf_exports(): void
    {
        Storage::fake('local');
        $form = $this->deployedHouseholdForm();
        $enumerator = $this->makeUser();
        $admin = \App\Models\User::where('role', 'admin')->first();
        $this->assign($form, $enumerator);
        $this->seedSubmissions($form, $enumerator);

        foreach (['xlsx', 'geojson', 'pdf'] as $format) {
            $this->actingAs($admin)->post("/data-collection/forms/{$form->id}/exports", ['format' => $format, 'labels' => true]);
        }
        $exports = DataCollectionExport::orderBy('id')->get()->keyBy('format');
        $this->assertSame(['ready', 'ready', 'ready'], $exports->pluck('status')->values()->all(), (string) $exports->pluck('error')->filter()->implode('; '));

        // XLSX: main sheet with split select_multiple columns, repeat sheet.
        $path = tempnam(sys_get_temp_dir(), 'x').'.xlsx';
        file_put_contents($path, Storage::disk('local')->get($exports['xlsx']->path));
        $book = IOFactory::load($path);
        $this->assertSame(2, $book->getSheetCount());
        $header = $book->getSheet(0)->rangeToArray('A1:'.$book->getSheet(0)->getHighestColumn().'1')[0];
        $this->assertContains('Most urgent needs/Food', $header);
        $this->assertSame(5, $book->getSheet(1)->getHighestRow() - 1, 'one row per household member');
        @unlink($path);

        $geo = json_decode(Storage::disk('local')->get($exports['geojson']->path), true);
        $this->assertSame('FeatureCollection', $geo['type']);
        $this->assertCount(3, $geo['features']);
        $this->assertSame([31.05, -17.82], $geo['features'][0]['geometry']['coordinates']);

        $this->assertStringStartsWith('%PDF', Storage::disk('local')->get($exports['pdf']->path));
    }

    public function test_data_feed_is_odata_style_and_masks_pii(): void
    {
        $form = $this->deployedHouseholdForm();
        $enumerator = $this->makeUser();
        $this->assign($form, $enumerator);
        $this->seedSubmissions($form, $enumerator);
        $analyst = $this->makeUser(['nav_permissions' => ['data_api']]);
        $token = $analyst->createToken('power-bi')->plainTextToken;
        $this->app['auth']->forgetGuards(); // drop the enumerator from the uploads above

        $page = $this->withToken($token)->getJson("/api/data/forms/{$form->id}/submissions?\$top=2")->assertOk();
        $page->assertJsonPath('@odata.count', 3)
            ->assertJsonCount(2, 'value')
            ->assertJsonPath('value.0.respondent_name', '•••')
            ->assertJsonPath('value.0.ward', 'Ward 1');
        $this->assertStringContainsString('%24skip=2', $page->json('@odata.nextLink'));

        $this->withToken($token)->getJson("/api/data/forms/{$form->id}/submissions?values=value&\$skip=2")
            ->assertJsonPath('value.0.ward', 'ward_2')
            ->assertJsonPath('@odata.nextLink', null);

        $csv = $this->withToken($token)->get("/api/data/forms/{$form->id}/submissions.csv")->streamedContent();
        $this->assertStringNotContainsString('Rudo Moyo', $csv);
    }
}
