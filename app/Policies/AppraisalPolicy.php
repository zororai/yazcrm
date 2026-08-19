<?php

namespace App\Policies;

use App\Models\Appraisal;
use App\Models\User;

class AppraisalPolicy
{
    private function isManager(User $user): bool
    {
        return in_array($user->role, ['admin', 'director'], true);
    }

    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Appraisal $appraisal): bool
    {
        return $this->isManager($user)
            || $appraisal->user_id === $user->id
            || $appraisal->supervisor_id === $user->id;
    }

    public function create(User $user): bool
    {
        return true;
    }

    // Employee self-assessment edits, own draft only (or a manager, any time).
    public function update(User $user, Appraisal $appraisal): bool
    {
        return $this->isManager($user)
            || ($appraisal->user_id === $user->id && $appraisal->status === 'draft');
    }

    public function submit(User $user, Appraisal $appraisal): bool
    {
        return $this->update($user, $appraisal) && $appraisal->status === 'draft';
    }

    // Supervisor review edits, assigned + submitted only (or a manager, any time).
    public function review(User $user, Appraisal $appraisal): bool
    {
        return $this->isManager($user)
            || ($appraisal->supervisor_id === $user->id && $appraisal->status === 'submitted');
    }

    public function complete(User $user, Appraisal $appraisal): bool
    {
        return $this->review($user, $appraisal) && $appraisal->status === 'submitted';
    }

    public function reopen(User $user, Appraisal $appraisal): bool
    {
        return $this->isManager($user);
    }

    public function delete(User $user, Appraisal $appraisal): bool
    {
        return $user->role === 'admin';
    }

    public function manage(User $user, Appraisal $appraisal): bool
    {
        return $this->isManager($user);
    }
}
