# Audytor IT - Architektura

## Cel

Audytor IT ma prowadzić inżyniera Global IT krok po kroku przez audyt klienta, pilnować kompletności danych, zdjęć, screenów i rekomendacji, a następnie generować raport biznesowy, techniczny oraz wewnętrzny raport sprzedażowy.

Aplikacja nie jest zwykłym formularzem. Jest silnikiem standaryzowanych audytów IT, w którym administrator definiuje szablony, moduły, pytania, wymagane dowody, poziomy ryzyka i rekomendacje.

## Role

- Super Admin: pełny dostęp.
- Administrator Global IT: zarządzanie klientami, użytkownikami, szablonami, modułami, pytaniami, rekomendacjami i raportami.
- Lider techniczny: weryfikacja audytów, poprawki rekomendacji, zatwierdzanie raportów.
- Audytor / Inżynier: wykonywanie przypisanych audytów, odpowiedzi, zdjęcia, screeny, ryzyka, rekomendacje.
- Sales / Handlowiec: raport sprzedażowy i rekomendacje handlowe.
- Klient: etap przyszły, wyłącznie własne raporty online bez raportu sprzedażowego.

## Moduły domenowe

- Klienci i lokalizacje.
- Szablony audytów.
- Moduły audytowe.
- Pytania i checklisty.
- Baza rekomendacji.
- Audyty i przypisania.
- Odpowiedzi i załączniki.
- Walidacja kompletności.
- Weryfikacja lidera.
- Raporty: business, technical, sales_internal.
- Log aktywności.
- PWA/offline sync.

## Docelowy stack

- Laravel 12 / PHP 8.3+
- PostgreSQL
- FilamentPHP dla panelu administracyjnego
- Inertia.js + Vue 3 dla widoku audytora
- Laravel Queue dla PDF, DOCX, zdjęć i synchronizacji
- Storage prywatny lokalny z możliwością przełączenia na S3-compatible storage
- Spatie Laravel Permission albo natywne role, jeśli lepiej pasują do Filament

## Statusy audytu

- draft
- scheduled
- in_progress
- syncing
- needs_completion
- submitted_for_review
- changes_requested
- technically_approved
- reports_generated
- published_to_client
- closed
- cancelled

## Statusy synchronizacji

- local_only
- pending_sync
- syncing
- synced
- sync_failed

## Etap 1 po zainstalowaniu Laravel

1. Utworzyć projekt Laravel 12.
2. Skonfigurować PostgreSQL.
3. Dodać auth, role i użytkowników testowych.
4. Dodać Filament.
5. Utworzyć modele `Client` i `ClientLocation`.
6. Utworzyć CRUD klientów i lokalizacji.
7. Uruchomić migracje i seedery.
8. Sprawdzić logowanie, role i CRUD.

## Zasady projektowe

- Checklisty nie mogą być zakodowane na sztywno.
- Pytania, wymagane zdjęcia, screeny i rekomendacje muszą być zarządzane z panelu.
- Raport sprzedażowy jest zawsze oddzielony od danych widocznych dla klienta.
- Pliki nie mogą być publiczne.
- Offline-first ma wpływać na model danych od początku: `sync_status`, `local_uuid`, historia zmian i kolejka synchronizacji.

