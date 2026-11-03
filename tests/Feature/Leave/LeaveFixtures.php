<?php

namespace Tests\Feature\Leave;

use App\Models\Leave\LeaveRequest;
use App\Models\Leave\LeaveType;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

trait LeaveFixtures
{
    protected const API = '/api/v1/leave';

    protected LeaveType $annual;

    protected function setUpLeave(): void
    {
        $this->annual = LeaveType::factory()->create(['name' => 'Annual', 'default_days' => 22]);
    }

    protected function makeUser(string $role = 'staff', array $perms = [], array $attributes = []): User
    {
        static $n = 0;
        $n++;

        return User::create(array_merge([
            'name'            => "Leave User {$n}",
            'email'           => "leave{$n}@example.test",
            'password'        => bcrypt('password'),
            'role'            => $role,
            'is_active'       => true,
            'nav_permissions' => $perms,
        ], $attributes));
    }

    protected function approver(): User
    {
        return $this->makeUser('staff', ['leave_approve']);
    }

    protected function reportOf(User $manager): User
    {
        return $this->makeUser('staff', [], ['supervisor_id' => $manager->id]);
    }

    protected function pendingFor(User $user, array $attributes = []): LeaveRequest
    {
        return LeaveRequest::factory()->create(array_merge([
            'user_id'       => $user->id,
            'leave_type_id' => $this->annual->id,
        ], $attributes));
    }

    protected function as(User $user): static
    {
        Sanctum::actingAs($user);

        return $this;
    }

    // Mon 2 – Fri 6 March 2026: five working days, no public holidays.
    protected function application(array $overrides = []): array
    {
        return array_merge([
            'leave_type_id' => $this->annual->id,
            'start_date'    => '2026-03-02',
            'end_date'      => '2026-03-06',
            'reason'        => 'Family visit',
        ], $overrides);
    }
}
