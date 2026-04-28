# Database Migrations

Ten katalog i ten dokument opisuja dwa rozne poziomy pracy z baza danych.

## 1. Canonical schema

Pelny, canonical schema source pozostaje tutaj:

- `dashboard-panel/database/v2/001_core_schema.sql`
- `dashboard-panel/database/v2/002_seed_defaults.sql`
- `dashboard-panel/database/v2/003_operational_views.sql`

Tych plikow uzywamy, gdy:

- stawiamy nowa baze od zera
- odtwarzamy swieza instancje aplikacji
- chcemy miec jedno source of truth dla calego schematu

## 2. Incremental migrations

Przyrostowe zmiany dla juz istniejacej bazy trzymamy tutaj:

- `dashboard-panel/database/migrations/`

Tych plikow uzywamy, gdy:

- dodajemy nowa kolumne
- dodajemy nowa tabele
- dodajemy indeks
- modyfikujemy istniejacy widok
- chcemy zaktualizowac produkcje bez resetu calej bazy

## Najwazniejsza zasada

Kazda zmiana schematu produkcyjnego powinna trafic w dwa miejsca:

1. do `dashboard-panel/database/v2/`, aby canonical schema byla aktualna
2. do `dashboard-panel/database/migrations/`, aby istniejace serwery dalo sie zaktualizowac bez pelnego importu od zera

## Konwencja nazewnictwa migracji

Uzywaj nazw w stylu:

- `2026_04_28_001_add_customer_provider_visibility.sql`
- `2026_04_28_002_add_page_guidance_flag.sql`
- `2026_04_29_001_create_help_topics_table.sql`

Zasady:

- data na poczatku
- na koncu krotki opis zmiany
- jedna migracja = jedna logiczna zmiana

## Co powinno byc w migracji

Migracja powinna byc:

- idempotentna tam, gdzie to mozliwe
- ostrozna dla istniejacych danych
- krotka i czytelna

Najlepiej:

- `ALTER TABLE ... ADD COLUMN ...`
- `CREATE TABLE ...`
- `CREATE INDEX ...`
- `DROP VIEW ...`
- `CREATE VIEW ...`

Jesli MySQL nie wspiera bezpiecznego `IF NOT EXISTS` dla danej operacji, opisz to w komentarzu i wykonuj migracje tylko raz na danym serwerze.

## Czego nie robic

Nie uzywaj jako source of truth:

- eksportu z phpMyAdmin
- backupu SQL z panelu admina
- recznych zmian zrobionych tylko na localhost
- recznych zmian zrobionych tylko na produkcji

## Przykładowy update na serwerze

Jesli repo jest w `~/subme`, migracje odpalasz tak:

```bash
cd ~/subme
mysql -u DB_USER -p DB_NAME < dashboard-panel/database/migrations/2026_04_28_001_add_customer_provider_visibility.sql
```

Jesli repo jest w innym katalogu, uruchamiasz to samo z prawdziwej sciezki repo.

## Dodatkowa warstwa zgodnosci

Jesli deploy kodu moze trafic na serwer zanim migracja SQL zostanie odpalona, wolno dodac tymczasowy fallback runtime:

- `CREATE TABLE IF NOT EXISTS`
- `ALTER TABLE` w helperze `app_ensure_*`

Ale to jest tylko warstwa zgodnosci. Docelowa zmiana i tak musi byc zapisana:

- w `dashboard-panel/database/v2/`
- i w `dashboard-panel/database/migrations/`
