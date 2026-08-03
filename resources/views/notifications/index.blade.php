<x-auditor.layout :title="'Powiadomienia - Audytor IT'">
    <div class="page-head">
        <div class="stack">
            <h1>Powiadomienia</h1>
            <p class="muted" style="margin: 0;">Alerty workflow oraz przypomnienia o audytach wymagajacych reakcji.</p>
        </div>
        <form method="post" action="{{ route('notifications.read-all') }}">
            @csrf
            <button type="submit">Oznacz wszystkie jako przeczytane</button>
        </form>
    </div>

    @if (session('status'))
        <div class="notice" style="margin-bottom: 16px;">{{ session('status') }}</div>
    @endif

    @if ($reminders !== [])
        <section class="card" style="margin-bottom: 18px;">
            <h2>Przypomnienia</h2>
            <div class="stack">
                @foreach ($reminders as $reminder)
                    <a class="card" href="{{ $reminder['action_url'] }}" style="box-shadow: none;">
                        <strong>{{ $reminder['title'] }}</strong>
                        <div class="muted">{{ $reminder['body'] }}</div>
                    </a>
                @endforeach
            </div>
        </section>
    @endif

    <section class="card">
        <h2>Historia alertow</h2>
        <div class="stack">
            @forelse ($notifications as $notification)
                <article class="card" style="box-shadow: none;">
                    <div class="meta">
                        <span class="pill {{ $notification->read_at ? 'ok' : 'warn' }}">
                            {{ $notification->read_at ? 'Przeczytane' : 'Nowe' }}
                        </span>
                        <span class="pill">{{ $notification->created_at->format('Y-m-d H:i') }}</span>
                        @if ($notification->audit)
                            <span class="pill">{{ $notification->audit->client->name }}</span>
                        @elseif ($notification->auditOrder)
                            <span class="pill">{{ $notification->auditOrder->client->name }}</span>
                        @elseif ($notification->technicalAudit)
                            <span class="pill">{{ $notification->technicalAudit->client->name }}</span>
                        @endif
                    </div>
                    <div class="stack">
                        <strong>{{ $notification->title }}</strong>
                        @if ($notification->body)
                            <div class="muted">{{ $notification->body }}</div>
                        @endif
                    </div>
                    <div class="meta">
                        @if ($notification->action_url)
                            <a class="button" href="{{ $notification->action_url }}">Otworz</a>
                        @endif
                        @unless ($notification->read_at)
                            <form method="post" action="{{ route('notifications.read', $notification) }}">
                                @csrf
                                <button type="submit">Oznacz jako przeczytane</button>
                            </form>
                        @endunless
                    </div>
                </article>
            @empty
                <p class="muted" style="margin: 0;">Brak zapisanych powiadomien.</p>
            @endforelse
        </div>
    </section>
</x-auditor.layout>
