<?php

namespace Database\Factories\Leave;

use App\Models\Leave\LeaveRequest;
use App\Models\Leave\LeaveType;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<LeaveRequest> */
class LeaveRequestFactory extends Factory
{
    protected $model = LeaveRequest::class;

    // Monday to Friday of a fixed week with no Zimbabwe public holiday, so
    // tests don't depend on today's date.
    public function definition(): array
    {
        return [
            'user_id'       => fn () => User::create(['name' => fake()->name(), 'email' => fake()->unique()->safeEmail(), 'password' => bcrypt('password'), 'role' => 'staff'])->id,
            'leave_type_id' => LeaveType::factory(),
            'start_date'    => '2026-03-02',
            'end_date'      => '2026-03-06',
            'days'          => 5,
            'reason'        => fake()->sentence(),
            'status'        => LeaveRequest::PENDING,
        ];
    }

    public function approved(?User $approver = null): static
    {
        return $this->state(fn () => [
            'status'      => LeaveRequest::APPROVED,
            'approver_id' => $approver?->id,
            'decided_at'  => now(),
        ]);
    }

    public function rejected(?User $approver = null): static
    {
        return $this->state(fn () => [
            'status'           => LeaveRequest::REJECTED,
            'approver_id'      => $approver?->id,
            'approver_comment' => 'Not this week.',
            'decided_at'       => now(),
        ]);
    }
}
