# CODEX CONTEXT - Audytor IT

Ten plik jest przeznaczony dla przyszlych rozmow z Codex. Ma szybko ustawic kontekst projektu bez ponownego odkrywania wszystkiego od zera.

Ostatnia analiza wskrzeszeniowa projektu: 2026-08-01.

Najpierw przeczytaj `PROJECT_RECOVERY_AUDIT.md`. To aktualna mapa projektu: co dziala, czego brakuje, jakie sa ryzyka i jaka jest rekomendowana kolejnosc dalszych prac.

## Cel aplikacji

Audytor IT to wewnetrzna aplikacja Global IT do standaryzowanych audytow IT. System prowadzi od konfiguracji biblioteki audytowej, przez prace audytora i weryfikacje lidera, do raportow, publikacji klientowi oraz follow-up.

Glowne role:

- `super_admin`
- `global_admin`
- `technical_lead`
- `auditor`
- `sales`
- `client`

Haslo kont testowych: `password`.

## Stack

- Laravel 12.
- PHP `^8.2`.
- Filament 4 pod `/admin`.
- Blade views, brak SPA.
- Vite/Tailwind dostepne, ale UI operacyjne jest glownie Blade + inline CSS.
- PostgreSQL lokalnie wedlug `.env.example`.
- SQLite `:memory:` w testach.
- Laravel Queue `database`.
- Storage local/private.
- PWA: manifest, service worker, offline fallback, IndexedDB draft.

## Najwazniejsze komendy

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
php artisan serve
```

Testy i jakosc:

```bash
php artisan test
./vendor/bin/pint --test
composer validate
npm run build
```

Worker:

```bash
php artisan queue:work --queue=default --tries=3 --timeout=120
```

## Najwazniejsze adresy

- `/admin` - Filament admin.
- `/auditor/login`, `/auditor`, `/auditor/audits/{audit}` - audytor.
- `/reviewer` - lider techniczny.
- `/reports/audits/{audit}/technical`
- `/reports/audits/{audit}/business`
- `/reports/audits/{audit}/sales`
- `/reports/exports`
- `/client/login`, `/client/portal`
- `/client/reports/{token}`
- `/dashboard`, `/archive`, `/follow-ups`, `/notifications`

## Struktura

- `app/Filament/Resources` - admin CRUD.
- `app/Http/Controllers` - workflow web.
- `app/Models` - modele Eloquent.
- `app/Support` - logika pomocnicza.
- `app/Jobs/GenerateAuditReportExport.php` - eksport PDF/DOCX w kolejce.
- `database/migrations` - schemat.
- `database/seeders/DatabaseSeeder.php` - dane testowe i biblioteka audytu.
- `resources/views` - UI Blade.
- `public/service-worker.js`, `public/offline-audit.js`, `public/manifest.webmanifest` - PWA.
- `tests/Feature` - glowne testy.

## Obecny stan

MVP jest funkcjonalne. Pokryte sa:

- admin CRUD,
- audyty i przypisania,
- praca audytora,
- walidacja kompletosci,
- zalaczniki,
- weryfikacja lidera,
- raporty HTML,
- PDF/DOCX,
- kolejka eksportow i historia eksportow,
- publikacja klientowi,
- portal klienta,
- feedback klienta,
- follow-up,
- dashboard,
- archiwum,
- powiadomienia,
- PWA podstawowe.

Baseline Git jest ustalony. Etap 1A zostal scalony do `main`, a Etap 1B jest rozwijany na `codex/etap-1b-security-foundation`.

Ostatnio dokumentacja z lipca wskazywala przechodzace testy feature. Przy wznowieniu prac trzeba ponownie uruchomic `php artisan test`, `./vendor/bin/pint --test`, `composer validate` i, jesli zaleznosci Node sa dostepne, `npm run build`.

## Wazne decyzje projektowe

- Checklisty i rekomendacje nie sa zakodowane w UI; sa zarzadzane w bazie przez Filament.
- Sales report jest oddzielony od raportow klienta.
- Pliki audytowe i eksporty sa prywatne na dysku `local`.
- Raporty sa dostepne dopiero po akceptacji technicznej.
- Follow-up powstaje z rekomendacji zaakceptowanych przez klienta.
- `recommendations.tags_json` musi byc `jsonb` na PostgreSQL. Zwykly `json` powodowal blad Filament przy `distinct`.
- Menu admina zostalo przestawione z grup etapowych na grupy biznesowe: Audyty, Biblioteka audytu, Klienci, Raporty i operacje.

## Znane ograniczenia

- PDF/DOCX sa minimalne technicznie, bez finalnego brandingu i zaawansowanego layoutu.
- Offline nie jest pelnym syncem; IndexedDB trzyma drafty, ale nie ma pelnej automatycznej wysylki do backendu.
- Etap 1A dodal Enum rol, middleware i Policies dla kluczowych modeli.
- Etap 1B dodal CRUD uzytkownikow w Filament, centralny `AuditLog` i Form Requests dla kluczowych akcji.
- Logowanie audytora i klienta jest ograniczone wspolnym limiterem `login`: 5 prob/min dla e-mail + IP.
- Brak E2E/browser tests.
- Brak realnych integracji zewnetrznych.
- `docs/architecture.md` zawiera starsza wzmianke o Inertia/Vue jako docelowym stacku, ale realny projekt uzywa Blade/Filament.
- W repo widoczny jest `app/.DS_Store`; nie usuwac bez zgody uzytkownika, ale warto posprzatac.
- Sales nie ma dostepu do panelu Filament.

## Zalecany sposob dalszej pracy z Codex

1. Najpierw czytaj istniejace wzorce.
   - Filament resources w `app/Filament/Resources`.
   - Kontrolery workflow w `app/Http/Controllers`.
   - Testy w `tests/Feature`.

2. Przy zmianach backendowych dodawaj lub aktualizuj Feature Tests.

3. Nie rob duzych refaktorow bez potrzeby.
   - Projekt jest MVP i ma duzo logiki w kontrolerach.
   - Najpierw maly, bezpieczny krok, potem testy.

4. Przy zmianach bazodanowych pamietaj o PostgreSQL i SQLite testowym.
   - Migracje z raw SQL dla Postgres powinny sprawdzac `DB::getDriverName()`.

5. Przy zmianach UI sprawdzaj mobile/ciasne widoki.
   - W przeszlosci Filament toggle labels nachodzily na siebie.
   - Polskie etykiety bywaja dlugie.

6. Przy eksportach raportow pamietaj o workerze kolejki.
   - `audit_report_exports` ma statusy `queued`, `processing`, `completed`, `failed`.
   - Job zapisuje pliki do `report-exports/audit-{id}` na dysku `local`.

7. Po zmianach uruchom:

```bash
php artisan test
./vendor/bin/pint --test
composer validate
```

Jesli zmieniasz frontend assets:

```bash
npm run build
```

## Najlepsze nastepne zadanie

Najbardziej wartosciowe nastepne prace:

1. Utrzymywac prace etapowe na osobnych galeziach i scalac przez PR po pelnej walidacji.
2. Przejsc recznie scenariusz end-to-end na danych testowych.
3. Domknac pozostala czesc Etapu 1: Enum/status workflow i centralne akcje przejsc statusow.
4. Dopracowac PDF/DOCX pod branding Global IT.
5. UAT biblioteki pytan i rekomendacji.
6. Rozszerzac Policies/Form Requests wraz z nowymi modulami.
7. Pelny offline sync albo jasne ograniczenie offline tylko do draftow.
