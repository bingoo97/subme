-- Panel access payments are intentionally separate from customer revenue and balances.
CREATE TABLE IF NOT EXISTS panel_subscription_config (
 id TINYINT UNSIGNED NOT NULL PRIMARY KEY,
 enabled TINYINT(1) NOT NULL DEFAULT 0,
 notify_admin TINYINT(1) NOT NULL DEFAULT 1,
 currency_code CHAR(3) NOT NULL DEFAULT 'EUR',
 price_1 DECIMAL(10,2) NOT NULL DEFAULT 29.00,
 price_2 DECIMAL(10,2) NOT NULL DEFAULT 59.00,
 price_3 DECIMAL(10,2) NOT NULL DEFAULT 99.00,
 limit_1 INT UNSIGNED NOT NULL DEFAULT 50,
 limit_2 INT UNSIGNED NOT NULL DEFAULT 250,
 wallet_btc VARCHAR(191) NOT NULL DEFAULT '',
 wallet_sol VARCHAR(191) NOT NULL DEFAULT '',
 wallet_doge VARCHAR(191) NOT NULL DEFAULT '',
 wallet_usdt VARCHAR(191) NOT NULL DEFAULT '',
 last_scan_at DATETIME DEFAULT NULL,
 usage_synced_at DATETIME DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS panel_subscription_entitlements (
 order_id BIGINT UNSIGNED NOT NULL PRIMARY KEY,
 customer_id INT UNSIGNED NOT NULL,
 paid_through DATETIME NOT NULL,
 recorded_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 KEY idx_panel_entitlements_expiry(paid_through)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
-- No FK: removing an order/customer must not erase its paid-through history.
CREATE TABLE IF NOT EXISTS panel_subscription_requests (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 open_slot TINYINT UNSIGNED DEFAULT NULL,
 admin_user_id INT UNSIGNED NOT NULL,
 status VARCHAR(24) NOT NULL DEFAULT 'pending',
 tier TINYINT UNSIGNED NOT NULL,
 usage_count INT UNSIGNED NOT NULL,
 months TINYINT UNSIGNED NOT NULL,
 quoted_days SMALLINT UNSIGNED NOT NULL,
 granted_days SMALLINT UNSIGNED DEFAULT NULL,
 received_crypto DECIMAL(28,9) NOT NULL DEFAULT 0,
 amount_fiat DECIMAL(10,2) NOT NULL,
 currency_code CHAR(3) NOT NULL,
 asset_code VARCHAR(8) NOT NULL,
 network_code VARCHAR(20) NOT NULL,
 wallet_address VARCHAR(191) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 amount_crypto DECIMAL(28,9) NOT NULL,
 exchange_rate DECIMAL(24,10) NOT NULL,
 created_at DATETIME NOT NULL,
 pay_before DATETIME NOT NULL,
 check_until DATETIME NOT NULL,
 last_checked_at DATETIME DEFAULT NULL,
 transaction_hash VARCHAR(100) CHARACTER SET ascii COLLATE ascii_bin DEFAULT NULL,
 paid_at DATETIME DEFAULT NULL,
 previous_paid_until DATE DEFAULT NULL,
 new_paid_until DATE DEFAULT NULL,
 UNIQUE KEY uniq_panel_open_request(open_slot),
 UNIQUE KEY uniq_panel_transaction(network_code,transaction_hash),
 UNIQUE KEY uniq_panel_invoice_amount(asset_code,wallet_address,amount_crypto),
 KEY idx_panel_requests_scan(status,check_until,last_checked_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS panel_subscription_transfers (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 request_id BIGINT UNSIGNED NOT NULL,
 network_code VARCHAR(20) NOT NULL,
 transaction_hash VARCHAR(100) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 amount_crypto DECIMAL(28,9) NOT NULL,
 received_at DATETIME NOT NULL,
 UNIQUE KEY uniq_panel_transfer(network_code,transaction_hash),
 KEY idx_panel_transfer_request(request_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- First network observation is evidence of an on-time broadcast, never a credited payment.
CREATE TABLE IF NOT EXISTS panel_subscription_observations (
 request_id BIGINT UNSIGNED NOT NULL,
 network_code VARCHAR(20) NOT NULL,
 transaction_hash VARCHAR(100) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 first_seen_at DATETIME NOT NULL,
 PRIMARY KEY(network_code,transaction_hash),
 KEY idx_panel_observation_request(request_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
