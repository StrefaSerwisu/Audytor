# Audytor IT - dokumentacja wskrzeszenia projektu

Data analizy: 2026-08-01

Ten dokument jest praktyczna mapa projektu po przerwie. Ma pomoc szybko zrozumiec, co juz mamy, czego brakuje, jakie sa ryzyka i od czego najlepiej zaczac dalsze prace.

## 1. Streszczenie

Audytor IT to monolityczna aplikacja Laravel dla Global IT do prowadzenia standaryzowanych audytow infrastruktury IT u klientow. Projekt ma juz funkcjonalny MVP: panel administracyjny, biblioteke audytowa, prace audytora, weryfikacje lidera, raporty, publikacje klientowi, portal klienta, follow-up, dashboard, archiwum, powiadomienia oraz podstawowe PWA/offline.

Najwazniejszy wniosek: projekt nie jest pusty ani prototypowy w sensie technicznym. To dzialajacy MVP, ale wymaga uporzadkowania przed dalszym rozwojem: baseline w Git, UAT biznesowego, dopracowania raportow PDF/DOCX, CRUD uzytkownikow, porzadniejszej autoryzacji i decyzji o offline.

## 2. Technologia

| Element | Stan |
| --- | --- |
| Backend | Laravel 12 |
| PHP | `^8.2` |
| Admin panel | Filament 4 pod `/admin` |
| Frontend operacyjny | Blade + CSS w widokach |
| Build frontend | Vite 7, Tailwind 4, laravel-vite-plugin |
| Baza lokalna | PostgreSQL `audytor_it` wedlug `.env.example` |
| Baza testowa | SQLite `:memory:` w `phpunit.xml` |
| Kolejka | Laravel Queue, domyslnie `database` |
| Storage | Laravel filesystem, pliki prywatne na dysku `local` |
| Testy | PHPUnit 11, Laravel Feature Tests |
| Formatowanie | Laravel Pint |
| PWA | Manifest, service worker, offline page, IndexedDB draft |

Nie ma SPA, Inertia ani publicznego REST API. Interakcje sa realizowane przez web routes, formularze Blade i komponenty Filament/Livewire.

## 3. Struktura projektu

| Sciezka | Znaczenie |
| --- | --- |
| `app/Models` | Modele Eloquent i relacje domenowe |
| `app/Http/Controllers` | Kontrolery workflow: audytor, lider, raporty, klient, dashboard |
| `app/Filament/Resources` | CRUD admina Filament |
| `app/Providers/Filament/AdminPanelProvider.php` | Konfiguracja panelu `/admin` i menu |
| `app/Support` | Logika pomocnicza: raporty, PDF/DOCX, follow-up, powiadomienia |
| `app/Jobs/GenerateAuditReportExport.php` | Kolejkowe generowanie PDF/DOCX |
| `database/migrations` | Schemat bazy danych |
| `database/seeders/DatabaseSeeder.php` | Dane startowe i konta testowe |
| `resources/views` | Widoki Blade aplikacji operacyjnej i raportow |
| `routes/web.php` | Cala mapa tras web |
| `public` | PWA assets, service worker, offline scripts, front controller |
| `tests/Feature` | Testy funkcjonalne glownego workflow |
| `docs` | Starsze dokumenty architektoniczne i checklisty |

## 4. Pliki konfiguracyjne

| Plik | Uwagi |
| --- | --- |
| `composer.json` | Definiuje Laravel 12, Filament 4, PHPUnit, Pint oraz skrypty `setup`, `dev`, `test` |
| `package.json` | Definiuje `npm run dev` i `npm run build` dla Vite |
| `.env.example` | PostgreSQL: `audytor_it`, `postgres/postgres`, queue/cache/session na database |
| `phpunit.xml` | Testy na SQLite `:memory:`, `QUEUE_CONNECTION=sync` |
| `vite.config.js` | Standardowy Vite dla Laravel |
| `config/database.php` | Standard Laravel, uzywane pgsql lokalnie i sqlite w testach |
| `config/queue.php` | Queue database gotowa do eksportow raportow |
| `config/filesystems.php` | Storage local; pliki raportow i dowody nie sa publiczne |

## 5. Uruchomienie lokalne

Minimalny start:

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
php artisan serve --host=127.0.0.1 --port=8000
```

Panel admina:

```text
http://127.0.0.1:8000/admin
```

Widok audytora:

```text
http://127.0.0.1:8000/auditor
```

Portal klienta:

```text
http://127.0.0.1:8000/client/login
```

Worker kolejki dla eksportow:

```bash
php artisan queue:work --queue=default --tries=3 --timeout=120
```

Komenda developerska z `composer.json`:

```bash
composer run dev
```

Uruchamia serwer Laravel, queue listener, logi Laravel Pail i Vite przez `concurrently`.

## 6. Konta testowe

Haslo dla wszystkich kont: `password`.

| Rola | Email | Gdzie uzywac |
| --- | --- | --- |
| Super Admin | `superadmin@globalit.test` | `/admin`, caly system |
| Global Admin | `admin@globalit.test` | `/admin`, caly system administracyjny |
| Lider techniczny | `lider@globalit.test` | `/reviewer`, raporty, dashboard, archiwum |
| Audytor | `audytor@globalit.test` | `/auditor` |
| Sales | `sales@globalit.test` | raport sprzedazowy, eksporty/follow-up wedlug uprawnien |
| Klient | `klient@globalit.test` | `/client/login`, portal klienta |

## 7. Routing i ekrany

| Obszar | URL | Funkcja |
| --- | --- | --- |
| Root | `/` | Redirect do `/auditor` |
| Admin | `/admin` | Panel Filament |
| Login audytora | `/auditor/login` | Logowanie wewnetrzne |
| Audytor | `/auditor` | Lista przypisanych audytow |
| Szczegol audytu | `/auditor/audits/{audit}` | Pytania, odpowiedzi, dowody, wysylka |
| Weryfikacja | `/reviewer` | Lista audytow do weryfikacji |
| Szczegol weryfikacji | `/reviewer/audits/{audit}` | Akceptacja lub zwrot |
| Dashboard | `/dashboard` | KPI, statusy, mapa ryzyka |
| Powiadomienia | `/notifications` | Lista i przypomnienia |
| Follow-up | `/follow-ups` | Plan dzialan poaudytowych |
| Eksporty raportow | `/reports/exports` | Historia PDF/DOCX i retry |
| Raport techniczny | `/reports/audits/{audit}/technical` | Raport HTML |
| Raport biznesowy | `/reports/audits/{audit}/business` | Raport HTML dla klienta/zarzadu |
| Raport sales | `/reports/audits/{audit}/sales` | Raport wewnetrzny sprzedazowy |
| Archiwum | `/archive` | Zamkniete/anulowane audyty |
| Login klienta | `/client/login` | Portal klienta |
| Portal klienta | `/client/portal` | Lista aktywnych raportow klienta |
| Link publiczny | `/client/reports/{token}` | Publiczny widok raportu po tokenie |

## 8. Baza danych i modele

### Najwazniejsze tabele

| Tabela | Znaczenie |
| --- | --- |
| `users` | Uzytkownicy, role, aktywnosc, przypisanie do klienta |
| `clients` | Klienci Global IT |
| `client_locations` | Lokalizacje klientow |
| `audit_modules` | Moduly audytowe, np. UTM, Switche, Backup |
| `audit_templates` | Szablony audytu |
| `audit_template_modules` | Moduly przypisane do szablonow |
| `audit_questions` | Pytania, typy pol, wymagania, ryzyko |
| `recommendations` | Baza rekomendacji technicznych/biznesowych/sales |
| `audit_question_recommendation` | Relacja pytan i rekomendacji |
| `audits` | Glowne rekordy audytow |
| `audit_assignees` | Audytorzy przypisani do audytu |
| `audit_selected_modules` | Moduly wybrane dla konkretnego audytu |
| `audit_answers` | Odpowiedzi audytora |
| `audit_answer_attachments` | Dowody: zdjecia, screenshoty, pliki |
| `audit_reviews` | Decyzje lidera technicznego |
| `audit_publications` | Publikacje raportu klientowi |
| `audit_follow_up_tasks` | Zadania poaudytowe |
| `audit_notifications` | Powiadomienia wewnetrzne |
| `audit_report_exports` | Kolejka/status eksportow PDF/DOCX |
| `audit_closures` | Historia zamkniec audytow |

### Statusy audytu

`Audit::STATUSES`:

- `draft`
- `scheduled`
- `in_progress`
- `syncing`
- `needs_completion`
- `submitted_for_review`
- `changes_requested`
- `technically_approved`
- `reports_generated`
- `published_to_client`
- `closed`
- `cancelled`

### Ważna decyzja PostgreSQL

Kolumna `recommendations.tags_json` jest `jsonb`. To wazne, bo zwykly typ `json` powodowal blad PostgreSQL przy zapytaniu Filament z `distinct recommendations.*`: PostgreSQL nie ma operatora rownosci dla `json`.

## 9. Panel admina

Panel admina jest w Filament pod `/admin`.

Zasoby CRUD:

| Zasob | URL | Status |
| --- | --- | --- |
| Klienci | `/admin/clients` | Dziala |
| Lokalizacje | `/admin/client-locations` | Dziala |
| Szablony audytow | `/admin/audit-templates` | Dziala |
| Moduly audytowe | `/admin/audit-modules` | Dziala |
| Pytania audytowe | `/admin/audit-questions` | Dziala |
| Rekomendacje | `/admin/recommendations` | Dziala |
| Audyty | `/admin/audits` | Dziala |

Menu jest pogrupowane biznesowo:

- `Audyty`
- `Biblioteka audytu`
- `Klienci`
- `Raporty i operacje`

Brak wazny: nie ma jeszcze CRUD uzytkownikow w panelu admina. Konta istnieja z seedera.

## 10. Role i autoryzacja

Role sa zapisane w `users.role`.

| Rola | Dostep |
| --- | --- |
| `super_admin` | Pelny dostep administracyjny i operacyjny |
| `global_admin` | Dostep administracyjny i operacyjny |
| `technical_lead` | Dostep do Filament, weryfikacji, raportow, dashboardu, archiwum i follow-up |
| `auditor` | Tylko przypisane audyty w `/auditor`; brak Filament |
| `sales` | Dostep do Filament wedlug `canAccessPanel`, raportu sales i wybranych operacji |
| `client` | Portal klienta i wlasne publikacje |

Dostep do panelu Filament kontroluje `User::canAccessPanel`.

Ograniczenie: autoryzacja jest rozproszona w metodach prywatnych kontrolerow. Dla dalszego rozwoju warto wprowadzic Laravel Policies i Form Requests.

## 11. Aktualny workflow biznesowy

1. Admin tworzy klienta i lokalizacje.
2. Admin buduje biblioteke audytu: moduly, pytania, rekomendacje, szablony.
3. Admin tworzy audyt, wybiera klienta, lokalizacje, szablon, moduly, audytorow i lidera.
4. Audytor loguje sie do `/auditor`, otwiera przypisany audyt.
5. Audytor odpowiada na pytania, dodaje komentarze, ryzyko, rekomendacje i dowody.
6. System blokuje wysylke, jezeli brakuje wymaganych odpowiedzi/dowodow/rekomendacji.
7. Audytor wysyla audyt do weryfikacji.
8. Lider otwiera `/reviewer`, zatwierdza lub zwraca do poprawek.
9. Po akceptacji dostepne sa raporty techniczny, biznesowy i sales.
10. Raporty mozna pobrac bezposrednio jako PDF/DOCX albo zlecic eksport do kolejki.
11. Lider/admin publikuje raport klientowi.
12. Klient widzi raport przez portal lub token, moze ustawic status i zaakceptowac rekomendacje.
13. Z zaakceptowanych rekomendacji tworza sie zadania follow-up.
14. Lider/admin zamyka audyt, ktory trafia do archiwum.

## 12. Obecny stan funkcji

| Funkcja | Status | Uwagi |
| --- | --- | --- |
| Panel admina | Dziala | CRUD glownych encji istnieje |
| CRUD uzytkownikow | Brak | Priorytet do dalszej pracy |
| Biblioteka audytu | Dziala | Wymaga UAT merytorycznego |
| Dane seed | Dziala | Demo klient, moduly, pytania, rekomendacje, audyt |
| Praca audytora | Dziala | Lista, szczegol, odpowiedzi, dowody |
| Walidacje kompletosci | Dziala | Wymagane pola, ryzyko, dowody, N/D |
| Upload dowodow | Dziala | Prywatny local storage |
| Weryfikacja lidera | Dziala | Akceptacja i zwrot |
| Raport HTML techniczny | Dziala | Po akceptacji technicznej |
| Raport HTML biznesowy | Dziala | Po akceptacji technicznej |
| Raport sales | Dziala | Wewnetrzny |
| PDF/DOCX | Czesciowo dziala | Technicznie generuje pliki, wizualnie minimalne |
| Eksporty kolejkowe | Dziala | Wymaga stalego workera w produkcji |
| Publikacja klientowi | Dziala | Token i portal |
| Portal klienta | Dziala | Status, komentarz, rekomendacje |
| Follow-up | Dziala | Z zaakceptowanych rekomendacji |
| Dashboard KPI | Dziala | Podstawowe liczniki i mapa ryzyka |
| Archiwum | Dziala | Filtry i CSV |
| Powiadomienia | Dziala | Wewnetrzne DB notifications |
| Mail/push | Brak | Mail w `.env.example` jest `log` |
| PWA | Czesciowo dziala | Fallback i cache |
| Offline sync | Czesciowo dziala | IndexedDB draft, brak pelnej automatycznej synchronizacji |
| API REST | Brak | Brak osobnego API |
| Integracje zewnetrzne | Brak | Brak CRM/SSO/S3/email real integration |
| Testy automatyczne | Dziala | Feature tests istnieja |
| Testy E2E/browser | Brak | Potrzebne przed produkcja |

## 13. Testy i jakosc

Dostepne komendy:

```bash
php artisan test
./vendor/bin/pint --test
composer validate
npm run build
```

Wynik walidacji z 2026-08-01:

| Komenda | Wynik |
| --- | --- |
| `php artisan test` | PASS: 72 testy, 299 asercji |
| `./vendor/bin/pint --test` | PASS |
| `composer validate` | PASS: `composer.json` valid |
| `npm run build` | FAIL lokalny: `sh: vite: command not found`; brakuje zaleznosci Node / `node_modules` |

Testy feature obejmuja m.in.:

- seeder danych startowych,
- dostep do Filament,
- klientow i lokalizacje,
- szablony i moduly,
- pytania,
- rekomendacje,
- tworzenie audytu,
- widok audytora,
- zapis odpowiedzi,
- uploady,
- walidacje,
- wysylke do weryfikacji,
- weryfikacje lidera,
- raporty,
- eksporty,
- portal klienta,
- PWA.

## 14. Znane problemy i dlug techniczny

| Problem | Waga | Co zrobic |
| --- | --- | --- |
| Wszystkie pliki sa niezatwierdzone w Git (`??`) | Wysoka | Ustalic baseline i zrobic pierwszy commit projektu |
| Brak CRUD uzytkownikow | Wysoka | Dodac `UserResource` w Filament |
| PDF/DOCX minimalne wizualnie | Wysoka | Zaprojektowac finalny generator/layout raportow |
| Offline nie robi pelnego syncu | Srednia/Wysoka | Zdecydowac: pelny offline sync albo jawnie tylko draft lokalny |
| Autoryzacja w kontrolerach | Srednia | Przeniesc do Policies |
| Walidacje inline | Srednia | Przeniesc do Form Requests |
| Brak E2E browser tests | Srednia | Dodac Playwright/Dusk lub inny test UI |
| Brak realnego mail/push | Srednia | Dodac kanal powiadomien produkcyjnych |
| Brak integracji storage produkcyjnego | Srednia | Decyzja local/NAS/S3 |
| Demo pytania i rekomendacje | Srednia | UAT i finalna biblioteka Global IT |
| Brak importu biblioteki | Niska/Srednia | XLSX/CSV import dla pytan i rekomendacji |
| `app/.DS_Store` w projekcie | Niska | Posprzatac i upewnic sie, ze `.DS_Store` jest ignorowane |

## 15. Co trzeba ustalic biznesowo

1. Czy aplikacja ma byc najpierw narzedziem wewnetrznym MVP, czy od razu produkcyjnym systemem dla klientow?
2. Jak ma wygladac finalny raport PDF/DOCX Global IT?
3. Czy klient ma miec tylko portal z raportami, czy rowniez komunikacje/akceptacje zakresow prac?
4. Czy offline ma byc funkcja krytyczna, czy wystarczy lokalny draft?
5. Jakie role faktycznie maja miec dostep do Filament?
6. Czy sales powinien miec dostep do admina, czy tylko do raportow sales?
7. Czy potrzebne jest SSO/Microsoft Entra ID?
8. Gdzie maja byc trzymane pliki dowodowe i raporty w produkcji?
9. Czy follow-up ma integrowac sie z CRM/systemem zadan?
10. Jakie moduly audytowe sa finalnie wymagane przez Global IT?

## 16. Rekomendowany plan dalszych prac

### Etap A - odzyskanie kontroli nad projektem

1. Uruchomic pelny zestaw testow i zapisac wynik.
2. Ustalic baseline w Git: pierwszy commit albo branch roboczy.
3. Zweryfikowac logowanie kazdej roli.
4. Przejsc recznie glowny scenariusz end-to-end na danych testowych.
5. Zrobic liste bledow UX z realnego klikania.

### Etap B - administracja systemem

1. Dodac CRUD uzytkownikow w Filament.
2. Dopracowac menu admina i linki do raportow.
3. Uporzadkowac role i dostepy.
4. Dodac podstawowy ekran instrukcyjny/startowy dla admina albo lepszy dashboard admina.

### Etap C - raporty

1. Ustalic szablon raportu Global IT.
2. Zdecydowac technologie generowania PDF/DOCX.
3. Dopracowac raport techniczny.
4. Dopracowac raport biznesowy.
5. Dopracowac raport sales.
6. Dodac testy zawartosci raportow.

### Etap D - stabilizacja

1. Policies i Form Requests.
2. Testy E2E.
3. Queue worker + monitoring.
4. Backup bazy i storage.
5. Audyt bezpieczenstwa linkow i plikow.

### Etap E - funkcje rozwojowe

1. Import XLSX/CSV biblioteki pytan.
2. Pelny offline sync lub usuniecie mylacej obietnicy syncu.
3. Integracje mail/CRM/task manager.
4. Dashboard trendow, SLA, ryzyk per klient.
5. Wersjonowanie szablonow audytow.

## 17. Najlepszy nastepny krok

Najlepiej zaczac od stabilizacji fundamentu:

1. Uruchomic `php artisan test`, `./vendor/bin/pint --test`, `composer validate`.
2. Kliknac recznie scenariusz: admin tworzy audyt -> audytor wypelnia -> lider akceptuje -> raport -> klient -> follow-up.
3. Dodac CRUD uzytkownikow.
4. Dopracowac raporty PDF/DOCX, bo to najbardziej widoczny efekt aplikacji.

## 18. Szybka instrukcja dla kolejnego chatu Codex

Przed zmianami przeczytaj:

- `PROJECT_RECOVERY_AUDIT.md`
- `CODEX_CONTEXT.md`
- `TECHNICAL_DOCUMENTATION.md`
- `FEATURES_STATUS.md`
- `TODO_NEXT_STEPS.md`

Najwazniejsze pliki kodu:

- `routes/web.php`
- `app/Models/Audit.php`
- `app/Models/User.php`
- `app/Http/Controllers/AuditorAuditController.php`
- `app/Http/Controllers/TechnicalReviewController.php`
- `app/Http/Controllers/AuditReportController.php`
- `app/Providers/Filament/AdminPanelProvider.php`
- `database/seeders/DatabaseSeeder.php`

Nie zaczynaj od refaktoru. Najpierw maly krok, test, potem kolejny krok.
