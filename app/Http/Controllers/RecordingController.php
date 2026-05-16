<?php

namespace App\Http\Controllers;

use App\Models\Recording;
use App\Services\YeastarService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RecordingController extends Controller
{
    public function __construct(private YeastarService $yeastar) {}

    public function show(Recording $recording): JsonResponse
    {
        return response()->json($recording->load('call'));
    }

    public function download(Recording $recording): JsonResponse
    {
        $url = $recording->file_url
            ?? $this->yeastar->getRecordingDownloadUrl($recording->file_name);

        if (!$url) {
            return response()->json(['message' => 'Recording not available.'], 404);
        }

        if (!$recording->file_url && $url) {
            $recording->update(['file_url' => $url]);
        }

        return response()->json(['url' => $url]);
    }
}
