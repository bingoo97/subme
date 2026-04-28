# Incremental SQL Migrations

Ten katalog jest na przyrostowe zmiany dla juz istniejacych baz danych.

Uzywaj go do zmian takich jak:

- nowe kolumny
- nowe tabele
- nowe indeksy
- poprawki widokow

## Relacja do `v2/`

Po dodaniu migracji:

1. zaktualizuj tez canonical schema w `dashboard-panel/database/v2/`
2. dopisz, jesli trzeba, tymczasowy runtime fallback w PHP

## Nazewnictwo

Przyklad:

- `2026_04_28_001_add_customer_provider_visibility.sql`

## Uruchamianie

Przyklad:

```bash
cd ~/subme
mysql -u DB_USER -p DB_NAME < dashboard-panel/database/migrations/2026_04_28_001_add_customer_provider_visibility.sql
```

## Szablon

Skopiuj:

- `dashboard-panel/database/migrations/_TEMPLATE.sql`

i zmien nazwe na prawdziwa migracje.
