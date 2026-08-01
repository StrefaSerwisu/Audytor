<!doctype html>
<html lang="pl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Podsumowanie biznesowe - {{ $audit->title }}</title>
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
        .recommendation { margin-top: 12px; padding-top: 12px; border-top: 1px solid #dce3ee; }
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
        <a class="button" href="{{ route('reports.download.pdf', [$audit, 'business']) }}">Pobierz PDF</a>
        <a class="button" href="{{ route('reports.download.docx', [$audit, 'business']) }}">Pobierz DOCX</a>
        <form method="post" action="{{ route('reports.queue-export', [$audit, 'business']) }}">
            @csrf
            <input type="hidden" name="format" value="pdf">
            <button type="submit">Kolejkuj PDF</button>
        </form>
        <a class="button" href="{{ route('reports.technical', $audit) }}">Raport techniczny</a>
    </div>

    <main>
        <header>
            <h1>Podsumowanie biznesowe</h1>
            <p class="muted">{{ $audit->title }}</p>
            <div class="meta">
                <span class="pill">{{ $audit->client->name }}</span>
                <span class="pill">{{ $audit->location->name }}</span>
                <span class="pill ok">Zatwierdzony technicznie</span>
            </div>
        </header>

        @if (in_array($audit->status, ['technically_approved', 'reports_generated'], true))
            <section>
                <h2>Publikacja dla klienta</h2>
                <form method="post" action="{{ route('reports.publish', $audit) }}">
                    @csrf
                    <label>
                        Notatka dla klienta
                        <textarea name="notes" style="width: 100%; min-height: 90px;">{{ old('notes') }}</textarea>
                    </label>
                    <label>
                        Data wygasniecia linku
                        <input name="expires_at" type="date" value="{{ old('expires_at') }}" style="width: 100%; min-height: 38px;">
                    </label>
                    <p class="muted">Po publikacji status audytu zmieni sie na opublikowany dla klienta.</p>
                    <button type="submit">Opublikuj dla klienta</button>
                </form>
            </section>
        @elseif ($audit->status === 'published_to_client')
            <section>
                <h2>Zamkniecie audytu</h2>
                <form method="post" action="{{ route('reports.close', $audit) }}">
                    @csrf
                    <label>
                        Notatka zamykajaca
                        <textarea name="notes" style="width: 100%; min-height: 90px;">{{ old('notes') }}</textarea>
                    </label>
                    <p class="muted">Zamkniety audyt trafi do archiwum i pozostanie dostepny w raportach historycznych.</p>
                    <button type="submit">Zamknij audyt</button>
                </form>
            </section>
        @elseif ($audit->status === 'closed')
            @php
                $closure = $audit->closures->sortByDesc('closed_at')->first();
            @endphp
            <section>
                <h2>Audyt zamkniety</h2>
                <p class="muted" style="margin: 0;">
                    Zamknieto {{ $closure?->closed_at?->format('Y-m-d H:i') ?? $audit->completed_at?->format('Y-m-d H:i') ?? '-' }}
                    przez {{ $closure?->closer?->name ?? 'Global IT' }}.
                </p>
                @if ($closure?->notes)
                    <p>{{ $closure->notes }}</p>
                @endif
                <a class="button" href="{{ route('archive.show', $audit) }}">Otworz archiwum</a>
            </section>
        @endif

        <section>
            <h2>Wnioski zarzadcze</h2>
            <p>
                Audyt zostal zatwierdzony technicznie i jest gotowy do omowienia z klientem.
                Ponizsze zestawienie pokazuje ryzyka oraz rekomendowane dzialania w ujeciu biznesowym.
            </p>
            <table>
                <tr><th>Klient</th><td>{{ $audit->client->name }}</td></tr>
                <tr><th>Lokalizacja</th><td>{{ $audit->location->name }}</td></tr>
                <tr><th>Data zatwierdzenia</th><td>{{ $audit->approved_at?->format('Y-m-d H:i') ?? '-' }}</td></tr>
                <tr><th>Liczba rekomendacji</th><td>{{ $recommendations->count() }}</td></tr>
            </table>
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
            <h2>Rekomendowane dzialania</h2>
            @forelse ($recommendations as $recommendation)
                <div class="recommendation">
                    <div class="meta">
                        <span class="pill {{ in_array($recommendation['risk_level'], ['high', 'critical'], true) ? 'danger' : 'warn' }}">
                            {{ $recommendation['risk_level'] ? ($riskLevels[$recommendation['risk_level']] ?? $recommendation['risk_level']) : 'Bez ryzyka' }}
                        </span>
                        @if ($recommendation['sales_category'])
                            <span class="pill">{{ $recommendation['sales_category'] }}</span>
                        @endif
                    </div>
                    <h3>{{ $recommendation['title'] }}</h3>
                    <p class="muted">Zrodlo: {{ $recommendation['source'] }}</p>
                    @if ($recommendation['business_description'])
                        <p>{{ $recommendation['business_description'] }}</p>
                    @endif
                    <p>{{ $recommendation['recommendation_text'] }}</p>
                </div>
            @empty
                <p class="muted">Brak rekomendacji do pokazania w podsumowaniu biznesowym.</p>
            @endforelse
        </section>
    </main>
</body>
</html>
