# Audytor IT - technical documentation

Aktualizacja analizy: 2026-08-01.

Dokument startowy do wznowienia prac: `PROJECT_RECOVERY_AUDIT.md`.

## Technologia

- PHP: wymagane `^8.2`.
- Framework: Laravel `^12.0`.
- Admin panel: Filament `^4.0`.
- Frontend build: Vite `^7`, Tailwind CSS `^4`, laravel-vite-plugin.
- Testy: PHPUnit 11, Laravel Feature Tests.
- Formatowanie PHP: Laravel Pint.
- Baza lokalna wedlug `.env.example`: PostgreSQL `audytor_it`.
- Baza testowa: SQLite `:memory:` z `phpunit.xml`.
- Kolejki: Laravel Queue, domyslnie `database`; testy uzywaja `sync`.
- Storage: Laravel filesystem, prywatne pliki na dysku `local`.

## Architektura

Projekt jest monolitem Laravel:

- Filament odpowiada za administracyjny CRUD.
- Kontrolery w `app/Http/Controllers` obsluguja workflow audytora, lidera, raportow i klienta.
- Widoki Blade w `resources/views` tworza frontend aplikacyjny.
- Modele Eloquent w `app/Models` definiuja relacje domenowe.
- Migracje w `database/migrations` definiuja schemat danych.
- `database/seeders/DatabaseSeeder.php` tworzy dane testowe i startowa biblioteke audytu.
- Support klasy w `app/Support` trzymaja logike pomocnicza: powiadomienia, dane raportow, follow-up, proste generatory PDF/DOCX.
- Job `GenerateAuditReportExport` generuje pliki raportow w tle.

Nie ma oddzielnego API ani SPA. Mimo starej notatki w `docs/architecture.md`, projekt aktualnie nie uzywa Inertia ani Vue.

## Stan wskrzeszenia projektu

Projekt jest funkcjonalnym MVP, ale przed dalszym rozwojem wymaga porzadkow organizacyjno-technicznych:

- ustalic baseline w Git, bo obecny `git status --short` pokazuje projekt jako praktycznie caly niezatwierdzony;
- ponownie uruchomic testy i walidacje po dluzszej przerwie;
- przejsc reczny scenariusz end-to-end;
- dodac administracje uzytkownikami w Filament;
- dopracowac docelowe raporty PDF/DOCX;
- zdecydowac, czy offline ma byc pelnym syncem czy tylko lokalnym draftem;
- docelowo przeniesc role/autoryzacje do Policies i walidacje do Form Requests.

## Struktura katalogow

- `app/Filament/Resources` - zasoby admina Filament: klienci, lokalizacje, szablony, moduly, pytania, rekomendacje, audyty.
- `app/Http/Controllers` - kontrolery web workflow.
- `app/Jobs` - joby kolejkowe.
- `app/Models` - encje Eloquent.
- `app/Providers/Filament` - konfiguracja panelu admina.
- `app/Support` - klasy pomocnicze domeny.
- `config` - konfiguracja Laravel.
- `database/migrations` - schemat bazy.
- `database/seeders` - dane startowe.
- `public` - PWA assets, service worker, offline page, zbudowane assets Filament/Vite.
- `resources/views` - widoki Blade.
- `routes/web.php` - routing web.
- `tests/Feature` - testy funkcjonalne.
- `docs` - dodatkowe notatki architektoniczne i checklisty.

## Najwazniejsze pliki

- `routes/web.php` - centralna mapa tras aplikacji.
- `app/Providers/Filament/AdminPanelProvider.php` - konfiguracja panelu `/admin`, menu i zasobow Filament.
- `app/Models/Audit.php` - glowna encja audytu i statusy.
- `app/Http/Controllers/AuditorAuditController.php` - praca audytora, odpowiedzi, uploady, wysylka do weryfikacji.
- `app/Http/Controllers/TechnicalReviewController.php` - akceptacja techniczna i zwrot do poprawek.
- `app/Http/Controllers/AuditReportController.php` - raporty HTML, PDF, DOCX, publikacja i kolejka eksportow.
- `app/Http/Controllers/ReportExportController.php` - historia i pobieranie wygenerowanych eksportow.
- `app/Http/Controllers/ClientPortalController.php` - portal klienta i feedback.
- `app/Support/AuditReportData.php` - dane tekstowe do PDF/DOCX.
- `app/Support/SimplePdf.php` i `app/Support/SimpleDocx.php` - minimalne generatory plikow.
- `app/Support/FollowUpTaskBuilder.php` - tworzenie zadan follow-up z zaakceptowanych rekomendacji.
- `database/seeders/DatabaseSeeder.php` - konta testowe, klient testowy, moduly, pytania, rekomendacje, audyt testowy.
- `public/service-worker.js` i `public/offline-audit.js` - PWA/offline.

## Baza danych

Glowne tabele:

- `users` - uzytkownicy i role (`super_admin`, `global_admin`, `technical_lead`, `auditor`, `sales`, `client`).
- `clients`, `client_locations` - klienci i lokalizacje.
- `audit_modules`, `audit_templates`, `audit_template_modules` - biblioteka modulow i szablonow.
- `audit_questions` - pytania, typy pol, walidacje, wymagane zdjecia/screeny, ryzyko.
- `recommendations`, `audit_question_recommendation` - baza rekomendacji i powiazania z pytaniami.
- `audits`, `audit_assignees`, `audit_selected_modules` - audyty, osoby i wybrane moduly.
- `audit_answers`, `audit_answer_attachments` - odpowiedzi i prywatne dowody.
- `audit_reviews` - decyzje lidera technicznego.
- `audit_publications` - publikacje dla klienta, tokeny, status klienta i feedback.
- `audit_closures` - zamkniecia audytow.
- `audit_notifications` - wewnetrzne powiadomienia.
- `audit_follow_up_tasks` - plan dzialan poaudytowych.
- `audit_report_exports` - kolejka/status wygenerowanych PDF/DOCX.
- `jobs`, `failed_jobs`, `job_batches` - Laravel queue.

Uwaga PostgreSQL: `recommendations.tags_json` jest `jsonb`, bo zwykly `json` powodowal problem z `select distinct recommendations.*` w Filament.

## Modele / encje

- `User` - implementuje `FilamentUser`; kontroluje dostep do admin panelu.
- `Client` i `ClientLocation` - dane klienta i miejsca audytu.
- `AuditModule`, `AuditTemplate`, `AuditTemplateModule`, `AuditQuestion` - biblioteka audytu.
- `Recommendation` - rekomendacje techniczne, biznesowe i sprzedazowe.
- `Audit` - glowny agregat: klient, lokalizacja, status, przypisania, moduly, odpowiedzi, publikacje, follow-up, eksporty.
- `AuditAnswer` - odpowiedz, N/D, ryzyko, rekomendacja audytora, status sync.
- `AuditAnswerAttachment` - dowody audytowe na prywatnym storage.
- `AuditReview` - decyzje lidera.
- `AuditPublication` - publikacja dla klienta, token, status klienta, feedback.
- `AuditFollowUpTask` - zadania poaudytowe.
- `AuditReportExport` - eksporty PDF/DOCX.
- `AuditNotification` - powiadomienia wewnetrzne.
- `AuditClosure` - zamkniecie audytu.

## Routing

Wszystkie trasy sa w `routes/web.php`.

Grupy:

- `guest`: logowanie audytora i klienta.
- `/auditor`: lista audytow, szczegol audytu, zapis odpowiedzi, upload/pobieranie/usuwanie zalacznikow, wysylka do weryfikacji.
- `/reviewer`: lista audytow do weryfikacji, szczegol, akceptacja, zwrot do poprawek.
- `/dashboard`: KPI i CSV.
- `/notifications`: lista i oznaczanie powiadomien.
- `/follow-ups`: plan wdrozen i CSV.
- `/reports`: raporty HTML, PDF, DOCX, eksporty, publikacja, zamkniecie.
- `/archive`: archiwum i CSV.
- `/client/portal`: zalogowany portal klienta.
- `/client/reports/{token}`: publiczny link raportu.

## API

Projekt nie ma osobnego REST/JSON API. Interakcje sa realizowane przez:

- Laravel web routes,
- formularze HTML/Blade,
- Livewire/Filament w panelu admina,
- pobieranie plikow jako response/stream.

Potencjalne API publiczne lub integracyjne trzeba dopiero zaprojektowac.

## Frontend

- Panel admina: Filament 4.
- Ekrany operacyjne: Blade templates w `resources/views`.
- Layout audytora: `resources/views/components/auditor/layout.blade.php`.
- Raporty HTML: `resources/views/reports/*.blade.php`.
- Portal klienta: `resources/views/client-portal` i `resources/views/client-reports`.
- CSS w duzej mierze inline w layoutach Blade; `resources/css/app.css` i Vite sa dostepne, ale aplikacja nie jest SPA.
- PWA: `public/manifest.webmanifest`, `public/service-worker.js`, `public/offline.html`, `public/offline-audit.js`.

## Backend

Backend opiera sie na kontrolerach i modelach Eloquent. Autoryzacja jest obecnie w duzej mierze w metodach prywatnych kontrolerow, a nie w policy/form requestach.

Najwazniejsze workflow:

1. Admin tworzy klienta, lokalizacje, szablon, moduly, pytania, rekomendacje i audyt.
2. Audytor wypelnia pytania i dowody.
3. Audytor wysyla kompletny audyt do weryfikacji.
4. Lider zatwierdza lub zwraca do poprawek.
5. System udostepnia raporty i eksporty.
6. Lider publikuje raport klientowi.
7. Klient nadaje status, komentarz i akceptuje rekomendacje.
8. System tworzy follow-up.
9. Lider/admin zamyka audyt i trafia on do archiwum.

## Autoryzacja i role

Role sa przechowywane w `users.role`.

- `super_admin`: pelny dostep do panelu i operacji.
- `global_admin`: dostep administracyjny podobny do super admina.
- `technical_lead`: weryfikacja, dashboard, raporty, publikacja, archiwum i follow-up dla widocznych audytow.
- `auditor`: praca tylko na przypisanych audytach; brak dostepu do Filament.
- `sales`: raport sprzedazowy i obszary operacyjne zwiazane z raportami/follow-up.
- `client`: tylko portal klienta i wlasne publikacje.

Dostep do panelu Filament kontroluje `User::canAccessPanel`.

## Integracje zewnetrzne

Brak realnych integracji zewnetrznych w kodzie produkcyjnym.

Obecne mechanizmy infrastrukturalne:

- Filament jako pakiet admin UI.
- Laravel Queue.
- Laravel Storage local.
- PWA/service worker.
- Mail skonfigurowany domyslnie jako `log` w `.env.example`.
- Redis/SQS/S3 sa obecne w standardowej konfiguracji Laravel, ale nie sa aktywnie uzywane w logice biznesowej.

## Konfiguracja srodowiska

Minimalnie:

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
php artisan serve
```

Dla assetow:

```bash
npm install
npm run build
```

Dla pracy deweloperskiej:

```bash
composer run dev
```

Ta komenda uruchamia serwer Laravel, kolejke, logi `pail` i Vite przez `concurrently`.

Worker produkcyjny/kolejkowy:

```bash
php artisan queue:work --queue=default --tries=3 --timeout=120
```

Testy:

```bash
php artisan test
./vendor/bin/pint --test
composer validate
npm run build
```

## Dane startowe

Seeder tworzy:

- 6 kont testowych.
- klienta `Klient Testowy Sp. z o.o.` i 3 lokalizacje.
- szablon `Audyt podstawowy IT`.
- 5 modulow: UTM/firewall, Switche, Serwery, Microsoft 365, Backup.
- pytania dla kazdego modulu.
- 5 rekomendacji.
- testowy audyt z przypisanym audytorem i liderem.

Haslo testowe: `password`.
