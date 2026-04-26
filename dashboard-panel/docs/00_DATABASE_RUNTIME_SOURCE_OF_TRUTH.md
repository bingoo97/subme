# Database Runtime Source Of Truth

Ten dokument definiuje jedno źródło prawdy dla lokalnego środowiska Docker.

## Aktywna baza aplikacji

- `http://localhost:8088` działa z kontenera `reseller_web_local`.
- Ten kontener używa:
  - `DB_HOST=db`
  - `DB_NAME=reseller_v2`
  - `DB_USER=marbodz_reseller`
- Kontener `reseller_payment_worker_local` używa tej samej konfiguracji DB.
- Ten worker uruchamia cyklicznie:
  - `dashboard-panel/scripts/system_maintenance.php`

## Jedno źródło prawdy

- Dla lokalnego runtime źródłem prawdy jest baza `reseller_v2`.
- Jeśli admin i frontend usera pokazują różne dane, najpierw trzeba sprawdzić zawartość `reseller_v2`, nie `marbodz_reseller`.
- `dashboard-panel/config/mysql.php` jest wspólnym punktem połączenia DB dla:
  - frontendu usera
  - panelu admina
  - workerów i skryptów pomocniczych

## Ładowanie schematu przy czystym starcie

Przy pustym wolumenie Docker baza jest inicjalizowana z:

- `dashboard-panel/database/v2/001_core_schema.sql`
- `dashboard-panel/database/v2/002_seed_defaults.sql`
- `dashboard-panel/database/v2/003_operational_views.sql`

## Import danych legacy

- Import legacy nie jest częścią podstawowego bootstrapu kontenera DB.
- Jeśli potrzebne są dane historyczne, należy po starcie kontenerów uruchomić:

```bash
dashboard-panel/database/v2/init_v2_in_docker.sh
```

## Reguła na przyszłość

- Każda zmiana konfiguracji Dockera, dokumentacji lokalnego uruchomienia albo modułów czytających dane z DB musi utrzymywać zgodność z tym dokumentem.
- Jeśli aktywna baza runtime się zmieni, ten plik trzeba zaktualizować w tym samym commicie co zmiana konfiguracji.
