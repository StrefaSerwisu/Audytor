<x-auditor.layout :title="$audit->title . ' - Archiwum'">
    <div class="page-head">
        <div class="stack">
            <a class="button secondary" href="{{ route('archive.index') }}" style="width: max-content;">Wroc</a>
            <div class="stack">
                <h1>Archiwum audytu</h1>
                <div class="muted">{{ $audit->title }} / {{ $audit->client->name }} / {{ $audit->location->name }}</div>
            </div>
            <div class="meta">
                <span class="pill {{ $audit->status === 'closed' ? 'ok' : 'warn' }}">
                    {{ \App\Models\Audit::STATUSES[$audit->status] ?? $audit->status }}
                </span>
                @if ($audit->completed_at)
                    <span class="pill">Zamkniety {{ $audit->completed_at->format('Y-m-d H:i') }}</span>
                @endif
                <span class="pill">Lider: {{ $audit->leadReviewer?->name ?? 'Nieprzypisany' }}</span>
            </div>
        </div>
    </div>

    @if (session('status'))
        <div class="notice" style="margin-bottom: 16px;">{{ session('status') }}</div>
    @endif

    @php
        $closure = $audit->closures->sortByDesc('closed_at')->first();
        $publication = $audit->publications->sortByDesc('published_at')->first();
    @endphp

    <section class="card" style="margin-bottom: 18px;">
        <h2>Podsumowanie historyczne</h2>
        <div class="grid">
            <div>
                <strong>Klient</strong>
                <div class="muted">{{ $audit->client->name }}</div>
            </div>
            <div>
                <strong>Lokalizacja</strong>
                <div class="muted">{{ $audit->location->name }}</div>
            </div>
            <div>
                <strong>Szablon</strong>
                <div class="muted">{{ $audit->template->name }}</div>
            </div>
            <div>
                <strong>Zamknal</strong>
                <div class="muted">{{ $closure?->closer?->name ?? 'Global IT' }}</div>
            </div>
        </div>
        @if ($closure?->notes)
            <div>
                <strong>Notatka zamykajaca</strong>
                <p class="muted" style="margin-bottom: 0;">{{ $closure->notes }}</p>
            </div>
        @endif
    </section>

    <section class="card" style="margin-bottom: 18px;">
        <h2>Dokumenty</h2>
        <div class="meta">
            <a class="button" href="{{ route('reports.technical', $audit) }}">Raport techniczny</a>
            <a class="button" href="{{ route('reports.business', $audit) }}">Podsumowanie biznesowe</a>
            @if ($publication)
                <a class="button" href="{{ route('client.reports.show', $publication->token) }}">Link klienta</a>
            @endif
        </div>
    </section>

    <section class="card" style="margin-bottom: 18px;">
        <h2>Mapa ryzyka</h2>
        <div class="grid">
            @foreach ($riskLevels as $key => $label)
                <div>
                    <strong>{{ $label }}</strong>
                    <div class="muted">{{ $riskSummary[$key] ?? 0 }} odpowiedzi</div>
                </div>
            @endforeach
        </div>
    </section>

    @if ($audit->reviews->isNotEmpty())
        <section class="card">
            <h2>Historia decyzji</h2>
            <div class="stack">
                @foreach ($audit->reviews->sortByDesc('created_at') as $review)
                    <div>
                        <div class="meta">
                            <span class="pill {{ $review->decision === 'approved' ? 'ok' : 'warn' }}">
                                {{ \App\Models\AuditReview::DECISIONS[$review->decision] ?? $review->decision }}
                            </span>
                            <span class="pill">{{ $review->created_at->format('Y-m-d H:i') }}</span>
                            <span class="pill">{{ $review->reviewer?->name ?? 'Reviewer' }}</span>
                        </div>
                        @if ($review->notes)
                            <p class="muted" style="margin: 8px 0 0;">{{ $review->notes }}</p>
                        @endif
                    </div>
                @endforeach
            </div>
        </section>
    @endif
</x-auditor.layout>
