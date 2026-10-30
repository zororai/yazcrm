<?php

namespace Tests\Feature\DataCollection;

use App\Models\BeneficiaryProfile;
use App\Models\DataCollectionFormMapping;
use App\Models\DataCollectionMappingLog;
use App\Models\DataCollectionSubmission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CrmMappingTest extends TestCase
{
    use RefreshDatabase, BuildsDataCollectionForms;

    private function map($form, array $attributes): void
    {
        DataCollectionFormMapping::updateOrCreate(['form_id' => $form->id], $attributes + [
            'target'    => 'beneficiary',
            'field_map' => ['full_name' => 'respondent_name', 'age' => 'respondent_age', 'phone_number' => 'respondent_phone', 'gender' => 'respondent_gender'],
        ]);
    }

    public function test_create_mode_creates_a_beneficiary_and_links_it(): void
    {
        $form = $this->deployedHouseholdForm();
        $enumerator = $this->makeUser();
        $this->assign($form, $enumerator);
        $this->map($form, ['mode' => 'create', 'match_crm_field' => 'phone_number', 'match_form_field' => 'respondent_phone']);

        $item = $this->validItem($form);
        $id = $this->batch($enumerator, [$item])->json('results.0.server_id');

        $profile = BeneficiaryProfile::sole();
        $this->assertSame('Rudo Moyo', $profile->full_name);
        $this->assertSame(19, $profile->age);
        $this->assertSame($item['client_uuid'], $profile->client_uuid);
        $this->assertSame($enumerator->id, $profile->created_by_user_id);

        $submission = DataCollectionSubmission::find($id);
        $this->assertSame('beneficiary', $submission->crm_record_type);
        $this->assertSame($profile->id, $submission->crm_record_id);
        $this->assertSame('created', DataCollectionMappingLog::where('submission_id', $id)->value('result'));
    }

    public function test_duplicates_are_detected_on_the_key_field(): void
    {
        $form = $this->deployedHouseholdForm();
        $enumerator = $this->makeUser();
        $this->assign($form, $enumerator);
        $this->map($form, ['mode' => 'create', 'match_crm_field' => 'phone_number', 'match_form_field' => 'respondent_phone']);

        $this->batch($enumerator, [$this->validItem($form)]);
        $second = $this->batch($enumerator, [$this->validItem($form, ['respondent_name' => 'Same phone'])])->json('results.0.server_id');

        $this->assertSame(1, BeneficiaryProfile::count());
        $log = DataCollectionMappingLog::where('submission_id', $second)->sole();
        $this->assertSame('duplicate', $log->result);
        $this->assertSame(BeneficiaryProfile::value('id'), $log->crm_record_id);
    }

    public function test_update_mode_and_unmatched_rows_are_skipped(): void
    {
        $form = $this->deployedHouseholdForm();
        $enumerator = $this->makeUser();
        $this->assign($form, $enumerator);
        $existing = BeneficiaryProfile::create(['client_uuid' => (string) \Illuminate\Support\Str::uuid(), 'full_name' => 'Old name', 'phone_number' => '0771234567']);
        $this->map($form, ['mode' => 'update', 'match_crm_field' => 'phone_number', 'match_form_field' => 'respondent_phone']);

        $matched = $this->batch($enumerator, [$this->validItem($form)])->json('results.0.server_id');
        $unmatched = $this->batch($enumerator, [$this->validItem($form, ['respondent_phone' => '0719999999'])])->json('results.0.server_id');

        $this->assertSame('Rudo Moyo', $existing->fresh()->full_name);
        $this->assertSame('updated', DataCollectionMappingLog::where('submission_id', $matched)->value('result'));
        $this->assertSame('skipped', DataCollectionMappingLog::where('submission_id', $unmatched)->value('result'));
    }

    public function test_a_mapping_failure_never_loses_the_submission_and_can_be_retried(): void
    {
        $form = $this->deployedHouseholdForm();
        $enumerator = $this->makeUser();
        $this->assign($form, $enumerator);
        // full_name is required by the CRM but not mapped: every run fails.
        DataCollectionFormMapping::create(['form_id' => $form->id, 'target' => 'beneficiary', 'mode' => 'create', 'field_map' => ['age' => 'respondent_age']]);

        $result = $this->batch($enumerator, [$this->validItem($form)])->json('results.0');
        $this->assertSame('accepted', $result['status']);
        $this->assertNotNull(DataCollectionSubmission::find($result['server_id']));
        $log = DataCollectionMappingLog::where('submission_id', $result['server_id'])->sole();
        $this->assertSame('failed', $log->result);
        $this->assertStringContainsString('full_name', $log->error);

        // Fix the mapping in the screen, then retry the failed rows.
        $admin = User::where('role', 'admin')->first();
        $this->actingAs($admin)->put("/data-collection/forms/{$form->id}/mapping", [
            'target' => 'beneficiary', 'mode' => 'create',
            'field_map' => ['full_name' => 'respondent_name', 'age' => 'respondent_age', 'not_a_field' => 'respondent_name'],
        ])->assertSessionHas('success');
        $this->assertArrayNotHasKey('not_a_field', $form->fresh()->mapping->field_map);

        $this->actingAs($admin)->post("/data-collection/forms/{$form->id}/mapping/rerun", ['failed_only' => true])->assertSessionHas('success');

        $log->refresh();
        $this->assertSame('created', $log->result);
        $this->assertSame(2, $log->attempts);
        $this->assertSame(1, BeneficiaryProfile::count());

        // Re-running for all updates the linked record instead of creating another.
        $this->actingAs($admin)->post("/data-collection/forms/{$form->id}/mapping/rerun")->assertSessionHas('success');
        $this->assertSame(1, BeneficiaryProfile::count());
        $this->assertSame('updated', $log->fresh()->result);
    }
}
