# Audytor IT

Wewnetrzna aplikacja Global IT do prowadzenia standaryzowanych audytow IT.

## Etap 1

Zaimplementowany fundament:

- Laravel 12
- konfiguracja pod PostgreSQL
- FilamentPHP panel pod `/admin`
- role uzytkownikow Global IT
- modele `Client` i `ClientLocation`
- CRUD klientow i lokalizacji w panelu
- seedery uzytkownikow i klienta testowego
- CRUD szablonow audytow i modulow audytowych
- przypisywanie modulow do szablonu z kolejnoscia
- seeder szablonu `Audyt podstawowy IT` i modulow testowych
- CRUD pytan audytowych
- dynamiczne typy pol pytan
- wymagane zdjecia, screeny, komentarz przy N/D i ocena ryzyka
- seeder 14 pytan dla modulu `UTM/firewall`
- CRUD bazy rekomendacji Global IT
- powiazania rekomendacji z pytaniami audytowymi
- pola techniczne, biznesowe i sprzedazowe rekomendacji
- seeder 5 rekomendacji testowych
- CRUD audytow
- wybor klienta, lokalizacji, szablonu, modulow i audytorow
- statusy audytu przygotowane w modelu
- seeder testowego audytu z 5 modulami i 2 przypisanymi osobami
- osobny login audytora pod `/auditor/login`
- responsywny widok `Moje audyty` pod `/auditor`
- szczegol audytu z modulami, pytaniami, rekomendacjami i postepem
- model `AuditAnswer` oraz zapis odpowiedzi, komentarzy, N/D, ryzyka i rekomendacji audytora
- kontrola dostepu do audytow po przypisaniu uzytkownika
- model `AuditAnswerAttachment` dla dowodow audytowych
- upload zdjec, screenshotow i plikow do odpowiedzi audytowej
- prywatne pobieranie i usuwanie zalacznikow przez widok audytora
- pytania wymagajace zdjecia lub screenshotu licza sie jako ukonczone dopiero po dodaniu zalacznika
- walidacje odpowiedzi audytora dla pytan wymaganych, N/D, dowodow i ryzyka
- wymagany poziom ryzyka dla pytan z wlaczona ocena ryzyka
- wymagana rekomendacja audytora dla ryzyka wysokiego i krytycznego
- oznaczenie odpowiedzi roboczych jako `Do poprawy` w widoku audytu
- panel wysylki audytu do weryfikacji technicznej
- blokujaca lista brakow przed wyslaniem audytu
- zmiana statusu audytu na `submitted_for_review` po kompletnej wysylce
- zapis daty `submitted_at`
- osobny widok lidera technicznego pod `/reviewer`
- lista audytow wyslanych do weryfikacji
- szczegol audytu w trybie przegladu z odpowiedziami, ryzykami, rekomendacjami i zalacznikami
- akceptacja techniczna audytu ze statusem `technically_approved`
- zwrot audytu do poprawek ze statusem `changes_requested` i wymaganymi uwagami
- historia decyzji lidera w tabeli `audit_reviews`
- raport techniczny dla zatwierdzonego audytu
- podsumowanie biznesowe z mapa ryzyka i rekomendacjami
- raport sprzedazowy Global IT dla Sales/Admin/Lider
- raport sprzedazowy zawiera kategorie sprzedazowe, priorytety, estymacje godzin i rekomendowane nastepne kroki
- widoki raportow przygotowane do drukowania lub zapisu jako PDF z przegladarki
- pobieranie raportow technicznych, biznesowych i sprzedazowych jako PDF
- pobieranie raportow technicznych, biznesowych i sprzedazowych jako DOCX
- kolejka eksportow raportow w tabeli `audit_report_exports`
- job `GenerateAuditReportExport` zapisujacy wygenerowane pliki do storage
- panel historii eksportow raportow pod `/reports/exports`
- pobieranie gotowych eksportow i ponawianie eksportow zakonczonych bledem
- manifest PWA z nazwa, ikona, start URL i trybem standalone
- service worker z podstawowym cache i strona offline
- rejestracja PWA w widokach audytora oraz portalu klienta
- offline fallback pod `/offline.html`
- roboczy zapis formularzy audytora do IndexedDB przez `/offline-audit.js`
- rejestracja synchronizacji `audytor-it-sync` po powrocie online
- zadania follow-up tworzone z rekomendacji zaakceptowanych przez klienta
- panel planu wdrozen pod `/follow-ups`
- eksport planu wdrozen CSV pod `/follow-ups/export`
- statusy zadan: nowe, zaplanowane, w trakcie, zakonczone, odrzucone
- wlasciciel, termin, priorytet, notatki i widocznosc zadania dla klienta
- plan dzialan poaudytowych widoczny w portalu klienta
- raporty dostepne tylko po akceptacji technicznej
- publikacja raportu dla klienta przez bezpieczny token
- status `published_to_client` po publikacji
- publiczny widok klienta pod `/client/reports/{token}`
- opcjonalna data wygasniecia linku klienta
- historia publikacji w tabeli `audit_publications`
- zamkniecie opublikowanego audytu przez lidera technicznego lub administratora
- status `closed` oraz data `completed_at` po zamknieciu audytu
- historia zamkniec w tabeli `audit_closures`
- archiwum audytow historycznych pod `/archive`
- raporty techniczne i biznesowe pozostaja dostepne dla zamknietych audytow
- dashboard KPI pod `/dashboard`
- szybki podglad liczby audytow otwartych, do weryfikacji, raportowanych, opublikowanych i historycznych
- zestawienie statusow oraz mapa ryzyka w dashboardzie
- filtrowanie archiwum po statusie, kliencie, wyszukiwanej frazie i dacie zamkniecia
- eksport dashboardu KPI do CSV pod `/dashboard/export`
- eksport przefiltrowanego archiwum do CSV pod `/archive/export`
- wewnetrzne powiadomienia workflow pod `/notifications`
- licznik nowych powiadomien w gornym pasku aplikacji
- powiadomienie lidera po wyslaniu audytu do weryfikacji
- powiadomienie audytorow po akceptacji, zwrocie do poprawek, publikacji raportu i zamknieciu audytu
- przypomnienia dla liderow o audytach czekajacych na weryfikacje
- przypomnienia dla audytorow o audytach wymagajacych uzupelnienia lub poprawek
- portal klienta pod `/client/portal`
- osobne logowanie klienta pod `/client/login`
- konto testowe klienta `klient@globalit.test`
- lista aktywnych raportow opublikowanych dla klienta bez recznego tokena w URL
- ukrywanie wygaslych publikacji w portalu klienta
- status raportu po stronie klienta: odebrane, do omowienia, zaakceptowane
- komentarz klienta do opublikowanego raportu
- wybieranie rekomendacji, ktore klient chce wdrozyc
- widok reakcji klienta w szczegole audytu lidera technicznego

## Uruchomienie

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
php artisan serve
```

Panel administracyjny:

```text
http://127.0.0.1:8000/admin
```

Widok audytora:

```text
http://127.0.0.1:8000/auditor
```

Widok lidera technicznego:

```text
http://127.0.0.1:8000/reviewer
```

Raporty po zatwierdzeniu technicznym:

```text
http://127.0.0.1:8000/reports/audits/{audit}/technical
http://127.0.0.1:8000/reports/audits/{audit}/business
http://127.0.0.1:8000/reports/audits/{audit}/sales
```

Pobieranie raportow:

```text
http://127.0.0.1:8000/reports/audits/{audit}/{technical|business|sales}/pdf
http://127.0.0.1:8000/reports/audits/{audit}/{technical|business|sales}/docx
```

Historia eksportow raportow:

```text
http://127.0.0.1:8000/reports/exports
```

Publiczny link klienta po publikacji:

```text
http://127.0.0.1:8000/client/reports/{token}
```

Archiwum audytow:

```text
http://127.0.0.1:8000/archive
```

Dashboard KPI:

```text
http://127.0.0.1:8000/dashboard
```

Eksporty CSV:

```text
http://127.0.0.1:8000/dashboard/export
http://127.0.0.1:8000/archive/export
```

Powiadomienia:

```text
http://127.0.0.1:8000/notifications
```

Portal klienta:

```text
http://127.0.0.1:8000/client/login
http://127.0.0.1:8000/client/portal
```

PWA:

```text
http://127.0.0.1:8000/manifest.webmanifest
http://127.0.0.1:8000/service-worker.js
http://127.0.0.1:8000/offline.html
http://127.0.0.1:8000/offline-audit.js
```

Plan wdrozen:

```text
http://127.0.0.1:8000/follow-ups
http://127.0.0.1:8000/follow-ups/export
```

Konta testowe maja haslo:

```text
password
```

Konta:

- superadmin@globalit.test
- admin@globalit.test
- lider@globalit.test
- audytor@globalit.test
- sales@globalit.test

Rola `auditor` nie ma dostepu do panelu administracyjnego Filament. Audytorzy pracuja w osobnym widoku pod `/auditor`.

## PostgreSQL

Domyslna baza w `.env.example`:

```text
DB_DATABASE=audytor_it
DB_USERNAME=postgres
DB_PASSWORD=postgres
```

## Kolejka eksportow

W srodowisku produkcyjnym nalezy uruchomic worker kolejek:

```bash
php artisan queue:work --queue=default --tries=3 --timeout=120
```

Gotowe pliki eksportow sa zapisywane na dysku `local` w katalogu `report-exports`.

## Kolejny etap

MVP jest domkniete funkcjonalnie. Dalsze prace to stabilizacja produkcyjna, testy UAT i dopracowanie szablonow PDF/DOCX pod finalny branding Global IT.
