<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: 'Helvetica', sans-serif; font-size: 10px; color: #1f2937; }
        h1 { font-size: 16px; margin: 0 0 2px; }
        h2 { font-size: 12px; margin: 16px 0 6px; color: #1f3864; }
        .meta { font-size: 9px; color: #6b7280; margin-bottom: 12px; }
        .note { font-size: 8px; color: #6b7280; margin: 2px 0 6px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #e5e7eb; padding: 5px 7px; text-align: left; vertical-align: middle; }
        th { background: #1f3864; color: #fff; font-size: 9px; text-transform: uppercase; }
        tr:nth-child(even) { background: #f3f6fb; }
        .num { text-align: right; }
        tfoot td { font-weight: bold; background: #e5eaf3; }
        .tiles td { border: 1px solid #e5e7eb; background: #fff; width: 25%; padding: 8px; }
        .tile-label { font-size: 8px; color: #6b7280; text-transform: uppercase; }
        .tile-value { font-size: 14px; font-weight: bold; margin-top: 2px; }
        .tile-sub { font-size: 8px; color: #6b7280; margin-top: 2px; }
        .bar { height: 9px; }
        .bar span { display: inline-block; height: 9px; }
        .s1 { background: #2a78d6; }
        .s2 { background: #eb6834; }
        .key span { display: inline-block; width: 8px; height: 8px; margin: 0 3px 0 10px; }
        .overdue { color: #b91c1c; font-weight: bold; }
        thead { display: table-header-group; }
        tr { page-break-inside: avoid; }
        .keep-together { page-break-inside: avoid; }
        .neg { color: #b91c1c; }
        .pos { color: #15803d; }
    </style>
</head>
<body>
    @php
        $money = fn ($v) => $v === null ? '—' : number_format((float) $v, 2);
        $signed = fn ($v) => ($v > 0 ? '+' : ($v < 0 ? '-' : '')) . number_format(abs((float) $v), 2);
        $maxCost = max(1, collect($byCategory)->max('cost') ?? 1);
        $maxProjection = max(1, collect($projection)->max('book_value') ?? 1);
        $depreciatedPct = $summary['cost'] > 0 ? round($summary['accumulated_depreciation'] / $summary['cost'] * 100) : 0;
    @endphp

    <h1>Fixed Asset Dashboard</h1>
    <p class="meta">
        Generated {{ $generatedAt->format('d M Y, H:i') }}
        &middot; Category: {{ $categoryName ?? 'All' }}
        &middot; Department: {{ $departmentName ?? 'All' }}
        &middot; Values exclude disposed assets ({{ $summary['disposed_count'] }})
    </p>

    <table class="tiles">
        <tr>
            <td>
                <div class="tile-label">Assets on books</div>
                <div class="tile-value">{{ number_format($summary['asset_count']) }}</div>
            </td>
            <td>
                <div class="tile-label">Net book value</div>
                <div class="tile-value">{{ $money($summary['book_value']) }}</div>
                <div class="tile-sub">of {{ $money($summary['cost']) }} cost / revalued</div>
            </td>
            <td>
                <div class="tile-label">Accumulated depreciation</div>
                <div class="tile-value">{{ $money($summary['accumulated_depreciation']) }}</div>
                <div class="tile-sub">{{ $depreciatedPct }}% depreciated &middot; {{ $money($summary['annual_depreciation']) }} / year</div>
            </td>
            <td>
                <div class="tile-label">Revaluations</div>
                <div class="tile-value">{{ $summary['revaluations_overdue'] }} overdue</div>
                <div class="tile-sub">{{ $summary['revaluations_due_soon'] }} due in 90 days &middot; {{ $summary['revaluation_count_12m'] }} done in 12 months ({{ $signed($summary['revaluation_change_12m']) }})</div>
            </td>
        </tr>
    </table>

    <h2>Value by category</h2>
    <p class="note key">
        Bar = cost / revalued amount, split into
        <span class="s1"></span>Book value
        <span class="s2"></span>Accumulated depreciation
    </p>
    <table>
        <thead>
            <tr>
                <th>Category</th>
                <th class="num">Assets</th>
                <th class="num">Cost / Revalued</th>
                <th class="num">Accum. Dep.</th>
                <th class="num">Book Value</th>
                <th class="num">Annual Dep.</th>
                <th style="width: 22%"></th>
            </tr>
        </thead>
        <tbody>
            @forelse ($byCategory as $r)
                <tr>
                    <td>{{ $r['name'] }}</td>
                    <td class="num">{{ $r['count'] }}</td>
                    <td class="num">{{ $money($r['cost']) }}</td>
                    <td class="num">{{ $money($r['accumulated_depreciation']) }}</td>
                    <td class="num">{{ $money($r['book_value']) }}</td>
                    <td class="num">{{ $money($r['annual_depreciation']) }}</td>
                    <td>
                        <div class="bar"><span class="s1" style="width: {{ round(max($r['book_value'], 0) / $maxCost * 100, 1) }}%"></span><span class="s2" style="width: {{ round(max($r['accumulated_depreciation'], 0) / $maxCost * 100, 1) }}%"></span></div>
                    </td>
                </tr>
            @empty
                <tr><td colspan="7">No assets match these filters.</td></tr>
            @endforelse
        </tbody>
        @if (count($byCategory))
            <tfoot>
                <tr>
                    <td>Total</td>
                    <td class="num">{{ $summary['asset_count'] }}</td>
                    <td class="num">{{ $money($summary['cost']) }}</td>
                    <td class="num">{{ $money($summary['accumulated_depreciation']) }}</td>
                    <td class="num">{{ $money($summary['book_value']) }}</td>
                    <td class="num">{{ $money($summary['annual_depreciation']) }}</td>
                    <td></td>
                </tr>
            </tfoot>
        @endif
    </table>

    <table style="margin-top: 16px; border: none;">
        <tr>
            <td style="width: 50%; border: none; padding: 0 8px 0 0; vertical-align: top;">
                <h2 style="margin-top: 0;">Projected net book value</h2>
                <p class="note">Straight-line, no new purchases, disposals or revaluations.</p>
                <table>
                    <thead><tr><th>Year</th><th class="num">Book Value</th><th style="width: 45%"></th></tr></thead>
                    <tbody>
                        @foreach ($projection as $p)
                            <tr>
                                <td>{{ $p['year'] }}</td>
                                <td class="num">{{ $money($p['book_value']) }}</td>
                                <td><div class="bar"><span class="s1" style="width: {{ round(max($p['book_value'], 0) / $maxProjection * 100, 1) }}%"></span></div></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </td>
            <td style="width: 50%; border: none; padding: 0 0 0 8px; vertical-align: top;">
                <h2 style="margin-top: 0;">Assets by status</h2>
                <p class="note">Includes disposed assets.</p>
                <table>
                    <thead><tr><th>Status</th><th class="num">Assets</th></tr></thead>
                    <tbody>
                        @forelse ($byStatus as $s)
                            <tr>
                                <td>{{ ucfirst(str_replace('_', ' ', $s['status'])) }}</td>
                                <td class="num">{{ $s['count'] }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="2">No assets.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </td>
        </tr>
    </table>

    @php $dueTotal = $summary['revaluations_overdue'] + $summary['revaluations_due_soon']; @endphp
    <h2>Revaluations due (next 90 days)</h2>
    @if ($dueTotal > count($upcomingRevaluations))
        <p class="note">Showing the {{ count($upcomingRevaluations) }} most overdue of {{ $dueTotal }} — see the Asset Revaluations report for the full list.</p>
    @endif
    <table>
        <thead><tr><th>Asset #</th><th>Asset</th><th>Category</th><th>Due</th><th class="num">Book Value</th></tr></thead>
        <tbody>
            @forelse ($upcomingRevaluations as $r)
                <tr>
                    <td>{{ $r['asset_number'] }}</td>
                    <td>{{ $r['name'] }}</td>
                    <td>{{ $r['category'] ?? 'Uncategorised' }}</td>
                    <td class="{{ $r['overdue'] ? 'overdue' : '' }}">{{ $r['overdue'] ? 'OVERDUE · ' : '' }}{{ $r['due'] }}</td>
                    <td class="num">{{ $money($r['book_value']) }}</td>
                </tr>
            @empty
                <tr><td colspan="5">Nothing due in the next 90 days.</td></tr>
            @endforelse
        </tbody>
    </table>

    <div class="keep-together">
    <h2>Recent revaluations (last 12 months)</h2>
    @if ($summary['revaluation_count_12m'] > count($recentRevaluations))
        <p class="note">Showing the {{ count($recentRevaluations) }} most recent of {{ $summary['revaluation_count_12m'] }}.</p>
    @endif
    <table>
        <thead><tr><th>Date</th><th>Asset #</th><th>Asset</th><th>By</th><th class="num">Previous</th><th class="num">Revalued</th><th class="num">Change</th></tr></thead>
        <tbody>
            @forelse ($recentRevaluations as $r)
                <tr>
                    <td>{{ $r['date'] }}</td>
                    <td>{{ $r['asset_number'] }}</td>
                    <td>{{ $r['name'] }}</td>
                    <td>{{ $r['by'] ?? '—' }}</td>
                    <td class="num">{{ $money($r['previous_value']) }}</td>
                    <td class="num">{{ $money($r['revalued_amount']) }}</td>
                    <td class="num {{ $r['change'] < 0 ? 'neg' : 'pos' }}">{{ $signed($r['change']) }}</td>
                </tr>
            @empty
                <tr><td colspan="7">No revaluations in the last 12 months.</td></tr>
            @endforelse
        </tbody>
    </table>
    </div>
</body>
</html>
