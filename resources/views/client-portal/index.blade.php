<!doctype html>
<html lang="pl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#102337">
    <link rel="manifest" href="/manifest.webmanifest">
    <link rel="icon" href="/icons/audytor-it-icon.svg" type="image/svg+xml">
    <script src="/pwa.js" defer></script>
    <title>Raporty klienta - Audytor IT</title>
    <style>
        body { margin: 0; background: #f5f7fb; color: #172033; font-family: Arial, sans-serif; line-height: 1.5; }
        .topbar { display: flex; justify-content: space-between; align-items: center; gap: 16px; padding: 12px 24px; background: #102337; color: #fff; }
        main { width: min(1020px, 100%); margin: 0 auto; padding: 28px 22px 56px; }
        h1, h2 { margin: 0; }
        .muted { color: #657085; }
        .grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(270px, 1fr)); gap: 14px; }
        .card { display: grid; gap: 12px; padding: 16px; border: 1px solid #dce3ee; border-radius: 8px; background: #fff; color: inherit; text-decoration: none; box-shadow: 0 12px 26px rgb(15 23 42 / 6%); }
        .meta { display: flex; flex-wrap: wrap; gap: 8px; }
        .pill { display: inline-flex; min-height: 28px; align-items: center; padding: 0 10px; border-radius: 999px; background: #eef2f7; font-size: 13px; }
        .ok { background: #dcfce7; color: #15803d; }
        button { min-height: 40px; padding: 0 14px; border: 1px solid rgb(255 255 255 / 22%); border-radius: 6px; background: transparent; color: #fff; font: inherit; font-weight: 700; cursor: pointer; }
    </style>
</head>
<body>
    <header class="topbar">
        <strong>Audytor IT / Portal klienta</strong>
        <form method="post" action="{{ route('client.logout') }}">
            @csrf
            <button type="submit">Wyloguj</button>
        </form>
    </header>

    <main>
        <div style="margin-bottom: 20px;">
            <h1>Raporty audytowe</h1>
            <p class="muted" style="margin: 8px 0 0;">Lista aktywnych raportow opublikowanych dla Twojej organizacji.</p>
        </div>

        @if ($publications->isEmpty())
            <div class="card">
                <h2>Brak aktywnych raportow</h2>
                <p class="muted" style="margin: 0;">Nowe raporty pojawia sie tutaj po publikacji przez Global IT.</p>
            </div>
        @else
            <div class="grid">
                @foreach ($publications as $publication)
                    <a class="card" href="{{ route('client.portal.reports.show', $publication) }}">
                        <div>
                            <h2>{{ $publication->audit->title }}</h2>
                            <div class="muted">{{ $publication->audit->client->name }} / {{ $publication->audit->location->name }}</div>
                        </div>
                        <div class="meta">
                            <span class="pill ok">Opublikowany</span>
                            <span class="pill">{{ $publication->published_at?->format('Y-m-d H:i') ?? '-' }}</span>
                            @if ($publication->client_status)
                                <span class="pill">{{ \App\Models\AuditPublication::CLIENT_STATUSES[$publication->client_status] ?? $publication->client_status }}</span>
                            @endif
                        </div>
                    </a>
                @endforeach
            </div>
        @endif
    </main>
</body>
</html>
