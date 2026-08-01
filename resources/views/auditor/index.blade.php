<x-auditor.layout :title="'Moje audyty - Audytor IT'">
    <div class="page-head">
        <div class="stack">
            <h1>Moje audyty</h1>
            <p class="muted" style="margin: 0;">Lista audytow przypisanych do Twojego konta.</p>
        </div>
    </div>

    @if ($audits->isEmpty())
        <div class="card">
            <h2>Brak przypisanych audytow</h2>
            <p class="muted" style="margin: 0;">Nowe audyty pojawia sie tutaj po przypisaniu przez zespol Global IT.</p>
        </div>
    @else
        <div class="grid">
            @foreach ($audits as $row)
                @php
                    $audit = $row['audit'];
                    $progress = $row['progress'];
                @endphp

                <a class="card" href="{{ route('auditor.audits.show', $audit) }}">
                    <div class="stack">
                        <h2>{{ $audit->title }}</h2>
                        <div class="muted">{{ $audit->client->name }} / {{ $audit->location->name }}</div>
                    </div>
                    <div class="meta">
                        <span class="pill">{{ \App\Models\Audit::STATUSES[$audit->status] ?? $audit->status }}</span>
                        <span class="pill {{ $progress['missing'] === 0 && $progress['total'] > 0 ? 'ok' : 'warn' }}">
                            {{ $progress['completed'] }}/{{ $progress['total'] }} odpowiedzi
                        </span>
                    </div>
                    <div class="progress" aria-label="Postep {{ $progress['percent'] }}%">
                        <span style="--progress: {{ $progress['percent'] }}%;"></span>
                    </div>
                    <div class="muted">
                        {{ $progress['missing'] }} do uzupelnienia
                    </div>
                </a>
            @endforeach
        </div>
    @endif
</x-auditor.layout>
