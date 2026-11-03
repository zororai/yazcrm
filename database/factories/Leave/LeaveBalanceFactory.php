<?php

namespace Database\Factories\Leave;

use App\Models\Leave\LeaveBalance;
use App\Models\Leave\LeaveType;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<LeaveBalance> */
class LeaveBalanceFactory extends Factory
{
    protected $model = LeaveBalance::class;

    public function definition(): array
    {
        return [
            'user_id'       => fn () => User::create(['name' => fake()->name(), 'email' => fake()->unique()->safeEmail(), 'password' => bcrypt('password'), 'role' => 'staff'])->id,
            'leave_type_id' => LeaveType::factory(),
            'year'          => now()->year,
            'entitled_days' => 22,
            'used_days'     => 0,
        ];
    }
}
