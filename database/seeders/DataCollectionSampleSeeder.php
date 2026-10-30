<?php

namespace Database\Seeders;

use App\Models\DataCollectionForm;
use App\Models\DataCollectionProject;
use App\Models\User;
use App\Services\DataCollection\DataCollectionFormService;
use App\Services\DataCollection\XlsForm\XlsFormSamples;
use Illuminate\Database\Seeder;
use Illuminate\Http\UploadedFile;

// A sample household survey (group, repeat, skip logic, PII fields),
// deployed, plus a test enumerator assigned to collect it in the app.
// Safe to run again: it reuses what's already there.
//   php artisan db:seed --class=DataCollectionSampleSeeder
class DataCollectionSampleSeeder extends Seeder
{
    public const ENUMERATOR_EMAIL = 'enumerator@example.test';
    public const ENUMERATOR_PASSWORD = 'enumerator123';

    public function run(DataCollectionFormService $service): void
    {
        $designer = User::where('role', 'admin')->orderBy('id')->first()
            ?? User::create([
                'name' => 'Data Collection Admin', 'email' => 'dc-admin@example.test',
                'password' => bcrypt(self::ENUMERATOR_PASSWORD), 'role' => 'admin', 'is_active' => true,
            ]);

        $enumerator = User::firstOrCreate(['email' => self::ENUMERATOR_EMAIL], [
            'name'            => 'Test Enumerator',
            'password'        => bcrypt(self::ENUMERATOR_PASSWORD),
            'role'            => 'agent',
            'is_active'       => true,
            'nav_permissions' => ['data_collection'],
        ]);

        $project = DataCollectionProject::where('code', 'SAMPLE')->first()
            ?? $service->createProject($designer, ['code' => 'SAMPLE', 'name' => 'Sample data collection', 'description' => 'Seeded example project.']);

        $form = DataCollectionForm::where('code', 'HOUSEHOLD-SAMPLE')->first()
            ?? $service->createForm($project, $designer, ['code' => 'HOUSEHOLD-SAMPLE', 'name' => 'Household Survey (sample)']);

        if (! $form->current_version_id) {
            $path = tempnam(sys_get_temp_dir(), 'xlsform').'.xlsx';
            XlsFormSamples::save(XlsFormSamples::householdSurvey(), $path);
            $version = $service->uploadXlsForm($form, $designer, new UploadedFile($path, 'household-survey-sample.xlsx', null, null, true));
            $service->deployVersion($version, $designer);
            @unlink($path);
        }

        $service->assign($form, $designer, $enumerator->id, null, ['collect']);

        $this->command?->info("Sample form #{$form->id} deployed. Test enumerator: ".self::ENUMERATOR_EMAIL.' / '.self::ENUMERATOR_PASSWORD);
    }
}
