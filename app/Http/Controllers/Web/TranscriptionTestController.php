<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Call;
use App\Models\Recording;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

// Lets an admin upload any audio file and instantly get a test Call +
// Recording to run through the real transcription/AI-summary pipeline —
// no need for a real PBX recording to test the ASR feature.
class TranscriptionTestController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('Testing/TranscriptionTest', [
            'recentTestCalls' => Call::where('call_id', 'like', 'TEST-%')
                ->latest()
                ->limit(10)
                ->get(['id', 'call_id', 'caller', 'created_at']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'audio' => 'required|file|mimes:wav,mp3,ogg,m4a,oga|max:51200',
            'label' => 'nullable|string|max:255',
        ]);

        $file = $request->file('audio');
        $filename = Str::uuid() . '.' . $file->getClientOriginalExtension();
        $path = $file->storeAs('test-recordings', $filename, 'public');

        $call = Call::create([
            'call_id'    => 'TEST-' . Str::uuid(),
            'caller'     => $data['label'] ?? 'Test Upload',
            'callee'     => 'ASR Test Tool',
            'direction'  => 'inbound',
            'status'     => 'answered',
            'duration'   => 0,
            'started_at' => now(),
            'ended_at'   => now(),
        ]);

        Recording::create([
            'call_id'   => $call->id,
            'file_name' => $file->getClientOriginalName(),
            'file_path' => $path,
            'file_url'  => url(Storage::url($path)),
            'format'    => $file->getClientOriginalExtension(),
        ]);

        return redirect()->route('calls.show', $call)->with('success', 'Test call created. Use the Transcription section below to run it through the pipeline.');
    }
}
