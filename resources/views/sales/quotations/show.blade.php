<x-auditor.layout :title="$quotation->number . ' - Audytor IT'">
    <div class="page-head">
        <div class="stack">
            <a class="button secondary" href="{{ route('sales.quotations.index') }}" style="width:max-content;">Wroc</a>
            <h1>{{ $quotation->number }}</h1>
            <div class="muted">{{ $quotation->client->name }} / {{ $quotation->auditType->name }} / v{{ $quotation->versionDefinition->version }}</div>
            <div class="meta">
                <span class="pill">{{ $quotation->status->label() }}</span>
                <span class="pill">Wersja wyceny {{ $quotation->version }}{{ $quotation->is_current ? ' / aktualna' : '' }}</span>
                <span class="pill">Sales: {{ $quotation->salesOwner->name }}</span>
                <span class="pill">Wazna do {{ $quotation->valid_until?->format('Y-m-d') ?? '-' }}</span>
            </div>
        </div>
        <div class="stack" style="text-align:right;">
            <strong style="font-size:28px;">{{ str_replace('.', ',', $quotation->net_price) }} {{ $quotation->currency }}</strong>
            <span class="muted">netto / {{ str_replace('.', ',', $quotation->gross_price) }} {{ $quotation->currency }} brutto</span>
        </div>
    </div>

    @if(session('status'))<div class="notice" style="margin-bottom:16px;">{{ session('status') }}</div>@endif
    @if($errors->any())<div class="error" style="margin-bottom:16px;">{{ $errors->first() }}</div>@endif

    @if($quotation->auditOrder)
        <div class="notice" style="margin-bottom:16px;">Utworzono zlecenie <a href="{{ route('delivery.audit-orders.show', $quotation->auditOrder) }}"><strong>{{ $quotation->auditOrder->number }}</strong></a>.</div>
    @elseif(auth()->user()->can('createAuditOrder', $quotation))
        <form method="post" action="{{ route('sales.quotations.audit-order.store', $quotation) }}" style="margin-bottom:16px;">@csrf<button type="submit">Utworz zlecenie audytu</button></form>
    @endif

    <section class="card" style="margin-bottom:18px;">
        <div class="grid">
            <div><strong>Kwalifikacja</strong><div><a href="{{ route('sales.qualifications.show', $quotation->qualification) }}">#{{ $quotation->qualification->id }} {{ $quotation->qualification->title }}</a></div></div>
            <div><strong>Zakres</strong><div class="muted">{{ $quotation->qualification->scope_summary ?: 'Brak podsumowania' }}</div></div>
            <div><strong>Liczba inzynierow</strong><div>{{ $quotation->engineers_count }}</div></div>
            <div><strong>Stawka godzinowa</strong><div>{{ str_replace('.', ',', $quotation->hourly_rate) }} {{ $quotation->currency }}</div></div>
        </div>
        @if($quotation->assumptions)<div><strong>Zalozenia</strong><div class="muted">{{ $quotation->assumptions }}</div></div>@endif
        @if($quotation->exclusions)<div><strong>Wylaczenia</strong><div class="muted">{{ $quotation->exclusions }}</div></div>@endif
    </section>

    <section class="card" style="margin-bottom:18px; overflow-x:auto;">
        <h2>Pozycje kalkulacji</h2>
        <table style="width:100%; border-collapse:collapse; min-width:760px;">
            <thead><tr><th style="text-align:left;padding:10px;border-bottom:1px solid var(--line);">Pozycja</th><th>Kategoria</th><th>Ilosc</th><th>Godz./jedn.</th><th>Godziny</th><th>Cena</th></tr></thead>
            <tbody>
                @foreach($quotation->lines as $line)
                    <tr>
                        <td style="padding:10px;border-bottom:1px solid var(--line);"><strong>{{ $line->name }}</strong><div class="muted">{{ $line->code }}</div></td>
                        <td style="text-align:center;">{{ \App\Models\PricingRule::CATEGORIES[$line->category] ?? $line->category }}</td>
                        <td style="text-align:center;">{{ $line->quantity }} {{ $line->unit }}</td>
                        <td style="text-align:center;">{{ $line->unit_hours }}</td>
                        <td style="text-align:center;">{{ $line->total_hours }}</td>
                        <td style="text-align:right;">{{ str_replace('.', ',', $line->total_price) }} {{ $quotation->currency }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </section>

    <section class="card" style="margin-bottom:18px;">
        <h2>Podsumowanie finansowe</h2>
        <div class="grid">
            <div><span class="muted">Godziny bazowe</span><strong style="display:block;">{{ $quotation->base_hours }} h</strong></div>
            <div><span class="muted">Godziny dodatkowe</span><strong style="display:block;">{{ $quotation->additional_hours }} h</strong></div>
            <div><span class="muted">Godziny razem</span><strong style="display:block;">{{ $quotation->total_hours }} h</strong></div>
            <div><span class="muted">Cena bazowa</span><strong style="display:block;">{{ str_replace('.', ',', $quotation->base_price) }} {{ $quotation->currency }}</strong></div>
            <div><span class="muted">Koszty dodatkowe</span><strong style="display:block;">{{ str_replace('.', ',', $quotation->additional_costs) }} {{ $quotation->currency }}</strong></div>
            <div><span class="muted">Rabat</span><strong style="display:block;">-{{ str_replace('.', ',', $quotation->discount_amount) }} {{ $quotation->currency }}</strong></div>
            <div><span class="muted">Netto</span><strong style="display:block;">{{ str_replace('.', ',', $quotation->net_price) }} {{ $quotation->currency }}</strong></div>
            <div><span class="muted">VAT {{ $quotation->tax_rate }}%</span><strong style="display:block;">{{ str_replace('.', ',', $quotation->tax_amount) }} {{ $quotation->currency }}</strong></div>
            <div><span class="muted">Brutto</span><strong style="display:block;">{{ str_replace('.', ',', $quotation->gross_price) }} {{ $quotation->currency }}</strong></div>
        </div>
    </section>

    @if($canOverride)
        <section class="card" style="margin-bottom:18px;">
            <h2>Kontrolowana korekta</h2>
            <form method="post" action="{{ route('sales.quotations.override', $quotation) }}" class="stack">
                @csrf @method('patch')
                <div class="grid">
                    <label>Stawka godzinowa<input type="number" step="0.01" min="0" name="hourly_rate" placeholder="{{ $quotation->hourly_rate }}"></label>
                    <label>Liczba inzynierow<input type="number" min="1" name="engineers_count" placeholder="{{ $quotation->engineers_count }}"></label>
                    <label>Dodatkowe godziny<input type="number" step="0.01" min="0" name="additional_hours" placeholder="{{ $quotation->additional_hours }}"></label>
                    <label>Koszty dodatkowe<input type="number" step="0.01" min="0" name="additional_costs" placeholder="{{ $quotation->additional_costs }}"></label>
                    <label>Typ rabatu<select name="discount_type"><option value="">Bez zmiany</option><option value="percent">Procentowy</option><option value="fixed">Kwotowy</option></select></label>
                    <label>Wartosc rabatu<input type="number" step="0.01" min="0" name="discount_value" placeholder="{{ $quotation->discount_value }}"></label>
                    <label>Wazna do<input type="date" name="valid_until"></label>
                </div>
                <label>Zalozenia<textarea name="assumptions" placeholder="Bez zmiany, jesli puste"></textarea></label>
                <label>Wylaczenia<textarea name="exclusions" placeholder="Bez zmiany, jesli puste"></textarea></label>
                <label>Powod korekty<textarea name="reason" required maxlength="2000"></textarea></label>
                <button type="submit" style="width:max-content;">Zapisz korekte i przelicz</button>
            </form>
        </section>
    @endif

    <section class="card" style="margin-bottom:18px;">
        <h2>Proces zatwierdzania</h2>
        <div class="meta">
            @if($quotation->status === \App\Enums\QuotationStatus::Calculated && auth()->user()->can('sendForReview', $quotation))
                <form method="post" action="{{ route('sales.quotations.review', $quotation) }}">@csrf<button type="submit">Przekaz do weryfikacji</button></form>
            @endif
            @if($quotation->status === \App\Enums\QuotationStatus::InternalReview && auth()->user()->can('approveInternally', $quotation))
                <form method="post" action="{{ route('sales.quotations.approve', $quotation) }}">@csrf<button type="submit">Zatwierdz wewnetrznie</button></form>
            @endif
            @if($quotation->status === \App\Enums\QuotationStatus::InternallyApproved && auth()->user()->can('sendToClient', $quotation))
                <form method="post" action="{{ route('sales.quotations.send', $quotation) }}">@csrf<button type="submit">Oznacz jako wyslana</button></form>
            @endif
        </div>

        @if($quotation->status === \App\Enums\QuotationStatus::InternalReview && auth()->user()->can('returnForChanges', $quotation))
            <form method="post" action="{{ route('sales.quotations.return', $quotation) }}" class="grid">@csrf<label>Komentarz do poprawy<input name="comment" required></label><button type="submit" style="align-self:end;">Cofnij do poprawy</button></form>
        @endif
        @if($quotation->status === \App\Enums\QuotationStatus::SentToClient && auth()->user()->can('recordClientDecision', $quotation))
            <form method="post" action="{{ route('sales.quotations.accept', $quotation) }}" class="grid">@csrf<label>Zaakceptowal<input name="accepted_by" required></label><label>Data akceptacji<input type="date" name="accepted_at"></label><label>Numer zamowienia<input name="purchase_order_number"></label><label>Komentarz<input name="comment"></label><button type="submit" style="align-self:end;">Zapisz akceptacje</button></form>
            <form method="post" action="{{ route('sales.quotations.reject', $quotation) }}" class="grid">@csrf<label>Powod odrzucenia<input name="reason" required></label><button type="submit" style="align-self:end;">Oznacz jako odrzucona</button></form>
            @if($quotation->valid_until?->isPast())<form method="post" action="{{ route('sales.quotations.expire', $quotation) }}">@csrf<button type="submit">Oznacz jako wygasla</button></form>@endif
        @endif
        @if(in_array($quotation->status, [\App\Enums\QuotationStatus::Calculated, \App\Enums\QuotationStatus::InternalReview, \App\Enums\QuotationStatus::InternallyApproved], true) && auth()->user()->can('cancel', $quotation))
            <form method="post" action="{{ route('sales.quotations.cancel', $quotation) }}" class="grid">@csrf<label>Powod anulowania<input name="reason" required></label><button type="submit" style="align-self:end;">Anuluj wycene</button></form>
        @endif
    </section>

    <section class="card" style="margin-bottom:18px;">
        <h2>Historia korekt</h2>
        @forelse($quotation->overrides as $override)
            <div><strong>{{ $override->field }}</strong>: {{ $override->old_value ?? '-' }} -> {{ $override->new_value ?? '-' }}<div class="muted">{{ $override->user?->name ?? 'System' }} / {{ $override->created_at?->format('Y-m-d H:i') }} / {{ $override->reason }}</div></div>
        @empty<div class="muted">Brak korekt.</div>@endforelse
    </section>

    <section class="card">
        <h2>Historia statusow</h2>
        @forelse($quotation->auditLogs as $log)
            <div><strong>{{ $log->event }}</strong><div class="muted">{{ $log->actor?->name ?? 'System' }} / {{ $log->created_at->format('Y-m-d H:i') }}</div></div>
        @empty<div class="muted">Brak zdarzen.</div>@endforelse
    </section>
</x-auditor.layout>
