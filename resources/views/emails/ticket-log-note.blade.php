<!doctype html>
<html>
<body style="margin:0;padding:24px;background:#f3f4f6;font-family:Arial,Helvetica,sans-serif;color:#111827;">
    <div style="max-width:560px;margin:0 auto;background:#ffffff;border-radius:8px;padding:24px;">
        <p style="margin:0 0 4px;font-size:12px;color:#6b7280;text-transform:uppercase;letter-spacing:.05em;">Log note · Ticket #{{ $ticket->id }}</p>
        <h1 style="margin:0 0 16px;font-size:18px;">{{ $ticket->subject }}</h1>

        <table style="width:100%;font-size:13px;color:#374151;margin-bottom:16px;" cellpadding="0" cellspacing="0">
            <tr><td style="padding:2px 0;color:#6b7280;width:120px;">Priority</td><td style="text-transform:capitalize;">{{ $ticket->priority }}</td></tr>
            <tr><td style="padding:2px 0;color:#6b7280;">Status</td><td style="text-transform:capitalize;">{{ str_replace('_', ' ', $ticket->status) }}</td></tr>
            @if ($ticket->follow_up_date)
                <tr><td style="padding:2px 0;color:#6b7280;">Follow-up date</td><td>{{ \Illuminate\Support\Carbon::parse($ticket->follow_up_date)->format('j M Y') }}</td></tr>
            @endif
        </table>

        <div style="border-left:3px solid #4f46e5;background:#f9fafb;padding:12px 14px;font-size:14px;white-space:pre-wrap;">{{ $note->body }}</div>
        <p style="margin:8px 0 20px;font-size:12px;color:#6b7280;">{{ $note->user?->name ?? 'Unknown' }} · {{ $note->created_at?->format('j M Y, H:i') }}</p>

        <a href="{{ $url }}" style="display:inline-block;background:#4f46e5;color:#ffffff;text-decoration:none;padding:10px 16px;border-radius:6px;font-size:14px;">Open the ticket</a>
    </div>
</body>
</html>
