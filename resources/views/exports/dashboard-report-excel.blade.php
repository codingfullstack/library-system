<!DOCTYPE html>
<html lang="lt">
<head>
    <meta charset="UTF-8">
    <title>Ataskaita</title>
    <style>
        body { font-family: Arial, sans-serif; font-size: 12px; color: #111827; }
        h1, h2 { margin: 0 0 8px; }
        p { margin: 0 0 12px; }
        table { width: 100%; border-collapse: collapse; margin: 0 0 24px; }
        th, td { border: 1px solid #d1d5db; padding: 6px 8px; text-align: left; vertical-align: top; }
        th { background: #f3f4f6; font-weight: 700; }
        .muted { color: #6b7280; }
    </style>
</head>
<body>
    <h1>Bibliotekos ataskaita</h1>
    <p class="muted">{{ $report['scopeLabel'] }}</p>

    @foreach($sections as $section)
        <h2>{{ $section['title'] }}</h2>
        <table>
            <thead>
                <tr>
                    @foreach($section['headers'] as $header)
                        <th>{{ $header }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @forelse($section['rows'] as $row)
                    <tr>
                        @foreach($row as $cell)
                            <td>{{ $cell }}</td>
                        @endforeach
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ count($section['headers']) }}">Duomenu nera</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    @endforeach
</body>
</html>
