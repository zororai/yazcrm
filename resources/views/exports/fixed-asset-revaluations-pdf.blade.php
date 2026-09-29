<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: 'Helvetica', sans-serif; font-size: 9px; color: #1f2937; }
        h1 { font-size: 16px; margin: 0 0 2px; }
        .meta { font-size: 9px; color: #6b7280; margin-bottom: 12px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #e5e7eb; padding: 4px 6px; text-align: left; }
        th { background: #1f3864; color: #fff; font-size: 8.5px; text-transform: uppercase; }
        tr:nth-child(even) { background: #f3f6fb; }
        .num { text-align: right; }
        .due { color: #b91c1c; font-weight: bold; }
    </style>
</head>
<body>
    <h1>Asset Revaluation Schedule</h1>
    <p class="meta">
        Generated {{ $generatedAt->format('d M Y, H:i') }} &middot; {{ $assets->count() }} assets
        @if ($dueOnly) &middot; Filtered to revaluations due @endif
    </p>

    <table>
        <thead>
            <tr>
                <th>Asset #</th>
                <th>Name</th>
                <th>Category</th>
                <th>Custodian</th>
                <th>Department</th>
                <th class="num">Cycle (yrs)</th>
                <th>Last Revalued</th>
                <th>Next Due</th>
                <th>Due Now</th>
                <th class="num">Current Value</th>
                <th class="num">Book Value</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($assets as $a)
                <tr>
                    <td>{{ $a->asset_number }}</td>
                    <td>{{ $a->name }}</td>
                    <td>{{ $a->category?->name ?? '—' }}</td>
                    <td>{{ $a->custodian?->name ?? '—' }}</td>
                    <td>{{ $a->department?->name ?? '—' }}</td>
                    <td class="num">{{ $a->revaluation_cycle_years ?? '—' }}</td>
                    <td>{{ $a->last_revalued_at?->format('Y-m-d') ?? 'Never' }}</td>
                    <td>{{ $a->next_revaluation_due ?? '—' }}</td>
                    <td class="{{ $a->revaluation_due ? 'due' : '' }}">{{ $a->revaluation_due ? 'Yes' : 'No' }}</td>
                    <td class="num">{{ $a->current_value !== null ? number_format($a->current_value, 2) : '—' }}</td>
                    <td class="num">{{ $a->book_value !== null ? number_format($a->book_value, 2) : '—' }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
