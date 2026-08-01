<x-auditor.layout :title="$audit->title . ' - Weryfikacja'">
    <div class="page-head">
        <div class="stack">
            <a class="button secondary" href="{{ route('reviewer.index') }}" style="width: max-content;">Wroc</a>
            <div class="stack">
                <h1>{{ $audit->title }}</h1>
                <div class="muted">{{ $audit->client->name }} / {{ $audit->location->name }} / {{ $audit->template->name }}</div>
            </div>
            <div class="meta">
                <span class="pill {{ $audit->status === 'technically_approved' ? 'ok' : ($audit->status === 'changes_requested' ? 'warn' : '') }}">
                    {{ \App\Models\Audit::STATUSES[$audit->status] ?? $audit->status }}
                </span>
                @if ($audit->submitted_at)
                    <span class="pill">Wyslany {{ $audit->submitted_at->format('Y-m-d H:i') }}</span>
                @endif
                @if ($audit->approved_at)
                    <span class="pill ok">Zatwierdzony {{ $audit->approved_at->format('Y-m-d H:i') }}</span>
                @endif
            </div>
        </div>
    </div>

    @if (session('status'))
        <div class="notice" style="margin-bottom: 16px;">{{ session('status') }}</div>
    @endif

    @if ($errors->any())
        <div class="error" style="margin-bottom: 16px;">{{ $errors->first() }}</div>
    @endif

    <section class="card" style="margin-bottom: 18px;">
        <div class="page-head" style="margin-bottom: 0;">
            <div class="stack">
                <h2>Decyzja lidera</h2>
                <p class="muted" style="margin: 0;">Akceptacja zamyka przeglad techniczny. Zwrot wymaga uwag dla audytora.</p>
            </div>
        </div>

        @if ($audit->status === 'submitted_for_review')
            <div class="grid">
                <form class="stack" method="post" action="{{ route('reviewer.audits.approve', $audit) }}">
                    @csrf
                    <label>
                        Uwagi do akceptacji
                        <textarea name="notes">{{ old('notes') }}</textarea>
                    </label>
                    <button type="submit">Zatwierdz technicznie</button>
                </form>

                <form class="stack" method="post" action="{{ route('reviewer.audits.request-changes', $audit) }}">
                    @csrf
                    <label>
                        Uwagi do poprawek
                        <textarea name="notes" required>{{ old('notes') }}</textarea>
                    </label>
                    <button type="submit">Zwroc do poprawek</button>
                </form>
            </div>
        @else
            <div class="stack">
                <p class="muted" style="margin: 0;">Decyzja dla tego audytu zostala juz zapisana.</p>
                @if (in_array($audit->status, ['technically_approved', 'reports_generated'], true))
                    <div class="meta">
                        <a class="button" href="{{ route('reports.technical', $audit) }}">Raport techniczny</a>
                        <a class="button" href="{{ route('reports.business', $audit) }}">Podsumowanie biznesowe</a>
                        <a class="button" href="{{ route('reports.sales', $audit) }}">Raport sprzedazowy</a>
                    </div>
                @elseif ($audit->status === 'published_to_client')
                    @php
                        $publication = $audit->publications->sortByDesc('published_at')->first();
                    @endphp
                    <div class="meta">
                        <a class="button" href="{{ route('reports.technical', $audit) }}">Raport techniczny</a>
                        <a class="button" href="{{ route('reports.business', $audit) }}">Podsumowanie biznesowe</a>
                        <a class="button" href="{{ route('reports.sales', $audit) }}">Raport sprzedazowy</a>
                        @if ($publication)
                            <a class="button" href="{{ route('client.reports.show', $publication->token) }}">Link klienta</a>
                        @endif
                    </div>
                @endif
            </div>
        @endif
    </section>

    @if ($audit->reviews->isNotEmpty())
        <section class="card" style="margin-bottom: 18px;">
            <h2>Historia decyzji</h2>
            <div class="stack">
                @foreach ($audit->reviews->sortByDesc('created_at') as $review)
                    <div>
                        <div class="meta">
                            <span class="pill {{ $review->decision === 'approved' ? 'ok' : 'warn' }}">
                                {{ \App\Models\AuditReview::DECISIONS[$review->decision] ?? $review->decision }}
                            </span>
                            <span class="pill">{{ $review->created_at->format('Y-m-d H:i') }}</span>
                            <span class="pill">{{ $review->reviewer?->name ?? 'Reviewer' }}</span>
                        </div>
                        @if ($review->notes)
                            <p class="muted" style="margin: 8px 0 0;">{{ $review->notes }}</p>
                        @endif
                    </div>
                @endforeach
            </div>
        </section>
    @endif

    @php
        $clientFeedbackPublication = $audit->publications
            ->filter(fn ($publication) => $publication->client_comment || $publication->accepted_recommendations_json || $publication->client_status)
            ->sortByDesc('client_feedback_at')
            ->first();
    @endphp

    @if ($clientFeedbackPublication)
        <section class="card" style="margin-bottom: 18px;">
            <h2>Reakcja klienta</h2>
            <div class="meta">
                @if ($clientFeedbackPublication->client_status)
                    <span class="pill ok">
                        {{ \App\Models\AuditPublication::CLIENT_STATUSES[$clientFeedbackPublication->client_status] ?? $clientFeedbackPublication->client_status }}
                    </span>
                @endif
                @if ($clientFeedbackPublication->client_feedback_at)
                    <span class="pill">{{ $clientFeedbackPublication->client_feedback_at->format('Y-m-d H:i') }}</span>
                @endif
                <span class="pill">{{ count($clientFeedbackPublication->accepted_recommendations_json ?? []) }} rekomendacji do wdrozenia</span>
            </div>
            @if ($clientFeedbackPublication->client_comment)
                <p class="muted" style="margin-bottom: 0;">{{ $clientFeedbackPublication->client_comment }}</p>
            @endif
        </section>
    @endif

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

                @foreach ($questions as $question)
                    @php
                        $answer = $answersByQuestion->get($question->id);
                        $storedValue = $answer?->value_json['value'] ?? null;
                    @endphp

                    <article class="question stack">
                        <div class="stack">
                            <div class="meta">
                                <span class="pill">{{ \App\Models\AuditQuestion::FIELD_TYPES[$question->field_type] ?? $question->field_type }}</span>
                                <span class="pill {{ $answer?->status === 'completed' ? 'ok' : 'warn' }}">
                                    {{ $answer?->status === 'completed' ? 'Kompletne' : 'Robocze' }}
                                </span>
                                @if ($answer?->risk_level)
                                    <span class="pill">{{ $riskLevels[$answer->risk_level] ?? $answer->risk_level }}</span>
                                @endif
                            </div>
                            <h3>{{ $question->question }}</h3>
                            @if ($question->instruction)
                                <p class="muted" style="margin: 0;">{{ $question->instruction }}</p>
                            @endif
                        </div>

                        <div class="card" style="box-shadow: none;">
                            <strong>Odpowiedz audytora</strong>
                            <div class="muted">
                                @if ($answer?->not_applicable)
                                    N/D: {{ $answer->not_applicable_reason ?: 'bez powodu' }}
                                @else
                                    {{ $storedValue ?: 'Brak odpowiedzi' }}
                                @endif
                            </div>

                            @if ($answer?->comment)
                                <div>
                                    <strong>Komentarz</strong>
                                    <div class="muted">{{ $answer->comment }}</div>
                                </div>
                            @endif

                            @if ($answer?->recommendation_text)
                                <div>
                                    <strong>Rekomendacja audytora</strong>
                                    <div class="muted">{{ $answer->recommendation_text }}</div>
                                </div>
                            @endif
                        </div>

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
                                        <span></span>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </article>
                @endforeach
            </section>
        @endforeach
    </div>
</x-auditor.layout>
