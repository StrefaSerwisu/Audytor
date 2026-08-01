# Audytor IT - todo next steps

Aktualizacja analizy: 2026-08-01.

Szczegolowy dokument wskrzeszenia projektu: `PROJECT_RECOVERY_AUDIT.md`.

## Priorytet P-1 - odzyskanie kontroli nad projektem

1. Ustalic baseline w Git.
   - `git status --short` pokazuje praktycznie caly projekt jako niezatwierdzony.
   - Przed dalszym rozwojem trzeba zrobic pierwszy commit lub uzgodniony branch roboczy.
   - Bez tego trudno bedzie odroznic stare zmiany, nowe zmiany i potencjalne regresje.

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

5. Ustalic, czy `sales` ma miec dostep do Filament.
   - Obecnie `User::canAccessPanel` wpuszcza role `sales`.
   - Biznesowo trzeba zdecydowac, czy sales ma widziec admin panel, czy tylko raporty sales.

## Priorytet P0 - przed dalszym rozwojem produkcyjnym

1. Dodac zarzadzanie uzytkownikami w panelu admina.
   - `UserResource` w Filament.
   - Pola: imie/nazwa, email, rola, klient dla roli `client`, aktywnosc, haslo/reset hasla.
   - Filtry po roli i aktywnosci.

2. Uporzadkowac dostepy rol.
   - Zdefiniowac docelowa macierz uprawnien.
   - Przeniesc kluczowe sprawdzenia do Policies.
   - Ograniczyc menu i akcje pod role.

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

3. CRUD uzytkownikow w adminie.
   - Aktualnie konta testowe ida z seeda.
   - Produkcyjnie potrzebne zarzadzanie uzytkownikami, aktywnoscia, rolami i klientami.

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
