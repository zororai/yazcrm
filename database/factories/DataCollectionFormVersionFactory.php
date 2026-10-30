<?php

namespace Database\Factories;

use App\Models\DataCollectionForm;
use App\Models\DataCollectionFormVersion;
use App\Services\DataCollection\DataCollectionFormService;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<DataCollectionFormVersion> */
class DataCollectionFormVersionFactory extends Factory
{
    protected $model = DataCollectionFormVersion::class;

    public function definition(): array
    {
        return [
            'form_id'        => DataCollectionForm::factory(),
            'version_number' => 1,
            'schema'         => [
                'title'    => 'Simple form',
                'children' => [
                    ['type' => 'text', 'name' => 'name', 'label' => 'Name', 'required' => 'yes'],
                    ['type' => 'integer', 'name' => 'age', 'label' => 'Age'],
                ],
                'choices'  => [],
            ],
            'status'     => 'draft',
            'created_by' => fn (array $attributes) => DataCollectionForm::find($attributes['form_id'])->created_by,
        ];
    }

    // Deployed, with the checksum and version stamped as deployVersion() does.
    public function deployed(): static
    {
        return $this->state(fn () => ['status' => 'deployed', 'deployed_at' => now()])
            ->afterCreating(function (DataCollectionFormVersion $version) {
                $schema = $version->schema + ['version' => $version->version_number];
                $version->update(['schema' => $schema, 'checksum' => DataCollectionFormService::checksum($schema)]);
                $version->form->update(['current_version_id' => $version->id, 'status' => 'deployed']);
            });
    }
}
