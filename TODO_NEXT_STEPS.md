# Audytor IT - todo next steps

Aktualizacja analizy: 2026-08-03.

Szczegolowy dokument wskrzeszenia projektu: `PROJECT_RECOVERY_AUDIT.md`.

## Priorytet P-1 - odzyskanie kontroli nad projektem

1. Utrzymac workflow branch -> walidacja -> PR -> squash merge.
   - Baseline istnieje, a Etap 1A jest scalony do `main`.
   - Kazdy kolejny zakres powinien miec osobna galaz i jednoznaczne kryteria odbioru.

2. Uruchomic pelna walidacje lokalna.
   - `php artisan test`
   - `./vendor/bin/pint --test`
   - `composer validate`
   - `npm run build`, jesli zaleznosci Node sa zainstalowane.

3. Przejsc recznie scenariusz end-to-end.
   - Admin tworzy lub sprawdza audyt.
   - Audytor wypelnia pytania.
   - Lider zatwierdza.
   - Raport jest generowany/publikowany.
   - Klient akceptuje rekomendacje.
   - Follow-up powstaje i jest widoczny.

4. Spisac realne bledy UX z klikania.
   - Menu admina.
   - Widok audytora na szerokim ekranie i mobile.
   - Raporty.
   - Portal klienta.

5. Ustalic mapowanie obecnych rol na docelowe role 2.0.
   - Sales nie ma dostepu do Filament i korzysta tylko z przypisanych sekcji operacyjnych.
   - Przed Etapem 2 trzeba zdecydowac o `audit_product_admin`, `sales_manager`, `delivery_manager`, `client_admin` i `client_user`.

## Priorytet P0 - przed dalszym rozwojem produkcyjnym

1. Domknac centralny workflow statusow audytu.
   - Dodac `AuditStatus` Enum.
   - Zdefiniowac dozwolone przejscia i wykonywac je przez jedna usluge/action.
   - Logowac przejscia i testowac odrzucenie niedozwolonych zmian.

2. Domknac cykl zycia konta.
   - Uniewazniac aktywne sesje po dezaktywacji.
   - Dodac bezpieczny reset hasla.
   - Przygotowac MFA/SSO jako osobny, zatwierdzony zakres.

1. Przejsc UAT z Global IT na realnym scenariuszu audytu.
   - Sprawdzic, czy pytania w modulach sa merytorycznie kompletne.
   - Sprawdzic, czy walidacje nie blokuja normalnej pracy audytora.
   - Sprawdzic, czy role widza dokladnie to, co powinny.

2. Dopracowac raporty PDF/DOCX.
   - Obecne generatory sa minimalne.
   - Potrzebny layout z naglowkami, stopkami, tabelami, numeracja stron i brandingiem Global IT.
   - Warto rozwazyc biblioteke PDF/DOCX albo generowanie PDF z HTML.

3. Uporzadkowac produkcyjny queue worker.
   - Ustawic `QUEUE_CONNECTION=database` lub docelowy backend.
   - Uruchomic supervisor/systemd dla `php artisan queue:work --queue=default --tries=3 --timeout=120`.
   - Monitorowac `failed_jobs` i eksporty `failed`.

4. Ustalic backup.
   - PostgreSQL.
   - `storage/app/private` i eksporty raportow.
   - Pliki dowodowe audytow.

5. Przejrzec bezpieczenstwo plikow i linkow.
   - Potwierdzic, ze zalaczniki i eksporty nie sa publiczne.
   - Rozwazyc podpisane, wygasajace linki do pobran.
   - Dodac audyt dostepu do raportow klienta.

## Priorytet P1 - jakosc i utrzymanie

1. Przeniesc autoryzacje do policies.
   - Teraz wiele zasad jest w metodach prywatnych kontrolerow.
   - Policies ulatwia dalszy rozwoj i testowanie.

2. Dodac Form Requesty.
   - Walidacje sa glownie inline w kontrolerach.
   - Request classes poprawia czytelnosc i reuzywalnosc.

3. Dopracowac typy pol pytan.
   - `AuditQuestion::FIELD_TYPES` ma wiele typow.
   - Nie wszystkie typy maja pelna dedykowana obsluge UI/backend.

4. Rozwinac testy browser/E2E.
   - Logowanie admina.
   - Stworzenie audytu w Filament.
   - Wypelnienie audytu na widoku audytora.
   - Publikacja i akceptacja rekomendacji przez klienta.

5. Uporzadkowac CSS.
   - Widoki maja duzo stylow inline.
   - Warto przeniesc wspolne style do assetow Vite albo komponentow Blade.

6. Usunac przypadkowe pliki systemowe.
   - W repo widoczny jest `app/.DS_Store`.
   - Nie kasowac bez zgody, ale warto dodac do checklisty cleanup.

## Priorytet P2 - funkcje rozwojowe

1. Pelny offline sync.
   - Aktualnie IndexedDB trzyma drafty, ale nie wysyla ich automatycznie do backendu.
   - Trzeba zaprojektowac konflikt resolution i kolejke synchronizacji.

2. Import/eksport biblioteki audytu.
   - XLSX/CSV dla pytan, rekomendacji i szablonow.
   - Przydatne dla szybkiego wdrozenia realnej biblioteki Global IT.

3. Retencja i eksport audit logu.
   - Ustalic okres przechowywania, dostep administracyjny i format eksportu.
   - Dodac logowanie wejsc przez publiczny token raportu klienta.

4. Integracje.
   - Email notifications.
   - System zadan/CRM.
   - S3-compatible storage.
   - SSO/Microsoft Entra ID, jesli Global IT tego wymaga.

5. Rozbudowany dashboard.
   - Trendy w czasie.
   - SLA weryfikacji.
   - Ryzyka per klient.
   - Status wdrozen follow-up.

## Ryzyka

- Raporty PDF/DOCX moga nie spelniac oczekiwan wizualnych, bo generatory sa proste.
- Offline moze byc mylnie interpretowany jako pelna synchronizacja, a obecnie to glownie draft lokalny.
- Brak centralnych policies moze utrudnic dalsza rozbudowe uprawnien.
- Brak E2E browser tests zostawia ryzyko regresji UI w Filament/Blade.
- Produkcyjny storage wymaga decyzji: lokalny dysk, NAS, S3-compatible storage.
- Pytania i rekomendacje z seeda sa demonstracyjne i wymagaja zatwierdzenia merytorycznego.

## Co warto przetestowac recznie

1. Admin:
   - wejscie do `/admin`,
   - menu: Audyty, Biblioteka audytu, Klienci, Raporty i operacje,
   - CRUD pytania, rekomendacji i audytu.

2. Audytor:
   - logowanie,
   - wypelnienie pytan w kazdym module,
   - upload zdjecia/screenshotu,
   - walidacja ryzyka i rekomendacji,
   - wysylka do weryfikacji.

3. Lider:
   - lista `/reviewer`,
   - zwrot do poprawek,
   - akceptacja,
   - raporty,
   - publikacja klientowi,
   - zamkniecie audytu.

4. Klient:
   - login `/client/login`,
   - lista publikacji,
   - status raportu,
   - komentarz,
   - wybieranie rekomendacji,
   - widocznosc follow-up.

5. Operacje:
   - `/dashboard`,
   - `/archive`,
   - `/follow-ups`,
   - `/reports/exports`,
   - eksporty CSV, PDF, DOCX.

## Najlepszy punkt startu kolejnych prac

Najpierw dopracowac raporty PDF/DOCX i UAT biblioteki pytan. To sa najbardziej widoczne elementy dla klienta i Global IT. Potem warto uporzadkowac autoryzacje w policies oraz dodac zarzadzanie uzytkownikami w adminie.
