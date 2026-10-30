<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: 'Helvetica', sans-serif; font-size: 10px; color: #1f2937; }
        h1 { font-size: 16px; margin: 0 0 2px; }
        h2 { font-size: 11px; margin: 14px 0 4px; color: #1f3864; }
        .meta { font-size: 9px; color: #6b7280; margin-bottom: 12px; }
        .note { font-size: 8px; color: #6b7280; margin: 0 0 4px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #e5e7eb; padding: 4px 6px; text-align: left; vertical-align: middle; }
        th { background: #1f3864; color: #fff; font-size: 8px; text-transform: uppercase; }
        tr { page-break-inside: avoid; }
        .keep-together { page-break-inside: avoid; }
        .num { text-align: right; width: 60px; }
        .bar { height: 8px; background: #2a78d6; }
    </style>
</head>
<body>
    <h1>{{ $form->name }} — summary</h1>
    <div class="meta">
        {{ number_format($summary['total']) }} submissions · generated {{ now()->format('j M Y H:i') }}
        @if($filters)
            · filters:
            @foreach($filters as $key => $value)
                {{ $key }}={{ is_array($value) ? collect($value)->map(fn ($v, $k) => "$k:$v")->implode(', ') : $value }}@if(! $loop->last), @endif
            @endforeach
        @endif
    </div>

    @foreach($summary['fields'] as $field)
        <div class="keep-together">
            <h2>{{ $field['label'] }} @if($field['repeat'])<span class="note">(in {{ $field['repeat'] }})</span>@endif</h2>

            @if($field['kind'] === 'hidden')
                <p class="note">Personal data — hidden.</p>
            @elseif($field['kind'] === 'choices')
                <p class="note">{{ number_format($field['answered']) }} answered</p>
                @php $max = max(1, collect($field['rows'])->max('count')); @endphp
                <table>
                    <thead><tr><th>Answer</th><th class="num">Count</th><th class="num">%</th><th style="width:35%"></th></tr></thead>
                    <tbody>
                    @foreach($field['rows'] as $row)
                        <tr>
                            <td>{{ $row['label'] }}</td>
                            <td class="num">{{ number_format($row['count']) }}</td>
                            <td class="num">{{ $row['pct'] }}</td>
                            <td><div class="bar" style="width: {{ round($row['count'] / $max * 100) }}%"></div></td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            @elseif($field['kind'] === 'numeric')
                <table>
                    <thead><tr><th>Answered</th><th>Mean</th><th>Median</th><th>Mode</th><th>Min</th><th>Max</th><th>Std dev</th></tr></thead>
                    <tbody><tr>
                        <td>{{ number_format($field['count']) }}</td><td>{{ $field['mean'] ?? '—' }}</td><td>{{ $field['median'] ?? '—' }}</td>
                        <td>{{ $field['mode'] ?? '—' }}</td><td>{{ $field['min'] ?? '—' }}</td><td>{{ $field['max'] ?? '—' }}</td><td>{{ $field['stddev'] ?? '—' }}</td>
                    </tr></tbody>
                </table>
            @elseif($field['kind'] === 'timeline')
                <p class="note">{{ number_format($field['answered']) }} answered
                    @if(count($field['timeline'])) · {{ $field['timeline']->first()['day'] }} to {{ $field['timeline']->last()['day'] }} @endif</p>
            @elseif($field['kind'] === 'text')
                <p class="note">{{ number_format($field['answered']) }} answered. Latest:</p>
                <table><tbody>
                    @foreach(array_slice($field['samples'], 0, 8) as $sample)
                        <tr><td>{{ $sample }}</td></tr>
                    @endforeach
                </tbody></table>
            @else
                <p class="note">{{ number_format($field['answered']) }} answered</p>
            @endif
        </div>
    @endforeach

    @if($summary['repeats'])
        <h2>Repeat groups</h2>
        <table>
            <thead><tr><th>Repeat</th><th class="num">Submissions</th><th class="num">Entries</th><th class="num">Mean per submission</th><th class="num">Max</th></tr></thead>
            <tbody>
            @foreach($summary['repeats'] as $repeat)
                <tr><td>{{ $repeat['label'] }}</td><td class="num">{{ $repeat['submissions'] }}</td><td class="num">{{ $repeat['instances'] }}</td>
                    <td class="num">{{ $repeat['mean'] ?? '—' }}</td><td class="num">{{ $repeat['max'] ?? '—' }}</td></tr>
            @endforeach
            </tbody>
        </table>
    @endif
</body>
</html>
