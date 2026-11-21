<!doctype html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <title>{{ __('Virtual sud natijalari') }}</title>
    <style>
        body{font-family:Arial,sans-serif;margin:32px;color:#111827}h1{font-size:24px}h2{font-size:18px}
        table{width:100%;border-collapse:collapse;margin-top:16px}th,td{border:1px solid #cbd5e1;padding:8px;text-align:left;font-size:12px}
        .warning{background:#fff7ed;border-left:4px solid #ea580c;padding:12px}
        @media print{.no-print{display:none}body{margin:12px}}
    </style>
</head>
<body>
    <button class="no-print" onclick="window.print()">{{ __('PDF / Chop etish') }}</button>
    <h1>{{ $report['session']['scenario'] }}</h1>
    <p>{{ $report['session']['group'] }} · {{ $report['session']['course'] }}</p>
    @if($report['outcome'])
        <h2>{{ \App\Services\VirtualCourtroom\GroupResultReport::winnerLabel($report['outcome']['winner']) }}</h2>
        <p>{{ $report['outcome']['reason'] }}</p>
        <p class="warning">{{ __('Bu faqat virtual sud mashg‘uloti natijasi, real sud qarori emas.') }}</p>
    @endif
    <table>
        <thead><tr><th>{{ __('Talaba') }}</th><th>{{ __('Rol') }}</th><th>{{ __('Individual') }}</th><th>{{ __('Faollik') }}</th><th>{{ __('O‘qituvchi') }}</th><th>{{ __('Holat') }}</th></tr></thead>
        <tbody>
            @foreach($report['people'] as $person)
                @php($grade = $person['grade'] ?? [])
                <tr><td>{{ $person['name'] }}</td><td>{{ $person['role'] }}</td><td>{{ $grade['individual_score'] ?? '—' }}</td><td>{{ $grade['activity_score'] ?? '—' }}</td><td>{{ $grade['teacher_final_grade'] ?? '—' }}</td><td>{{ \App\Services\VirtualCourtroom\GroupResultReport::statusLabel($grade['status'] ?? 'preliminary') }}</td></tr>
            @endforeach
        </tbody>
    </table>
    @if(!empty($report['conclusion']))
        <h2>{{ __('O‘qituvchi yakuniy xulosasi') }}</h2>
        @foreach($report['conclusion'] as $value)
            @if($value)<p>{{ $value }}</p>@endif
        @endforeach
    @endif
</body>
</html>
