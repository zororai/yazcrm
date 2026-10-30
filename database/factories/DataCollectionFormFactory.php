<?php

namespace Database\Factories;

use App\Models\DataCollectionForm;
use App\Models\DataCollectionProject;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<DataCollectionForm> */
class DataCollectionFormFactory extends Factory
{
    protected $model = DataCollectionForm::class;

    public function definition(): array
    {
        return [
            'project_id' => DataCollectionProject::factory(),
            'code'       => strtoupper($this->faker->unique()->bothify('FRM-####')),
            'name'       => $this->faker->words(2, true),
            'status'     => 'draft',
            'created_by' => fn (array $attributes) => DataCollectionProject::find($attributes['project_id'])->created_by,
        ];
    }
}
