<x-auditor.layout :title="'Nowa kwalifikacja - Audytor IT'">
    <div class="page-head">
        <div class="stack"><a class="button secondary" href="{{ route('sales.qualifications.index') }}" style="width: max-content;">Wroc</a><h1>Nowa kwalifikacja</h1></div>
    </div>
    @if($errors->any())<div class="error" style="margin-bottom:16px;">{{ $errors->first() }}</div>@endif
    <form class="card stack" method="post" action="{{ route('sales.qualifications.store') }}">
        @csrf
        <div class="grid">
            <label>Klient<select name="client_id" required><option value="">Wybierz</option>@foreach($clients as $client)<option value="{{ $client->id }}" @selected(old('client_id') == $client->id)>{{ $client->name }}</option>@endforeach</select></label>
            <label>Lokalizacja<select name="client_location_id"><option value="">Brak</option>@foreach($clients as $client)@foreach($client->locations as $location)<option value="{{ $location->id }}" @selected(old('client_location_id') == $location->id)>{{ $client->name }} / {{ $location->name }}</option>@endforeach @endforeach</select></label>
            <label>Typ audytu<select name="audit_type_id" required><option value="">Wybierz</option>@foreach($auditTypes as $type)<option value="{{ $type->id }}" @disabled(!$type->currentVersion || $type->currentVersion->status !== 'published') @selected(old('audit_type_id') == $type->id)>{{ $type->name }}{{ $type->currentVersion ? ' / v'.$type->currentVersion->version : ' / brak opublikowanej wersji' }}</option>@endforeach</select></label>
            @if(!auth()->user()->hasRole(\App\Enums\UserRole::Sales))
                <label>Opiekun Sales<select name="sales_owner_id" required><option value="">Wybierz</option>@foreach($salesOwners as $owner)<option value="{{ $owner->id }}" @selected(old('sales_owner_id') == $owner->id)>{{ $owner->name }}</option>@endforeach</select></label>
            @endif
            <label>Tytul<input name="title" required maxlength="255" value="{{ old('title') }}"></label>
            <label>Oczekiwany termin<input type="date" name="expected_date" value="{{ old('expected_date') }}"></label>
            <label>Osoba kontaktowa<input name="contact_name" value="{{ old('contact_name') }}"></label>
            <label>E-mail kontaktowy<input type="email" name="contact_email" value="{{ old('contact_email') }}"></label>
            <label>Telefon<input name="contact_phone" value="{{ old('contact_phone') }}"></label>
        </div>
        <label>Cel audytu<textarea name="purpose">{{ old('purpose') }}</textarea></label>
        <label>Notatki wewnetrzne<textarea name="internal_notes">{{ old('internal_notes') }}</textarea></label>
        <button type="submit" style="width:max-content;">Utworz kwalifikacje</button>
    </form>
</x-auditor.layout>
