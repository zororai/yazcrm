<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: 'Helvetica', sans-serif; font-size: 10px; color: #1f2937; }
        h1 { font-size: 16px; margin: 0 0 2px; }
        .meta { font-size: 9px; color: #6b7280; margin-bottom: 12px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #e5e7eb; padding: 5px 7px; text-align: left; }
        th { background: #1f3864; color: #fff; font-size: 9px; text-transform: uppercase; }
        tr:nth-child(even) { background: #f3f6fb; }
        .num { text-align: right; }
        tfoot td { font-weight: bold; background: #e5eaf3; }
    </style>
</head>
<body>
    <h1>Depreciation Report</h1>
    <p class="meta">Generated {{ $generatedAt->format('d M Y, H:i') }} &middot; {{ $assets->count() }} assets</p>

    <table>
        <thead>
            <tr>
                <th>Asset Name</th>
                <th class="num">Salvage Value</th>
                <th class="num">Annual Depreciation</th>
                <th class="num">Accumulated Depreciation</th>
                <th class="num">Book Value</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($assets as $a)
                <tr>
                    <td>{{ $a->name }}</td>
                    <td class="num">{{ number_format($a->salvage_value ?? 0, 2) }}</td>
                    <td class="num">{{ $a->annual_depreciation !== null ? number_format($a->annual_depreciation, 2) : '—' }}</td>
                    <td class="num">{{ $a->accumulated_depreciation !== null ? number_format($a->accumulated_depreciation, 2) : '—' }}</td>
                    <td class="num">{{ $a->book_value !== null ? number_format($a->book_value, 2) : '—' }}</td>
                </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr>
                <td>Total</td>
                <td class="num">{{ number_format($assets->sum('salvage_value'), 2) }}</td>
                <td class="num">{{ number_format($assets->sum('annual_depreciation'), 2) }}</td>
                <td class="num">{{ number_format($assets->sum('accumulated_depreciation'), 2) }}</td>
                <td class="num">{{ number_format($assets->sum('book_value'), 2) }}</td>
            </tr>
        </tfoot>
    </table>
</body>
</html>
