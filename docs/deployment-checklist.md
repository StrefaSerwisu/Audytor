# Audytor IT - checklist wdrozeniowy

## Przed startem

- Ustaw produkcyjny `APP_KEY`, `APP_URL`, dane PostgreSQL i sekrety aplikacji.
- Uruchom `php artisan migrate --force`.
- Uruchom `php artisan storage:link`, jezeli publiczne zasoby storage beda potrzebne poza prywatnymi eksportami.
- Ustaw `QUEUE_CONNECTION=database` albo docelowy driver kolejki.
- Uruchom stalego workera: `php artisan queue:work --queue=default --tries=3 --timeout=120`.
- Dodaj nadzor procesu workera w supervisorze/systemd.
- Uruchom `php artisan config:cache`, `php artisan route:cache` i `php artisan view:cache`.

## Po wdrozeniu

- Zaloguj sie jako `lider@globalit.test` albo konto produkcyjnego lidera.
- Otworz audyt zatwierdzony technicznie i wygeneruj eksport PDF oraz DOCX.
- Sprawdz `/reports/exports`, status eksportu i pobieranie gotowego pliku.
- Sprawdz portal klienta `/client/portal` na koncie klienta przypisanym do audytu.
- Sprawdz PWA: instalacja, ekran offline i powrot online.
- Sprawdz, czy logi nie zawieraja bledow workerow kolejek.

## Monitorowanie

- Kontroluj liczbe eksportow w statusach `queued`, `processing` i `failed`.
- Reaguj na eksporty `failed` przez akcje `Ponow eksport` w `/reports/exports`.
- Monitoruj miejsce na dysku dla katalogu `storage/app/private/report-exports`.
