# Audytor IT 2.0 - implementation plan

Data baseline: 2026-08-01

Data domkniecia Etapu 0: 2026-08-01

Zakres tego dokumentu: Etap 0 z dokumentu "AUDYTOR IT 2.0 - KOMPLETNA SPECYFIKACJA PRZEBUDOWY SYSTEMU".

Nie wykonano przebudowy logiki aplikacji. Ten dokument podsumowuje aktualny stan MVP, luki wzgledem specyfikacji 2.0, ryzyka oraz rekomendowany plan etapowej migracji.

## 1. Aktualny stan repozytorium

Repozytorium: `https://github.com/StrefaSerwisu/Audytor.git`

Branch lokalny: `main`

Baseline Git:

- commit: `7e9a2da Initial Audytor IT project`
- `main` sledzi `origin/main`
- po commicie inicjalnym repo nie ma niezatwierdzonych zmian poza tym dokumentem

Aktualny projekt to dzialajace MVP w:

- Laravel 12,
- PHP 8.2+,
- Filament 4,
- Blade,
- PostgreSQL lokalnie,
- SQLite in-memory w testach,
- Laravel Queue database,
- prywatny storage local,
- podstawowe PWA/offline draft.

Projekt jest monolitem Laravel. Nie ma SPA, Inertia, Vue ani osobnego REST API.

## 2. Wyniki walidacji Etapu 0

| Komenda | Wynik |
| --- | --- |
| `php artisan test` | PASS: 72 testy, 299 asercji |
| `./vendor/bin/pint --test` | PASS |
| `composer validate` | PASS: `composer.json is valid` |
| `npm install` | PASS: zainstalowano 86 pakietow, 0 vulnerabilities; utworzono `package-lock.json` |
| `npm run build` | PASS: Vite 7.3.6, 56 modules transformed, wygenerowano assety w `public/build` |
| `php artisan migrate:status` | PASS po dopuszczeniu lokalnego PostgreSQL; wszystkie migracje `Ran` |
| `APP_ENV=testing DB_CONNECTION=sqlite DB_DATABASE=/tmp/audytor2-baseline.sqlite CACHE_STORE=array SESSION_DRIVER=array QUEUE_CONNECTION=sync php artisan migrate:fresh --seed` | PASS |

Wniosek:

- backend i migracje sa stabilne na poziomie MVP;
- build frontendowy dziala po instalacji zaleznosci Node przez `npm install`;
- nie wykryto potrzeby poprawiania logiki, aby uruchomic testy.

Znane ograniczenia srodowiska:

- `node_modules` jest wymagane lokalnie do builda, ale pozostaje ignorowane przez Git;
- `public/build` jest generowany przez `npm run build` i pozostaje ignorowany przez Git;
- lokalne polaczenie do PostgreSQL `127.0.0.1:5432` wymaga dostepu poza sandboxem narzedziowym;
- `npm install` pokazal ostrzezenie `allow-scripts` dla `esbuild@0.28.1`, ale build przeszedl poprawnie i `npm install` wskazal `found 0 vulnerabilities`;
- tymczasowa baza SQLite do testu migracji byla poza repo: `/tmp/audytor2-baseline.sqlite`.

## 3. Aktualna struktura kodu

Obecne glowne katalogi:

| Katalog | Stan |
| --- | --- |
| `app/Models` | Modele domeny MVP |
| `app/Http/Controllers` | Glowna logika workflow w kontrolerach |
| `app/Filament/Resources` | CRUD Filament dla encji MVP |
| `app/Support` | Pomocnicza logika raportow, follow-up, powiadomien |
| `app/Jobs` | Eksport raportow |
| `database/migrations` | Schemat MVP |
| `database/seeders` | Dane demo i konta testowe |
| `resources/views` | Blade UI i raporty |
| `routes/web.php` | Wszystkie trasy web |
| `tests/Feature` | Testy MVP |

Brak katalogow wymaganych docelowo przez 2.0:

- `app/Actions`
- `app/Services`
- `app/Policies`
- `app/Enums`
- `app/Data`
- `app/DTO`
- `app/Http/Requests`
- `app/Contracts`

## 4. Elementy MVP do zachowania

Te elementy dzialaja i powinny byc zachowane podczas przebudowy:

1. Panel Filament `/admin`.
2. CRUD klientow i lokalizacji.
3. Biblioteka audytu: szablony, moduly, pytania, rekomendacje.
4. Tworzenie audytu z klientem, lokalizacja, szablonem, modulami, audytorami i liderem.
5. Widok audytora `/auditor`.
6. Zapis odpowiedzi, N/D, komentarzy, ryzyka i rekomendacji audytora.
7. Wymagane dowody: zdjecia, screenshoty, pliki.
8. Prywatny download i usuwanie zalacznikow.
9. Walidacja kompletosci przed wyslaniem do weryfikacji.
10. Weryfikacja lidera `/reviewer`.
11. Raporty HTML: techniczny, biznesowy, sales.
12. Minimalny eksport PDF/DOCX.
13. Kolejka eksportow i historia `/reports/exports`.
14. Publikacja raportu klientowi.
15. Portal klienta `/client/portal`.
16. Publiczny link klienta `/client/reports/{token}`.
17. Feedback klienta i wybor rekomendacji.
18. Follow-up z zaakceptowanych rekomendacji.
19. Dashboard KPI.
20. Archiwum.
21. Powiadomienia wewnetrzne.
22. PWA manifest, service worker i awaryjny draft IndexedDB.
23. Testy feature pokrywajace glowny workflow.

## 5. Elementy do przebudowania

### 5.1. Architektura kodu

Obecnie kontrolery zawieraja duzo logiki biznesowej:

- autoryzacja przez prywatne metody i `abort_unless`,
- walidacje inline przez `$request->validate(...)`,
- zmiany statusow przez `forceFill(...)`,
- generowanie danych raportowych blisko kontrolerow/supportow.

Docelowo nalezy wydzielic:

- Actions dla operacji biznesowych,
- Services dla kalkulacji i workflow,
- Policies dla autoryzacji,
- Form Requests dla walidacji,
- Enums dla statusow/rol/decyzji,
- DTO/Data dla przenoszenia danych miedzy warstwami.

### 5.2. Role i uprawnienia

Obecnie:

- role sa stringami w `users.role`;
- `User::canAccessPanel()` wpuszcza `super_admin`, `global_admin`, `technical_lead`, `sales`;
- specyfikacja 2.0 mowi, ze Sales nie powinien miec pelnego dostepu do panelu administracyjnego;
- brak permissions i policies.

Docelowo:

- role jako Enum,
- permissions lub przynajmniej centralna mapa uprawnien,
- Policies dla kluczowych modeli,
- testy dostepu dla kazdej roli i kluczowej trasy,
- ograniczenie panelu Sales.

### 5.3. Statusy i workflow

Obecnie:

- statusy audytu sa stringami w `Audit::STATUSES`;
- Filament pozwala wybrac status audytu w formularzu;
- przejscia statusow sa rozproszone w kontrolerach;
- brak centralnej historii przejsc.

Docelowo:

- statusy jako Enum,
- centralny workflow service,
- dozwolone przejscia statusow,
- log kazdego przejscia: poprzedni status, nowy status, user, data, IP, komentarz, zrodlo akcji.

### 5.4. Zalaczniki i dowody

Obecnie:

- dowody sa prywatne i autoryzowane przy pobraniu;
- brak skanowania antywirusowego;
- brak statusu skanowania;
- brak historii pobran/usuniec;
- brak rozroznionej encji dowodu 2.0.

Docelowo:

- rozbudowana encja Evidence,
- status skanowania,
- status zatwierdzenia,
- audit trail pobran/usuniec,
- bezpieczne MIME,
- limity uploadow,
- adapter antywirusowy.

### 5.5. Raporty

Obecnie:

- HTML raporty dzialaja;
- PDF/DOCX sa minimalne i tekstowe;
- brak wersjonowania raportow;
- brak finalnego brandingu;
- historia eksportow istnieje, ale bez wersji robocza/finalna.

Docelowo:

- raport zarzadczy, techniczny i sales jako oddzielne wersjonowane artefakty;
- branding Global IT;
- status draft/final;
- wersja raportu;
- autor i data wygenerowania;
- testy zawartosci.

### 5.6. Offline

Obecnie:

- IndexedDB zapisuje lokalny draft;
- service worker zawiera `audytor-it-sync`, ale nie ma pelnej synchronizacji odpowiedzi do backendu.

Docelowo na pierwszym etapie:

- jasno opisac to jako "Lokalny szkic awaryjny";
- pokazywac, co jest lokalne, a co zsynchronizowane;
- nie obiecywac pelnego offline sync bez kompletnej implementacji.

## 6. Lista brakow wzgledem specyfikacji 2.0

| Obszar 2.0 | Obecny stan | Brak |
| --- | --- | --- |
| Kontakty klienta | Czesciowo w `clients`/`client_locations` | Osobny model kontaktow, role kontaktu, uprawnienia akceptacji |
| Typy audytow | Czesciowo jako `audit_templates` | `AuditType`, wersje, kod, instrukcje Sales/Delivery, AI config, pricing rules |
| Kwalifikacja Sales | Brak | Proces, pytania kwalifikacyjne, odpowiedzi, warunki |
| Silnik wyceny | Brak | Reguly, kalkulator, statusy wyceny, audit zmian recznych |
| Akceptacja wyceny | Brak | Utworzenie zlecenia z zaakceptowanej wyceny |
| Delivery planning | Czesciowo w audycie | Planowanie, kompetencje, checklista, ostrzezenia |
| Etapy/sekcje/kontrole | Czesciowo jako moduly/pytania | Pelny model kontroli audytowej |
| Ustalenia | Brak | `Finding` jako glowny rezultat audytu |
| Macierz ryzyka | Brak | Metodologie, skale, wagi, progi, override |
| Eskalacje | Brak | Statusy konsultacji, kolejka lidera |
| OpenAI | Brak | Sanitizer, secret detection, structured outputs, job, historia, koszty |
| Weryfikacja AI przez lidera | Brak | Ekran porownawczy i akceptacja sugestii |
| Raporty 2.0 | Czesciowo | Wersje, draft/final, branding, testy zawartosci |
| Portal klienta 2.0 | Czesciowo | Decyzje per ustalenie, akceptacja ryzyka, proba o wycene, dowody realizacji |
| Reaudyt | Brak | Porownanie wynikow i dojrzalosci |
| Wersjonowanie konfiguracji | Brak | Snapshoty typow, modulow, pytan, rekomendacji, ryzyka, promptow |
| Permissions | Brak | Policies, middleware ról, permissions |
| Security hardening | Czesciowo | MFA, SSO, rate limiting, CSP, audit log, session invalidation |
| Audit log | Brak centralny | Tabela i usluga audit trail |
| Integracje | Brak | Adaptery ClickUp/CRM/Entra/S3/OpenAI |
| E2E browser tests | Brak | Pelny scenariusz Sales -> klient |

## 7. Proponowane nowe modele

### Etap 1 - bezpieczeństwo i fundament

- `AuditLog`
- `StatusTransition`
- `UserPermission` lub prostsza tabela `role_permissions`, jesli nie zostanie uzyty pakiet permissions
- docelowo `UserResource` w Filament, bez koniecznie nowego modelu

### Etap 2 - typy audytow i wersjonowanie

- `AuditType`
- `AuditTypeVersion`
- `AuditTypeModule`
- `AuditTypeRequirement`
- `AuditConfigurationSnapshot`
- `Competency`
- `UserCompetency`

### Etap 3 - kwalifikacja i wyceny

- `ClientContact`
- `SalesQualification`
- `QualificationQuestion`
- `QualificationAnswer`
- `PricingRule`
- `Quotation`
- `QuotationLine`
- `QuotationOverride`
- `QuotationApproval`

### Etap 4 - Delivery i techniczna sciezka audytu

- `AuditOrder`
- `AuditPlan`
- `AuditPlanAssignee`
- `AuditPreparationChecklistItem`
- `AuditStage`
- `AuditSection`
- `AuditControl`
- `AuditControlResult`
- `Evidence`
- `EvidenceAccessLog`
- `Escalation`
- `Finding`
- `RiskMethodology`
- `RiskMatrix`
- `RiskScore`

### Etap 5 - OpenAI

- `AiAnalysis`
- `AiAnalysisInput`
- `AiAnalysisResult`
- `AiFindingSuggestion`
- `AiPromptVersion`
- `AiSchemaVersion`
- `AiUsageLog`

### Etap 6 - raporty

- `ReportTemplate`
- `ReportVersion`
- `ReportSection`
- `ReportExport`

Uwaga: obecny `AuditReportExport` mozna migrowac albo zachowac jako kompatybilna podstawe dla `ReportExport`.

### Etap 7 - portal/follow-up/reaudyt

- `ClientDecision`
- `RiskAcceptance`
- `RemediationTask`
- `RemediationEvidence`
- `Reaudit`
- `ReauditComparison`

## 8. Proponowane migracje

Kolejnosc migracji powinna minimalizowac ryzyko regresji:

1. Dodac tabele fundamentu bez przepinania istniejacej logiki:
   - `audit_logs`
   - `status_transitions`
   - opcjonalnie `role_permissions`

2. Dodac `client_contacts` bez usuwania pol kontaktowych z `clients` i `client_locations`.

3. Dodac typy audytow:
   - `audit_types`
   - `audit_type_versions`
   - `audit_type_modules`
   - `audit_configuration_snapshots`

4. Dodac kwalifikacje i wyceny:
   - `sales_qualifications`
   - `qualification_questions`
   - `qualification_answers`
   - `pricing_rules`
   - `quotations`
   - `quotation_lines`
   - `quotation_overrides`

5. Dodac delivery:
   - `audit_orders`
   - `audit_plans`
   - `audit_plan_assignees`
   - `audit_preparation_checklist_items`

6. Dodac techniczna sciezke 2.0:
   - `audit_stages`
   - `audit_sections`
   - `audit_controls`
   - `audit_control_results`
   - `evidences`
   - `evidence_access_logs`
   - `escalations`
   - `findings`

7. Dodac ryzyko:
   - `risk_methodologies`
   - `risk_scales`
   - `risk_matrix_cells`
   - `risk_scores`

8. Dodac AI:
   - `ai_prompt_versions`
   - `ai_schema_versions`
   - `ai_analyses`
   - `ai_analysis_results`
   - `ai_usage_logs`

9. Dodac raporty 2.0:
   - `report_templates`
   - `report_versions`
   - `report_exports`

10. Dodac portal/follow-up 2.0:
   - `client_decisions`
   - `risk_acceptances`
   - `remediation_tasks`
   - `remediation_evidences`
   - `reaudits`
   - `reaudit_comparisons`

Zasada migracyjna: najpierw dodawac nowe struktury rownolegle, potem przepinac workflow, dopiero na koncu deprecjonowac stare pola.

## 9. Proponowane Enums

Pierwsze Enums do wprowadzenia:

- `App\Enums\UserRole`
- `App\Enums\AuditStatus`
- `App\Enums\QuotationStatus`
- `App\Enums\ReportStatus`
- `App\Enums\RiskLevel`
- `App\Enums\ClientDecisionStatus`
- `App\Enums\AiAnalysisStatus`
- `App\Enums\FollowUpStatus`
- `App\Enums\EvidenceType`
- `App\Enums\EvidenceScanStatus`
- `App\Enums\CompetencyLevel`
- `App\Enums\AuditTransitionSource`

## 10. Proponowane statusy

### Workflow 2.0

Docelowy przeplyw:

```text
qualification_draft
-> qualification_completed
-> quotation_draft
-> quotation_internal_review
-> quotation_sent
-> quotation_accepted
-> audit_order_created
-> audit_planned
-> audit_in_progress
-> audit_waiting_for_data
-> audit_submitted
-> ai_analysis
-> technical_review
-> changes_requested
-> technically_approved
-> reports_generated
-> published_to_client
-> follow_up
-> closed
```

### Mapowanie z MVP

| MVP `Audit::status` | Proponowany status 2.0 |
| --- | --- |
| `draft` | `audit_order_created` albo `audit_planned`, zależnie od pochodzenia |
| `scheduled` | `audit_planned` |
| `in_progress` | `audit_in_progress` |
| `syncing` | techniczny status sync, nie status biznesowy |
| `needs_completion` | `audit_waiting_for_data` |
| `submitted_for_review` | `technical_review` albo `audit_submitted` przed AI |
| `changes_requested` | `changes_requested` |
| `technically_approved` | `technically_approved` |
| `reports_generated` | `reports_generated` |
| `published_to_client` | `published_to_client` |
| `closed` | `closed` |
| `cancelled` | `cancelled` jako dodatkowy status koncowy |

## 11. Proponowane role

Docelowe role ze specyfikacji:

- `super_admin`
- `global_admin`
- `audit_product_admin`
- `sales`
- `sales_manager`
- `delivery_manager`
- `technical_lead`
- `auditor`
- `client_admin`
- `client_user`

### Mapowanie z MVP

| MVP | 2.0 |
| --- | --- |
| `super_admin` | `super_admin` |
| `global_admin` | `global_admin` |
| `technical_lead` | `technical_lead` |
| `auditor` | `auditor` |
| `sales` | `sales` lub `sales_manager`, do decyzji |
| `client` | `client_admin` albo `client_user`, zależnie od uprawnien kontaktu |

Decyzja wymagana: czy obecny `sales` ma miec jakikolwiek dostep do Filament. Specyfikacja 2.0 sugeruje ograniczenie.

## 12. Plan etapow

### Etap 0 - analiza i baseline

Status: wykonany w tym dokumencie.

Kryteria:

- dokumentacja przeczytana,
- testy uruchomione,
- Pint uruchomiony,
- Composer validate uruchomiony,
- build frontend sprawdzony,
- migracje sprawdzone,
- braki i ryzyka spisane,
- nie zmieniono logiki biznesowej.

### Etap 1 - bezpieczenstwo i fundament

Zakres:

1. Enums dla rol i statusow.
2. Policies dla kluczowych modeli.
3. Middleware rol.
4. Form Requests dla najwazniejszych akcji.
5. Testy autoryzacji dla rol i tras.
6. Centralny `AuditLog`.
7. Ograniczenie panelu Sales.
8. Zabezpieczenie zalacznikow i logging pobran/usuniec.
9. Centralne przejscia statusow.
10. CRUD uzytkownikow w Filament.

Warunek wejscia: zatwierdzenie Etapu 0.

### Etap 2 - typy audytow i wersjonowanie

Zakres:

1. `AuditType` i `AuditTypeVersion`.
2. Rozdzial Sales modules i technical modules.
3. Snapshot konfiguracji audytu.
4. Instrukcje Sales/Delivery/audytora.
5. Kompetencje i wymagane role.

Warunek wejscia: Enums, Policies, User CRUD.

### Etap 3 - kwalifikacja i wyceny

Zakres:

1. Proces kwalifikacji Sales.
2. Dynamiczne pytania kwalifikacyjne.
3. Reguly wyceny.
4. Kalkulator wyceny.
5. Weryfikacja wewnetrzna.
6. Akceptacja klienta.
7. Utworzenie zlecenia audytu.

Warunek wejscia: typy audytow i wersje.

### Etap 4 - workflow Delivery i sciezka techniczna

Zakres:

1. Planowanie Delivery.
2. Kompetencje i ostrzezenia.
3. Checklista przygotowania.
4. Etapy/sekcje/kontrole.
5. Eskalacje.
6. Ustalenia.
7. Macierz ryzyka.

Warunek wejscia: audyt order i snapshot.

### Etap 5 - OpenAI

Zakres:

1. Konfiguracja OpenAI per klient.
2. Sensitive data sanitizer.
3. Secret detector.
4. Responses API i Structured Outputs.
5. Job kolejki.
6. Historia analiz, tokeny, koszt.
7. Widok lidera do porownania i akceptacji.
8. Testy z mockiem API.

Warunek wejscia: ustalenia, ryzyka, evidence.

### Etap 6 - raporty 2.0

Zakres:

1. Raport zarzadczy.
2. Raport techniczny.
3. Raport Sales.
4. Branding Global IT.
5. PDF/DOCX.
6. Wersjonowanie raportow.
7. Historia eksportow.
8. Testy zawartosci.

Warunek wejscia: findings i zatwierdzone ryzyka.

### Etap 7 - portal klienta, follow-up i reaudyt

Zakres:

1. Decyzje klienta per ustalenie.
2. Akceptacja ryzyka.
3. Prosba o wycene.
4. Zadania naprawcze.
5. Dowody wykonania.
6. Follow-up.
7. Reaudyt.
8. Porownanie wynikow.

Warunek wejscia: raporty 2.0 i findings.

### Etap 8 - produkcja

Zakres:

1. Redis.
2. Worker kolejki.
3. Monitoring.
4. Backup.
5. S3/MinIO.
6. CI/CD.
7. Staging.
8. Security headers/CSP.
9. E2E.
10. Dokumentacja wdrozeniowa.

## 13. Zaleznosci miedzy etapami

```text
Etap 0
  -> Etap 1
      -> Etap 2
          -> Etap 3
              -> Etap 4
                  -> Etap 5
                  -> Etap 6
                      -> Etap 7
                          -> Etap 8
```

Najwazniejsza zaleznosc: nie warto budowac OpenAI ani raportow 2.0 bez `Finding`, `Evidence`, macierzy ryzyka i wersjonowanych snapshotow konfiguracji.

## 14. Problemy bezpieczeństwa znalezione w Etapie 0

1. Brak centralnych Policies.
2. Brak Form Requests.
3. Role sa zwyklymi stringami.
4. Sales ma dostep do Filament, co koliduje z docelowa specyfikacja.
5. Brak centralnego audit log.
6. Brak historii logowania.
7. Brak prawdziwego MFA mimo pola `mfa_enabled`.
8. Brak rate limitingu dla loginow poza domyslami Laravel.
9. Publiczny token raportu klienta jest losowy i moze wygasac, ale brakuje uniewazniania i audit trail dostepu.
10. Brak rejestrowania pobran dowodow i eksportow.
11. Brak skanowania zalacznikow.
12. Brak statusow skanowania i zatwierdzania dowodow.
13. Brak CSP/security headers w aplikacji.
14. Brak centralnej ochrony przed IDOR poza kontrolerowymi sprawdzeniami.
15. Brak szyfrowania specyficznych danych wrazliwych poza standardem Laravel.

## 15. Miejsca wymagajace refaktoru

| Obszar | Pliki | Dlaczego |
| --- | --- | --- |
| Praca audytora | `app/Http/Controllers/AuditorAuditController.php` | Walidacja, zapis odpowiedzi, upload, kompletosc i statusy sa w jednym kontrolerze |
| Raporty | `app/Http/Controllers/AuditReportController.php`, `app/Support/AuditReportData.php`, `SimplePdf`, `SimpleDocx` | Logika raportow i eksportow wymaga osobnych services/actions |
| Weryfikacja lidera | `TechnicalReviewController.php` | Status transitions i decyzje powinny przejsc do workflow service |
| Portal klienta | `ClientPortalController.php`, `ClientReportController.php` | Decyzje klienta beda rosly do osobnych modeli i policies |
| Follow-up | `FollowUpTaskController.php`, `FollowUpTaskBuilder.php` | Docelowo follow-up powinien wynikac z findings/client decisions |
| Autoryzacja | wszystkie kontrolery | Prywatne metody trzeba zastapic policies |
| Statusy | modele i kontrolery | Stringi zastapic enums i workflow service |
| Filament AuditResource | `app/Filament/Resources/Audits/AuditResource.php` | Nie powinien pozwalac na dowolne reczne ustawianie statusu |

## 16. Kryteria odbioru Etapu 0

Etap 0 uznajemy za zakonczony, gdy:

- istnieje `docs/AUDYTOR_2_IMPLEMENTATION_PLAN.md`;
- dokument zawiera aktualny stan, braki, elementy do zachowania i przebudowy;
- dokument zawiera proponowane migracje, modele, statusy, role, etapy i zaleznosci;
- dokument zawiera ryzyka i kryteria odbioru;
- testy i walidacje zostaly uruchomione i wynik jest zapisany;
- nie zmieniono logiki biznesowej aplikacji.

Status: spelnione. Build frontendu przechodzi po wykonaniu `npm install`.

## 17. Kryteria odbioru Etapu 1

Etap 1 powinien zostac odebrany dopiero gdy:

1. Istnieja Enums dla rol i statusow.
2. Kluczowe modele maja Policies.
3. Kluczowe akcje maja Form Requests.
4. Sales nie ma pelnego dostepu do panelu administracyjnego.
5. Jest CRUD uzytkownikow w Filament.
6. Istnieje centralny audit log.
7. Pobranie i usuniecie dowodu jest logowane.
8. Status audytu zmienia sie przez centralny workflow/action, nie przez reczne ustawianie dowolnego statusu w UI.
9. Testy autoryzacji pokrywaja role:
   - `super_admin`
   - `global_admin`
   - `audit_product_admin`
   - `sales`
   - `sales_manager`
   - `delivery_manager`
   - `technical_lead`
   - `auditor`
   - `client_admin`
   - `client_user`
10. `php artisan test`, `./vendor/bin/pint --test`, `composer validate` przechodza.

## 17.1. Kryteria rozpoczecia Etapu 1

Etap 1 mozna rozpoczac dopiero po lacznym spelnieniu ponizszych warunkow:

1. Ten dokument zostal zaakceptowany przez wlasciciela projektu.
2. Commit domykajacy Etap 0 jest wypchniety do GitHub.
3. Repozytorium jest czyste po commicie/pushu.
4. Backend validation jest zielona:
   - `php artisan test`: 72 testy, 299 asercji;
   - `./vendor/bin/pint --test`: PASS;
   - `composer validate`: PASS.
5. Migracje sa potwierdzone:
   - lokalny PostgreSQL: wszystkie migracje `Ran`;
   - czysta SQLite testowa: `migrate:fresh --seed` PASS.
6. Frontend validation jest zielona:
   - `npm install`: PASS;
   - `npm run build`: PASS.
7. W Etapie 1 nie wolno zaczynac typow audytow, kwalifikacji Sales, wycen, OpenAI ani raportow 2.0 przed uporzadkowaniem rol, policies, audit log i CRUD uzytkownikow.

## 18. Rekomendowany nastepny etap

Nastepny logiczny krok: Etap 1 - bezpieczenstwo i fundament.

Pierwszy maly zakres Etapu 1 powinien obejmowac:

1. `App\Enums\UserRole`.
2. Aktualizacje `User::canAccessPanel()` z ograniczeniem Sales.
3. `UserResource` w Filament.
4. Testy dostepu do panelu dla wszystkich docelowych rol.

Nie zaczynac jeszcze typow audytow, kwalifikacji ani OpenAI przed uporzadkowaniem rol i autoryzacji.

## 19. Etap 1A - wynik implementacji

Data wykonania: 2026-08-01

Branch: `refactor/security-foundation`

Etap 1A domyka fundament autoryzacji i rol bez zmian w schemacie bazy danych oraz bez rozpoczynania User CRUD, audit log, MFA, SSO, Sales qualification, pricing, OpenAI, typow audytow ani raportow 2.0.

### Zakres wykonany

1. Dodano `App\Enums\UserRole` dla aktualnie istniejacych rol:
   - `super_admin`
   - `global_admin`
   - `technical_lead`
   - `auditor`
   - `sales`
   - `client`
2. `User::role` jest castowany do `UserRole`, przy zachowaniu kompatybilnosci z obecna kolumna string w tabeli `users`.
3. `User::canAccessPanel()` opiera sie na enumie i dopuszcza tylko:
   - `super_admin`
   - `global_admin`
   - `technical_lead`
4. Sales nie ma pelnego dostepu do panelu Filament.
5. Dodano middleware `role`, ktory:
   - przyjmuje jedna lub wiele rol,
   - blokuje uzytkownikow nieaktywnych,
   - zwraca `403` dla niedozwolonego dostepu.
6. Zabezpieczono kluczowe sekcje routingu zgodnie z zakresem Etapu 1A:
   - `/auditor`
   - `/reviewer`
   - `/dashboard`
   - `/notifications`
   - `/follow-ups`
   - raporty techniczne, biznesowe i sales
   - publikacja, zamykanie, kolejka eksportow i retry
   - `/archive`
   - `/client/portal`
7. Publiczny link klienta `/client/reports/{token}` pozostawiono bez zmian.
8. Dodano centralne Policies:
   - `AuditPolicy`
   - `AuditAnswerAttachmentPolicy`
   - `AuditReportExportPolicy`
   - `AuditPublicationPolicy`
   - `AuditFollowUpTaskPolicy`
9. Kontrolery korzystaja z policies tam, gdzie Etap 1A obejmowal autoryzacje dostepu:
   - praca audytora,
   - weryfikacja lidera,
   - raporty,
   - archiwum,
   - eksporty raportow,
   - portal klienta,
   - follow-up.
10. Zachowano dotychczasowe zachowanie portalu klienta:
    - cudza publikacja zwraca `403`,
    - wlasna wygasla publikacja jest ukryta jako `404`.
11. Dodano testy feature w `tests/Feature/Authorization/RoleAuthorizationTest.php`, w tym:
    - dostep rol zarzadzajacych do sekcji wewnetrznych,
    - blokade Sales w panelu admina i raportach technicznych/biznesowych,
    - izolacje audytora do przypisanych audytow,
    - izolacje klienta do wlasnego portalu,
    - blokade kont nieaktywnych,
    - test IDOR dla zalacznikow,
    - blokade pobierania technicznego eksportu przez Sales.

### Wyniki walidacji Etapu 1A

| Komenda | Wynik |
| --- | --- |
| `php artisan test` | PASS: 81 testow, 367 asercji |
| `./vendor/bin/pint --test` | PASS |
| `composer validate` | PASS: `composer.json is valid` |
| `php artisan migrate:status` | PASS po dopuszczeniu lokalnego PostgreSQL; wszystkie migracje `Ran` |
| `npm run build` | PASS: Vite 7.3.6, 56 modules transformed |

Znane ograniczenia srodowiska:

- `php artisan migrate:status` wymaga dostepu do lokalnego PostgreSQL `127.0.0.1:5432` poza sandboxem narzedziowym.
- Testy feature uzywaja SQLite in-memory i nie wymagaja lokalnego PostgreSQL.
- Nie dodano migracji w Etapie 1A.
- Nie dodano zewnetrznego pakietu permissions.
- `node_modules`, `vendor`, `.env`, lokalne bazy testowe i assety builda pozostaja poza commitem.

### Kryteria rozpoczecia Etapu 1B

Etap 1B mozna rozpoczac dopiero po lacznym spelnieniu warunkow:

1. Branch `refactor/security-foundation` jest wypchniety do GitHub.
2. Repozytorium jest czyste po commicie/pushu Etapu 1A.
3. Pelna walidacja Etapu 1A pozostaje zielona:
   - `php artisan test`
   - `./vendor/bin/pint --test`
   - `composer validate`
   - `php artisan migrate:status`
   - `npm run build`
4. Zakres Etapu 1B zostanie jawnie zatwierdzony przez wlasciciela projektu.
5. Etap 1B powinien zaczac sie od kolejnego elementu fundamentu bezpieczenstwa, rekomendacyjnie:
   - CRUD uzytkownikow w Filament,
   - audit log,
   - Form Requests,
   - workflow/status actions.

Nie rozpoczynac Etapu 2 ani funkcji biznesowych 2.0 przed zamknieciem calego Etapu 1.

## 20. Etap 1B - wynik implementacji

Data wykonania: 2026-08-03

Branch: `codex/etap-1b-security-foundation`

Etap 1B obejmuje zarzadzanie uzytkownikami, centralny audit trail i wydzielenie walidacji kluczowych akcji. Nie rozpoczeto Etapu 2 ani przebudowy workflow statusow.

### Zakres wykonany

1. Dodano `UserResource` w Filament:
   - tworzenie i edycja kont,
   - role z `UserRole`,
   - aktywnosc konta,
   - przypisanie konta klienta do firmy,
   - wyszukiwanie i filtrowanie.
2. Dodano `UserPolicy`:
   - dostep do zarzadzania maja `super_admin` i `global_admin`,
   - `global_admin` nie moze zarzadzac kontem ani rola `super_admin`,
   - tylko `super_admin` moze usuwac inne konta.
3. Dodano zabezpieczenia modelu konta:
   - uzytkownik nie moze odebrac sobie roli ani zdezaktywowac wlasnego konta,
   - `global_admin` nie moze nadac ani zmienic roli `super_admin`,
   - przypisanie klienta jest automatycznie czyszczone dla rol wewnetrznych.
4. Dodano centralny audit trail:
   - migracja i model `AuditLog`,
   - usluga `AuditTrail`,
   - aktor, zdarzenie, obiekt, stare i nowe wartosci, metadane, IP, user agent i data,
   - filtrowany, tylko do odczytu `AuditLogResource` w Filament,
   - automatyczny audit zmian uzytkownikow przez `UserObserver`,
   - redakcja pol wrazliwych w centralnej usludze.
5. Rejestrowane sa m.in.:
   - utworzenie, aktualizacja i usuniecie konta,
   - pobranie i usuniecie dowodu,
   - wyslanie audytu, akceptacja techniczna i zwrot do poprawek,
   - publikacja, pobranie i eksport raportu,
   - zamkniecie audytu,
   - zmiana follow-upu,
   - status i feedback klienta.
6. Dodano Form Requests dla kluczowych operacji zapisu:
   - logowanie,
   - zapis odpowiedzi audytora i zalacznikow,
   - akceptacja i zwrot audytu,
   - publikacja i kolejka eksportu raportu,
   - zamkniecie audytu,
   - aktualizacja follow-upu,
   - status i feedback klienta.
7. Dodano testy dostepu, zabezpieczen kont oraz audit trailu dowodow i workflow.
8. Recznie sprawdzono w Filament:
   - menu `Bezpieczenstwo`,
   - liste i formularz uzytkownikow,
   - dziennik zdarzen,
   - brak problemow z ukladem nowych ekranow.

### Wyniki walidacji Etapu 1B

| Komenda | Wynik |
| --- | --- |
| `php artisan test` | PASS: 88 testow, 394 asercje |
| `./vendor/bin/pint --test` | PASS |
| `composer validate` | PASS: `composer.json is valid` |
| `php artisan migrate:status` | PASS: wszystkie migracje `Ran`, `audit_logs` w batchu 9 |
| `npm run build` | PASS: Vite 7.3.6, 56 modules transformed |

### Ograniczenia i dalszy zakres Etapu 1

- Audit log nie ma jeszcze polityki retencji ani eksportu.
- Dezaktywacja konta nie uniewaznia jeszcze wszystkich aktywnych sesji.
- MFA pozostaje flaga danych; pelny mechanizm MFA i SSO jest poza Etapem 1B.
- Filtry list pozostaja walidowane lokalnie w kontrolerach; operacje zapisu maja Form Requests.
- Statusy audytu nadal sa stringami, a przejscia nie korzystaja jeszcze z centralnego workflow service.
- Przed Etapem 2 nalezy domknac pozostala czesc Etapu 1: `AuditStatus`, centralne akcje przejsc statusow i testy dozwolonych przejsc.
