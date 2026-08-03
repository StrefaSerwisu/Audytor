<x-auditor.layout :title="$qualification->title . ' - Audytor IT'">
    <div class="page-head">
        <div class="stack">
            <a class="button secondary" href="{{ route('sales.qualifications.index') }}" style="width:max-content;">Wroc</a>
            <h1>{{ $qualification->title }}</h1>
            <div class="muted">{{ $qualification->client->name }}{{ $qualification->location ? ' / '.$qualification->location->name : '' }} / {{ $qualification->auditType->name }} / v{{ $qualification->version->version }}</div>
            <div class="meta">
                <span class="pill">{{ $qualification->status->label() }}</span>
                <span class="pill">Sales: {{ $qualification->salesOwner->name }}</span>
                <span class="pill {{ $progress['missing'] === 0 ? 'ok' : 'warn' }}">{{ $progress['completed'] }}/{{ $progress['required'] }} wymaganych</span>
            </div>
        </div>
        <div style="min-width:min(280px,100%);">
            <div class="progress"><span style="--progress: {{ $progress['percent'] }}%;"></span></div>
            <p class="muted" style="margin:8px 0 0;">{{ $progress['percent'] }}% ukonczone</p>
        </div>
    </div>

    @if(session('status'))<div class="notice" style="margin-bottom:16px;">{{ session('status') }}</div>@endif
    @if($errors->any())<div class="error" style="margin-bottom:16px;">{{ $errors->first() }}</div>@endif

    <section class="card" style="margin-bottom:18px;">
        <div class="grid">
            <div><strong>Kontakt</strong><div class="muted">{{ $qualification->contact_name ?: 'Brak' }}{{ $qualification->contact_email ? ' / '.$qualification->contact_email : '' }}{{ $qualification->contact_phone ? ' / '.$qualification->contact_phone : '' }}</div></div>
            <div><strong>Oczekiwany termin</strong><div class="muted">{{ $qualification->expected_date?->format('Y-m-d') ?? 'Brak' }}</div></div>
        </div>
        @if($qualification->purpose)<div><strong>Cel</strong><div class="muted">{{ $qualification->purpose }}</div></div>@endif
        @if(data_get($qualification->qualification_snapshot, 'sales_instructions'))<div><strong>Instrukcje Sales</strong><div class="muted">{{ data_get($qualification->qualification_snapshot, 'sales_instructions') }}</div></div>@endif
        @if($qualification->scope_summary)<div class="notice"><strong>Zakres gotowy do wyceny</strong><div>{{ $qualification->scope_summary }}</div></div>@endif
    </section>

    @if($canEdit)
        <section class="card" style="margin-bottom:18px;">
            <div class="meta">
                @if($qualification->status === \App\Enums\SalesQualificationStatus::Draft)
                    <form method="post" action="{{ route('sales.qualifications.start', $qualification) }}">@csrf<button type="submit">Rozpocznij kwalifikacje</button></form>
                @elseif($qualification->status === \App\Enums\SalesQualificationStatus::InProgress)
                    <form method="post" action="{{ route('sales.qualifications.wait', $qualification) }}">@csrf<button type="submit">Oczekiwanie na klienta</button></form>
                    <form method="post" action="{{ route('sales.qualifications.complete', $qualification) }}">@csrf<button type="submit">Zakoncz kwalifikacje</button></form>
                @elseif($qualification->status === \App\Enums\SalesQualificationStatus::WaitingForClient)
                    <form method="post" action="{{ route('sales.qualifications.resume', $qualification) }}">@csrf<button type="submit">Wznow</button></form>
                @endif
                <button type="button" disabled title="Dostepne w Etapie 2C">Przejdz do wyceny - Etap 2C</button>
            </div>
            @if(in_array($qualification->status, [\App\Enums\SalesQualificationStatus::Draft, \App\Enums\SalesQualificationStatus::InProgress, \App\Enums\SalesQualificationStatus::WaitingForClient], true))
                <form method="post" action="{{ route('sales.qualifications.cancel', $qualification) }}" class="grid">
                    @csrf
                    <label>Powod anulowania<input name="reason" required maxlength="2000"></label>
                    <button type="submit" style="align-self:end;">Anuluj</button>
                </form>
            @endif
        </section>
    @endif

    <div class="stack">
        @forelse($visibleModules as $module)
            <section class="module">
                <div class="stack"><h2>{{ $module['name'] }}</h2>@if($module['description'] ?? null)<p class="muted" style="margin:0;">{{ $module['description'] }}</p>@endif</div>
                @foreach($module['questions'] as $question)
                    @php
                        $answer = $answersByCode->get($question['code']);
                        $stored = $answer?->value_json['value'] ?? null;
                        $hasStored = $answer && is_array($answer->value_json) && array_key_exists('value', $answer->value_json);
                        $options = $question['options_json'] ?? [];
                    @endphp
                    <article class="question stack" id="question-{{ $question['code'] }}">
                        <div class="meta">
                            <span class="pill">{{ \App\Models\SalesQualificationQuestion::FIELD_TYPES[$question['field_type']] ?? $question['field_type'] }}</span>
                            @if($question['required'] ?? false)<span class="pill warn">Wymagane</span>@endif
                            @if($question['affects_scope'] ?? false)<span class="pill">Wplywa na zakres</span>@endif
                            @if($question['affects_pricing'] ?? false)<span class="pill">Wplywa na wycene</span>@endif
                            @if($answer)<span class="pill ok">Zapisane</span>@endif
                        </div>
                        <h3>{{ $question['question'] }}</h3>
                        @if($question['description'] ?? null)<p class="muted" style="margin:0;">{{ $question['description'] }}</p>@endif
                        @if($question['helper_text'] ?? null)<p class="muted" style="margin:0;">{{ $question['helper_text'] }}</p>@endif

                        @if($question['field_type'] === 'info')
                            <div class="notice">{{ $question['description'] ?? $question['question'] }}</div>
                        @elseif($canEdit)
                            <form class="stack" method="post" enctype="multipart/form-data" action="{{ route('sales.qualifications.answers.update', [$qualification, $question['code']]) }}">
                                @csrf
                                @if($question['field_type'] === 'textarea')
                                    <label>Odpowiedz<textarea name="value">{{ is_string($stored) ? $stored : '' }}</textarea></label>
                                @elseif($question['field_type'] === 'number')
                                    <label>Odpowiedz<input type="number" step="any" name="value" value="{{ $stored }}"></label>
                                @elseif($question['field_type'] === 'boolean')
                                    <label>Odpowiedz<select name="value"><option value="">Wybierz</option><option value="true" @selected($hasStored && $stored === true)>Tak</option><option value="false" @selected($hasStored && $stored === false)>Nie</option><option value="unknown" @selected($hasStored && $stored === null)>Nie wiem</option></select></label>
                                @elseif($question['field_type'] === 'select')
                                    <label>Odpowiedz<select name="value"><option value="">Wybierz</option>@foreach($options as $value => $label)@php($optionValue = array_is_list($options) ? $label : $value)<option value="{{ $optionValue }}" @selected($stored === $optionValue)>{{ $label }}</option>@endforeach</select></label>
                                @elseif($question['field_type'] === 'multiselect')
                                    <label>Odpowiedz<select name="value[]" multiple>@foreach($options as $value => $label)@php($optionValue = array_is_list($options) ? $label : $value)<option value="{{ $optionValue }}" @selected(in_array($optionValue, is_array($stored) ? $stored : [], true))>{{ $label }}</option>@endforeach</select></label>
                                @elseif($question['field_type'] === 'date')
                                    <label>Data<input type="date" name="value" value="{{ is_string($stored) ? $stored : '' }}"></label>
                                @elseif($question['field_type'] === 'file')
                                    <label>Plik<input type="file" name="file" accept=".pdf,.jpg,.jpeg,.png,.docx,.xlsx"></label>
                                @else
                                    <label>Odpowiedz<input name="value" value="{{ is_string($stored) ? $stored : '' }}"></label>
                                @endif
                                <button type="submit" style="width:max-content;">Zapisz odpowiedz</button>
                            </form>
                        @else
                            <div class="card" style="box-shadow:none;"><strong>Odpowiedz</strong><span class="muted">@if($stored === true) Tak @elseif($stored === false) Nie @elseif($hasStored && $stored === null) Nie wiem @elseif(is_array($stored)) {{ implode(', ', $stored) }} @else {{ $stored ?? 'Brak' }} @endif</span></div>
                        @endif

                        @if($answer?->attachments->isNotEmpty())
                            <div class="attachments">
                                @foreach($answer->attachments as $attachment)
                                    <div class="attachment-row">
                                        <div><strong>{{ $attachment->original_name }}</strong><div class="muted">{{ number_format($attachment->size_bytes / 1024, 1, ',', ' ') }} KB</div></div>
                                        <a class="button" href="{{ route('sales.qualifications.attachments.download', $attachment) }}">Pobierz</a>
                                        @if($canEdit)<form method="post" action="{{ route('sales.qualifications.attachments.destroy', $attachment) }}">@csrf @method('delete')<button type="submit">Usun</button></form>@endif
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </article>
                @endforeach
            </section>
        @empty
            <div class="card"><h2>Brak aktywnych modulow Sales</h2></div>
        @endforelse
    </div>
</x-auditor.layout>
