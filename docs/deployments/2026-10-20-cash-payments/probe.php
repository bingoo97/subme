<?php
declare(strict_types=1);if(PHP_SAPI!=='cli')exit(1);
$app=$argv[1];require $app.'/admin/bootstrap.php';require $app.'/admin/cash_payments.php';require $app.'/admin/crypto_quick.php';
app_crypto_network_budget(0,0);$db=Mysql_ks::get_instance();
if(!schema_object_exists($db,'admin_cash_payments'))throw new RuntimeException('Cash receipts missing');
if(!function_exists('admin_create_cash_payment')||!function_exists('admin_cash_payment_orders'))throw new RuntimeException('Cash handler missing');
$csrfToken='read-only-deployment-preview';$messages=[];ob_start();require $app.'/admin/partials/crypto-create.php';$html=ob_get_clean();
foreach(['data-create-apply-renewal','data-create-renewal-wrap','data-create-product-label','data-create-assets','data-create-form'] as $marker)if(!str_contains($html,$marker))throw new RuntimeException('Creator markup missing');
$copy=admin_crypto_create_labels();foreach(['cash','cash_receipt_title','cash_pending','cash_renewal_pending','cash_renewed','renewal_apply_help','purpose_help'] as $key)if(empty($copy[$key]))throw new RuntimeException('Payment labels incomplete');
$web=trim(file_get_contents($app.'/.public-root-path'));$js=file_get_contents($web.'/assets/js/admin-crypto-create.js');$css=file_get_contents($web.'/assets/css/admin.css');
foreach(['payment_method:','operation_key:','apply_renewal:','expected_expiry:','admin-cash-receipt__link','copy.cash_receipt_title'] as $marker)if(!str_contains($js,$marker))throw new RuntimeException('Cash workflow missing');
if(!str_contains($css,'.admin-cash-receipt__body')||!str_contains($css,'.admin-cash-receipt__link'))throw new RuntimeException('Cash receipt styles missing');
if(admin_crypto_asset_logo_url('BTC')===''||!str_starts_with(admin_crypto_asset_logo_url('BTC'),'/img/crypto/'))throw new RuntimeException('Crypto logo missing');
$count=$db->select_user('SELECT COUNT(*) n FROM admin_cash_payments');if($db->error)throw new RuntimeException('Cash schema unreadable');
echo "CASH_HANDLERS_RECEIPT_UI_RENEWAL_OPTIONS_AND_LOGOS_OK_NO_PAYMENTS_CREATED\n";
