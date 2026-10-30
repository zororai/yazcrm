<?php

namespace Tests\Feature\DataCollection;

use App\Models\DataCollectionForm;
use App\Models\DataCollectionFormVersion;
use App\Models\DataCollectionProject;
use App\Models\User;
use App\Services\DataCollection\DataCollectionFormService;
use App\Services\DataCollection\XlsForm\XlsFormSamples;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

trait BuildsDataCollectionForms
{
    private function makeUser(array $attributes = []): User
    {
        static $n = 0;
        $n++;

        return User::create(array_merge([
            'name'     => "DC User {$n}",
            'email'    => "dc{$n}-".Str::random(6).'@example.test',
            'password' => bcrypt('password'),
            'role'     => 'agent',
        ], $attributes));
    }

    private function xlsUpload(?Spreadsheet $book = null, string $name = 'household.xlsx'): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'xlsform').'.xlsx';
        XlsFormSamples::save($book ?? XlsFormSamples::householdSurvey(), $path);

        return new UploadedFile($path, $name, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);
    }

    // The sample household survey, uploaded and deployed by an admin.
    private function deployedHouseholdForm(?User $admin = null): DataCollectionForm
    {
        $admin ??= $this->makeUser(['role' => 'admin']);
        $service = app(DataCollectionFormService::class);

        $project = $service->createProject($admin, ['code' => 'HH-'.Str::random(4), 'name' => 'Household project']);
        $form = $service->createForm($project, $admin, ['code' => 'HH-'.Str::random(5), 'name' => 'Household Survey']);
        $version = $service->uploadXlsForm($form, $admin, $this->xlsUpload());
        $service->deployVersion($version, $admin);

        return $form->fresh('currentVersion');
    }

    private function assign(DataCollectionForm $form, User $user, array $permissions = ['collect']): void
    {
        $admin = User::where('role', 'admin')->first() ?? $this->makeUser(['role' => 'admin']);
        app(DataCollectionFormService::class)->assign($form, $admin, $user->id, null, $permissions);
    }

    // A submission that passes every rule of the household survey.
    private function validItem(DataCollectionForm $form, array $dataOverrides = [], array $itemOverrides = []): array
    {
        $data = array_merge([
            'respondent_name'    => 'Rudo Moyo',
            'respondent_phone'   => '0771234567',
            'respondent_gender'  => 'female',
            'respondent_age'     => 19,
            'ward'               => 'ward_2',
            'interview_date'     => '2026-10-29',
            'household_location' => ['lat' => -17.82, 'lng' => 31.05, 'accuracy' => 6.5],
            'house_photo'        => 'abc.jpg',
            'household_size'     => 4,
            'has_income'         => 'yes',
            'income_sources'     => ['farming', 'business'],
            'monthly_income'     => 120.5,
            'members'            => [
                ['member_name' => 'Tariro', 'member_age' => 12, 'member_gender' => 'female', 'in_school' => 'yes'],
                ['member_name' => 'Baba', 'member_age' => 45, 'member_gender' => 'male'],
            ],
            'member_count'       => 2,
            'needs'              => ['food', 'skills'],
        ], $dataOverrides);

        return array_merge([
            'client_uuid'  => (string) Str::uuid(),
            'form_id'      => $form->id,
            'form_version' => $form->currentVersion->version_number,
            'collected_at' => '2026-10-30T09:15:00+02:00',
            'device_id'    => 'device-abc',
            'app_version'  => '1.4.0',
            'lat'          => -17.82,
            'lng'          => 31.05,
            'data'         => $data,
            'media'        => [['field_name' => 'house_photo', 'filename' => 'abc.jpg']],
        ], $itemOverrides);
    }

    private function batch(User $user, array $items)
    {
        return $this->actingAs($user, 'sanctum')->postJson('/api/v1/submissions/batch', ['submissions' => $items]);
    }

    private function newVersion(DataCollectionForm $form, ?Spreadsheet $book = null): DataCollectionFormVersion
    {
        $admin = User::where('role', 'admin')->first();
        $service = app(DataCollectionFormService::class);
        $version = $service->uploadXlsForm($form, $admin, $this->xlsUpload($book));

        return $service->deployVersion($version, $admin);
    }
}
