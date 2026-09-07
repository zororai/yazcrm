<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\SuccessStory;
use App\Models\Ticket;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

// Success Stories — an agent picks one of their own tickets, writes up the
// outcome, and attaches photos plus (optionally) the recording from that
// ticket's call. Reviewed by a manager the same way Progress Reports are.
class SuccessStoryController extends Controller
{
    private const STATUSES = ['pending', 'approved', 'needs_revision'];

    private function isManager(Request $request): bool
    {
        return in_array($request->user()->role, ['admin', 'director', 'helpline_manager'], true);
    }

    public function index(Request $request): Response
    {
        $user      = $request->user();
        $isManager = $this->isManager($request);
        $status    = $request->input('status');

        $stories = SuccessStory::with(['ticket:id,subject', 'user:id,name', 'photos'])
            ->when(! $isManager, fn ($q) => $q->where('user_id', $user->id))
            ->when($status, fn ($q) => $q->where('status', $status))
            ->orderByDesc('created_at')
            ->paginate(12)
            ->withQueryString();

        // Tickets this agent can write a story for — their own, most recent
        // first.
        $myTickets = Ticket::where('agent_id', $user->id)
            ->orderByDesc('created_at')
            ->limit(100)
            ->get(['id', 'subject', 'contact_number', 'call_id']);

        // If arriving from a "Mark Success" link on an older ticket that
        // fell outside that recent window, include it explicitly so it's
        // still selectable in the picker.
        $preselectTicketId = $request->input('ticket_id');
        if ($preselectTicketId && ! $myTickets->contains('id', (int) $preselectTicketId)) {
            $preselected = Ticket::where('agent_id', $user->id)
                ->where('id', $preselectTicketId)
                ->first(['id', 'subject', 'contact_number', 'call_id']);
            if ($preselected) {
                $myTickets->prepend($preselected);
            }
        }

        return Inertia::render('SuccessStories/Index', [
            'stories'    => $stories,
            'myTickets'  => $myTickets,
            'isManager'  => $isManager,
            'filters'    => ['status' => $status],
        ]);
    }

    // The recording (if any) tied to a ticket's call, so the create form
    // can offer "attach this call's recording" without a manual search.
    public function ticketRecording(Request $request, Ticket $ticket): JsonResponse
    {
        abort_unless($ticket->agent_id === $request->user()->id || $this->isManager($request), 403);

        $recording = $ticket->call?->recording;

        return response()->json([
            'recording_id' => $recording?->id,
            'duration'     => $recording?->duration,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'ticket_id'     => 'required|exists:tickets,id',
            'title'         => 'required|string|max:255',
            'story'         => 'required|string',
            'recording_id'  => 'nullable|exists:recordings,id',
            'photos'        => 'nullable|array|max:6',
            'photos.*'      => 'image|max:4096',
        ]);

        $ticket = Ticket::findOrFail($validated['ticket_id']);
        abort_unless($ticket->agent_id === $request->user()->id, 403, 'You can only write a success story for your own ticket.');

        // Only the recording actually tied to this ticket's call may be
        // attached — not an arbitrary recording id.
        $ticketRecordingId = $ticket->call?->recording?->id;
        $recordingId = (! empty($validated['recording_id']) && $validated['recording_id'] == $ticketRecordingId)
            ? $ticketRecordingId
            : null;

        $story = SuccessStory::create([
            'ticket_id'    => $ticket->id,
            'user_id'      => $request->user()->id,
            'recording_id' => $recordingId,
            'title'        => $validated['title'],
            'story'        => $validated['story'],
            'status'       => 'pending',
        ]);

        foreach ($request->file('photos', []) as $photo) {
            $story->photos()->create([
                'path' => $photo->store('success-stories', 'public'),
            ]);
        }

        return redirect()->route('success-stories.index')->with('success', 'Success story submitted.');
    }

    public function show(Request $request, SuccessStory $successStory): Response
    {
        abort_unless($successStory->user_id === $request->user()->id || $this->isManager($request), 403);

        $successStory->load(['ticket:id,subject,contact_number,description', 'user:id,name', 'photos', 'recording:id,duration', 'reviewer:id,name']);

        return Inertia::render('SuccessStories/Show', [
            'story'     => $successStory,
            'isManager' => $this->isManager($request),
            'statuses'  => self::STATUSES,
        ]);
    }

    public function exportPdf(Request $request, SuccessStory $successStory): \Illuminate\Http\Response
    {
        abort_unless($successStory->user_id === $request->user()->id || $this->isManager($request), 403);

        $successStory->load(['ticket:id,subject,contact_number', 'user:id,name', 'photos']);

        $pdf = new \FPDF('P', 'mm', 'A4');
        $pdf->SetMargins(15, 15, 15);
        $pdf->AddPage();
        $usableWidth = 210 - 30;

        // Title
        $pdf->SetFont('Arial', 'B', 16);
        $pdf->MultiCell($usableWidth, 8, $this->ascii($successStory->title));
        $pdf->Ln(1);

        // Meta line
        $pdf->SetFont('Arial', '', 9);
        $pdf->SetTextColor(110, 110, 110);
        $statusLabel = ['pending' => 'Pending Review', 'approved' => 'Approved', 'needs_revision' => 'Needs Revision'][$successStory->status] ?? $successStory->status;
        $meta = 'By ' . $this->ascii($successStory->user->name)
            . '  |  Ticket #' . $successStory->ticket->id . ' - ' . $this->ascii($successStory->ticket->subject)
            . '  |  ' . $statusLabel
            . '  |  ' . $successStory->created_at->format('d M Y');
        $pdf->MultiCell($usableWidth, 5, $meta);
        $pdf->SetTextColor(0, 0, 0);
        $pdf->Ln(3);

        // Story body
        $pdf->SetFont('Arial', '', 11);
        $pdf->MultiCell($usableWidth, 6, $this->ascii($successStory->story));
        $pdf->Ln(4);

        // Photos — one per row, scaled to fit the page width, skipping any
        // format FPDF's core image support doesn't handle (only JPEG/PNG).
        foreach ($successStory->photos as $photo) {
            $absolutePath = Storage::disk('public')->path($photo->path);
            $ext = strtolower(pathinfo($absolutePath, PATHINFO_EXTENSION));
            if (! file_exists($absolutePath) || ! in_array($ext, ['jpg', 'jpeg', 'png'], true)) {
                continue;
            }

            [$widthPx, $heightPx] = @getimagesize($absolutePath) ?: [0, 0];
            if (! $widthPx || ! $heightPx) {
                continue;
            }

            $imgWidthMm = min($usableWidth, 120);
            $imgHeightMm = $imgWidthMm * ($heightPx / $widthPx);

            if ($pdf->GetY() + $imgHeightMm > 280) {
                $pdf->AddPage();
            }

            $pdf->Image($absolutePath, null, null, $imgWidthMm, $imgHeightMm);
            $pdf->Ln(4);
        }

        $filename = 'success-story-' . $successStory->id . '.pdf';

        return response($pdf->Output('S'), 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    // FPDF's core fonts are Latin-1 only — strip anything outside that to
    // avoid mojibake (matches the same guard used for the Timetable PDF).
    private function ascii(string $text): string
    {
        return mb_convert_encoding($text, 'ISO-8859-1', 'UTF-8');
    }

    public function updateStatus(Request $request, SuccessStory $successStory): RedirectResponse
    {
        abort_unless($this->isManager($request), 403);

        $validated = $request->validate([
            'status'       => 'required|in:' . implode(',', self::STATUSES),
            'review_notes' => 'nullable|string|max:1000',
        ]);

        $successStory->update([
            'status'       => $validated['status'],
            'review_notes' => $validated['review_notes'] ?? null,
            'reviewed_by'  => $request->user()->id,
            'reviewed_at'  => now(),
        ]);

        return back()->with('success', 'Success story reviewed.');
    }

    public function destroy(Request $request, SuccessStory $successStory): RedirectResponse
    {
        abort_unless($successStory->user_id === $request->user()->id || $this->isManager($request), 403);

        foreach ($successStory->photos as $photo) {
            Storage::disk('public')->delete($photo->path);
        }
        $successStory->delete();

        return redirect()->route('success-stories.index')->with('success', 'Success story deleted.');
    }
}
