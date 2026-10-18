<?php
/** Isolated database and fake cached chain data only. Never accepts a real payment. */
declare(strict_types=1);
if (strpos((string)getenv('DB_NAME'), '_test_') === false || !getenv('APP_CRYPTO_CACHE_DIR')) {
    throw new RuntimeException('An isolated _test_ database and APP_CRYPTO_CACHE_DIR are required.');
}
require_once dirname(__DIR__) . '/admin/bootstrap.php';
app_crypto_network_budget(0, 0);
$db = Mysql_ks::get_instance();
$messages = admin_load_messages('pl');
$checks = 0;
function preview_check(bool $condition, string $label): void {
    global $checks;
    if (!$condition) throw new RuntimeException($label);
    $checks++;
}
$now = time();
$requested = $now - 1200;
$payment = ['id' => 901, 'requested_at' => date('Y-m-d H:i:s', $requested), 'requested_amount_crypto' => .002, 'requested_amount_value' => 100, 'exchange_rate' => 50000];
$valid = ['hash' => str_repeat('9', 64), 'direction' => 'incoming', 'amount_crypto' => .002, 'timestamp' => $now - 60, 'hash_compact' => 'aaaa…aaaa', 'amount_crypto_label' => '.002 BTC'];
$classify = static fn(array $rows, array $changes = []): array => admin_crypto_accept_preview_classify_rows($rows, array_merge($payment, $changes), $messages, 24, $now);
$result = $classify([$valid]);
preview_check(count($result['transactions']) === 1 && $result['candidate']['amount_crypto'] === .002, 'A single timely incoming transfer provides its actual amount');
foreach ([1, 60, 899, 900, 3600, 883 * 60] as $seconds) {
    $result = $classify([array_merge($valid, ['timestamp' => $requested - $seconds])]);
    preview_check($result['candidate'] === null && !$result['transactions'] && $result['history'][0]['history_reason_code'] === 'before', 'Any pre-request transfer stays in history: ' . $seconds);
}
foreach ([['direction' => 'outgoing'], ['amount_crypto' => 0], ['amount_crypto' => -1], ['amount_crypto' => INF], ['amount_crypto' => NAN], ['timestamp' => 0], ['timestamp' => $now + 1], ['hash' => ''], ['deposit_request_id' => 902], ['amount_crypto' => .00001, 'amount_fiat' => 100000]] as $changes) {
    $result = $classify([array_merge($valid, $changes)]);
    preview_check($result['candidate'] === null && !$result['transactions'], 'Invalid/unrelated transfer cannot prefill an amount: ' . implode(',', array_keys($changes)));
}
preview_check($classify([$valid], ['requested_at' => ''])['candidate'] === null, 'Unknown request time cannot produce a match');
preview_check($classify([$valid], ['requested_amount_crypto' => 0, 'requested_amount_value' => 0])['candidate'] === null, 'A request without a quote cannot produce a match');
$boundary = array_merge($valid, ['timestamp' => $requested]);
preview_check($classify([$boundary])['candidate'] !== null, 'Exact request timestamp is eligible');
$oldPayment = array_merge($payment, ['requested_at' => date('Y-m-d H:i:s', $now - 25 * 3600)]);
$result = admin_crypto_accept_preview_classify_rows([$valid], $oldPayment, $messages, 24, $now);
preview_check($result['candidate'] === null && $result['history'][0]['history_reason_code'] === 'outside', 'Transfer outside the request window is history');
$result = $classify([$valid, array_merge($valid, ['hash' => str_repeat('b', 64)])]);
preview_check(count($result['transactions']) === 2 && $result['candidate'] === null, 'Multiple possible transfers never prefill an arbitrary amount');
$result = $classify([array_merge($valid, ['deposit_request_id' => 901, 'timestamp' => $requested - 3600])]);
preview_check($result['linked'] && $result['candidate'] !== null, 'Explicit ownership is retained for an already linked transfer');
$result = $classify([array_merge($valid, ['deposit_request_id' => 901]), array_merge($valid, ['deposit_request_id' => 901, 'hash' => str_repeat('b', 64)])]);
preview_check($result['candidate'] === null, 'Several linked transfers are not reduced to an arbitrary amount');
$result = $classify([array_merge($valid, ['amount_crypto' => .0016])]);
preview_check($result['candidate']['amount_crypto'] === .0016, 'A plausible partial payment retains the actual received crypto amount');

// Cached chain data must not be hidden by an older database transfer.
admin_app_settings($db);
$asset = (int)$db->select_user("SELECT id FROM crypto_assets WHERE code='BTC'")['id'];
$db->query("INSERT INTO customers(id,email,password_hash,status) VALUES(901,'preview@example.invalid','unused','active')");
$db->query("INSERT INTO crypto_wallet_addresses(id,crypto_asset_id,address,network_code,status) VALUES(901,{$asset},'preview-fixture-wallet','bitcoin','assigned')");
$db->query("INSERT INTO crypto_deposit_requests(id,customer_id,crypto_asset_id,wallet_address_id,requested_fiat_amount,fiat_currency_id,exchange_rate,requested_crypto_amount,status,requested_at) VALUES(901,901,{$asset},901,100,2,50000,.002,'pending',DATE_SUB(NOW(), INTERVAL 20 MINUTE)),(902,901,{$asset},901,100,2,50000,.002,'approved',DATE_SUB(NOW(), INTERVAL 1 HOUR))");
$payment = admin_payment_find($db, 'crypto', 901);
$address = (string)$payment['wallet_address'];
$quote = sha1(json_encode([$payment['id'], $payment['exchange_rate'], $payment['requested_at'], $payment['requested_amount_value']]));
$historyKey = 'btc_rawaddr_' . sha1($address . '|window:24') . '|quote:' . $quote;
$balanceKey = 'wallet_balance_v2_' . sha1('BTC|bitcoin|' . $address) . '|quote:' . $quote;
admin_crypto_accept_preview_cache_write($balanceKey, ['crypto' => '.002 BTC', 'fiat' => '100 EUR']);
$db->query("INSERT INTO crypto_deposit_transactions(wallet_address_id,crypto_asset_id,transaction_hash,amount_crypto,confirmations,received_at) VALUES(901,{$asset},'" . str_repeat('c', 64) . "',.002,6,DATE_SUB(UTC_TIMESTAMP(), INTERVAL 2 HOUR))");
admin_crypto_accept_preview_cache_write($historyKey, ['rows' => [$valid]]);
$GLOBALS['app_crypto_api_observations'] = [];
$preview = admin_crypto_accept_preview_payload($db, $payment, $messages);
preview_check($preview['has_candidate'] === 1 && count($preview['history']) === 1 && $preview['candidate_crypto_amount'] !== '', 'New cached incoming transfer is found despite old database history');
preview_check($GLOBALS['app_crypto_api_observations'] === [], 'Warm preview reuses caches with no external requests');
$db->query("INSERT INTO crypto_deposit_transactions(wallet_address_id,crypto_asset_id,deposit_request_id,transaction_hash,amount_crypto,confirmations,received_at) VALUES(901,{$asset},902,'" . $valid['hash'] . "',.002,6,UTC_TIMESTAMP())");
$preview = admin_crypto_accept_preview_payload($db, $payment, $messages);
preview_check(!$preview['has_candidate'] && $preview['candidate_crypto_amount'] === '' && $preview['match_summary_title'] === '' && $preview['match_summary_percent'] === 0, 'Claimed transfer yields no match summary or suggested amount');
$html = admin_render_crypto_accept_explorer_panel($preview);
preview_check(strpos($html, 'Nie znaleziono wpłaty dla tego requestu') !== false && strpos($html, '<details') !== false && strpos($html, '<details') < strpos($html, $preview['history'][0]['hash_compact']), 'Unrelated history is separate and initially collapsed');
preview_check(strpos($html, 'Dopasowanie') === false && strpos($html, '25%') === false && strpos($html, 'match-badge') === false, 'No misleading match percentages are rendered');
preview_check(strpos($html, 'assigned') === false, 'History uses human descriptions, not internal state names');
$preview['history'][0]['hash_compact'] = '<script>alert(1)</script>';
preview_check(strpos(admin_render_crypto_accept_explorer_panel($preview), '&lt;script&gt;') !== false, 'Transaction labels are HTML escaped');
$before = $db->select_user('SELECT balance_amount FROM customers WHERE id=901');
preview_check((float)$before['balance_amount'] === 0.0, 'Preview does not credit a customer balance');
$before = $db->select_user('SELECT status FROM crypto_deposit_requests WHERE id=901');
preview_check($before['status'] === 'pending', 'Preview does not accept the request');
echo "PASS: {$checks} crypto preview/classification/render/cache assertions\n";
