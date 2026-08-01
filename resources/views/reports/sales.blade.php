<!doctype html>
<html lang="pl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Raport sprzedazowy - {{ $audit->title }}</title>
    <style>
        body { margin: 0; background: #f5f7fb; color: #172033; font-family: Arial, sans-serif; line-height: 1.48; }
        main { width: min(980px, 100%); margin: 0 auto; padding: 28px 22px 56px; }
        header, section { margin-bottom: 18px; padding: 20px; background: #fff; border: 1px solid #dce3ee; border-radius: 8px; }
        h1, h2, h3 { margin: 0 0 10px; line-height: 1.15; }
        h1 { font-size: 34px; }
        h2 { font-size: 22px; }
        h3 { font-size: 18px; }
        table { width: 100%; border-collapse: collapse; margin-top: 12px; }
        th, td { padding: 10px; border: 1px solid #dce3ee; text-align: left; vertical-align: top; }
        th { background: #eef2f7; }
        .muted { color: #657085; }
        .meta { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 12px; }
        .pill { display: inline-flex; min-height: 28px; align-items: center; padding: 0 10px; border-radius: 999px; background: #eef2f7; font-size: 13px; }
        .ok { background: #dcfce7; color: #15803d; }
        .warn { background: #fef3c7; color: #b45309; }
        .danger { background: #fee2e2; color: #b91c1c; }
        .opportunity { margin-top: 12px; padding-top: 12px; border-top: 1px solid #dce3ee; }
        .actions { position: sticky; top: 0; z-index: 5; display: flex; gap: 10px; padding: 12px 22px; background: #102337; }
        .button, button { min-height: 40px; padding: 0 14px; border: 0; border-radius: 6px; background: #0f766e; color: #fff; font: inherit; font-weight: 700; text-decoration: none; cursor: pointer; }
        @media print {
            body { background: #fff; }
            main { width: 100%; padding: 0; }
            header, section { break-inside: avoid; border-radius: 0; }
            .actions { display: none; }
        }
    </style>
</head>
<body>
    <div class="actions">
        <a class="button" href="{{ route('reviewer.audits.show', $audit) }}">Wroc</a>
        <button type="button" onclick="window.print()">Drukuj / PDF</button>
        <a class="button" href="{{ route('reports.download.pdf', [$audit, 'sales']) }}">Pobierz PDF</a>
        <a class="button" href="{{ route('reports.download.docx', [$audit, 'sales']) }}">Pobierz DOCX</a>
        <form method="post" action="{{ route('reports.queue-export', [$audit, 'sales']) }}">
            @csrf
            <input type="hidden" name="format" value="pdf">
            <button type="submit">Kolejkuj PDF</button>
        </form>
        <a class="button" href="{{ route('reports.business', $audit) }}">Raport biznesowy</a>
        <a class="button" href="{{ route('reports.technical', $audit) }}">Raport techniczny</a>
    </div>

    <main>
        <header>
            <h1>Raport sprzedazowy Global IT</h1>
            <p class="muted">{{ $audit->title }}</p>
            <div class="meta">
                <span class="pill">{{ $audit->client->name }}</span>
                <span class="pill">{{ $audit->location->name }}</span>
                <span class="pill ok">Tylko wewnetrznie</span>
            </div>
        </header>

        <section>
            <h2>Podsumowanie handlowe</h2>
            <table>
                <tr><th>Klient</th><td>{{ $audit->client->name }}</td></tr>
                <tr><th>Lokalizacja</th><td>{{ $audit->location->name }}</td></tr>
                <tr><th>Lider techniczny</th><td>{{ $audit->leadReviewer?->name ?? '-' }}</td></tr>
                <tr><th>Liczba okazji sprzedazowych</th><td>{{ $opportunities->count() }}</td></tr>
                <tr><th>Szacowana pracochlonnosc</th><td>{{ $hoursSummary['min'] }}-{{ $hoursSummary['max'] }} h</td></tr>
            </table>
        </section>

        <section>
            <h2>Kategorie sprzedazowe</h2>
            @if ($categories->isEmpty())
                <p class="muted" style="margin: 0;">Brak kategorii sprzedazowych dla tego audytu.</p>
            @else
                <table>
                    <tr><th>Kategoria</th><th>Liczba rekomendacji</th></tr>
                    @foreach ($categories as $category => $count)
                        <tr><td>{{ $category }}</td><td>{{ $count }}</td></tr>
                    @endforeach
                </table>
            @endif
        </section>

        <section>
            <h2>Mapa ryzyka</h2>
            <table>
                <tr>
                    @foreach ($riskLevels as $label)
                        <th>{{ $label }}</th>
                    @endforeach
                </tr>
                <tr>
                    @foreach (array_keys($riskLevels) as $riskKey)
                        <td>{{ $riskSummary[$riskKey] ?? 0 }}</td>
                    @endforeach
                </tr>
            </table>
        </section>

        <section>
            <h2>TOP rekomendacje sprzedazowe</h2>
            @forelse ($opportunities as $opportunity)
                <div class="opportunity">
                    <div class="meta">
                        <span class="pill {{ in_array($opportunity['priority'], ['high', 'critical'], true) ? 'danger' : 'warn' }}">
                            {{ $opportunity['priority'] ? ($priorityLabels[$opportunity['priority']] ?? $opportunity['priority']) : 'Bez priorytetu' }}
                        </span>
                        @if ($opportunity['sales_category'])
                            <span class="pill">{{ $opportunity['sales_category'] }}</span>
                        @endif
                        @if ($opportunity['estimated_hours_min'] || $opportunity['estimated_hours_max'])
                            <span class="pill">{{ $opportunity['estimated_hours_min'] ?? 0 }}-{{ $opportunity['estimated_hours_max'] ?? 0 }} h</span>
                        @endif
                    </div>
                    <h3>{{ $opportunity['title'] }}</h3>
                    <p class="muted">Zrodlo: {{ $opportunity['source'] }}</p>
                    <p>{{ $opportunity['recommendation_text'] }}</p>
                    <p><strong>Proponowany nastepny krok:</strong> kontakt handlowy z klientem i kwalifikacja zakresu.</p>
                    @if ($opportunity['suggested_deadline'])
                        <p class="muted">Sugerowany termin: {{ $opportunity['suggested_deadline'] }}</p>
                    @endif
                </div>
            @empty
                <p class="muted" style="margin: 0;">Brak rekomendacji oznaczonych jako mozliwe do realizacji przez Global IT.</p>
            @endforelse
        </section>
    </main>
</body>
</html>
