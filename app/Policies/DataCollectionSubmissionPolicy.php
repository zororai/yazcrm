<?php

namespace App\Policies;

use App\Models\DataCollectionSubmission;
use App\Models\User;
use App\Support\DataCollection\FormAccess;

class DataCollectionSubmissionPolicy
{
    public function view(User $user, DataCollectionSubmission $submission): bool
    {
        return FormAccess::can($user, $submission->form_id, 'view');
    }

    // Editing answers and setting the validation status.
    public function update(User $user, DataCollectionSubmission $submission): bool
    {
        return FormAccess::can($user, $submission->form_id, 'edit');
    }

    // Soft delete only; restricted to admins and form designers.
    public function delete(User $user, DataCollectionSubmission $submission): bool
    {
        return FormAccess::isDesigner($user);
    }

    public function restore(User $user, DataCollectionSubmission $submission): bool
    {
        return FormAccess::isDesigner($user);
    }
}
