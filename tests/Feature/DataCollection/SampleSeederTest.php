<?php

namespace Tests\Feature\DataCollection;

use App\Models\DataCollectionForm;
use App\Models\User;
use Database\Seeders\DataCollectionSampleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SampleSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeder_deploys_the_sample_and_the_enumerator_can_download_it(): void
    {
        $this->seed(DataCollectionSampleSeeder::class);
        $this->seed(DataCollectionSampleSeeder::class); // re-running is harmless

        $this->assertSame(1, DataCollectionForm::where('code', 'HOUSEHOLD-SAMPLE')->count());
        $form = DataCollectionForm::where('code', 'HOUSEHOLD-SAMPLE')->sole();
        $this->assertSame('deployed', $form->status);

        $token = $this->postJson('/api/auth/login', [
            'email'    => DataCollectionSampleSeeder::ENUMERATOR_EMAIL,
            'password' => DataCollectionSampleSeeder::ENUMERATOR_PASSWORD,
        ])->assertOk()->json('token');

        $this->withToken($token)->getJson('/api/v1/forms')->assertJsonPath('forms.0.id', $form->id);
        $this->withToken($token)->getJson("/api/v1/forms/{$form->id}/schema")->assertOk()->assertJsonPath('title', 'Household Survey');
        $this->assertSame(1, User::where('email', DataCollectionSampleSeeder::ENUMERATOR_EMAIL)->count());
    }
}
