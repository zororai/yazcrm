<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Web\Concerns\DataCollectionFormContext;
use App\Models\DataCollectionForm;
use App\Services\DataCollection\SubmissionAnalytics;
use App\Services\DataCollection\SubmissionFilters;
use App\Support\DataCollection\FormAccess;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

// Summary report (with group-by and cross-tab), map and dashboard. All of
// them read the same filters as the data table and exports.
class DataCollectionAnalyticsController extends Controller
{
    use DataCollectionFormContext;

    private function analytics(Request $request, DataCollectionForm $form): SubmissionAnalytics
    {
        $this->authorize('view', $form);

        return SubmissionAnalytics::make($form, SubmissionFilters::fromRequest($request), FormAccess::canSeePii($request->user()));
    }

    public function summary(Request $request, DataCollectionForm $form): Response
    {
        $analytics = $this->analytics($request, $form);
        $rowField = $request->query('row');
        $colField = $request->query('col');

        return Inertia::render('DataCollection/Forms/Summary', $this->formContext($request, $form, $analytics->catalog()) + [
            'summary'  => $analytics->summary($request->query('group_by')),
            'crosstab' => $rowField && $colField ? $analytics->crosstab((string) $rowField, (string) $colField) : null,
            'groupBy'  => $request->query('group_by'),
            'row'      => $rowField,
            'col'      => $colField,
        ]);
    }

    public function map(Request $request, DataCollectionForm $form): Response
    {
        $analytics = $this->analytics($request, $form);
        $geo = $request->query('geo');
        if ($geo === null) {
            $geo = collect($analytics->catalog()->fields)->first(fn ($f) => $f['type'] === 'geopoint')['path'] ?? '';
        }

        return Inertia::render('DataCollection/Forms/Map', $this->formContext($request, $form, $analytics->catalog()) + [
            'map'     => $analytics->points($geo ?: null, $request->query('color')),
            'geo'     => $geo,
            'color'   => $request->query('color'),
        ]);
    }

    public function dashboard(Request $request, DataCollectionForm $form): Response
    {
        $analytics = $this->analytics($request, $form);

        return Inertia::render('DataCollection/Forms/Dashboard', $this->formContext($request, $form, $analytics->catalog()) + [
            'dashboard' => $analytics->dashboard(),
        ]);
    }
}
