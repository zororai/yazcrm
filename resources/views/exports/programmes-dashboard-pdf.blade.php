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
        .tile-value { font-size: 16px; font-weight: bold; margin-top: 2px; }
        .tile-sub { font-size: 8px; color: #6b7280; margin-top: 2px; }
        .bar { height: 9px; }
        .bar span { display: inline-block; height: 9px; }
        .s1 { background: #2a78d6; }
        .s2 { background: #eb6834; }
        .s3 { background: #1baf7a; }
        .key span { display: inline-block; width: 8px; height: 8px; margin: 0 3px 0 10px; }
        .side td.col { width: 50%; border: none; vertical-align: top; }
        .up { color: #15803d; }
        .down { color: #b91c1c; }
    </style>
</head>
<body>
    @php
        $n = fn ($v) => number_format((int) $v);
        $pct = fn ($part, $whole) => $whole > 0 ? (($v = round($part / $whole * 100)) == 0 && $part > 0 ? '<1' : $v) : 0;
        $maxOf = fn ($rows, $key) => max(1, collect($rows)->max($key) ?? 1);
        $prev = $summary['previous_total'];
        $genderTotal = collect($gender)->sum('count');
        $maxTrend = $maxOf($trend['points'], 'count');
        $maxCase = $maxOf($caseTypes, 'count');
        $maxService = $maxOf($services, 'referred');
        $maxAge = $maxOf($ageGroups, 'count');
        $maxAgeGender = max(1, collect($ageGender)->max(fn ($r) => max($r['male'], $r['female'], $r['other'])) ?? 1);
    @endphp

    <h1>Programmes Dashboard</h1>
    <p class="meta">
        Generated {{ $generatedAt->format('d M Y, H:i') }}
        &middot; Programme: {{ $filters['programme'] ?: 'All programmes' }}
        &middot; Period: {{ $periodLabel }}@if ($filters['from']) ({{ $filters['from'] }} – {{ $filters['to'] }})@endif
        @foreach ($activeFilters as $label => $value)
            &middot; {{ $label }}: {{ $value }}
        @endforeach
    </p>

    <table class="tiles">
        <tr>
            <td>
                <div class="tile-label">Total cases</div>
                <div class="tile-value">{{ $n($summary['total']) }}</div>
                @if ($prev !== null && $prev > 0)
                    @php $diff = round(($summary['total'] - $prev) / $prev * 100); @endphp
                    <div class="tile-sub {{ $diff >= 0 ? 'up' : 'down' }}">{{ $diff >= 0 ? '+' : '' }}{{ $diff }}% vs previous period ({{ $n($prev) }})</div>
                @endif
            </td>
            <td>
                <div class="tile-label">Referred cases</div>
                <div class="tile-value">{{ $n($summary['referred']) }}</div>
                <div class="tile-sub">{{ $pct($summary['referred'], $summary['total']) }}% of cases</div>
            </td>
            <td>
                <div class="tile-label">Confirmed service uptake</div>
                <div class="tile-value">{{ $n($summary['uptake']) }}</div>
                <div class="tile-sub">{{ $pct($summary['uptake'], $summary['total']) }}% of cases</div>
            </td>
            <td>
                <div class="tile-label">Valid cases</div>
                <div class="tile-value">{{ $n($summary['valid']) }}</div>
                <div class="tile-sub">{{ $pct($summary['valid'], $summary['total']) }}% valid &middot; {{ $n($summary['repeat_callers']) }} repeat callers</div>
            </td>
        </tr>
    </table>

    @php
        // Long daily series are summarised so the table stays on one page.
        $points = collect($trend['points']);
        $showTrend = $points->count() <= 31 ? $points : $points->filter(fn ($p) => $p['count'] > 0);
    @endphp
    <div class="keep-together">
        <h2>Cases per {{ $trend['unit'] }}</h2>
        @if ($showTrend->count() < $points->count())
            <p class="note">{{ $trend['unit'] === 'day' ? 'Days' : 'Months' }} with no cases are omitted.</p>
        @endif
        <table>
            <thead><tr><th>{{ ucfirst($trend['unit']) }}</th><th class="num">Cases</th><th style="width: 60%"></th></tr></thead>
            <tbody>
                @forelse ($showTrend as $p)
                    <tr>
                        <td>{{ $p['label'] }}</td>
                        <td class="num">{{ $n($p['count']) }}</td>
                        <td><div class="bar"><span class="s1" style="width: {{ round($p['count'] / $maxTrend * 100, 1) }}%"></span></div></td>
                    </tr>
                @empty
                    <tr><td colspan="3">No cases in this period.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="keep-together">
        <h2>Calls by case type</h2>
        <p class="note">{{ $n($caseTypeRecorded) }} of {{ $n($summary['total']) }} cases have a case type recorded.</p>
        <table>
            <thead><tr><th>Case type</th><th class="num">Cases</th><th class="num">Share</th><th style="width: 40%"></th></tr></thead>
            <tbody>
                @forelse ($caseTypes as $r)
                    <tr>
                        <td>{{ $r['name'] }}</td>
                        <td class="num">{{ $n($r['count']) }}</td>
                        <td class="num">{{ $pct($r['count'], $caseTypeRecorded) }}%</td>
                        <td><div class="bar"><span class="s1" style="width: {{ round($r['count'] / $maxCase * 100, 1) }}%"></span></div></td>
                    </tr>
                @empty
                    <tr><td colspan="4">No cases in this period.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="keep-together">
        <h2>Referral by service</h2>
        <p class="note key"><span class="s1"></span>Referred cases <span class="s2"></span>Confirmed service uptake</p>
        <table>
            <thead><tr><th>Service</th><th class="num">Referred</th><th class="num">Uptake</th><th class="num">Rate</th><th style="width: 35%"></th></tr></thead>
            <tbody>
                @forelse ($services as $s)
                    <tr>
                        <td>{{ $s['name'] }}</td>
                        <td class="num">{{ $n($s['referred']) }}</td>
                        <td class="num">{{ $n($s['uptake']) }}</td>
                        <td class="num">{{ $s['rate'] }}%</td>
                        <td>
                            <div class="bar"><span class="s1" style="width: {{ round($s['referred'] / $maxService * 100, 1) }}%"></span></div>
                            <div class="bar" style="margin-top: 2px;"><span class="s2" style="width: {{ round($s['uptake'] / $maxService * 100, 1) }}%"></span></div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5">No referrals in this period.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="keep-together">
        <h2>Demographics</h2>
        <table class="side" style="border: none;">
            <tr>
                <td class="col" style="padding: 0 8px 0 0;">
                    <table>
                        <thead><tr><th>Gender</th><th class="num">Cases</th><th class="num">Share</th></tr></thead>
                        <tbody>
                            @forelse ($gender as $g)
                                <tr><td>{{ $g['name'] }}</td><td class="num">{{ $n($g['count']) }}</td><td class="num">{{ $pct($g['count'], $genderTotal) }}%</td></tr>
                            @empty
                                <tr><td colspan="3">No gender recorded.</td></tr>
                            @endforelse
                            <tr><td>Not recorded</td><td class="num">{{ $n($summary['total'] - $genderTotal) }}</td><td></td></tr>
                        </tbody>
                    </table>
                </td>
                <td class="col" style="padding: 0 0 0 8px;">
                    <table>
                        <thead><tr><th>Age group</th><th class="num">Cases</th><th style="width: 45%"></th></tr></thead>
                        <tbody>
                            @foreach ($ageGroups as $a)
                                <tr>
                                    <td>{{ $a['name'] }}</td>
                                    <td class="num">{{ $n($a['count']) }}</td>
                                    <td><div class="bar"><span class="s1" style="width: {{ round($a['count'] / $maxAge * 100, 1) }}%"></span></div></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </td>
            </tr>
        </table>

        <h2>Cases by gender &amp; age</h2>
        <p class="note key"><span class="s1"></span>Male <span class="s2"></span>Female <span class="s3"></span>Other &nbsp;&middot; youth age bands as on the helpline screen</p>
        <table>
            <thead><tr><th>Age</th><th class="num">Male</th><th class="num">Female</th><th class="num">Other</th><th style="width: 40%"></th></tr></thead>
            <tbody>
                @foreach ($ageGender as $r)
                    <tr>
                        <td>{{ $r['band'] }}</td>
                        <td class="num">{{ $n($r['male']) }}</td>
                        <td class="num">{{ $n($r['female']) }}</td>
                        <td class="num">{{ $n($r['other']) }}</td>
                        <td>
                            <div class="bar"><span class="s1" style="width: {{ round($r['male'] / $maxAgeGender * 100, 1) }}%"></span></div>
                            <div class="bar" style="margin-top: 2px;"><span class="s2" style="width: {{ round($r['female'] / $maxAgeGender * 100, 1) }}%"></span></div>
                            <div class="bar" style="margin-top: 2px;"><span class="s3" style="width: {{ round($r['other'] / $maxAgeGender * 100, 1) }}%"></span></div>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</body>
</html>
