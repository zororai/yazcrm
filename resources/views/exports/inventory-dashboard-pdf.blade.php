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
        thead { display: table-header-group; }
        tr { page-break-inside: avoid; }
        .keep-together { page-break-inside: avoid; }
        .num { text-align: right; }
        .tiles td { border: 1px solid #e5e7eb; background: #fff; width: 25%; padding: 8px; vertical-align: top; }
        .tile-label { font-size: 8px; color: #6b7280; text-transform: uppercase; }
        .tile-value { font-size: 14px; font-weight: bold; margin-top: 2px; }
        .tile-sub { font-size: 8px; color: #6b7280; margin-top: 2px; }
        .bar { height: 9px; }
        .bar span { display: inline-block; height: 9px; }
        .s1 { background: #2a78d6; }
        .s2 { background: #eb6834; }
        .key span { display: inline-block; width: 8px; height: 8px; margin: 0 3px 0 10px; }
        .out { color: #b91c1c; font-weight: bold; }
        .low { color: #b45309; font-weight: bold; }
        .side td.col { width: 50%; border: none; vertical-align: top; }
    </style>
</head>
<body>
    @php
        $n = fn ($v) => number_format((int) $v);
        $maxTrend = max(1, collect($trend)->max(fn ($p) => max($p['issued'], $p['received'])) ?? 1);
        $maxItem = max(1, collect($topItems)->max('quantity') ?? 1);
        $maxDept = max(1, collect($byDepartment)->max('quantity') ?? 1);
        $periodLabel = ['30d' => 'Last 30 days', '90d' => 'Last 90 days', '12m' => 'Last 12 months'][$period['key']];
    @endphp

    <h1>Inventory Dashboard</h1>
    <p class="meta">
        Generated {{ $generatedAt->format('d M Y, H:i') }}
        &middot; {{ $periodLabel }} ({{ $period['from'] }} – {{ $period['to'] }})
        &middot; Store: {{ $storeName ?? 'All' }}
        &middot; Department: {{ $departmentName ?? 'All' }}
        &middot; Quantities in each item's own unit
    </p>

    <table class="tiles">
        <tr>
            <td>
                <div class="tile-label">Items issued</div>
                <div class="tile-value">{{ $n($summary['units_issued']) }} units</div>
                <div class="tile-sub">{{ $n($summary['issues']) }} issues</div>
            </td>
            <td>
                <div class="tile-label">Items received</div>
                <div class="tile-value">{{ $summary['receipts_counted'] ? $n($summary['units_received']) . ' units' : 'n/a' }}</div>
                <div class="tile-sub">{{ $summary['receipts_counted'] ? $periodLabel : 'Receipts have no department' }}</div>
            </td>
            <td>
                <div class="tile-label">Stock on hand</div>
                <div class="tile-value">{{ $n($summary['units_on_hand']) }} units</div>
                <div class="tile-sub">{{ $n($summary['active_items']) }} active items &middot; {{ $n($summary['units_reserved']) }} reserved &middot; {{ $summary['in_transit'] }} in transit</div>
            </td>
            <td>
                <div class="tile-label">Needs reordering</div>
                <div class="tile-value">{{ $summary['low_stock'] + $summary['out_of_stock'] }}</div>
                <div class="tile-sub">{{ $summary['low_stock'] }} low &middot; {{ $summary['out_of_stock'] }} out of stock</div>
            </td>
        </tr>
    </table>

    <h2>Issued vs received ({{ $period['key'] === '12m' ? 'per month' : 'per week' }})</h2>
    <p class="note key">
        <span class="s1"></span>Issued
        @if ($summary['receipts_counted'])<span class="s2"></span>Received @endif
    </p>
    <table>
        <thead><tr><th>{{ $period['key'] === '12m' ? 'Month' : 'Week of' }}</th><th class="num">Issued</th>@if ($summary['receipts_counted'])<th class="num">Received</th>@endif<th style="width: 45%"></th></tr></thead>
        <tbody>
            @foreach ($trend as $p)
                <tr>
                    <td>{{ $p['label'] }}</td>
                    <td class="num">{{ $n($p['issued']) }}</td>
                    @if ($summary['receipts_counted'])<td class="num">{{ $n($p['received']) }}</td>@endif
                    <td>
                        <div class="bar"><span class="s1" style="width: {{ round($p['issued'] / $maxTrend * 100, 1) }}%"></span></div>
                        @if ($summary['receipts_counted'])<div class="bar" style="margin-top: 2px;"><span class="s2" style="width: {{ round($p['received'] / $maxTrend * 100, 1) }}%"></span></div>@endif
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <table class="side keep-together" style="margin-top: 8px; border: none;">
        <tr>
            <td class="col" style="padding: 0 8px 0 0;">
                <h2>Most issued items</h2>
                <table>
                    <thead><tr><th>Item</th><th class="num">Units</th><th style="width: 35%"></th></tr></thead>
                    <tbody>
                        @forelse ($topItems as $r)
                            <tr>
                                <td>{{ $r['name'] }}</td>
                                <td class="num">{{ $n($r['quantity']) }}</td>
                                <td><div class="bar"><span class="s1" style="width: {{ round($r['quantity'] / $maxItem * 100, 1) }}%"></span></div></td>
                            </tr>
                        @empty
                            <tr><td colspan="3">Nothing issued in this period.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </td>
            <td class="col" style="padding: 0 0 0 8px;">
                <h2>Issued by department</h2>
                <table>
                    <thead><tr><th>Department</th><th class="num">Units</th><th style="width: 35%"></th></tr></thead>
                    <tbody>
                        @forelse ($byDepartment as $r)
                            <tr>
                                <td>{{ $r['name'] }}</td>
                                <td class="num">{{ $n($r['quantity']) }}</td>
                                <td><div class="bar"><span class="s1" style="width: {{ round($r['quantity'] / $maxDept * 100, 1) }}%"></span></div></td>
                            </tr>
                        @empty
                            <tr><td colspan="3">Nothing issued in this period.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </td>
        </tr>
    </table>

    <h2>Items to reorder</h2>
    @if ($reorderTotal > count($reorder))
        <p class="note">Showing {{ count($reorder) }} of {{ $reorderTotal }}, out of stock first.</p>
    @endif
    <table>
        <thead><tr><th>Item</th><th>Category</th><th>Status</th><th class="num">Available</th><th class="num">Reorder Level</th><th class="num">Minimum</th></tr></thead>
        <tbody>
            @forelse ($reorder as $r)
                <tr>
                    <td>{{ $r['name'] }}</td>
                    <td>{{ $r['category'] ?? 'Uncategorised' }}</td>
                    <td class="{{ $r['status'] }}">{{ $r['status'] === 'out' ? 'OUT OF STOCK' : 'Low' }}</td>
                    <td class="num">{{ $n($r['available']) }} {{ $r['unit'] }}</td>
                    <td class="num">{{ $n($r['reorder_level']) }}</td>
                    <td class="num">{{ $n($r['minimum_stock']) }}</td>
                </tr>
            @empty
                <tr><td colspan="6">No items at or below their reorder level.</td></tr>
            @endforelse
        </tbody>
    </table>

    <div class="keep-together">
        <h2>Recent issues</h2>
        <table>
            <thead><tr><th>Issue #</th><th>Date</th><th>Store</th><th>Issued To</th><th>Department</th><th>By</th><th class="num">Units</th></tr></thead>
            <tbody>
                @forelse ($recentIssues as $i)
                    <tr>
                        <td>{{ $i['number'] }}</td>
                        <td>{{ $i['date'] }}</td>
                        <td>{{ $i['store'] ?? '—' }}</td>
                        <td>{{ $i['issued_to'] ?: '—' }}</td>
                        <td>{{ $i['department'] ?? 'No department' }}</td>
                        <td>{{ $i['issued_by'] ?? '—' }}</td>
                        <td class="num">{{ $n($i['units']) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="7">No issues in this period.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="keep-together">
        <h2>Stock by store</h2>
        <table>
            <thead><tr><th>Store</th><th class="num">Items in stock</th><th class="num">Units</th><th class="num">Reserved</th></tr></thead>
            <tbody>
                @forelse ($byStore as $s)
                    <tr>
                        <td>{{ $s['name'] }}</td>
                        <td class="num">{{ $n($s['items']) }}</td>
                        <td class="num">{{ $n($s['quantity']) }}</td>
                        <td class="num">{{ $n($s['reserved']) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="4">No stock recorded.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</body>
</html>
