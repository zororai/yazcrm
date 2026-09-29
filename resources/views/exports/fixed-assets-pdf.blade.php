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
    </style>
</head>
<body>
    <h1>Fixed Assets Register</h1>
    <p class="meta">Generated {{ $generatedAt->format('d M Y, H:i') }} &middot; {{ $assets->count() }} assets</p>

    <table>
        <thead>
            <tr>
                <th>Asset #</th>
                <th>Name</th>
                <th>Category</th>
                <th>Custodian</th>
                <th>Department</th>
                <th>Status</th>
                <th class="num">Purchase Cost</th>
                <th class="num">Useful Life</th>
                <th class="num">Salvage Value</th>
                <th class="num">Annual Dep.</th>
                <th class="num">Accum. Dep.</th>
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
                    <td>{{ str_replace('_', ' ', $a->status) }}</td>
                    <td class="num">{{ $a->purchase_cost !== null ? number_format($a->purchase_cost, 2) : '—' }}</td>
                    <td class="num">{{ $a->useful_life_years ? $a->useful_life_years . ' yrs' : '—' }}</td>
                    <td class="num">{{ number_format($a->salvage_value ?? 0, 2) }}</td>
                    <td class="num">{{ $a->annual_depreciation !== null ? number_format($a->annual_depreciation, 2) : '—' }}</td>
                    <td class="num">{{ $a->accumulated_depreciation !== null ? number_format($a->accumulated_depreciation, 2) : '—' }}</td>
                    <td class="num">{{ $a->book_value !== null ? number_format($a->book_value, 2) : '—' }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
