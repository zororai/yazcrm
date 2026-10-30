<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Web\Concerns\DataCollectionFormContext;
use App\Jobs\DataCollection\RemapFormSubmissions;
use App\Models\DataCollectionForm;
use App\Models\DataCollectionFormMapping;
use App\Models\DataCollectionMappingLog;
use App\Services\DataCollection\CrmMappingService;
use App\Services\DataCollection\FormFieldCatalog;
use App\Support\DataCollection\CrmTargets;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

// Field mapping onto CRM records, and the sync log of every mapping run.
class DataCollectionMappingController extends Controller
{
    use DataCollectionFormContext;

    public function show(Request $request, DataCollectionForm $form): Response
    {
        $this->authorize('map', $form);

        $catalog = FormFieldCatalog::for($form);
        $result = $request->query('result');

        return Inertia::render('DataCollection/Forms/Mapping', $this->formContext($request, $form, $catalog) + [
            'mapping' => $form->mapping,
            'targets' => collect(CrmTargets::all())->map(fn ($t) => [
                'label'    => $t['label'],
                'match'    => $t['match'],
                'required' => $t['required'],
                'fields'   => collect($t['fields'])->map(fn ($f, $key) => ['key' => $key, 'label' => $f[0], 'type' => $f[1]])->values(),
            ]),
            'logs'    => DataCollectionMappingLog::where('form_id', $form->id)
                ->when(in_array($result, ['created', 'updated', 'skipped', 'duplicate', 'failed'], true), fn ($q) => $q->where('result', $result))
                ->latest('updated_at')
                ->paginate(25)
                ->withQueryString(),
            'counts'  => DataCollectionMappingLog::where('form_id', $form->id)->selectRaw('result, count(*) as n')->groupBy('result')->pluck('n', 'result'),
            'result'  => $result,
        ]);
    }

    public function update(Request $request, DataCollectionForm $form): RedirectResponse
    {
        $this->authorize('map', $form);

        $targets = CrmTargets::all();
        $data = $request->validate([
            'target'           => ['required', Rule::in(array_keys($targets))],
            'mode'             => ['required', Rule::in(DataCollectionFormMapping::MODES)],
            'match_crm_field'  => 'nullable|string|max:100',
            'match_form_field' => 'nullable|string|max:191',
            'field_map'        => 'array',
            'field_map.*'      => 'nullable|string|max:191',
        ]);

        $data += ['match_crm_field' => null, 'match_form_field' => null];
        $target = $targets[$data['target']];
        $topLevel = array_keys(FormFieldCatalog::for($form)->topLevel());
        $fieldMap = array_filter(
            array_intersect_key($data['field_map'] ?? [], $target['fields']),
            fn ($formField) => $formField && in_array($formField, $topLevel, true),
        );

        if ($data['mode'] === 'update' && (! in_array($data['match_crm_field'], $target['match'], true) || ! in_array($data['match_form_field'], $topLevel, true))) {
            return back()->with('error', 'Update mode needs a key: choose the CRM field and the form question to match records on.');
        }
        if ($data['match_crm_field'] && ! in_array($data['match_crm_field'], $target['match'], true)) {
            $data['match_crm_field'] = null;
        }

        DataCollectionFormMapping::updateOrCreate(['form_id' => $form->id], [
            'target'           => $data['target'],
            'mode'             => $data['mode'],
            'match_crm_field'  => $data['match_crm_field'],
            'match_form_field' => in_array($data['match_form_field'], $topLevel, true) ? $data['match_form_field'] : null,
            'field_map'        => $fieldMap,
            'updated_by'       => $request->user()->id,
        ]);

        return back()->with('success', 'Mapping saved. New submissions use it; re-run it to apply it to existing ones.');
    }

    public function rerun(Request $request, DataCollectionForm $form): RedirectResponse
    {
        $this->authorize('map', $form);

        RemapFormSubmissions::dispatch($form->id, $request->boolean('failed_only'));

        return back()->with('success', $request->boolean('failed_only') ? 'Retrying failed submissions.' : 'Re-running the mapping for all submissions.');
    }

    public function retry(Request $request, DataCollectionForm $form, DataCollectionMappingLog $log, CrmMappingService $mapper): RedirectResponse
    {
        $this->authorize('map', $form);
        abort_unless($log->form_id === $form->id, 404);

        $result = $mapper->map($log->submission()->with('form.mapping')->firstOrFail());

        return back()->with($result?->result === 'failed' ? 'error' : 'success', 'Mapping result: '.($result?->result ?? 'not mapped').'.');
    }
}
