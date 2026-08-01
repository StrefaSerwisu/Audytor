# Audytor IT - raport odbioru MVP

Data odbioru: 2026-06-26

## Wynik

MVP aplikacji jest funkcjonalnie domkniete i gotowe do prezentacji/UAT.

## Zakres sprawdzony

- Logowanie audytora pod `/auditor/login`.
- Logowanie klienta pod `/client/login`.
- Panel audytora, lidera technicznego, raporty, portal klienta, follow-up i eksporty raportow.
- Generowanie i pobieranie raportow PDF/DOCX.
- Historia eksportow pod `/reports/exports`.
- Kolejka eksportow przez `GenerateAuditReportExport`.
- PWA: manifest, service worker, fallback offline i roboczy zapis formularzy audytu.
- Migracje PostgreSQL.
- Kontrola dostepu dla rol: audytor, lider techniczny, sales, klient.

## Weryfikacja automatyczna

- `php artisan test`: 71 testow, 287 asercji, wynik pozytywny.
- `./vendor/bin/pint --test`: wynik pozytywny.
- `composer validate`: wynik pozytywny.
- `php artisan migrate:status`: wszystkie migracje uruchomione.
- `php artisan queue:work --once --queue=default --tries=3 --timeout=120`: wykonane bez bledu.

## Weryfikacja HTTP

- `GET /auditor/login`: 200 OK.
- `GET /client/login`: 200 OK.
- `GET /manifest.webmanifest`: 200 OK.
- `GET /reports/exports` bez sesji: 302 do `/auditor/login`.

## Do wykonania przed produkcja

- Ustawic produkcyjny `.env`.
- Uruchomic stalego workera kolejek pod supervisorem/systemd.
- Przejsc UAT z uzytkownikami Global IT.
- Dostosowac finalny branding i tresci raportow PDF/DOCX.
- Ustalic backup PostgreSQL i katalogu `storage`.
