<?php

namespace Database\Factories;

use App\Models\DataCollectionProject;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<DataCollectionProject> */
class DataCollectionProjectFactory extends Factory
{
    protected $model = DataCollectionProject::class;

    public function definition(): array
    {
        return [
            'code'       => strtoupper($this->faker->unique()->bothify('PRJ-####')),
            'name'       => $this->faker->words(3, true),
            'status'     => 'active',
            'created_by' => User::query()->value('id') ?? User::create([
                'name' => 'Factory Owner', 'email' => $this->faker->unique()->safeEmail(), 'password' => bcrypt('password'), 'role' => 'admin',
            ])->id,
        ];
    }
}
