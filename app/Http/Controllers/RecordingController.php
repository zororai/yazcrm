<?php

namespace App\Http\Controllers;

use App\Jobs\TranscribeAndDraftNotes;
use App\Models\Recording;
use App\Services\YeastarService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Http;

class RecordingController extends Controller
{
    public function __construct(private YeastarService $yeastar) {}

    public function show(Recording $recording): JsonResponse
    {
        return response()->json($recording->load('call'));
    }

    // The PBX's download link requires an Authorization header, which a plain
    // <audio>/<a> tag can't send — so we fetch it server-side and stream the bytes.
    public function download(Recording $recording): JsonResponse|Response
    {
        $url = $this->yeastar->getRecordingDownloadUrl($recording->file_name);

        if (!$url) {
            return response()->json(['message' => 'Recording not available.'], 404);
        }

        $recording->update(['file_url' => $url]);

        $token = $this->yeastar->getAccessToken();
        $audio = Http::withoutVerifying()->withHeaders(['Authorization' => $token])->get($url);

        if (!$audio->successful()) {
            return response()->json(['message' => 'Failed to fetch recording from PBX.'], 502);
        }

        return response($audio->body(), 200, [
            'Content-Type'        => $audio->header('Content-Type') ?: 'audio/wav',
            'Content-Disposition' => 'inline; filename="' . $recording->file_name . '"',
        ]);
    }

    public function aiNotes(Recording $recording): JsonResponse
    {
        return response()->json([
            'status'     => $recording->transcription_status,
            'transcript' => $recording->transcript,
            'ai_notes'   => $recording->ai_notes,
        ]);
    }

    public function retranscribe(Recording $recording): JsonResponse
    {
        if ($recording->transcription_status === 'processing') {
            return response()->json(['message' => 'Already processing.'], 409);
        }

        $recording->update(['transcription_status' => 'pending']);
        TranscribeAndDraftNotes::dispatch($recording->id)->onQueue('default');

        return response()->json(['message' => 'Transcription queued.']);
    }
}
