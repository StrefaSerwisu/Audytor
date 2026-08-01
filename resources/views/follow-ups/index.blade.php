<x-auditor.layout :title="'Follow-up - Audytor IT'">
    <div class="page-head">
        <div class="stack">
            <h1>Plan wdrozen</h1>
            <p class="muted" style="margin: 0;">Zadania utworzone z rekomendacji zaakceptowanych przez klientow.</p>
        </div>
    </div>

    @if (session('status'))
        <div class="notice" style="margin-bottom: 16px;">{{ session('status') }}</div>
    @endif

    <section class="card" style="margin-bottom: 18px;">
        <form method="get" action="{{ route('follow-ups.index') }}" class="stack">
            <div class="grid">
                <label>
                    Szukaj
                    <input name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Tytul, opis, klient">
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
                    Priorytet
                    <select name="priority">
                        <option value="">Wszystkie</option>
                        @foreach ($priorities as $value => $label)
                            <option value="{{ $value }}" @selected(($filters['priority'] ?? '') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </label>
            </div>
            <div class="meta">
                <button type="submit">Filtruj</button>
                <a class="button" href="{{ route('follow-ups.export', request()->query()) }}">Eksport CSV</a>
                <a class="button secondary" href="{{ route('follow-ups.index') }}">Wyczysc</a>
            </div>
        </form>
    </section>

    <div class="stack">
        @forelse ($tasks as $task)
            <article class="card">
                <div class="meta">
                    <span class="pill {{ in_array($task->status, ['done'], true) ? 'ok' : 'warn' }}">
                        {{ $statuses[$task->status] ?? $task->status }}
                    </span>
                    @if ($task->priority)
                        <span class="pill">{{ $priorities[$task->priority] ?? $task->priority }}</span>
                    @endif
                    <span class="pill">{{ $task->audit->client->name }}</span>
                    <span class="pill">{{ $task->audit->location->name }}</span>
                    @if ($task->due_date)
                        <span class="pill">Termin {{ $task->due_date->format('Y-m-d') }}</span>
                    @endif
                </div>
                <div>
                    <h2>{{ $task->title }}</h2>
                    @if ($task->description)
                        <p class="muted">{{ $task->description }}</p>
                    @endif
                </div>
                <form method="post" action="{{ route('follow-ups.update', $task) }}" class="stack">
                    @csrf
                    <div class="grid">
                        <label>
                            Status
                            <select name="status">
                                @foreach ($statuses as $value => $label)
                                    <option value="{{ $value }}" @selected($task->status === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </label>
                        <label>
                            Priorytet
                            <select name="priority">
                                <option value="">Brak</option>
                                @foreach ($priorities as $value => $label)
                                    <option value="{{ $value }}" @selected($task->priority === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </label>
                        <label>
                            Wlasciciel
                            <select name="owner_id">
                                <option value="">Nieprzypisany</option>
                                @foreach ($owners as $owner)
                                    <option value="{{ $owner->id }}" @selected($task->owner_id === $owner->id)>{{ $owner->name }}</option>
                                @endforeach
                            </select>
                        </label>
                        <label>
                            Termin
                            <input name="due_date" type="date" value="{{ $task->due_date?->toDateString() }}">
                        </label>
                    </div>
                    <label>
                        Notatki
                        <textarea name="notes">{{ $task->notes }}</textarea>
                    </label>
                    <label class="inline-check">
                        <input name="client_visible" type="checkbox" value="1" @checked($task->client_visible)>
                        Widoczne dla klienta
                    </label>
                    <button type="submit" style="width: max-content;">Zapisz zadanie</button>
                </form>
            </article>
        @empty
            <div class="card">
                <h2>Brak zadan follow-up</h2>
                <p class="muted" style="margin: 0;">Zadania pojawia sie po zaakceptowaniu rekomendacji przez klienta.</p>
            </div>
        @endforelse
    </div>
</x-auditor.layout>
