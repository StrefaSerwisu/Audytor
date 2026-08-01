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
        .topbar { display: flex; flex-wrap: wrap; justify-content: space-between; align-items: center; gap: 10px; padding: 12px 24px; background: #102337; color: #fff; }
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
        .button, button { min-height: 40px; padding: 0 14px; border: 0; border-radius: 6px; background: #0f766e; color: #fff; font: inherit; font-weight: 700; text-decoration: none; cursor: pointer; }
        select { min-height: 40px; padding: 0 10px; border: 1px solid #cbd5e1; border-radius: 6px; font: inherit; }
        textarea { width: 100%; min-height: 110px; padding: 10px; border: 1px solid #cbd5e1; border-radius: 6px; font: inherit; resize: vertical; }
        label { display: grid; gap: 6px; }
        .recommendation { display: grid; gap: 8px; padding: 12px; border: 1px solid #dce3ee; border-radius: 8px; background: #f8fafc; }
        .check-row { display: grid; grid-template-columns: auto 1fr; gap: 10px; align-items: flex-start; }
        .check-row input { margin-top: 4px; }
        .notice { margin-bottom: 16px; padding: 12px 14px; border: 1px solid #bbf7d0; border-radius: 8px; background: #f0fdf4; color: #166534; }
        @media print {
            body { background: #fff; }
            main { width: 100%; padding: 0; }
            header, section { border-radius: 0; }
            .topbar, .status-form { display: none; }
        }
    </style>
</head>
<body>
    <div class="topbar">
        <a class="button" href="{{ route('client.portal.index') }}">Wroc do listy</a>
        <button type="button" onclick="window.print()">Drukuj / PDF</button>
    </div>

    <main>
        @if (session('status'))
            <div class="notice">{{ session('status') }}</div>
        @endif

        <header>
            <h1>Raport audytu IT</h1>
            <p class="muted">{{ $audit->title }}</p>
            <div class="meta">
                <span class="pill">{{ $audit->client->name }}</span>
                <span class="pill">{{ $audit->location->name }}</span>
                <span class="pill ok">Opublikowany</span>
                @if ($publication->client_status)
                    <span class="pill">{{ $clientStatuses[$publication->client_status] ?? $publication->client_status }}</span>
                @endif
            </div>
        </header>

        <section class="status-form">
            <h2>Status po stronie klienta</h2>
            <form method="post" action="{{ route('client.portal.reports.status', $publication) }}" class="meta">
                @csrf
                <select name="client_status" required>
                    @foreach ($clientStatuses as $value => $label)
                        <option value="{{ $value }}" @selected($publication->client_status === $value)>{{ $label }}</option>
                    @endforeach
                </select>
                <button type="submit">Zapisz status</button>
            </form>
            @if ($publication->client_status_updated_at)
                <p class="muted">Ostatnia aktualizacja: {{ $publication->client_status_updated_at->format('Y-m-d H:i') }}</p>
            @endif
        </section>

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
            <h2>Plan dzialan poaudytowych</h2>
            @if ($followUpTasks->isEmpty())
                <p class="muted" style="margin: 0;">Plan dzialan pojawi sie po zapisaniu rekomendacji do wdrozenia przez klienta i opracowaniu ich przez Global IT.</p>
            @else
                <table>
                    <tr>
                        <th>Zadanie</th>
                        <th>Status</th>
                        <th>Termin</th>
                        <th>Wlasciciel</th>
                    </tr>
                    @foreach ($followUpTasks as $task)
                        <tr>
                            <td>
                                <strong>{{ $task->title }}</strong>
                                @if ($task->description)
                                    <div class="muted">{{ $task->description }}</div>
                                @endif
                            </td>
                            <td>{{ \App\Models\AuditFollowUpTask::STATUSES[$task->status] ?? $task->status }}</td>
                            <td>{{ $task->due_date?->format('Y-m-d') ?? '-' }}</td>
                            <td>{{ $task->owner?->name ?? 'Global IT' }}</td>
                        </tr>
                    @endforeach
                </table>
            @endif
        </section>

        <section class="status-form">
            <h2>Komentarz i rekomendacje do wdrozenia</h2>
            <form method="post" action="{{ route('client.portal.reports.feedback', $publication) }}">
                @csrf
                <label>
                    Komentarz dla Global IT
                    <textarea name="client_comment">{{ old('client_comment', $publication->client_comment) }}</textarea>
                </label>

                <div style="display: grid; gap: 10px; margin-top: 14px;">
                    @forelse ($recommendations as $recommendation)
                        <label class="recommendation">
                            <span class="check-row">
                                <input
                                    name="accepted_recommendations[]"
                                    type="checkbox"
                                    value="{{ $recommendation['key'] }}"
                                    @checked($acceptedRecommendationKeys->contains($recommendation['key']))
                                >
                                <span>
                                    <strong>{{ $recommendation['title'] }}</strong>
                                    <span class="muted" style="display: block;">Zrodlo: {{ $recommendation['source'] }}</span>
                                </span>
                            </span>
                            <span>{{ $recommendation['text'] }}</span>
                        </label>
                    @empty
                        <p class="muted" style="margin: 0;">Brak rekomendacji do wyboru.</p>
                    @endforelse
                </div>

                <div class="meta">
                    <button type="submit">Zapisz komentarz</button>
                    @if ($publication->client_feedback_at)
                        <span class="pill">Zapisano {{ $publication->client_feedback_at->format('Y-m-d H:i') }}</span>
                    @endif
                </div>
            </form>
        </section>
    </main>
</body>
</html>
