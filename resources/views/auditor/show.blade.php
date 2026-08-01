<x-auditor.layout :title="$audit->title . ' - Audytor IT'">
    <div class="page-head">
        <div class="stack">
            <a class="button secondary" href="{{ route('auditor.index') }}" style="width: max-content;">Wroc</a>
            <div class="stack">
                <h1>{{ $audit->title }}</h1>
                <div class="muted">{{ $audit->client->name }} / {{ $audit->location->name }} / {{ $audit->template->name }}</div>
            </div>
            <div class="meta">
                <span class="pill">{{ \App\Models\Audit::STATUSES[$audit->status] ?? $audit->status }}</span>
                <span class="pill {{ $progress['missing'] === 0 && $progress['total'] > 0 ? 'ok' : 'warn' }}">
                    {{ $progress['completed'] }}/{{ $progress['total'] }} odpowiedzi
                </span>
                <span class="pill">{{ $progress['missing'] }} brakujacych</span>
            </div>
        </div>
        <div style="min-width: min(280px, 100%);">
            <div class="progress" aria-label="Postep {{ $progress['percent'] }}%">
                <span style="--progress: {{ $progress['percent'] }}%;"></span>
            </div>
            <p class="muted" style="margin: 8px 0 0;">{{ $progress['percent'] }}% ukonczone</p>
        </div>
    </div>

    @if (session('status'))
        <div class="notice" style="margin-bottom: 16px;">{{ session('status') }}</div>
    @endif

    @if ($errors->any())
        <div class="error" style="margin-bottom: 16px;">{{ $errors->first() }}</div>
    @endif

    @php
        $visibleSubmitBlockers = session('submitBlockers', $submitBlockers);
        $isSubmitted = $audit->status === 'submitted_for_review';
    @endphp

    <section class="card" style="margin-bottom: 18px;">
        <div class="page-head" style="margin-bottom: 0;">
            <div class="stack">
                <h2>Weryfikacja techniczna</h2>
                @if ($isSubmitted)
                    <p class="muted" style="margin: 0;">Audyt zostal wyslany do lidera technicznego {{ $audit->submitted_at?->format('Y-m-d H:i') }}.</p>
                @elseif ($visibleSubmitBlockers === [])
                    <p class="muted" style="margin: 0;">Audyt jest kompletny i moze trafic do lidera technicznego.</p>
                @else
                    <p class="muted" style="margin: 0;">Uzupelnij braki, zanim wyslesz audyt do weryfikacji.</p>
                @endif
            </div>

            @if ($isSubmitted)
                <span class="pill ok">Wyslany</span>
            @elseif ($visibleSubmitBlockers === [])
                <form method="post" action="{{ route('auditor.audits.submit', $audit) }}">
                    @csrf
                    <button type="submit">Wyslij do weryfikacji</button>
                </form>
            @else
                <span class="pill warn">{{ count($visibleSubmitBlockers) }} blokad</span>
            @endif
        </div>

        @if ($visibleSubmitBlockers !== [])
            <div class="error">
                <strong>Braki blokujace wysylke</strong>
                <ul style="margin: 8px 0 0; padding-left: 20px;">
                    @foreach ($visibleSubmitBlockers as $blocker)
                        <li>
                            <strong>{{ $blocker['module'] }}:</strong>
                            {{ $blocker['question'] }}
                            <span class="muted">({{ implode(' ', $blocker['issues']) }})</span>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif
    </section>

    <div class="stack">
        @foreach ($audit->selectedModules as $selectedModule)
            @php
                $module = $selectedModule->module;
                $questions = $module?->questions?->where('active', true)->sortBy('sort_order') ?? collect();
            @endphp

            @continue(! $module)

            <section class="module">
                <div class="stack">
                    <h2>{{ $module->name }}</h2>
                    @if ($module->description)
                        <p class="muted" style="margin: 0;">{{ $module->description }}</p>
                    @endif
                </div>

                @forelse ($questions as $question)
                    @php
                        $answer = $answersByQuestion->get($question->id);
                        $storedValue = $answer?->value_json['value'] ?? '';
                        $isCompleted = $answer?->status === 'completed';
                        $needsEvidence = $question->require_photo || $question->require_screenshot || in_array($question->field_type, ['photo', 'screenshot', 'file'], true);
                        $validationMessages = $validationMessagesByQuestion[$question->id] ?? [];
                    @endphp

                    <article class="question stack" id="question-{{ $question->id }}">
                        <div class="stack">
                            <div class="meta">
                                <span class="pill">{{ \App\Models\AuditQuestion::FIELD_TYPES[$question->field_type] ?? $question->field_type }}</span>
                                @if ($question->is_required)
                                    <span class="pill warn">Wymagane</span>
                                @endif
                                @if ($isCompleted)
                                    <span class="pill ok">Zapisane</span>
                                @elseif ($answer && $validationMessages !== [])
                                    <span class="pill warn">Do poprawy</span>
                                @endif
                            </div>
                            <h3>{{ $question->question }}</h3>
                            @if ($question->instruction)
                                <p class="muted" style="margin: 0;">{{ $question->instruction }}</p>
                            @endif
                        </div>

                        @if ($validationMessages !== [])
                            <div class="error">
                                <strong>Walidacja odpowiedzi</strong>
                                <ul style="margin: 8px 0 0; padding-left: 20px;">
                                    @foreach ($validationMessages as $message)
                                        <li>{{ $message }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif

                        @if ($question->recommendations->isNotEmpty())
                            <div class="card" style="box-shadow: none;">
                                <strong>Powiazane rekomendacje</strong>
                                <div class="stack">
                                    @foreach ($question->recommendations as $recommendation)
                                        <div>
                                            <strong>{{ $recommendation->title }}</strong>
                                            <div class="muted">{{ $recommendation->recommendation_text }}</div>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @endif

                        @if ($answer?->attachments->isNotEmpty())
                            <div class="attachments">
                                <strong>Zalaczniki</strong>
                                @foreach ($answer->attachments as $attachment)
                                    <div class="attachment-row">
                                        <div>
                                            <strong>{{ $attachment->original_name }}</strong>
                                            <div class="muted">
                                                {{ strtoupper($attachment->evidence_type) }} / {{ number_format($attachment->size_bytes / 1024, 1, ',', ' ') }} KB
                                                @if ($attachment->caption)
                                                    / {{ $attachment->caption }}
                                                @endif
                                            </div>
                                        </div>
                                        <a class="button" href="{{ route('auditor.attachments.download', [$audit, $attachment]) }}">Pobierz</a>
                                        <form method="post" action="{{ route('auditor.attachments.destroy', [$audit, $attachment]) }}">
                                            @csrf
                                            @method('delete')
                                            <button type="submit">Usun</button>
                                        </form>
                                    </div>
                                @endforeach
                            </div>
                        @endif

                        <form class="stack" method="post" action="{{ route('auditor.answers.update', [$audit, $question]) }}" enctype="multipart/form-data" data-offline-draft>
                            @csrf

                            @if ($question->field_type === 'yes_no')
                                <label>
                                    Odpowiedz
                                    <select name="value">
                                        <option value="">Wybierz</option>
                                        <option value="yes" @selected($storedValue === 'yes')>Tak</option>
                                        <option value="no" @selected($storedValue === 'no')>Nie</option>
                                    </select>
                                </label>
                            @elseif ($question->field_type === 'long_text')
                                <label>
                                    Odpowiedz
                                    <textarea name="value">{{ $storedValue }}</textarea>
                                </label>
                            @elseif ($question->field_type === 'number')
                                <label>
                                    Odpowiedz
                                    <input name="value" type="number" value="{{ $storedValue }}">
                                </label>
                            @elseif ($question->field_type === 'date')
                                <label>
                                    Odpowiedz
                                    <input name="value" type="date" value="{{ $storedValue }}">
                                </label>
                            @elseif ($question->field_type === 'risk_level')
                                <label>
                                    Poziom ryzyka
                                    <select name="risk_level">
                                        <option value="">Wybierz</option>
                                        @foreach ($riskLevels as $value => $label)
                                            <option value="{{ $value }}" @selected($answer?->risk_level === $value)>{{ $label }}</option>
                                        @endforeach
                                    </select>
                                </label>
                            @elseif (in_array($question->field_type, ['photo', 'screenshot', 'file'], true))
                                <label>
                                    Opis materialu
                                    <textarea name="value">{{ $storedValue }}</textarea>
                                </label>
                                <p class="muted" style="margin: 0;">Dodaj wymagany material jako zalacznik ponizej.</p>
                            @else
                                <label>
                                    Odpowiedz
                                    <input name="value" type="text" value="{{ $storedValue }}">
                                </label>
                            @endif

                            <label>
                                Komentarz
                                <textarea name="comment">{{ $answer?->comment }}</textarea>
                            </label>

                            @if ($question->risk_enabled && $question->field_type !== 'risk_level')
                                <label>
                                    Poziom ryzyka
                                    <select name="risk_level">
                                        <option value="">Wybierz</option>
                                        @foreach ($riskLevels as $value => $label)
                                            <option value="{{ $value }}" @selected($answer?->risk_level === $value)>{{ $label }}</option>
                                        @endforeach
                                    </select>
                                </label>
                            @endif

                            <label>
                                Rekomendacja audytora
                                <textarea name="recommendation_text">{{ $answer?->recommendation_text }}</textarea>
                            </label>
                            <p class="muted" style="margin: -6px 0 0;">Dla ryzyka wysokiego lub krytycznego rekomendacja jest wymagana.</p>

                            @if ($needsEvidence)
                                <div class="stack">
                                    <label>
                                        Zalaczniki
                                        <input name="attachments[]" type="file" multiple accept=".jpg,.jpeg,.png,.webp,.pdf,.txt,.csv,.doc,.docx,.xls,.xlsx,.zip">
                                    </label>
                                    <label>
                                        Opis zalacznikow
                                        <input name="attachment_caption" type="text" value="">
                                    </label>
                                    <p class="muted" style="margin: 0;">Mozesz dodac maksymalnie 6 plikow naraz, do 10 MB kazdy.</p>
                                </div>
                            @endif

                            @if ($question->allow_not_applicable)
                                <label class="inline-check">
                                    <input name="not_applicable" type="checkbox" value="1" @checked($answer?->not_applicable)>
                                    Nie dotyczy
                                </label>
                                <label>
                                    Powod N/D
                                    <textarea name="not_applicable_reason">{{ $answer?->not_applicable_reason }}</textarea>
                                </label>
                            @endif

                            <button type="submit">Zapisz odpowiedz</button>
                        </form>
                    </article>
                @empty
                    <div class="card">
                        <strong>Brak pytan w module</strong>
                        <span class="muted">Pytania pojawia sie po uzupelnieniu biblioteki audytowej.</span>
                    </div>
                @endforelse
            </section>
        @endforeach
    </div>
</x-auditor.layout>
