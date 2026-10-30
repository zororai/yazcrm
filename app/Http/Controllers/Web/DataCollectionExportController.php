<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Jobs\DataCollection\GenerateSubmissionExport;
use App\Models\DataCollectionExport;
use App\Models\DataCollectionForm;
use App\Services\DataCollection\SubmissionFilters;
use App\Support\DataCollection\FormAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

// Exports run as queued jobs; the file is downloadable from the bell
// notification or the data page once ready, by the person who asked for it.
class DataCollectionExportController extends Controller
{
    public function store(Request $request, DataCollectionForm $form): RedirectResponse
    {
        $this->authorize('export', $form);

        $data = $request->validate([
            'format' => 'required|in:csv,xlsx,geojson,pdf',
            'labels' => 'boolean',
        ]);

        $export = DataCollectionExport::create([
            'form_id' => $form->id,
            'user_id' => $request->user()->id,
            'format'  => $data['format'],
            'options' => [
                'filters' => SubmissionFilters::fromRequest($request),
                'labels'  => $request->boolean('labels', true),
            ],
            'status'  => 'queued',
        ]);

        GenerateSubmissionExport::dispatch($export->id);

        return back()->with('success', 'Export started. You\'ll get a notification when it\'s ready to download.');
    }

    public function download(Request $request, DataCollectionExport $export)
    {
        abort_unless($export->user_id === $request->user()->id || FormAccess::isAdmin($request->user()), 403);
        abort_unless($export->status === 'ready' && $export->path, 404);

        $extension = pathinfo($export->path, PATHINFO_EXTENSION);
        $name = str($export->form?->name ?? 'form')->slug().'-'.$export->created_at->format('Ymd-His').'.'.$extension;

        return Storage::disk($export->disk)->download($export->path, $name);
    }
}
