<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Risk Register Report</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: Arial, sans-serif; font-size: 12px; color: #1a1a1a; padding: 20px; }
        h1 { font-size: 20px; margin-bottom: 4px; }
        .subtitle { color: #666; margin-bottom: 24px; font-size: 11px; }
        h2 { font-size: 14px; margin: 24px 0 10px; border-bottom: 2px solid #e5e7eb; padding-bottom: 4px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 24px; }
        th { background: #f3f4f6; text-align: left; padding: 6px 8px; font-size: 10px; text-transform: uppercase; letter-spacing: 0.05em; border: 1px solid #e5e7eb; }
        td { padding: 6px 8px; border: 1px solid #e5e7eb; vertical-align: top; }
        tr:nth-child(even) td { background: #f9fafb; }
        .band-red   { background: #fee2e2; color: #991b1b; font-weight: bold; padding: 2px 6px; border-radius: 4px; }
        .band-amber { background: #fef3c7; color: #92400e; font-weight: bold; padding: 2px 6px; border-radius: 4px; }
        .band-green { background: #d1fae5; color: #065f46; font-weight: bold; padding: 2px 6px; border-radius: 4px; }
        .status-open        { background: #fef3c7; color: #92400e; padding: 2px 6px; border-radius: 4px; }
        .status-in_progress { background: #dbeafe; color: #1e40af; padding: 2px 6px; border-radius: 4px; }
        .status-done        { background: #d1fae5; color: #065f46; padding: 2px 6px; border-radius: 4px; }
        @media print {
            @page { margin: 15mm; }
            h2 { page-break-before: auto; }
        }
    </style>
</head>
<body>
    <h1>Risk Register Report</h1>
    <p class="subtitle">Generated: {{ now()->format('d M Y H:i') }}</p>

    <h2>Risks ({{ $risks->count() }} total)</h2>
    <table>
        <thead>
            <tr>
                <th>Ref</th>
                <th>Asset</th>
                <th>Category</th>
                <th>Description</th>
                <th>Likelihood</th>
                <th>Impact</th>
                <th>Inherent</th>
                <th>Residual</th>
                <th>Band</th>
                <th>Owner</th>
                <th>Controls</th>
            </tr>
        </thead>
        <tbody>
            @foreach($risks as $risk)
            <tr>
                <td>{{ $risk->risk_ref }}</td>
                <td>{{ $risk->asset?->name ?? '—' }}</td>
                <td>{{ str_replace('_', ' ', $risk->category) }}</td>
                <td>{{ $risk->description }}</td>
                <td style="text-align:center">{{ $risk->likelihood }}</td>
                <td style="text-align:center">{{ $risk->impact }}</td>
                <td style="text-align:center">{{ $risk->inherent_score }}</td>
                <td style="text-align:center">{{ $risk->residual_score ?? '—' }}</td>
                <td><span class="band-{{ $risk->band }}">{{ strtoupper($risk->band) }}</span></td>
                <td>{{ $risk->risk_owner ?? '—' }}</td>
                <td>{{ $risk->controls->count() }} control(s)</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <h2>Priority Actions ({{ $actions->count() }} total)</h2>
    <table>
        <thead>
            <tr>
                <th>Ref</th>
                <th>Risk</th>
                <th>Asset</th>
                <th>Description</th>
                <th>Owner</th>
                <th>Target Date</th>
                <th>Priority</th>
                <th>Status</th>
                <th>Completed</th>
            </tr>
        </thead>
        <tbody>
            @foreach($actions as $action)
            <tr>
                <td>{{ $action->action_ref }}</td>
                <td>{{ $action->risk?->risk_ref ?? '—' }}</td>
                <td>{{ $action->risk?->asset?->name ?? '—' }}</td>
                <td>{{ $action->description }}</td>
                <td>{{ $action->owner }}</td>
                <td>{{ $action->target_date?->format('d M Y') }}</td>
                <td>{{ ucfirst($action->priority) }}</td>
                <td><span class="status-{{ $action->status }}">{{ str_replace('_', ' ', $action->status) }}</span></td>
                <td>{{ $action->completed_at?->format('d M Y') ?? '—' }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
