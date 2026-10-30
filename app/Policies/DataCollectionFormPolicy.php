<?php

namespace App\Policies;

use App\Models\DataCollectionForm;
use App\Models\User;
use App\Support\DataCollection\FormAccess;

// Per-form permissions; see FormAccess for how roles map onto them.
class DataCollectionFormPolicy
{
    // Upload XLSForms, deploy, assign, archive.
    public function design(User $user, ?DataCollectionForm $form = null): bool
    {
        return FormAccess::isDesigner($user);
    }

    public function collect(User $user, DataCollectionForm $form): bool
    {
        return FormAccess::can($user, $form, 'collect');
    }

    public function view(User $user, DataCollectionForm $form): bool
    {
        return FormAccess::can($user, $form, 'view');
    }

    public function edit(User $user, DataCollectionForm $form): bool
    {
        return FormAccess::can($user, $form, 'edit');
    }

    public function export(User $user, DataCollectionForm $form): bool
    {
        return FormAccess::can($user, $form, 'export');
    }

    public function map(User $user, DataCollectionForm $form): bool
    {
        return FormAccess::can($user, $form, 'map');
    }
}
