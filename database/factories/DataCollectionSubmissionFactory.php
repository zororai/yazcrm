<?php

namespace Database\Factories;

use App\Models\DataCollectionFormVersion;
use App\Models\DataCollectionSubmission;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<DataCollectionSubmission> */
class DataCollectionSubmissionFactory extends Factory
{
    protected $model = DataCollectionSubmission::class;

    public function definition(): array
    {
        return [
            'client_uuid'     => (string) Str::uuid(),
            'form_version_id' => DataCollectionFormVersion::factory()->deployed(),
            'form_id'         => fn (array $a) => DataCollectionFormVersion::find($a['form_version_id'])->form_id,
            'submitted_by'    => fn (array $a) => DataCollectionFormVersion::find($a['form_version_id'])->created_by,
            'data'            => ['name' => $this->faker->name(), 'age' => $this->faker->numberBetween(10, 35)],
            'collected_at'    => now()->subHours($this->faker->numberBetween(1, 200)),
            'received_at'     => now(),
            'device_id'       => 'device-'.$this->faker->numberBetween(1, 9),
            'app_version'     => '1.4.0',
            'completion'      => 100,
            'status'          => 'pending',
        ];
    }
}
