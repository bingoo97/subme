-- Candidate index patch for performance (review before apply).
-- Target DB: MySQL 8.x
-- Note: run first on staging.

ALTER TABLE `produkty_user`
  ADD INDEX `idx_produkt_user_user_status` (`user`, `status`),
  ADD INDEX `idx_produkt_user_auction_status` (`aukcja`, `status`),
  ADD INDEX `idx_produkt_user_payment_shipment_status` (`platnosc`, `wysylka`, `status`),
  ADD INDEX `idx_produkt_user_end_status` (`data_zakonczenia`, `status`),
  ADD INDEX `idx_produkt_user_txid` (`id_transakcji`);

ALTER TABLE `products_users`
  ADD INDEX `idx_products_users_user_status` (`user_id`, `status`),
  ADD INDEX `idx_products_users_res_user` (`res_id`, `user_id`),
  ADD INDEX `idx_products_users_product_status` (`product_id`, `status`),
  ADD INDEX `idx_products_users_payment_shipment_status` (`payment`, `shipment`, `status`),
  ADD INDEX `idx_products_users_end_status` (`date_end`, `status`);

ALTER TABLE `email_send`
  ADD INDEX `idx_email_send_user_email_status` (`user`, `email_id`, `status`),
  ADD INDEX `idx_email_send_status_date` (`status`, `data`),
  ADD INDEX `idx_email_send_email_status` (`email_id`, `status`);

ALTER TABLE `user_online`
  ADD INDEX `idx_user_online_user_status` (`user`, `status`),
  ADD INDEX `idx_user_online_status` (`status`);

ALTER TABLE `produkty_chat`
  ADD INDEX `idx_produkty_chat_user2_status_date` (`user2`, `status`, `data`),
  ADD INDEX `idx_produkty_chat_user1_user2` (`user1`, `user2`);

ALTER TABLE `wplaty`
  ADD INDEX `idx_wplaty_user_status` (`user_id`, `status`),
  ADD INDEX `idx_wplaty_order_status` (`id_zamowienia`, `status`);

ALTER TABLE `wplaty_crypto`
  ADD INDEX `idx_wplaty_crypto_user_status` (`user_id`, `status`),
  ADD INDEX `idx_wplaty_crypto_crypto_status` (`crypto_id`, `status`),
  ADD INDEX `idx_wplaty_crypto_date_status` (`date`, `status`);

ALTER TABLE `resellers_news`
  ADD INDEX `idx_resellers_news_res_status_date` (`res_id`, `status`, `date`);

ALTER TABLE `users`
  ADD INDEX `idx_users_res_status` (`res_id`, `status`),
  ADD INDEX `idx_users_email` (`email`);

