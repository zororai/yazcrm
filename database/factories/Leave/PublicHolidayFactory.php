<?php

namespace Database\Factories\Leave;

use App\Models\Leave\PublicHoliday;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<PublicHoliday> */
class PublicHolidayFactory extends Factory
{
    protected $model = PublicHoliday::class;

    public function definition(): array
    {
        return [
            'name' => fake()->words(2, true).' Day',
            'date' => fake()->unique()->dateTimeBetween('2026-01-01', '2026-12-31')->format('Y-m-d'),
        ];
    }
}
