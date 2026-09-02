<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Recording;
use App\Models\Ticket;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

// Groups recordings (via their call's caller number) and tickets (via
// contact_number) by phone number, so anyone can see e.g. "this number
// has 10 tickets and 10 recordings" in one place.
class RecordingByNumberController extends Controller
{
    public function index(Request $request): Response
    {
        $recordingCounts = DB::table('recordings')
            ->join('calls', 'calls.id', '=', 'recordings.call_id')
            ->whereNotNull('calls.caller')
            ->where('calls.caller', '!=', '')
            ->select('calls.caller as number', DB::raw('count(*) as recordings_count'));

        $ticketCounts = DB::table('tickets')
            ->whereNull('tickets.deleted_at')
            ->whereNotNull('contact_number')
            ->where('contact_number', '!=', '')
            ->select('contact_number as number', DB::raw('count(*) as tickets_count'));

        if ($search = $request->string('search')->trim()->toString()) {
            $recordingCounts->where('calls.caller', 'like', "%{$search}%");
            $ticketCounts->where('contact_number', 'like', "%{$search}%");
        }

        $recordingRows = $recordingCounts->groupBy('calls.caller')->get()->keyBy('number');
        $ticketRows    = $ticketCounts->groupBy('contact_number')->get()->keyBy('number');

        $numbers = $recordingRows->keys()->merge($ticketRows->keys())->unique()->values();

        $rows = $numbers->map(function ($number) use ($recordingRows, $ticketRows) {
            return [
                'number'           => $number,
                'recordings_count' => (int) ($recordingRows[$number]->recordings_count ?? 0),
                'tickets_count'    => (int) ($ticketRows[$number]->tickets_count ?? 0),
            ];
        })->sortByDesc(fn ($r) => $r['recordings_count'] + $r['tickets_count'])->values();

        // Manual pagination — the grouping above can't be done efficiently
        // as a single paginated query since it merges two separate group-bys.
        $perPage = 25;
        $page    = max(1, (int) $request->input('page', 1));
        $total   = $rows->count();
        $paged   = $rows->slice(($page - 1) * $perPage, $perPage)->values();

        return Inertia::render('Recordings/ByNumber', [
            'rows'    => $paged,
            'filters' => $request->only(['search']),
            'pagination' => [
                'current_page' => $page,
                'per_page'     => $perPage,
                'total'        => $total,
                'last_page'    => (int) ceil($total / $perPage) ?: 1,
            ],
        ]);
    }

    // Drill-down for one number — fetched on expand, not upfront.
    public function details(Request $request): JsonResponse
    {
        $number = trim($request->get('number', ''));
        if ($number === '') {
            return response()->json(['recordings' => [], 'tickets' => []]);
        }

        $recordings = Recording::whereHas('call', fn ($q) => $q->where('caller', $number))
            ->with('call:id,caller,started_at')
            ->orderByDesc('created_at')
            ->limit(50)
            ->get(['id', 'call_id', 'created_at', 'duration', 'transcription_status']);

        $tickets = Ticket::where('contact_number', $number)
            ->orderByDesc('created_at')
            ->limit(50)
            ->get(['id', 'subject', 'status', 'priority', 'created_at']);

        return response()->json(['recordings' => $recordings, 'tickets' => $tickets]);
    }
}
