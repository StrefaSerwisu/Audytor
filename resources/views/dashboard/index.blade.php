<x-auditor.layout :title="'Dashboard KPI - Audytor IT'">
    <div class="page-head">
        <div class="stack">
            <h1>Dashboard KPI</h1>
            <p class="muted" style="margin: 0;">Operacyjny widok statusow, raportow i ryzyk audytowych.</p>
        </div>
        <div class="meta">
            <a class="button" href="{{ route('dashboard.export') }}">Eksport KPI CSV</a>
            <a class="button" href="{{ route('reviewer.index') }}">Weryfikacja</a>
            <a class="button" href="{{ route('archive.index') }}">Archiwum</a>
        </div>
    </div>

    <section class="grid" style="margin-bottom: 18px;">
        <div class="card">
            <strong>Wszystkie audyty</strong>
            <h2>{{ $kpis['all'] }}</h2>
            <div class="muted">Zakres widoczny dla Twojej roli</div>
        </div>
        <div class="card">
            <strong>Otwarte</strong>
            <h2>{{ $kpis['open'] }}</h2>
            <div class="muted">Praca audytora i przeglad</div>
        </div>
        <div class="card">
            <strong>Do weryfikacji</strong>
            <h2>{{ $kpis['review'] }}</h2>
            <div class="muted">Czekaja na lidera technicznego</div>
        </div>
        <div class="card">
            <strong>Raporty</strong>
            <h2>{{ $kpis['reports'] }}</h2>
            <div class="muted">Zatwierdzone, wygenerowane lub opublikowane</div>
        </div>
        <div class="card">
            <strong>Opublikowane</strong>
            <h2>{{ $kpis['published'] }}</h2>
            <div class="muted">Aktywne raporty klienta</div>
        </div>
        <div class="card">
            <strong>Historyczne</strong>
            <h2>{{ $kpis['closed'] }}</h2>
            <div class="muted">Zamkniete i anulowane</div>
        </div>
    </section>

    <div class="grid" style="margin-bottom: 18px;">
        <section class="card">
            <h2>Statusy</h2>
            <div class="stack">
                @foreach ($statusCounts as $status => $count)
                    @continue($count === 0)
                    <div class="meta" style="justify-content: space-between;">
                        <span>{{ \App\Models\Audit::STATUSES[$status] ?? $status }}</span>
                        <span class="pill">{{ $count }}</span>
                    </div>
                @endforeach
            </div>
        </section>

        <section class="card">
            <h2>Mapa ryzyka</h2>
            <div class="stack">
                @foreach ($riskSummary as $risk => $count)
                    <div class="meta" style="justify-content: space-between;">
                        <span>{{ \App\Models\AuditAnswer::RISK_LEVELS[$risk] ?? $risk }}</span>
                        <span class="pill {{ in_array($risk, ['high', 'critical'], true) ? 'warn' : 'ok' }}">{{ $count }}</span>
                    </div>
                @endforeach
            </div>
        </section>
    </div>

    <section class="card">
        <div class="page-head" style="margin-bottom: 0;">
            <div class="stack">
                <h2>Ostatnie audyty</h2>
                <p class="muted" style="margin: 0;">Najnowsze rekordy widoczne dla Twojej roli.</p>
            </div>
        </div>
        <div class="stack">
            @forelse ($audits as $audit)
                <a class="card" href="{{ in_array($audit->status, ['closed', 'cancelled'], true) ? route('archive.show', $audit) : route('reviewer.audits.show', $audit) }}" style="box-shadow: none;">
                    <div class="meta">
                        <span class="pill {{ in_array($audit->status, ['technically_approved', 'published_to_client', 'closed'], true) ? 'ok' : ($audit->status === 'changes_requested' ? 'warn' : '') }}">
                            {{ \App\Models\Audit::STATUSES[$audit->status] ?? $audit->status }}
                        </span>
                        <span class="pill">{{ $audit->client->name }}</span>
                        <span class="pill">{{ $audit->location->name }}</span>
                    </div>
                    <strong>{{ $audit->title }}</strong>
                    <div class="muted">Lider: {{ $audit->leadReviewer?->name ?? 'Nieprzypisany' }}</div>
                </a>
            @empty
                <p class="muted" style="margin: 0;">Brak audytow do pokazania.</p>
            @endforelse
        </div>
    </section>
</x-auditor.layout>
