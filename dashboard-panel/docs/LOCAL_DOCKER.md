# Uruchomienie lokalne (izolowane)

Ten setup uruchamia stronę na osobnych kontenerach, porcie i wolumenie, bez ingerencji w inne projekty Docker.

## 1) Start

W katalogu projektu:

```bash
docker compose --env-file .env.docker -p reseller_local up -d --build
```

## 2) Adresy

- Strona: `http://localhost:8088`
- phpMyAdmin: `http://localhost:8089`

## 3) Dane logowania do bazy

Wartości są w `.env.docker`:

- host: `db` (z poziomu kontenera `web`)
- host: `127.0.0.1` + port `3308` (z hosta)
- baza: `reseller_v2`
- user: `marbodz_reseller`
- hasło: `Reseller12@`

Domyślnie aplikacja działa na bazie `reseller_v2`. Możesz jawnie ustawić:

```bash
APP_DB_NAME=reseller_v2 docker compose --env-file .env.docker -p reseller_local up -d --build
```

Jeśli `APP_DB_NAME` nie jest ustawione, aplikacja używa `reseller_v2`.

SQL jest importowany przy pierwszym starcie pustego wolumenu DB z plików:

- `database/v2/001_core_schema.sql`
- `database/v2/002_seed_defaults.sql`
- `database/v2/003_operational_views.sql`

Jeśli chcesz doładować dane legacy do `reseller_v2`, uruchom po starcie kontenerów:

```bash
dashboard-panel/database/v2/init_v2_in_docker.sh
```

## 4) Stop

```bash
docker compose --env-file .env.docker -p reseller_local down
```

## 5) Reset samej tej bazy (opcjonalnie)

Usuwa tylko wolumen tego projektu (`reseller_db_data`):

```bash
docker compose --env-file .env.docker -p reseller_local down -v
```
