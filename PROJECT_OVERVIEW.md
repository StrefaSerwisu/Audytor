# Audytor IT - project overview

Aktualizacja analizy: 2026-08-01.

Najpelniejsza aktualna mapa projektu po przerwie znajduje sie w `PROJECT_RECOVERY_AUDIT.md`.

## Czym jest aplikacja

Audytor IT to wewnetrzna aplikacja Global IT do prowadzenia standaryzowanych audytow infrastruktury IT u klientow. System laczy panel administracyjny, widok pracy audytora, proces weryfikacji technicznej, generowanie raportow oraz portal klienta.

Glowna idea: administrator definiuje biblioteke audytu, audytor wypelnia checklisty i dolacza dowody, lider techniczny zatwierdza wynik, a system generuje raport techniczny, biznesowy i sprzedazowy.

## Dla kogo jest

- Administrator Global IT: zarzadza klientami, lokalizacjami, szablonami, modulami, pytaniami, rekomendacjami i audytami.
- Audytor / inzynier: realizuje przypisane audyty, odpowiada na pytania, dodaje komentarze, ryzyka i zalaczniki.
- Lider techniczny: przeglada audyty, akceptuje technicznie lub zwraca do poprawek.
- Sales: korzysta z raportu sprzedazowego i rekomendacji uslugowych.
- Klient: widzi opublikowane raporty, status, komentarz, rekomendacje i plan follow-up.

## Glowne moduly biznesowe

- Klienci i lokalizacje.
- Biblioteka audytu: szablony, moduly, pytania i rekomendacje.
- Audyty: statusy, przypisania, wybrane moduly, odpowiedzi i dowody.
- Weryfikacja techniczna: akceptacja, zwrot do poprawek, historia decyzji.
- Raportowanie: raport techniczny, biznesowy, sprzedazowy, PDF, DOCX, kolejka eksportow.
- Publikacja klientowi: token publiczny, portal klienta, status klienta i feedback.
- Follow-up: zadania wdrozeniowe tworzone z zaakceptowanych rekomendacji.
- Dashboard KPI, archiwum, powiadomienia i eksporty CSV.
- PWA/offline: manifest, service worker, strona offline, roboczy zapis formularzy audytora w IndexedDB.

## Obecny stan projektu

Projekt jest funkcjonalnym MVP. Ma zaimplementowany przeplyw od przygotowania audytu do publikacji raportu klientowi i follow-up. Testy feature pokrywaja najwazniejsze sciezki biznesowe.

Stan po ponownej analizie 2026-08-03:

- Backend: Laravel 12, PHP 8.2+, PostgreSQL w `.env.example`, SQLite in-memory w testach.
- Panel admina: Filament 4 pod `/admin`.
- Frontend aplikacyjny: Blade + CSS w layoutach + lekkie JS dla PWA/offline.
- Brak publicznego JSON API. Aplikacja dziala przez web routes i formularze Blade/Filament.
- Raporty PDF/DOCX sa generowane prostymi lokalnymi generatorami, nie przez zewnetrzny silnik DTP.
- Offline jest czesciowy: zapisy robocze trafiaja do IndexedDB, ale nie ma pelnej synchronizacji odpowiedzi z serwerem po reconnect.
- Baseline Git istnieje, Etap 1A jest na `main`, a Etap 1B powstaje na osobnej galezi.
- Etapy 1A/1B dodaly role, Policies, User CRUD, centralny audit trail i Form Requests.
- Najwazniejsze braki przed dalszym rozwojem: centralny workflow statusow, finalne raporty PDF/DOCX, UAT biblioteki pytan i decyzja o offline/sync.

## Najwazniejsze adresy lokalne

- `/admin` - panel Filament dla adminow.
- `/auditor/login` i `/auditor` - logowanie i praca audytora.
- `/reviewer` - weryfikacja techniczna.
- `/reports/audits/{audit}/technical` - raport techniczny.
- `/reports/audits/{audit}/business` - raport biznesowy.
- `/reports/audits/{audit}/sales` - raport sprzedazowy.
- `/reports/exports` - historia eksportow PDF/DOCX.
- `/client/login` i `/client/portal` - portal klienta.
- `/client/reports/{token}` - publiczny link raportu klienta.
- `/dashboard`, `/archive`, `/follow-ups`, `/notifications` - ekrany operacyjne.

## Konta testowe

Haslo dla kont testowych: `password`.

- `superadmin@globalit.test`
- `admin@globalit.test`
- `lider@globalit.test`
- `audytor@globalit.test`
- `sales@globalit.test`
- `klient@globalit.test`
