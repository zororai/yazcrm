<?php

namespace Database\Factories\Leave;

use App\Models\Leave\LeaveType;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<LeaveType> */
class LeaveTypeFactory extends Factory
{
    protected $model = LeaveType::class;

    public function definition(): array
    {
        return [
            'name'                => fake()->unique()->words(2, true).' leave',
            'default_days'        => 22,
            'requires_attachment' => false,
            'is_paid'             => true,
            'is_active'           => true,
        ];
    }

    public function unpaid(): static
    {
        return $this->state(['is_paid' => false, 'default_days' => 0]);
    }

    public function needsAttachment(): static
    {
        return $this->state(['requires_attachment' => true]);
    }
}
