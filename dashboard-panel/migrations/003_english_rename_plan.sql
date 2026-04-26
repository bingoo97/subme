-- 003_english_rename_plan.sql
-- Planning-only migration (DO NOT RUN directly in production).
-- This file documents target renames for phase 2.
--
-- Recommended rollout:
-- 1) Add parallel English columns/tables.
-- 2) Backfill data.
-- 3) Switch application writes.
-- 4) Switch reads.
-- 5) Drop legacy Polish names.

-- Table rename candidates:
-- RENAME TABLE `wplaty` TO `payments_legacy`;
-- RENAME TABLE `wplaty_crypto` TO `crypto_payments`;
-- RENAME TABLE `refy` TO `referrals`;
-- RENAME TABLE `podstrony` TO `pages`;
-- RENAME TABLE `komunikaty` TO `messages`;
-- NOTE: `produkty_user` and `products_users` overlap semantically.
--       Prefer convergence into one canonical table instead of direct rename.

-- Column rename candidates:
-- ALTER TABLE `produkty_user` CHANGE `id_zamowienia` `order_id` INT NOT NULL;
-- ALTER TABLE `produkty_user` CHANGE `platnosc` `payment_status` TINYINT NOT NULL;
-- ALTER TABLE `produkty_user` CHANGE `wysylka` `delivery_status` TINYINT NOT NULL;
-- ALTER TABLE `produkty_user` CHANGE `data_rozpoczecia` `start_at` TIMESTAMP NOT NULL;
-- ALTER TABLE `produkty_user` CHANGE `data_zakonczenia` `end_at` DATETIME NOT NULL;
-- ALTER TABLE `produkty_user` CHANGE `dodatkowe_info` `additional_info` TEXT NOT NULL;

-- A real executable rename migration should be generated only after:
-- - code switch PR is merged
-- - staging validation passes
-- - rollback scripts are prepared

