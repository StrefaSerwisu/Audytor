<x-auditor.layout :title="'Eksporty raportow - Audytor IT'">
    <div class="page-head">
        <div class="stack">
            <h1>Eksporty raportow</h1>
            <p class="muted" style="margin: 0;">Historia plikow PDF/DOCX generowanych z raportow audytowych.</p>
        </div>
    </div>

    @if (session('status'))
        <div class="notice" style="margin-bottom: 16px;">{{ session('status') }}</div>
    @endif

    <section class="grid" style="margin-bottom: 18px;">
        @foreach ($statuses as $status => $label)
            <div class="card">
                <strong>{{ $label }}</strong>
                <h2>{{ $statusCounts[$status] ?? 0 }}</h2>
                <div class="muted">Eksporty w tym statusie</div>
            </div>
        @endforeach
    </section>

    <section class="card" style="margin-bottom: 18px;">
        <form method="get" action="{{ route('reports.exports.index') }}" class="stack">
            <div class="grid">
                <label>
                    Szukaj
                    <input name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Audyt, klient, lokalizacja">
                </label>
                <label>
                    Status
                    <select name="status">
                        <option value="">Wszystkie</option>
                        @foreach ($statuses as $value => $label)
                            <option value="{{ $value }}" @selected(($filters['status'] ?? '') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </label>
                <label>
                    Typ raportu
                    <select name="type">
                        <option value="">Wszystkie</option>
                        @foreach ($reportTypes as $value => $label)
                            <option value="{{ $value }}" @selected(($filters['type'] ?? '') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </label>
                <label>
                    Format
                    <select name="format">
                        <option value="">Wszystkie</option>
                        @foreach ($formats as $value => $label)
                            <option value="{{ $value }}" @selected(($filters['format'] ?? '') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </label>
            </div>
            <div class="meta">
                <button type="submit">Filtruj</button>
                <a class="button secondary" href="{{ route('reports.exports.index') }}">Wyczysc</a>
            </div>
        </form>
    </section>

    <div class="stack">
        @forelse ($exports as $export)
            <article class="card">
                <div class="meta">
                    <span class="pill {{ $export->status === 'completed' ? 'ok' : (in_array($export->status, ['failed', 'processing'], true) ? 'warn' : '') }}">
                        {{ $statuses[$export->status] ?? $export->status }}
                    </span>
                    <span class="pill">{{ $reportTypes[$export->report_type] ?? $export->report_type }}</span>
                    <span class="pill">{{ $formats[$export->format] ?? strtoupper($export->format) }}</span>
                    <span class="pill">{{ $export->audit->client->name }}</span>
                    <span class="pill">{{ $export->audit->location->name }}</span>
                </div>

                <div>
                    <h2>{{ $export->audit->title }}</h2>
                    <p class="muted" style="margin-bottom: 0;">
                        Zlecil: {{ $export->queuedBy?->name ?? 'System' }}
                        / Utworzono: {{ $export->created_at->format('Y-m-d H:i') }}
                        @if ($export->completed_at)
                            / Gotowy: {{ $export->completed_at->format('Y-m-d H:i') }}
                        @endif
                    </p>
                </div>

                @if ($export->error)
                    <div class="notice" style="background: #fff7ed; border-color: #fed7aa; color: #9a3412;">
                        {{ $export->error }}
                    </div>
                @endif

                <div class="meta">
                    @if ($export->status === 'completed')
                        <a class="button" href="{{ route('reports.exports.download', $export) }}">Pobierz plik</a>
                    @endif
                    @if ($export->status === 'failed')
                        <form method="post" action="{{ route('reports.exports.retry', $export) }}">
                            @csrf
                            <button type="submit">Ponow eksport</button>
                        </form>
                    @endif
                    <a class="button secondary" href="{{ route('reports.'.$export->report_type, $export->audit) }}">Otworz raport</a>
                </div>
            </article>
        @empty
            <div class="card">
                <h2>Brak eksportow</h2>
                <p class="muted" style="margin: 0;">Eksporty pojawia sie po uzyciu akcji Kolejkuj PDF w raporcie.</p>
            </div>
        @endforelse
    </div>
</x-auditor.layout>
