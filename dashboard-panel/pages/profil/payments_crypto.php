<?php

switch ($site) {
    case 'cryptocurrency':
    case 'payments_crypto':
        if (!$user['logged']) {
            $smarty->display('no_access.tpl');
            break;
        }

        $cryptoTopupsReadTable = schema_read_target($db, 'crypto_topups');
        $cryptoTopupsWriteTable = schema_write_target('crypto_topups');
        $cryptoTopupProductIdColumn = schema_read_column($db, 'crypto_topups', 'product_id', 'package_id');
        $cryptoTopupProductPriceColumn = schema_read_column($db, 'crypto_topups', 'product_price', 'package_price');
        $cryptoTopupCreatedAtColumn = schema_read_column($db, 'crypto_topups', 'created_at', 'date');
        $cryptoTopupStatusColumn = schema_read_column($db, 'crypto_topups', 'is_active', 'status');

        $btcPayment = $db->select('cryptocurrency', '*', 'WHERE id = 1 LIMIT 1');
        if (!$btcPayment) {
            $btcPayment = [
                'id' => 1,
                'name' => 'Bitcoin',
                'symbol' => 'BTC',
                'logo_url' => '/img/crypto/btc.png',
                'rate' => 0.0,
            ];
        }
        $btcPayment['rate'] = isset($btcPayment['rate']) ? (float)$btcPayment['rate'] : 0.0;

        $ratePayload = @file_get_contents('https://bitpay.com/api/rates');
        if ($ratePayload !== false) {
            $rateData = json_decode($ratePayload, true);
            if (is_array($rateData) && isset($rateData[3]['rate'])) {
                $marketRate = (float)$rateData[3]['rate'];
                $discountedRate = $marketRate - ($marketRate * 0.01);
                $btcPayment['rate'] = $discountedRate;

                if (!empty($btcPayment['id'])) {
                    $db->update_using_id(['rate', 'date_rate'], [$btcPayment['rate'], $time], 'cryptocurrency', $btcPayment['id']);
                }
            }
        }
        $btcPayment['rate_formatted'] = app_format_crypto_rate($btcPayment['rate']);
        $safeNow = date('Y-m-d H:i:s');
        $v2CryptoRequestsEnabled = app_uses_v2_schema($db) && schema_object_exists($db, 'crypto_deposit_requests');
        $paymentRedirectUrl = '';
        $activeV2CryptoRequest = null;
        $activeV2CryptoRequestRemainingSeconds = 0;
        $topupCurrencyId = (int)($reseller['currency_id'] ?? $reseller['currency'] ?? 0);
        $topupCurrencyCode = strtoupper(trim((string)($reseller['currency_short'] ?? 'USD')));
        if ($topupCurrencyCode === '') {
            $topupCurrencyCode = 'USD';
        }
        $topupCurrencyId = app_effective_currency_id(is_array($settings ?? null) ? $settings : [], $topupCurrencyId);
        $topupCryptoAssets = [];

        if ($v2CryptoRequestsEnabled) {
            $db->query(
                "UPDATE crypto_deposit_requests
                 SET status = 'cancelled',
                     cancelled_at = CASE
                         WHEN cancelled_at IS NULL THEN '{$safeNow}'
                         ELSE cancelled_at
                     END
                 WHERE customer_id = " . (int)$user['id'] . "
                   AND order_id IS NULL
                   AND status IN ('pending', 'awaiting_confirmation')
                   AND expires_at IS NOT NULL
                   AND expires_at <= '{$safeNow}'"
            );
        }

        if ($v2CryptoRequestsEnabled && !empty($settings['crypto_payments_enabled'])) {
            $topupCryptoAssets = app_load_customer_crypto_assets($db, (int)$user['id'], $topupCurrencyCode, is_array($settings ?? null) ? $settings : []);
        }

        if ($v2CryptoRequestsEnabled) {
            $activeV2CryptoRequest = $db->select_user(
                "SELECT
                    crypto_deposit_requests.*,
                    crypto_assets.code AS crypto_code,
                    crypto_assets.name AS crypto_name,
                    crypto_assets.logo_url AS crypto_logo_url,
                    crypto_wallet_addresses.label AS wallet_label,
                    crypto_wallet_addresses.owner_full_name AS wallet_owner_full_name,
                    crypto_wallet_addresses.network_code AS wallet_network_code,
                    crypto_wallet_addresses.wallet_provider AS wallet_provider,
                    crypto_wallet_addresses.address AS wallet_address,
                    crypto_wallet_addresses.memo_tag AS wallet_memo_tag,
                    crypto_wallet_addresses.qr_url AS wallet_qr_url,
                    currencies.symbol AS currency_symbol,
                    currencies.code AS currency_code
                 FROM crypto_deposit_requests
                 LEFT JOIN crypto_assets ON crypto_assets.id = crypto_deposit_requests.crypto_asset_id
                 LEFT JOIN crypto_wallet_addresses ON crypto_wallet_addresses.id = crypto_deposit_requests.wallet_address_id
                 LEFT JOIN currencies ON currencies.id = crypto_deposit_requests.fiat_currency_id
                 WHERE crypto_deposit_requests.customer_id = " . (int)$user['id'] . "
                   AND crypto_deposit_requests.order_id IS NULL
                   AND crypto_deposit_requests.status IN ('pending', 'awaiting_confirmation')
                   AND (crypto_deposit_requests.expires_at IS NULL OR crypto_deposit_requests.expires_at > '{$safeNow}')
                 ORDER BY crypto_deposit_requests.id DESC
                 LIMIT 1"
            );

            if ($activeV2CryptoRequest) {
                $activeV2CryptoRequest['crypto_logo_path'] = app_crypto_logo_by_code((string)($activeV2CryptoRequest['crypto_code'] ?? ''), (string)($activeV2CryptoRequest['crypto_logo_url'] ?? ''));
                $activeV2CryptoRequest['qr_code_url'] = trim((string)($activeV2CryptoRequest['wallet_qr_url'] ?? ''));
                if ($activeV2CryptoRequest['qr_code_url'] === '' && !empty($activeV2CryptoRequest['wallet_address'])) {
                    $activeV2CryptoRequest['qr_code_url'] = 'https://quickchart.io/qr?text=' . rawurlencode((string)$activeV2CryptoRequest['wallet_address']) . '&size=300';
                } elseif ($activeV2CryptoRequest['qr_code_url'] !== '') {
                    $activeV2CryptoRequest['qr_code_url'] = app_format_logo_path($activeV2CryptoRequest['qr_code_url']);
                }
                $activeV2CryptoRequest['wallet_network_label'] = app_crypto_network_label((string)($activeV2CryptoRequest['wallet_network_code'] ?? ''));
                $activeV2CryptoRequestRemainingSeconds = !empty($activeV2CryptoRequest['expires_at'])
                    ? max(0, strtotime((string)$activeV2CryptoRequest['expires_at']) - $time_s)
                    : 0;
            }
        }

        if (isset($_POST['del_crypto'])) {
            if (!app_csrf_is_valid($_POST['_csrf'] ?? null)) {
                $smarty->assign('alert_error', localization_translate($t, 'csrf_invalid'));
                $smarty->display('alert.tpl');
            } else {
                $cryptoPaymentId = (int)$_POST['del_crypto'];
                $requestKind = trim((string)($_POST['del_crypto_kind'] ?? 'legacy'));
                if ($requestKind === 'v2' && $v2CryptoRequestsEnabled) {
                    $cryptoPayment = $db->select_user(
                        "SELECT id
                         FROM crypto_deposit_requests
                         WHERE id = {$cryptoPaymentId}
                           AND customer_id = " . (int)$user['id'] . "
                           AND order_id IS NULL
                         LIMIT 1"
                    );
                    if ($cryptoPayment) {
                        $db->delete_using_id('crypto_deposit_requests', $cryptoPaymentId);
                        $smarty->assign('alert', 'Crypto payment removed.');
                        $smarty->display('alert.tpl');
                    }
                } else {
                    $cryptoPayment = $db->select($cryptoTopupsWriteTable, '*', "WHERE id = {$cryptoPaymentId} AND user_id = " . (int)$user['id']);

                    if ($cryptoPayment) {
                        $db->delete_using_id($cryptoTopupsWriteTable, $cryptoPaymentId);
                        $smarty->assign('alert', 'Crypto payment removed.');
                        $smarty->display('alert.tpl');
                    }
                }
            }
        }

        $activePaymentCountQuery = $db->select(
            $cryptoTopupsReadTable,
            'COUNT(id) AS total',
            "WHERE user_id = " . (int)$user['id'] . " AND {$cryptoTopupStatusColumn} = 0"
        );
        $activePayment = isset($activePaymentCountQuery['total']) ? (int)$activePaymentCountQuery['total'] : 0;
        if ($v2CryptoRequestsEnabled) {
            $v2ActiveRow = $db->select_user(
                "SELECT COUNT(*) AS total
                 FROM crypto_deposit_requests
                 WHERE customer_id = " . (int)$user['id'] . "
                   AND order_id IS NULL
                   AND status IN ('pending', 'awaiting_confirmation')
                   AND (expires_at IS NULL OR expires_at > '{$safeNow}')"
            );
            $activePayment += (int)($v2ActiveRow['total'] ?? 0);
        }

        if (isset($_POST['create_topup_payment'])) {
            if (!app_csrf_is_valid($_POST['_csrf'] ?? null)) {
                $smarty->assign('alert_error', localization_translate($t, 'csrf_invalid'));
                $smarty->display('alert.tpl');
            } elseif (!$v2CryptoRequestsEnabled || empty($settings['crypto_payments_enabled'])) {
                $smarty->assign('alert_error', localization_translate($t, 'balance_topup_unavailable', 'Balance top-up is currently unavailable.'));
                $smarty->display('alert.tpl');
            } elseif ($activePayment > 0) {
                $paymentRedirectUrl = '/cryptocurrency';
            } else {
                $createdWalletAssignmentNow = false;
                $paymentMethod = trim((string)($_POST['payment_method'] ?? ''));
                $selectedAssetId = isset($_POST['crypto_wallet_assignment_id']) ? (int)$_POST['crypto_wallet_assignment_id'] : 0;
                $requestedAmountRaw = str_replace(',', '.', trim((string)($_POST['topup_amount'] ?? '')));
                $requestedAmount = is_numeric($requestedAmountRaw) ? round((float)$requestedAmountRaw, 2) : 0.0;
                $selectedAsset = null;

                foreach ($topupCryptoAssets as $asset) {
                    if ((int)($asset['wallet_assignment_id'] ?? 0) === $selectedAssetId || (int)($asset['id'] ?? 0) === $selectedAssetId) {
                        $selectedAsset = $asset;
                        break;
                    }
                }

                if ($paymentMethod !== 'crypto') {
                    $smarty->assign('alert_error', localization_translate($t, 'balance_topup_unavailable', 'Balance top-up is currently unavailable.'));
                    $smarty->display('alert.tpl');
                } elseif (!$selectedAsset) {
                    $smarty->assign('alert_error', localization_translate($t, 'balance_topup_choose_crypto_error', 'Choose a cryptocurrency first.'));
                    $smarty->display('alert.tpl');
                } elseif ($requestedAmount <= 0) {
                    $smarty->assign('alert_error', localization_translate($t, 'balance_topup_amount_error', 'Enter a valid top-up amount.'));
                    $smarty->display('alert.tpl');
                } elseif ($topupCurrencyId <= 0) {
                    $smarty->assign('alert_error', localization_translate($t, 'balance_topup_unavailable', 'Balance top-up is currently unavailable.'));
                    $smarty->display('alert.tpl');
                } else {
                    $latestRates = app_refresh_crypto_rates($db, $topupCurrencyCode);
                    foreach ($latestRates as $rateAsset) {
                        if ((int)($rateAsset['id'] ?? 0) === (int)($selectedAsset['crypto_asset_id'] ?? 0)) {
                            $selectedAsset['rate'] = isset($rateAsset['current_rate_fiat']) ? (float)$rateAsset['current_rate_fiat'] : 0.0;
                            break;
                        }
                    }

                    if ((float)($selectedAsset['rate'] ?? 0) <= 0) {
                        $smarty->assign('alert_error', localization_translate($t, 'balance_topup_rate_error', 'Exchange rate is unavailable for the selected cryptocurrency.'));
                        $smarty->display('alert.tpl');
                        $selectedAsset = null;
                    }

                    if (!$selectedAsset) {
                        // error already displayed above
                    } elseif ((int)($selectedAsset['wallet_assignment_id'] ?? 0) <= 0) {
                        $assignedWalletId = app_assign_customer_crypto_wallet(
                            $db,
                            (int)($selectedAsset['wallet_address_id'] ?? 0),
                            (int)$user['id'],
                            is_array($settings ?? null) ? $settings : [],
                            0,
                            'Assigned from balance top-up wizard'
                        );

                        if ($assignedWalletId <= 0) {
                            $smarty->assign('alert_error', localization_translate($t, 'balance_topup_create_error', 'Unable to create the payment request right now.'));
                            $smarty->display('alert.tpl');
                            $selectedAsset = null;
                        } else {
                            $createdWalletAssignmentNow = true;
                            $selectedAsset['wallet_assignment_id'] = $assignedWalletId;
                            $selectedAsset['is_assigned'] = true;
                        }
                    }

                    if ((int)($selectedAsset['wallet_assignment_id'] ?? 0) <= 0) {
                        $selectedAsset = null;
                    }

                    if ($selectedAsset) {
                    $requestedCryptoAmount = round($requestedAmount / (float)$selectedAsset['rate'], 8);
                    $createdCryptoRequestId = app_create_crypto_deposit_request($db, [
                        'customer_id' => (int)$user['id'],
                        'order_id' => null,
                        'crypto_asset_id' => (int)$selectedAsset['crypto_asset_id'],
                        'wallet_address_id' => (int)$selectedAsset['wallet_address_id'],
                        'wallet_assignment_id' => (int)$selectedAsset['wallet_assignment_id'],
                        'requested_fiat_amount' => $requestedAmount,
                        'fiat_currency_id' => $topupCurrencyId,
                        'exchange_rate' => (float)$selectedAsset['rate'],
                        'requested_crypto_amount' => $requestedCryptoAmount,
                        'assignment_mode' => 'manual',
                        'status' => 'pending',
                        'requested_at' => date('Y-m-d H:i:s', $time_s),
                        'expires_at' => date('Y-m-d H:i:s', $time_s + 3600),
                        'request_note' => 'Created from balance top-up wizard',
                    ]);

                    if ($createdCryptoRequestId > 0) {
                        $paymentRedirectUrl = '/cryptocurrency';
                    }

                    if ($paymentRedirectUrl === '') {
                        if ($createdWalletAssignmentNow && !empty($selectedAsset['wallet_assignment_id'])) {
                            app_release_crypto_wallet_assignment_if_unused($db, (int)$selectedAsset['wallet_assignment_id'], 'Released after failed balance top-up payment request creation');
                        }
                        $smarty->assign('alert_error', localization_translate($t, 'balance_topup_create_error', 'Unable to create the payment request right now.'));
                        $smarty->display('alert.tpl');
                    }
                    }
                }
            }
        }

        if (isset($_POST['add_new']) && $activePayment === 0) {
            if (!app_csrf_is_valid($_POST['_csrf'] ?? null)) {
                $smarty->assign('alert_error', localization_translate($t, 'csrf_invalid'));
                $smarty->display('alert.tpl');
            } else {
                $packagePrice = isset($_POST['price_package']) ? (float)$_POST['price_package'] : 0.0;
                $cryptoRate = isset($btcPayment['rate']) ? (float)$btcPayment['rate'] : 0.0;

                if ($packagePrice > 0 && $cryptoRate > 0) {
                    $discountPercent = 15;
                    $discountedPackagePrice = $packagePrice - (($discountPercent * $packagePrice) / 100);
                    $discountedPackagePrice = round($discountedPackagePrice * 2) / 2;
                    $cryptoAmount = number_format($discountedPackagePrice / $cryptoRate, 6, '.', '');

                    $cryptoInsertFields = ['user_id', 'package_id', 'package_price', 'crypto_id', 'crypto_rate', 'crypto_amount', 'date', 'status'];
                    $cryptoInsertValues = [$user['id'], 0, $packagePrice, 1, $cryptoRate, $cryptoAmount, $time, 0];

                    if (schema_column_exists($db, $cryptoTopupsWriteTable, 'product_id')) {
                        $cryptoInsertFields[] = 'product_id';
                        $cryptoInsertValues[] = 0;
                    }
                    if (schema_column_exists($db, $cryptoTopupsWriteTable, 'product_price')) {
                        $cryptoInsertFields[] = 'product_price';
                        $cryptoInsertValues[] = $packagePrice;
                    }
                    if (schema_column_exists($db, $cryptoTopupsWriteTable, 'created_at')) {
                        $cryptoInsertFields[] = 'created_at';
                        $cryptoInsertValues[] = $time;
                    }
                    if (schema_column_exists($db, $cryptoTopupsWriteTable, 'is_active')) {
                        $cryptoInsertFields[] = 'is_active';
                        $cryptoInsertValues[] = 0;
                    }

                    $db->insert($cryptoInsertFields, $cryptoInsertValues, $cryptoTopupsWriteTable);

                    $smarty->assign('alert', 'Crypto payment created.');
                    $smarty->display('alert.tpl');
                    $activePayment = 1;
                } else {
                    $smarty->assign('alert_error', 'Crypto payment could not be created.');
                    $smarty->display('alert.tpl');
                }
            }
        }

        $cryptoPaymentsQuery = "
            SELECT
                {$cryptoTopupsReadTable}.*,
                {$cryptoTopupsReadTable}.{$cryptoTopupProductIdColumn} AS payment_product_id,
                {$cryptoTopupsReadTable}.{$cryptoTopupProductPriceColumn} AS payment_product_price,
                {$cryptoTopupsReadTable}.{$cryptoTopupProductPriceColumn} AS package_price,
                {$cryptoTopupsReadTable}.{$cryptoTopupCreatedAtColumn} AS payment_created_at,
                {$cryptoTopupsReadTable}.{$cryptoTopupStatusColumn} AS payment_status,
                cryptocurrency.name AS crypto_name,
                cryptocurrency.adress_code AS address_code,
                cryptocurrency.note AS note,
                cryptocurrency.logo_url AS crypto_logo_url,
                cryptocurrency.qr_url AS qr_url,
                cryptocurrency.symbol AS crypto_symbol
            FROM {$cryptoTopupsReadTable}
            LEFT JOIN cryptocurrency ON {$cryptoTopupsReadTable}.crypto_id = cryptocurrency.id
            WHERE {$cryptoTopupsReadTable}.user_id = " . (int)$user['id'] . "
            ORDER BY {$cryptoTopupsReadTable}.{$cryptoTopupStatusColumn} ASC, {$cryptoTopupsReadTable}.{$cryptoTopupCreatedAtColumn} ASC
        ";
        $cryptoPayments = $db->select_full_user($cryptoPaymentsQuery);

        if ($cryptoPayments) {
            foreach ($cryptoPayments as $index => $cryptoPayment) {
                $cryptoPayments[$index]['discount_price'] = number_format(
                    (float)$cryptoPayment['crypto_amount'] * (float)$cryptoPayment['crypto_rate'],
                    2,
                    '.',
                    ''
                );

                $createdTimestamp = strtotime($cryptoPayment['payment_created_at']);
                $expiresAt = date('Y-m-d H:i:s', $createdTimestamp + (60 * 30));
                $expiresTimestamp = strtotime($expiresAt);

                $cryptoPayments[$index]['date_end'] = $expiresAt;
                $cryptoPayments[$index]['date_end_s'] = $expiresTimestamp;

                if ((int)$cryptoPayment['payment_status'] === 0 && $expiresTimestamp < $time_s) {
                    $statusFields = ['status'];
                    $statusValues = [1];
                    if (schema_column_exists($db, $cryptoTopupsWriteTable, 'is_active')) {
                        $statusFields[] = 'is_active';
                        $statusValues[] = 1;
                    }
                    $db->update_using_id($statusFields, $statusValues, $cryptoTopupsWriteTable, $cryptoPayment['id']);
                    $cryptoPayments[$index]['status'] = 1;
                }

                if ((int)$cryptoPayment['payment_status'] === 1 && $expiresTimestamp < ($time_s - (3600 * 12))) {
                    $db->delete_using_id($cryptoTopupsWriteTable, $cryptoPayment['id']);
                    unset($cryptoPayments[$index]);
                    continue;
                }

                $cryptoPayments[$index]['date_s'] = $createdTimestamp;
                $cryptoPayments[$index]['date'] = date('d.m.Y H:i', $createdTimestamp);
                $cryptoPayments[$index]['status'] = (int)$cryptoPayments[$index]['payment_status'];
                $cryptoPayments[$index]['crypto_rate_formatted'] = app_format_crypto_rate($cryptoPayments[$index]['crypto_rate'] ?? null);
            }

            $cryptoPayments = array_values($cryptoPayments);
        }

        if ($v2CryptoRequestsEnabled) {
            $v2CryptoPayments = $db->select_full_user(
                "SELECT
                    crypto_deposit_requests.id,
                    crypto_deposit_requests.status,
                    crypto_deposit_requests.requested_fiat_amount,
                    crypto_deposit_requests.requested_crypto_amount,
                    crypto_deposit_requests.exchange_rate,
                    crypto_deposit_requests.requested_at,
                    crypto_deposit_requests.expires_at,
                    crypto_assets.name AS crypto_name,
                    crypto_assets.code AS crypto_symbol,
                    crypto_assets.logo_url AS crypto_logo_url,
                    crypto_wallet_addresses.address AS address_code,
                    crypto_wallet_addresses.owner_full_name,
                    crypto_wallet_addresses.qr_url,
                    currencies.symbol AS currency_symbol,
                    currencies.code AS currency_code
                 FROM crypto_deposit_requests
                 LEFT JOIN crypto_assets ON crypto_assets.id = crypto_deposit_requests.crypto_asset_id
                 LEFT JOIN crypto_wallet_addresses ON crypto_wallet_addresses.id = crypto_deposit_requests.wallet_address_id
                 LEFT JOIN currencies ON currencies.id = crypto_deposit_requests.fiat_currency_id
                 WHERE crypto_deposit_requests.customer_id = " . (int)$user['id'] . "
                   AND crypto_deposit_requests.order_id IS NULL
                 ORDER BY crypto_deposit_requests.id DESC"
            );

            if ($v2CryptoPayments) {
                foreach ($v2CryptoPayments as $index => $cryptoPayment) {
                    $createdTimestamp = !empty($cryptoPayment['requested_at']) ? strtotime((string)$cryptoPayment['requested_at']) : 0;
                    $expiresTimestamp = !empty($cryptoPayment['expires_at']) ? strtotime((string)$cryptoPayment['expires_at']) : 0;
                    $statusRaw = strtolower(trim((string)($cryptoPayment['status'] ?? '')));
                    $statusValue = 1;
                    if (in_array($statusRaw, ['pending', 'awaiting_confirmation'], true) && $expiresTimestamp > $time_s) {
                        $statusValue = 0;
                    } elseif (in_array($statusRaw, ['confirmed', 'paid', 'completed'], true)) {
                        $statusValue = 2;
                    }

                    $v2CryptoPayments[$index]['status'] = $statusValue;
                    $v2CryptoPayments[$index]['request_kind'] = 'v2';
                    $v2CryptoPayments[$index]['discount_price'] = number_format((float)($cryptoPayment['requested_fiat_amount'] ?? 0), 2, '.', '');
                    $v2CryptoPayments[$index]['package_price'] = number_format((float)($cryptoPayment['requested_fiat_amount'] ?? 0), 2, '.', '');
                    $v2CryptoPayments[$index]['crypto_amount'] = number_format((float)($cryptoPayment['requested_crypto_amount'] ?? 0), 8, '.', '');
                    $v2CryptoPayments[$index]['crypto_rate_formatted'] = app_format_crypto_rate($cryptoPayment['exchange_rate'] ?? null);
                    $v2CryptoPayments[$index]['date_end'] = $expiresTimestamp > 0 ? date('Y-m-d H:i:s', $expiresTimestamp) : '';
                    $v2CryptoPayments[$index]['date_end_s'] = $expiresTimestamp;
                    $v2CryptoPayments[$index]['date_s'] = $createdTimestamp;
                    $v2CryptoPayments[$index]['date'] = $createdTimestamp > 0 ? date('d.m.Y H:i', $createdTimestamp) : '';
                    $v2CryptoPayments[$index]['crypto_logo_url'] = app_crypto_logo_by_code((string)($cryptoPayment['crypto_symbol'] ?? ''), (string)($cryptoPayment['crypto_logo_url'] ?? ''));
                    $v2CryptoPayments[$index]['note'] = trim((string)($cryptoPayment['owner_full_name'] ?? ''));
                    if (trim((string)($v2CryptoPayments[$index]['qr_url'] ?? '')) === '' && !empty($cryptoPayment['address_code'])) {
                        $v2CryptoPayments[$index]['qr_url'] = 'https://quickchart.io/qr?text=' . rawurlencode((string)$cryptoPayment['address_code']) . '&size=300';
                    }
                }

                $cryptoPayments = array_merge($v2CryptoPayments, $cryptoPayments ?: []);
            }
        }

        if ($cryptoPayments) {
            usort($cryptoPayments, static function (array $left, array $right): int {
                $leftStatus = (int)($left['status'] ?? 1);
                $rightStatus = (int)($right['status'] ?? 1);
                if ($leftStatus !== $rightStatus) {
                    return $leftStatus <=> $rightStatus;
                }

                return (int)($right['date_s'] ?? 0) <=> (int)($left['date_s'] ?? 0);
            });
            $smarty->assign('crypto_payments', $cryptoPayments);
        }

        $smarty->assign('pay_btc', $btcPayment);
        $smarty->assign('active_payment', $activePayment);
        $smarty->assign('active_v2_crypto_request', $activeV2CryptoRequest ?: null);
        $smarty->assign('active_v2_crypto_request_remaining_seconds', $activeV2CryptoRequestRemainingSeconds);
        $smarty->assign('payment_redirect_url', $paymentRedirectUrl);
        $smarty->assign('balance_topup_enabled', $v2CryptoRequestsEnabled && !empty($settings['crypto_payments_enabled']));
        $smarty->assign('balance_topup_crypto_assets', $topupCryptoAssets);
        $smarty->assign('balance_topup_action_url', '/cryptocurrency');
        $smarty->display('profil/payments_crypto.tpl');
        break;
}

?>
