<?php

namespace Tests\Feature\DataCollection;

use App\Models\DataCollectionFormVersion;
use App\Models\DataCollectionRejection;
use App\Models\DataCollectionSubmission;
use App\Models\DataCollectionSubmissionValue;
use App\Services\DataCollection\XlsForm\XlsFormSamples;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

// The /api/v1 contract the Flutter app is built against: forms, schema,
// sync status and auth errors. Ingestion and media are in their own tests.
class MobileApiContractTest extends TestCase
{
    use RefreshDatabase, BuildsDataCollectionForms;

    public function test_login_response_includes_user_id_and_name(): void
    {
        $user = $this->makeUser(['name' => 'Field Officer', 'is_active' => true]);

        $this->postJson('/api/auth/login', ['email' => $user->email, 'password' => 'password'])
            ->assertOk()
            ->assertJsonPath('user.id', $user->id)
            ->assertJsonPath('user.name', 'Field Officer')
            ->assertJsonStructure(['token']);
    }

    public function test_forms_lists_only_deployed_forms_the_user_collects_for(): void
    {
        $form = $this->deployedHouseholdForm();
        $enumerator = $this->makeUser();
        $stranger = $this->makeUser();
        $this->assign($form, $enumerator);

        $this->actingAs($enumerator, 'sanctum')->getJson('/api/v1/forms')
            ->assertOk()
            ->assertJsonCount(1, 'forms')
            ->assertJsonPath('forms.0.id', $form->id)
            ->assertJsonPath('forms.0.title', 'Household Survey')
            ->assertJsonPath('forms.0.version', 1)
            ->assertJsonPath('forms.0.checksum', $form->currentVersion->checksum)
            ->assertJsonStructure(['forms' => [['id', 'title', 'version', 'checksum', 'updated_at']]]);

        $this->actingAs($stranger, 'sanctum')->getJson('/api/v1/forms')->assertOk()->assertJsonCount(0, 'forms');

        // A view-only assignment doesn't put the form on the device.
        $viewer = $this->makeUser();
        $this->assign($form, $viewer, ['view']);
        $this->actingAs($viewer, 'sanctum')->getJson('/api/v1/forms')->assertJsonCount(0, 'forms');
    }

    public function test_forms_can_be_assigned_to_a_crm_role(): void
    {
        $form = $this->deployedHouseholdForm();
        $admin = \App\Models\User::where('role', 'admin')->first();
        app(\App\Services\DataCollection\DataCollectionFormService::class)->assign($form, $admin, null, 'field_officer', ['collect']);

        $officer = $this->makeUser(['role' => 'field_officer']);
        $this->actingAs($officer, 'sanctum')->getJson('/api/v1/forms')->assertJsonPath('forms.0.id', $form->id);
    }

    public function test_schema_endpoint(): void
    {
        $form = $this->deployedHouseholdForm();
        $enumerator = $this->makeUser();
        $this->assign($form, $enumerator);

        $response = $this->actingAs($enumerator, 'sanctum')->getJson("/api/v1/forms/{$form->id}/schema")->assertOk();
        $response->assertJsonPath('form_id', $form->id)
            ->assertJsonPath('version', 1)
            ->assertJsonPath('checksum', $form->currentVersion->checksum)
            ->assertJsonPath('title', 'Household Survey');

        $children = collect($response->json('children'))->keyBy('name');
        $this->assertSame('select_one', $children['ward']['type']);
        $this->assertCount(5, $children['ward']['choices'], 'choices are inline on the question');
        $this->assertSame('repeat', $children['members']['type']);
        $this->assertNotEmpty($response->json('choices.ward'));

        // 403 when not assigned, 404 when the form or version doesn't exist.
        $this->actingAs($this->makeUser(), 'sanctum')->getJson("/api/v1/forms/{$form->id}/schema")->assertForbidden();
        $this->actingAs($enumerator, 'sanctum')->getJson('/api/v1/forms/999999/schema')->assertNotFound();
        $this->actingAs($enumerator, 'sanctum')->getJson("/api/v1/forms/{$form->id}/schema?version=7")->assertNotFound();
    }

    public function test_schema_serves_an_older_version_on_request_and_the_checksum_changes(): void
    {
        $form = $this->deployedHouseholdForm();
        $enumerator = $this->makeUser();
        $this->assign($form, $enumerator);
        $v1Checksum = $form->currentVersion->checksum;

        $this->newVersion($form);

        $this->actingAs($enumerator, 'sanctum')->getJson('/api/v1/forms')
            ->assertJsonPath('forms.0.version', 2)
            ->assertJsonPath('forms.0.checksum', fn ($c) => $c !== $v1Checksum);
        $this->actingAs($enumerator, 'sanctum')->getJson("/api/v1/forms/{$form->id}/schema?version=1")
            ->assertOk()->assertJsonPath('version', 1)->assertJsonPath('checksum', $v1Checksum);
        $this->assertSame('archived', DataCollectionFormVersion::where('form_id', $form->id)->where('version_number', 1)->value('status'));
    }

    public function test_unauthenticated_and_expired_tokens_get_a_code(): void
    {
        $this->getJson('/api/v1/forms')->assertUnauthorized()->assertJsonPath('code', 'unauthenticated');
        $this->withToken('nonsense')->getJson('/api/v1/forms')->assertJsonPath('code', 'unauthenticated');

        $user = $this->makeUser();
        $token = $user->createToken('api-token')->plainTextToken;
        $this->withToken($token)->getJson('/api/v1/forms')->assertOk();

        $this->travel(31)->days();
        $this->app['auth']->forgetGuards();
        $this->withToken($token)->getJson('/api/v1/forms')->assertUnauthorized()->assertJsonPath('code', 'token_expired');
    }

    public function test_sync_status(): void
    {
        $form = $this->deployedHouseholdForm();
        $enumerator = $this->makeUser();
        $this->assign($form, $enumerator);

        $item = $this->validItem($form);
        $this->batch($enumerator, [$item])->assertJsonPath('results.0.status', 'accepted');

        $this->actingAs($enumerator, 'sanctum')->getJson('/api/v1/sync/status')
            ->assertOk()
            ->assertJsonStructure(['server_time', 'forms' => [['id', 'version', 'checksum']]])
            ->assertJsonMissingPath('accepted_client_uuids');

        $other = '00000000-0000-4000-8000-000000000000';
        $this->actingAs($enumerator, 'sanctum')->getJson("/api/v1/sync/status?client_uuids={$item['client_uuid']},{$other}")
            ->assertJsonPath('accepted_client_uuids', [$item['client_uuid']]);
        $this->actingAs($enumerator, 'sanctum')->getJson('/api/v1/sync/status?since='.urlencode(now()->subHour()->toIso8601String()))
            ->assertJsonPath('accepted_client_uuids', [$item['client_uuid']]);

        // Never another user's submissions.
        $this->actingAs($this->makeUser(), 'sanctum')->getJson("/api/v1/sync/status?client_uuids={$item['client_uuid']}")
            ->assertJsonPath('accepted_client_uuids', []);
    }

    public function test_rate_limit_returns_429_with_retry_after(): void
    {
        config(['data_collection.rate_limit_per_minute' => 2]);
        $user = $this->makeUser();

        $this->actingAs($user, 'sanctum')->getJson('/api/v1/forms')->assertOk();
        $this->actingAs($user, 'sanctum')->getJson('/api/v1/forms')->assertOk();
        $this->actingAs($user, 'sanctum')->getJson('/api/v1/forms')
            ->assertStatus(429)
            ->assertHeader('Retry-After');
    }
}
