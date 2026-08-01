<x-auditor.layout :title="'Archiwum audytow - Audytor IT'">
    <div class="page-head">
        <div class="stack">
            <a class="button secondary" href="{{ route('reviewer.index') }}" style="width: max-content;">Wroc</a>
            <div class="stack">
                <h1>Archiwum audytow</h1>
                <p class="muted" style="margin: 0;">Zamkniete i anulowane audyty historyczne Global IT.</p>
            </div>
        </div>
    </div>

    @if (session('status'))
        <div class="notice" style="margin-bottom: 16px;">{{ session('status') }}</div>
    @endif

    <section class="card" style="margin-bottom: 18px;">
        <form method="get" action="{{ route('archive.index') }}" class="stack">
            <div class="grid">
                <label>
                    Szukaj
                    <input name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Tytul, lokalizacja, lider">
                </label>
                <label>
                    Klient
                    <input name="client" value="{{ $filters['client'] ?? '' }}" placeholder="Nazwa klienta">
                </label>
                <label>
                    Status
                    <select name="status">
                        <option value="">Wszystkie historyczne</option>
                        @foreach ($statusOptions as $value => $label)
                            <option value="{{ $value }}" @selected(($filters['status'] ?? '') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </label>
                <label>
                    Zamkniete od
                    <input name="closed_from" type="date" value="{{ $filters['closed_from'] ?? '' }}">
                </label>
                <label>
                    Zamkniete do
                    <input name="closed_to" type="date" value="{{ $filters['closed_to'] ?? '' }}">
                </label>
            </div>
            <div class="meta">
                <button type="submit">Filtruj</button>
                <a class="button" href="{{ route('archive.export', request()->query()) }}">Eksport CSV</a>
                <a class="button secondary" href="{{ route('archive.index') }}">Wyczysc</a>
            </div>
        </form>
    </section>

    @if ($audits->isEmpty())
        <div class="card">
            <h2>Brak audytow historycznych</h2>
            <p class="muted" style="margin: 0;">Audyt pojawi sie tutaj po zamknieciu lub anulowaniu.</p>
        </div>
    @else
        <div class="grid">
            @foreach ($audits as $audit)
                @php
                    $closure = $audit->closures->sortByDesc('closed_at')->first();
                    $publication = $audit->publications->sortByDesc('published_at')->first();
                @endphp
                <a class="card" href="{{ route('archive.show', $audit) }}">
                    <div class="stack">
                        <h2>{{ $audit->title }}</h2>
                        <div class="muted">{{ $audit->client->name }} / {{ $audit->location->name }}</div>
                    </div>
                    <div class="meta">
                        <span class="pill {{ $audit->status === 'closed' ? 'ok' : 'warn' }}">
                            {{ \App\Models\Audit::STATUSES[$audit->status] ?? $audit->status }}
                        </span>
                        @if ($closure?->closed_at || $audit->completed_at)
                            <span class="pill">{{ ($closure?->closed_at ?? $audit->completed_at)->format('Y-m-d H:i') }}</span>
                        @endif
                        @if ($publication)
                            <span class="pill">Publikacja klienta</span>
                        @endif
                    </div>
                    <div class="muted">
                        Lider: {{ $audit->leadReviewer?->name ?? 'Nieprzypisany' }}
                    </div>
                </a>
            @endforeach
        </div>
    @endif
</x-auditor.layout>
