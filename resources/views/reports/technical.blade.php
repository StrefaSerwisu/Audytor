<!doctype html>
<html lang="pl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Raport techniczny - {{ $audit->title }}</title>
    <style>
        body { margin: 0; background: #f5f7fb; color: #172033; font-family: Arial, sans-serif; line-height: 1.45; }
        main { width: min(1040px, 100%); margin: 0 auto; padding: 28px 22px 56px; }
        header, section { margin-bottom: 18px; padding: 18px; background: #fff; border: 1px solid #dce3ee; border-radius: 8px; }
        h1, h2, h3 { margin: 0 0 10px; line-height: 1.15; }
        h1 { font-size: 32px; }
        h2 { font-size: 22px; }
        h3 { font-size: 18px; }
        table { width: 100%; border-collapse: collapse; margin-top: 12px; }
        th, td { padding: 9px; border: 1px solid #dce3ee; text-align: left; vertical-align: top; }
        th { background: #eef2f7; }
        .muted { color: #657085; }
        .meta { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 12px; }
        .pill { display: inline-flex; min-height: 28px; align-items: center; padding: 0 10px; border-radius: 999px; background: #eef2f7; font-size: 13px; }
        .ok { background: #dcfce7; color: #15803d; }
        .warn { background: #fef3c7; color: #b45309; }
        .question { margin-top: 12px; padding-top: 12px; border-top: 1px solid #dce3ee; }
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
        <a class="button" href="{{ route('reports.download.pdf', [$audit, 'technical']) }}">Pobierz PDF</a>
        <a class="button" href="{{ route('reports.download.docx', [$audit, 'technical']) }}">Pobierz DOCX</a>
        <form method="post" action="{{ route('reports.queue-export', [$audit, 'technical']) }}">
            @csrf
            <input type="hidden" name="format" value="pdf">
            <button type="submit">Kolejkuj PDF</button>
        </form>
        <a class="button" href="{{ route('reports.business', $audit) }}">Raport biznesowy</a>
    </div>

    <main>
        <header>
            <h1>Raport techniczny</h1>
            <p class="muted">{{ $audit->title }}</p>
            <div class="meta">
                <span class="pill">{{ $audit->client->name }}</span>
                <span class="pill">{{ $audit->location->name }}</span>
                <span class="pill">{{ $audit->template->name }}</span>
                <span class="pill ok">{{ \App\Models\Audit::STATUSES[$audit->status] ?? $audit->status }}</span>
            </div>
        </header>

        <section>
            <h2>Metryka audytu</h2>
            <table>
                <tr><th>Klient</th><td>{{ $audit->client->name }}</td></tr>
                <tr><th>Lokalizacja</th><td>{{ $audit->location->name }}</td></tr>
                <tr><th>Lider techniczny</th><td>{{ $audit->leadReviewer?->name ?? 'Nieprzypisany' }}</td></tr>
                <tr><th>Data wyslania</th><td>{{ $audit->submitted_at?->format('Y-m-d H:i') ?? '-' }}</td></tr>
                <tr><th>Data zatwierdzenia</th><td>{{ $audit->approved_at?->format('Y-m-d H:i') ?? '-' }}</td></tr>
            </table>
        </section>

        <section>
            <h2>Podsumowanie ryzyka</h2>
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

        @foreach ($audit->selectedModules as $selectedModule)
            @php
                $module = $selectedModule->module;
                $questions = $module?->questions?->where('active', true)->sortBy('sort_order') ?? collect();
            @endphp

            @continue(! $module)

            <section>
                <h2>{{ $module->name }}</h2>
                @if ($module->description)
                    <p class="muted">{{ $module->description }}</p>
                @endif

                @foreach ($questions as $question)
                    @php
                        $answer = $answersByQuestion->get($question->id);
                        $storedValue = $answer?->value_json['value'] ?? null;
                    @endphp

                    <div class="question">
                        <h3>{{ $question->question }}</h3>
                        @if ($question->instruction)
                            <p class="muted">{{ $question->instruction }}</p>
                        @endif
                        <table>
                            <tr>
                                <th>Odpowiedz</th>
                                <td>
                                    @if ($answer?->not_applicable)
                                        N/D: {{ $answer->not_applicable_reason ?: 'bez powodu' }}
                                    @else
                                        {{ $storedValue ?: 'Brak odpowiedzi' }}
                                    @endif
                                </td>
                            </tr>
                            <tr><th>Status</th><td>{{ $answer?->status ?? 'brak' }}</td></tr>
                            <tr><th>Ryzyko</th><td>{{ $answer?->risk_level ? ($riskLevels[$answer->risk_level] ?? $answer->risk_level) : '-' }}</td></tr>
                            <tr><th>Komentarz</th><td>{{ $answer?->comment ?: '-' }}</td></tr>
                            <tr><th>Rekomendacja audytora</th><td>{{ $answer?->recommendation_text ?: '-' }}</td></tr>
                            <tr>
                                <th>Zalaczniki</th>
                                <td>
                                    @forelse ($answer?->attachments ?? [] as $attachment)
                                        <div>{{ $attachment->original_name }} ({{ strtoupper($attachment->evidence_type) }})</div>
                                    @empty
                                        -
                                    @endforelse
                                </td>
                            </tr>
                        </table>
                    </div>
                @endforeach
            </section>
        @endforeach
    </main>
</body>
</html>
