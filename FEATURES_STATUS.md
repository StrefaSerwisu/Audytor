# Audytor IT - features status

Aktualizacja analizy: 2026-08-03.

Najpelniejszy aktualny dokument odzyskania projektu: `PROJECT_RECOVERY_AUDIT.md`.

Legenda:

- `Dziala` - funkcja jest zaimplementowana i ma testy lub zostala potwierdzona w projekcie.
- `Czesciowo dziala` - jest fundament, ale brakuje produkcyjnego dopracowania lub pelnego workflow.
- `Brak` - brak implementacji.
- `Do sprawdzenia` - kod istnieje, ale wymaga recznego UAT albo testow poza automatycznymi.

| Obszar | Status | Co jest gotowe | Czego brakuje / uwagi |
| --- | --- | --- | --- |
| Panel admina Filament | Dziala | CRUD klientow, lokalizacji, szablonow, modulow, pytan, rekomendacji, audytow i uzytkownikow. Menu pogrupowane biznesowo. | Wymaga UAT na docelowych kontach administracyjnych. |
| Baseline Git | Dziala | Etap 1A zostal scalony do `main`; Etap 1B jest rozwijany na osobnej galezi. | Kazdy kolejny etap powinien przechodzic przez PR i walidacje. |
| Role i dostep do admina | Dziala | Enum rol, middleware i Policies zabezpieczaja kluczowe sekcje; Sales nie ma dostepu do Filament. | Docelowe role 2.0 zostana rozszerzone w kolejnych etapach. |
| Administracja uzytkownikami | Dziala | `UserResource` obsluguje silne i potwierdzane hasla, role, aktywnosc, flage MFA i przypisanie klienta. Global Admin nie moze zarzadzac Super Adminem, a konta z historia mozna tylko dezaktywowac. | Produkcyjnie warto dodac reset hasla mailem i centralne usuwanie rekordow sesji po dezaktywacji. |
| Centralny audit trail | Dziala | `audit_logs`, centralna usluga, dziennik Filament oraz logowanie zmian kont, dowodow i kluczowych akcji workflow. | Brak polityki retencji, eksportu i osobnego logu wejsc przez publiczny token klienta. |
| Form Requests | Dziala | Kluczowe akcje zapisu korzystaja z dedykowanych klas walidacji. | Filtry list pozostaja walidowane lokalnie w kontrolerach. |
| Rate limiting logowania | Dziala | Logowanie audytora i klienta ma limit 5 prob na minute dla kombinacji e-mail + IP. Udane, nieudane i odrzucone logowania sa audytowane. | Filament stosuje wlasne zabezpieczenie logowania; docelowe limity produkcyjne wymagaja monitoringu. |
| Klienci i lokalizacje | Dziala | Modele, migracje, relacje, CRUD, seed danych. | Brak importu/eksportu klientow. |
| Biblioteka audytu | Dziala | Szablony, moduly, pytania, typy pol, wymagane dowody, N/D, ryzyko, rekomendacje. | Brak wersjonowania szablonow i pytan. |
| Seeder biblioteki | Dziala | 5 modulow i pytania dla kazdego; testy pilnuja liczby pytan. | Dane sa demonstracyjne, wymagaja UAT merytorycznego. |
| Tworzenie audytu | Dziala | Audyt, klient, lokalizacja, szablon, moduly, przypisani audytorzy, lider. | Brak workflow masowego tworzenia audytow. |
| Widok audytora | Dziala | Lista audytow, szczegol, moduly, pytania, zapis odpowiedzi. | UI wymaga UAT na telefonie/tablecie. |
| Walidacja odpowiedzi | Dziala | Wymagane pola, ryzyko, rekomendacja przy wysokim/krytycznym ryzyku, dowody. | Czesci typow pol z `AuditQuestion::FIELD_TYPES` moze nie miec pelnej dedykowanej kontrolki. |
| Zalaczniki audytowe | Dziala | Upload, prywatny storage, autoryzowane pobieranie i usuwanie oraz audit trail dodania, pobrania i usuniecia. | Brak skanowania antywirusowego, miniatur i limitow per audyt poza walidacja uploadu. |
| Wysylka do weryfikacji | Dziala | Blokady kompletosci, status `submitted_for_review`, data wyslania. | Brak podpisu elektronicznego. |
| Weryfikacja techniczna | Dziala | Lista, szczegol, akceptacja, zwrot do poprawek, historia decyzji. | Brak wieloetapowego approval chain. |
| Powiadomienia | Dziala | DB notifications, licznik w UI, przypomnienia na ekranie. | Brak mail/push/websocket. |
| Raport techniczny HTML | Dziala | Widok po akceptacji, ryzyka, odpowiedzi, rekomendacje. | Layout raportu wymaga finalnego brandingu. |
| Raport biznesowy HTML | Dziala | Podsumowanie, mapa ryzyka, rekomendacje, publikacja i zamkniecie. | Brak dopracowanego template DTP. |
| Raport sprzedazowy | Dziala | Widok wewnetrzny dla sales/admin/lidera, kategorie, priorytety, godziny. | UAT z dzialem sales wymagany. |
| PDF/DOCX na zadanie | Czesciowo dziala | Endpointy pobierania PDF/DOCX oraz testy sygnatur plikow. | Generatory sa minimalne; brak ladnych tabel, paginacji, naglowkow/stopki i brandingu. |
| Kolejka eksportow | Dziala | Tabela eksportow, job, statusy, retry, pobieranie gotowego pliku. | W produkcji trzeba stalego queue workera i monitoring failed jobs. |
| Historia eksportow | Dziala | `/reports/exports`, filtry, statusy, download, retry. | Brak czyszczenia starych plikow i limitow miejsca. |
| Publikacja klientowi | Dziala | Token, status `published_to_client`, opcjonalna data wygasniecia, widok publiczny. | Brak podpisywania linkow i audytu dostepow klienta. |
| Portal klienta | Dziala | Login klienta, lista aktywnych publikacji, status, komentarz, rekomendacje. | Brak rozbudowanej komunikacji z Global IT. |
| Follow-up | Dziala | Tworzenie z zaakceptowanych rekomendacji, statusy, priorytet, wlasciciel, widocznosc klienta. | Brak powiadomien terminow/deadline i integracji z systemem zadan. |
| Dashboard KPI | Dziala | Statusy, mapa ryzyka, ostatnie audyty, CSV. | Brak wykresow, trendow i zakresow dat. |
| Archiwum | Dziala | Zamkniete audyty, filtry, CSV, raporty pozostaja dostepne. | Brak retencji/archiwizacji plikow. |
| PWA | Czesciowo dziala | Manifest, service worker, offline fallback, cache podstawowych adresow. | Brak pelnego testu install/offline na realnym urzadzeniu. |
| Offline draft | Czesciowo dziala | Formularze audytora zapisuja draft do IndexedDB. | Brak pelnej synchronizacji draftow do backendu po online. |
| CSV export | Dziala | Dashboard, archiwum, follow-up. | Brak XLSX. |
| Testy automatyczne | Dziala | Feature tests pokrywaja glowne workflow, role, User CRUD, audit trail i rate limiting; Etap 1B: 101 testow i 453 asercje. | Brak stalego zestawu testow browser/E2E. |
| Build frontend | Dziala | Vite build przechodzi; frontend to glownie Blade/Filament. | Build nalezy uruchamiac po zmianach CSS/JS. |
| Produkcyjne wdrozenie | Czesciowo dziala | Checklisty w `docs`, migracje, queue, storage. | Brak supervisor/systemd config, backup planu, observability, HTTPS/domeny. |
| Integracje zewnetrzne | Brak | Standardowa konfiguracja mail/log/storage/queue. | Brak integracji z ClickUp/CRM/email/S3/SSO/M365 Graph. |

## Obecny poziom gotowosci

MVP jest gotowe do prezentacji i UAT, ale przed dalszym rozwojem trzeba odzyskac kontrole operacyjna nad projektem: baseline Git, ponowne uruchomienie testow, reczny scenariusz end-to-end i decyzje produktowe. Do produkcji potrzebne sa: finalny branding raportow, pelniejszy offline/sync albo decyzja o jego ograniczeniu, konfiguracja queue workera, backupy, monitoring i UAT na rzeczywistych scenariuszach Global IT.
