<!doctype html>
<html lang="pl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#102337">
    <link rel="manifest" href="/manifest.webmanifest">
    <link rel="icon" href="/icons/audytor-it-icon.svg" type="image/svg+xml">
    <script src="/pwa.js" defer></script>
    <title>Raport audytu - {{ $audit->title }}</title>
    <style>
        body { margin: 0; background: #f5f7fb; color: #172033; font-family: Arial, sans-serif; line-height: 1.5; }
        main { width: min(920px, 100%); margin: 0 auto; padding: 28px 22px 56px; }
        header, section { margin-bottom: 18px; padding: 20px; background: #fff; border: 1px solid #dce3ee; border-radius: 8px; }
        h1, h2 { margin: 0 0 10px; line-height: 1.15; }
        h1 { font-size: 34px; }
        h2 { font-size: 22px; }
        table { width: 100%; border-collapse: collapse; margin-top: 12px; }
        th, td { padding: 10px; border: 1px solid #dce3ee; text-align: left; }
        th { background: #eef2f7; }
        .muted { color: #657085; }
        .meta { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 12px; }
        .pill { display: inline-flex; min-height: 28px; align-items: center; padding: 0 10px; border-radius: 999px; background: #eef2f7; font-size: 13px; }
        .ok { background: #dcfce7; color: #15803d; }
        .actions { position: sticky; top: 0; z-index: 5; display: flex; gap: 10px; padding: 12px 22px; background: #102337; }
        .button, button { min-height: 40px; padding: 0 14px; border: 0; border-radius: 6px; background: #0f766e; color: #fff; font: inherit; font-weight: 700; text-decoration: none; cursor: pointer; }
        @media print {
            body { background: #fff; }
            main { width: 100%; padding: 0; }
            header, section { border-radius: 0; }
            .actions { display: none; }
        }
    </style>
</head>
<body>
    <div class="actions">
        <button type="button" onclick="window.print()">Drukuj / PDF</button>
    </div>

    <main>
        <header>
            <h1>Raport audytu IT</h1>
            <p class="muted">{{ $audit->title }}</p>
            <div class="meta">
                <span class="pill">{{ $audit->client->name }}</span>
                <span class="pill">{{ $audit->location->name }}</span>
                <span class="pill ok">Opublikowany</span>
            </div>
        </header>

        <section>
            <h2>Informacje o raporcie</h2>
            <table>
                <tr><th>Klient</th><td>{{ $audit->client->name }}</td></tr>
                <tr><th>Lokalizacja</th><td>{{ $audit->location->name }}</td></tr>
                <tr><th>Szablon audytu</th><td>{{ $audit->template->name }}</td></tr>
                <tr><th>Lider techniczny</th><td>{{ $audit->leadReviewer?->name ?? '-' }}</td></tr>
                <tr><th>Data publikacji</th><td>{{ $publication->published_at?->format('Y-m-d H:i') ?? '-' }}</td></tr>
                <tr><th>Wazny do</th><td>{{ $publication->expires_at?->format('Y-m-d H:i') ?? 'bezterminowo' }}</td></tr>
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

        @if ($publication->notes)
            <section>
                <h2>Informacja od Global IT</h2>
                <p>{{ $publication->notes }}</p>
            </section>
        @endif

        <section>
            <h2>Dalsze kroki</h2>
            <p>
                Raport zostal opublikowany przez Global IT. W celu omowienia rekomendacji,
                zakresu prac lub priorytetow skontaktuj sie z opiekunem Global IT.
            </p>
        </section>
    </main>
</body>
</html>
