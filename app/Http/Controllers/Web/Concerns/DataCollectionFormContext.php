<?php

namespace App\Http\Controllers\Web\Concerns;

use App\Models\DataCollectionForm;
use App\Models\DataCollectionSubmission;
use App\Models\User;
use App\Services\DataCollection\FormFieldCatalog;
use App\Services\DataCollection\SubmissionFilters;
use App\Support\DataCollection\FormAccess;
use Illuminate\Http\Request;

// Props every form tab (data, summary, map, dashboard, mapping) shares:
// the form, what the viewer may do, the field catalog for filters/columns,
// the enumerators who have sent data, and the active filters.
trait DataCollectionFormContext
{
    protected function formContext(Request $request, DataCollectionForm $form, FormFieldCatalog $catalog): array
    {
        $user = $request->user();
        $canSeePii = FormAccess::canSeePii($user);

        $enumeratorIds = DataCollectionSubmission::withTrashed()->where('form_id', $form->id)->distinct()->pluck('submitted_by');

        return [
            'form'        => $form->only(['id', 'name', 'code', 'status', 'project_id']) + ['project' => $form->project?->only(['id', 'name'])],
            'can'         => [
                'permissions' => FormAccess::permissions($user, $form),
                'design'      => FormAccess::isDesigner($user),
                'pii'         => $canSeePii,
            ],
            'fields'      => array_values(array_map(fn ($f) => [
                'path'    => $f['path'],
                'name'    => $f['name'],
                'label'   => $f['label'],
                'type'    => $f['type'],
                'repeat'  => $f['repeat'],
                'pii'     => $f['pii'],
                'hidden'  => $f['pii'] && ! $canSeePii,
                'choices' => $f['choices'],
            ], $catalog->fields)),
            'repeats'     => array_values($catalog->repeats),
            'enumerators' => User::whereIn('id', $enumeratorIds)->orderBy('name')->get(['id', 'name']),
            'filters'     => (object) SubmissionFilters::fromRequest($request),
            'statuses'    => DataCollectionSubmission::STATUSES,
        ];
    }

    protected function hiddenFields(Request $request, FormFieldCatalog $catalog): array
    {
        return $catalog->hiddenFor(FormAccess::canSeePii($request->user()));
    }
}
