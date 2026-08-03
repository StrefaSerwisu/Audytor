<x-auditor.layout :title="'Kwalifikacje Sales - Audytor IT'">
    <div class="page-head">
        <div class="stack">
            <h1>Kwalifikacje Sales</h1>
            <p class="muted" style="margin: 0;">Rozmowy kwalifikacyjne powiazane z wersjonowanymi typami audytow.</p>
        </div>
        @if ($canCreate)
            <a class="button" href="{{ route('sales.qualifications.create') }}">Nowa kwalifikacja</a>
        @endif
    </div>

    @if (session('status'))
        <div class="notice" style="margin-bottom: 16px;">{{ session('status') }}</div>
    @endif

    <section class="card" style="margin-bottom: 18px;">
        <form method="get" class="stack">
            <div class="grid">
                <label>Klient
                    <select name="client_id"><option value="">Wszyscy</option>@foreach($clients as $client)<option value="{{ $client->id }}" @selected(($filters['client_id'] ?? null) == $client->id)>{{ $client->name }}</option>@endforeach</select>
                </label>
                <label>Typ audytu
                    <select name="audit_type_id"><option value="">Wszystkie</option>@foreach($auditTypes as $type)<option value="{{ $type->id }}" @selected(($filters['audit_type_id'] ?? null) == $type->id)>{{ $type->name }}</option>@endforeach</select>
                </label>
                <label>Status
                    <select name="status"><option value="">Wszystkie</option>@foreach($statuses as $value => $label)<option value="{{ $value }}" @selected(($filters['status'] ?? '') === $value)>{{ $label }}</option>@endforeach</select>
                </label>
                <label>Sales
                    <select name="sales_owner_id"><option value="">Wszyscy</option>@foreach($salesOwners as $owner)<option value="{{ $owner->id }}" @selected(($filters['sales_owner_id'] ?? null) == $owner->id)>{{ $owner->name }}</option>@endforeach</select>
                </label>
                <label>Data od<input type="date" name="date_from" value="{{ $filters['date_from'] ?? '' }}"></label>
                <label>Data do<input type="date" name="date_to" value="{{ $filters['date_to'] ?? '' }}"></label>
            </div>
            <div class="meta"><button type="submit">Filtruj</button><a class="button secondary" href="{{ route('sales.qualifications.index') }}">Wyczysc</a></div>
        </form>
    </section>

    <div class="stack">
        @forelse($qualifications as $row)
            @php($qualification = $row['qualification'])
            <a class="card" href="{{ route('sales.qualifications.show', $qualification) }}">
                <div class="page-head" style="margin-bottom: 0;">
                    <div class="stack">
                        <h2>#{{ $qualification->id }} {{ $qualification->title }}</h2>
                        <div class="muted">{{ $qualification->client->name }} / {{ $qualification->auditType->name }} / v{{ $qualification->version->version }}</div>
                    </div>
                    <span class="pill">{{ $qualification->status->label() }}</span>
                </div>
                <div class="meta">
                    <span class="pill">Sales: {{ $qualification->salesOwner->name }}</span>
                    <span class="pill">Utworzono {{ $qualification->created_at->format('Y-m-d') }}</span>
                    @if($qualification->expected_date)<span class="pill">Termin {{ $qualification->expected_date->format('Y-m-d') }}</span>@endif
                    @if($qualification->completed_at)<span class="pill ok">Ukonczono {{ $qualification->completed_at->format('Y-m-d') }}</span>@endif
                </div>
                <div class="progress"><span style="--progress: {{ $row['progress']['percent'] }}%;"></span></div>
                <div class="muted">{{ $row['progress']['completed'] }}/{{ $row['progress']['required'] }} wymaganych odpowiedzi, {{ $row['progress']['percent'] }}%</div>
            </a>
        @empty
            <div class="card"><h2>Brak kwalifikacji</h2></div>
        @endforelse
    </div>
</x-auditor.layout>
