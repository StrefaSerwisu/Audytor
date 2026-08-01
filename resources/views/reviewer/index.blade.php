<x-auditor.layout :title="'Weryfikacja techniczna - Audytor IT'">
    <div class="page-head">
        <div class="stack">
            <h1>Weryfikacja techniczna</h1>
            <p class="muted" style="margin: 0;">Audyty wyslane do przegladu lidera technicznego.</p>
        </div>
    </div>

    @if ($audits->isEmpty())
        <div class="card">
            <h2>Brak audytow do przegladu</h2>
            <p class="muted" style="margin: 0;">Nowe audyty pojawia sie tutaj po wyslaniu przez audytora.</p>
            <a class="button" href="{{ route('archive.index') }}" style="width: max-content;">Archiwum</a>
        </div>
    @else
        <div class="meta" style="margin-bottom: 14px;">
            <a class="button" href="{{ route('dashboard.index') }}">Dashboard KPI</a>
            <a class="button" href="{{ route('archive.index') }}">Archiwum</a>
        </div>
        <div class="grid">
            @foreach ($audits as $audit)
                <a class="card" href="{{ route('reviewer.audits.show', $audit) }}">
                    <div class="stack">
                        <h2>{{ $audit->title }}</h2>
                        <div class="muted">{{ $audit->client->name }} / {{ $audit->location->name }}</div>
                    </div>
                    <div class="meta">
                        <span class="pill {{ $audit->status === 'technically_approved' ? 'ok' : ($audit->status === 'changes_requested' ? 'warn' : '') }}">
                            {{ \App\Models\Audit::STATUSES[$audit->status] ?? $audit->status }}
                        </span>
                        <span class="pill">{{ $audit->answers->count() }} odpowiedzi</span>
                    </div>
                    <div class="muted">
                        Lider: {{ $audit->leadReviewer?->name ?? 'Nieprzypisany' }}
                    </div>
                </a>
            @endforeach
        </div>
    @endif
</x-auditor.layout>
