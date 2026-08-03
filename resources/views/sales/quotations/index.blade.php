<x-auditor.layout :title="'Wyceny audytow - Audytor IT'">
    <div class="page-head">
        <div class="stack">
            <h1>Wyceny audytow</h1>
            <p class="muted" style="margin:0;">Kalkulacje utworzone na podstawie zakonczonych kwalifikacji Sales.</p>
        </div>
    </div>

    @if(session('status'))<div class="notice" style="margin-bottom:16px;">{{ session('status') }}</div>@endif
    <section class="card" style="margin-bottom:18px;">
        <form method="get" class="stack">
            <div class="grid">
                <label>Klient<select name="client_id"><option value="">Wszyscy</option>@foreach($clients as $client)<option value="{{ $client->id }}" @selected(($filters['client_id'] ?? null) == $client->id)>{{ $client->name }}</option>@endforeach</select></label>
                <label>Typ audytu<select name="audit_type_id"><option value="">Wszystkie</option>@foreach($auditTypes as $type)<option value="{{ $type->id }}" @selected(($filters['audit_type_id'] ?? null) == $type->id)>{{ $type->name }}</option>@endforeach</select></label>
                <label>Status<select name="status"><option value="">Wszystkie</option>@foreach($statuses as $value => $label)<option value="{{ $value }}" @selected(($filters['status'] ?? '') === $value)>{{ $label }}</option>@endforeach</select></label>
                <label>Sales<select name="sales_owner_id"><option value="">Wszyscy</option>@foreach($salesOwners as $owner)<option value="{{ $owner->id }}" @selected(($filters['sales_owner_id'] ?? null) == $owner->id)>{{ $owner->name }}</option>@endforeach</select></label>
                <label>Data od<input type="date" name="date_from" value="{{ $filters['date_from'] ?? '' }}"></label>
                <label>Data do<input type="date" name="date_to" value="{{ $filters['date_to'] ?? '' }}"></label>
                <label>Cena netto od<input type="number" step="0.01" min="0" name="price_from" value="{{ $filters['price_from'] ?? '' }}"></label>
                <label>Cena netto do<input type="number" step="0.01" min="0" name="price_to" value="{{ $filters['price_to'] ?? '' }}"></label>
            </div>
            <div class="meta"><button type="submit">Filtruj</button><a class="button secondary" href="{{ route('sales.quotations.index') }}">Wyczysc</a></div>
        </form>
    </section>

    <div class="stack">
        @forelse($quotations as $quotation)
            <a class="card" href="{{ route('sales.quotations.show', $quotation) }}">
                <div class="page-head" style="margin-bottom:0;">
                    <div class="stack">
                        <h2>{{ $quotation->number }}</h2>
                        <div class="muted">{{ $quotation->client->name }} / {{ $quotation->auditType->name }} / v{{ $quotation->versionDefinition->version }}</div>
                    </div>
                    <span class="pill">{{ $quotation->status->label() }}</span>
                </div>
                <div class="meta">
                    <span class="pill">Sales: {{ $quotation->salesOwner->name }}</span>
                    <span class="pill">Wersja wyceny {{ $quotation->version }}{{ $quotation->is_current ? ' / aktualna' : '' }}</span>
                    <span class="pill">{{ $quotation->total_hours }} h</span>
                    <span class="pill ok">{{ str_replace('.', ',', $quotation->net_price) }} {{ $quotation->currency }} netto</span>
                    <span class="pill">Wazna do {{ $quotation->valid_until?->format('Y-m-d') ?? '-' }}</span>
                </div>
            </a>
        @empty
            <div class="card"><h2>Brak wycen</h2></div>
        @endforelse
    </div>
</x-auditor.layout>
