-- 001_preflight_schema_audit.sql
-- Run first. Read-only checks before any schema migration.

-- 1) Engine / collation inventory
SELECT
    table_name,
    engine,
    table_collation
FROM information_schema.tables
WHERE table_schema = DATABASE()
ORDER BY table_name;

-- 2) Current indexes
SELECT
    table_name,
    index_name,
    non_unique,
    seq_in_index,
    column_name
FROM information_schema.statistics
WHERE table_schema = DATABASE()
ORDER BY table_name, index_name, seq_in_index;

-- 3) Columns with legacy Polish names (baseline)
SELECT
    table_name,
    column_name,
    column_type
FROM information_schema.columns
WHERE table_schema = DATABASE()
  AND (
      column_name LIKE '%_zamowienia%'
      OR column_name IN ('platnosc', 'wysylka', 'dodatkowe_info', 'data_rozpoczecia', 'data_zakonczenia', 'nazwa', 'tresc')
  )
ORDER BY table_name, ordinal_position;

