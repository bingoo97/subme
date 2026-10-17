<?php
declare(strict_types=1);

/** Only confirmed, successful transfers are eligible for automatic credit. */
function admin_crypto_verified_incoming_row(array $payment, array $row, ?callable $ethLookup = null): ?array
{
    $asset = strtoupper(trim((string)($payment['asset_code'] ?? '')));
    $network = admin_crypto_accept_preview_normalize_network_code((string)($payment['network_code'] ?? ''), $asset);
    $hash = trim((string)($row['hash'] ?? ''));
    $amount = (float)($row['amount_crypto'] ?? 0);
    if (($row['direction'] ?? '') !== 'incoming' || !is_finite($amount) || $amount <= 0) return null;
    if ($asset === 'SOL' && $network === 'solana') {
        if (!preg_match('/^[1-9A-HJ-NP-Za-km-z]{64,88}$/D', $hash) || ($row['confirmation_status'] ?? '') !== 'finalized' || empty($row['transfer_verified'])) return null;
        return $row;
    }
    if (!preg_match($network === 'ethereum' ? '/^0x[a-f0-9]{64}$/iD' : '/^[a-f0-9]{64}$/iD', $hash)) return null;
    $row['hash'] = strtolower($hash);
    $required = ['BTC:bitcoin' => 2, 'DOGE:dogecoin' => 6, 'TRX:tron' => 20];
    if (isset($required[$asset . ':' . $network])) {
        return (int)($row['confirmations'] ?? 0) >= $required[$asset . ':' . $network] ? $row : null;
    }
    if ($network !== 'ethereum' || !in_array($asset, ['ETH', 'USDT', 'USDC'], true)) return null;
    $receipt = $ethLookup ? $ethLookup($hash) : admin_http_json('https://api.ethplorer.io/getTxInfo/' . rawurlencode($hash) . '?apiKey=freekey', 6, 3);
    if (!is_array($receipt) || ($receipt['success'] ?? null) !== true || (int)($receipt['confirmations'] ?? 0) < 12 || strtolower((string)($receipt['hash'] ?? '')) !== strtolower($hash)) return null;
    $wallet = strtolower(trim((string)($payment['wallet_address'] ?? '')));
    if ($asset === 'ETH') {
        if (strtolower((string)($receipt['to'] ?? '')) !== $wallet || strtolower((string)($receipt['from'] ?? '')) === $wallet) return null;
        $amount = isset($receipt['rawValue']) ? admin_crypto_accept_preview_decimal_from_raw($receipt['rawValue'], 18) : (float)($receipt['value'] ?? 0);
    } else {
        $contract = admin_crypto_accept_preview_known_token_contract($asset, $network);
        if ($contract === '') return null;
        $amount = 0.0;
        foreach ((array)($receipt['operations'] ?? []) as $operation) {
            if (!is_array($operation) || strtolower((string)($operation['tokenInfo']['address'] ?? '')) !== $contract
                || strtolower((string)($operation['to'] ?? '')) !== $wallet || strtolower((string)($operation['from'] ?? '')) === $wallet) continue;
            // The allowlisted USDT and USDC contracts both have six decimals.
            $amount += admin_crypto_accept_preview_decimal_from_raw($operation['value'] ?? '', 6);
        }
    }
    if (!is_finite($amount) || $amount <= 0) return null;
    $row['amount_crypto'] = $amount;
    $row['timestamp'] = (int)($receipt['timestamp'] ?? 0);
    $row['received_at'] = app_datetime_utc_from_unix_timestamp($row['timestamp']);
    $row['confirmations'] = (int)$receipt['confirmations'];
    return $row;
}

function admin_render_crypto_network_status_grid(array $rows, array $messages): string
{
    ob_start();
    ?>
                                            <div class="admin-crypto-network-status-grid">
                                                <?php foreach ($rows as $statusRow): ?>
                                                    <div class="admin-crypto-network-status-card admin-crypto-network-status-card--<?php echo admin_e((string)($statusRow['key'] ?? 'default')); ?>">
                                                        <div class="admin-crypto-network-status-card__header">
                                                            <div>
                                                                <div class="admin-crypto-network-status-card__asset"><?php echo admin_e((string)($statusRow['asset_label'] ?? 'CRYPTO')); ?></div>
                                                                <div class="admin-crypto-network-status-card__network"><?php echo admin_e((string)($statusRow['network_label'] ?? '')); ?></div>
                                                            </div>
                                                            <span class="admin-status-pill <?php echo (($statusRow['status_code'] ?? '') === 'available') ? 'admin-status-pill--available' : ((($statusRow['status_code'] ?? '') === 'warning') ? 'admin-status-pill--warning' : 'admin-status-pill--danger'); ?>">
                                                                <?php echo admin_e((string)($statusRow['status_label'] ?? '')); ?>
                                                            </span>
                                                        </div>
                                                        <div class="admin-crypto-network-status-card__provider"><?php echo admin_e(admin_t($messages, 'settings_crypto_preview_status_provider_prefix', 'Provider:')); ?> <?php echo admin_e((string)($statusRow['provider_label'] ?? '')); ?></div>
                                                        <div class="admin-crypto-network-status-card__message"><?php echo admin_e((string)($statusRow['message'] ?? '')); ?></div>
                                                        <div class="admin-crypto-network-status-card__meta">
                                                            <span><?php echo admin_e(admin_t($messages, 'settings_crypto_preview_status_checked_at', 'Checked')); ?>: <?php echo admin_e((string)($statusRow['checked_at_label'] ?? '')); ?></span>
                                                            <span><?php echo admin_e(admin_t($messages, 'settings_crypto_preview_status_latency', 'Latency')); ?>: <?php echo admin_e((string)((int)($statusRow['latency_ms'] ?? 0))); ?> ms</span>
                                                        </div>
                                                        <?php $statusChecks = (array)($statusRow['checks'] ?? []); ?>
                                                        <?php if ($statusChecks): ?>
                                                            <div class="admin-crypto-network-status-card__checks">
                                                                <?php foreach ($statusChecks as $statusCheck): ?>
                                                                    <div class="admin-crypto-network-status-card__check">
                                                                        <span class="admin-status-pill <?php echo !empty($statusCheck['ok']) ? 'admin-status-pill--available' : (!empty($statusCheck['deferred']) ? 'admin-status-pill--warning' : 'admin-status-pill--danger'); ?>">
                                                                            <?php echo admin_e((string)($statusCheck['label'] ?? 'Check')); ?>
                                                                        </span>
                                                                        <div class="admin-crypto-network-status-card__check-detail"><?php echo admin_e((string)($statusCheck['detail'] ?? '')); ?></div>
                                                                    </div>
                                                                <?php endforeach; ?>
                                                            </div>
                                                        <?php endif; ?>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
    <?php
    return (string)ob_get_clean();
}
