<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') exit(1);
$app = $argv[1];
require $app . '/admin/bootstrap.php';
$db = Mysql_ks::get_instance();
$now = admin_dashboard_sales_now();
$day = $now->setTime(0, 0);
$metrics = admin_dashboard_period_metrics($db, $now);
$expected = [
    'weekly' => [$day->modify('-6 days'), $day->modify('+1 day')],
    'monthly' => [$day->modify('first day of this month'), $day->modify('first day of next month')],
];
foreach ($expected as $key => [$from, $until]) {
    if ($metrics[$key]['from'] !== $from->format('Y-m-d H:i:s') ||
        $metrics[$key]['to'] !== $until->modify('-1 second')->format('Y-m-d H:i:s')) {
        throw new RuntimeException('Incorrect dashboard period: ' . $key);
    }
}
$series = admin_dashboard_sales_series($db, 7, $now);
$revenue = admin_dashboard_revenue_sum($series);
$weekly = $metrics['weekly']['revenue_by_currency'];
ksort($revenue); ksort($weekly);
if (array_sum(array_column($series, 'paid_orders')) !== $metrics['weekly']['paid_orders'] || $revenue != $weekly) {
    throw new RuntimeException('Weekly card and daily chart disagree');
}
if (admin_dashboard_revenue_state([]) !== 'is-zero' ||
    admin_dashboard_revenue_state(['EUR' => 1]) !== 'is-positive' ||
    admin_dashboard_average_sales_label(13 / 365) !== '0.04') {
    throw new RuntimeException('Dashboard value presentation failed');
}
$web = trim(file_get_contents($app . '/.public-root-path'));
$css = file_get_contents($web . '/assets/css/admin.css');
$js = file_get_contents($web . '/assets/js/admin.js');
if (str_contains($css, 'will-change:transform, opacity, filter, max-height') ||
    str_contains($css, 'adminChatBubblePlop') ||
    !str_contains($js, 'function adminChatMotionReduced()') ||
    !str_contains($css, '--admin-chat-composer-bottom-space') ||
    !str_contains($css, '.admin-chat-inbox__item-actions{display:flex;align-items:center;')) {
    throw new RuntimeException('Chat polish assets are missing');
}
echo "DASHBOARD_CALENDAR_MONTH_ROLLING_WEEK_MATCHES_CHART_CHAT_ASSETS_OK\n";

function checkNavigation(string $app, string $route, int $paymentEditorId, bool $orderShowCreate, string $target, string $mode, int $level = 1000): void {
    $messages = admin_load_messages('pl');
    $adminUser = ['role_access_level' => $level];
    ob_start(); require $app . '/admin/partials/mobile-navigation.php'; $html = ob_get_clean();
    if (!str_contains($html,'href="/admin/?page='.$target.'"') || !str_contains($html,'data-navigation-mode="'.$mode.'"') || !str_contains($html,'aria-label="')) throw new RuntimeException('Navigation render failed: '.$route);
}
foreach ([['dashboard',0,false,'orders','link'],['orders',0,false,'payments','link'],['orders',0,true,'dashboard','back'],['payments',0,false,'orders','link'],['payments',123,false,'payments','back'],['users',0,false,'dashboard','back'],['settings',0,false,'dashboard','back']] as $case) checkNavigation($app,...$case);
checkNavigation($app,'orders',0,false,'dashboard','back',0);
if (!str_contains($css,'background:#ffffff;color:#5d6a7d;box-shadow:0 5px 16px rgba(25,39,58,.16),0 2px 5px rgba(25,39,58,.06);') || !str_contains($css,'body:has(.modal.show,.modal-backdrop,dialog[open]') || !str_contains($js,'function initMobileNavigation()')) throw new RuntimeException('Mobile navigation assets missing');
echo "MOBILE_NAVIGATION_8_ROUTES_PERMISSIONS_GREY_STYLE_OVERLAY_RULES_OK\n";

foreach (['width:65px;height:65px','border:1px solid #e6e6e6','font-size:26px','padding-bottom:calc(99px'] as $rule) if (!str_contains($css,$rule)) throw new RuntimeException('Navigation style missing');
echo "NAVIGATION_65PX_ICON_26PX_MATCHED_BORDER_SHADOW_OK\n";

$quickSource=file_get_contents($app.'/admin/crypto_quick.php');
$quickView=file_get_contents($app.'/admin/views/crypto_quick.php');
$reminderJs=file_get_contents($web.'/assets/js/admin-crypto-reminder.js');
if(!str_contains($quickSource,'data-receipt-order-id=')||!str_contains($quickSource,'<a class="admin-crypto-quick__purpose" href="/admin/?page=orders&amp;order_id=')||!str_contains($quickView,'<a class="admin-crypto-quick__purpose" data-reminder-receipt-purpose')||!str_contains($reminderJs,"purpose.removeAttribute('href')"))throw new RuntimeException('Order purpose link implementation missing');
if(!str_contains($css,'.admin-orders-actions>.admin-orders-menu-trigger{flex-basis:36px;width:36px;height:36px;margin-inline-start:auto;}')||!str_contains($css,'margin-inline-start:auto;border-radius:8px;}'))throw new RuntimeException('Compact menu layout rules missing');
echo "ORDER_PURPOSE_LINKS_REMINDER_BADGE_AND_COMPACT_MENUS_OK\n";

if(!str_contains($css,':is(a.admin-crypto-quick__purpose[href],a.admin-crypto-quick__next-step):is(:hover,:focus,:focus-visible,:active,:visited){text-decoration:none;}'))throw new RuntimeException('Order link state styling missing');
$deleteDom=new DOMDocument();@$deleteDom->loadHTML($quickSource);$deleteXp=new DOMXPath($deleteDom);
$deleteForms=$deleteXp->query('//form[input[@name="quick_action" and @value="delete"]]');
if($deleteForms->length!==1||!$deleteForms->item(0)->hasAttribute('data-crypto-quick-action'))throw new RuntimeException('Inline deletion form missing');
echo "INLINE_DELETE_FORM_AND_NO_UNDERLINE_LINK_STATES_OK\n";

$indexSource=file_get_contents($app.'/admin/index.php');
foreach(['admin-payments-filter-group--statuses','admin-payments-filter-group--types','data-payment-scope=','$showPaymentTypeFilters = $bankTransfersVisibleInPayments && $visiblePaymentTypeCount > 1;'] as $rule)if(!str_contains($indexSource,$rule))throw new RuntimeException('Payment toolbar markup missing');
foreach(['function initPaymentToolbar()','selected.left - bounds.left - 8'] as $rule)if(!str_contains($js,$rule))throw new RuntimeException('Active scope reveal missing');
foreach(['right:max(16px,env(safe-area-inset-right,0px))','--scope-bg:#212529;--scope-ink:#fff;--scope-border:#212529;','overflow-x:auto;overflow-y:hidden;scrollbar-width:none'] as $rule)if(!str_contains($css,$rule))throw new RuntimeException('Responsive payment styling missing');
echo "RESPONSIVE_TOOLBAR_ACTIVE_FILTER_BLACK_ARCHIVE_RIGHT_WHITE_NAV_OK\n";

$settings=app_fetch_settings($db);
if(!array_key_exists('admin_action_reminders_enabled',$settings))throw new RuntimeException('Reminder setting absent');
if(app_format_money_value(36,'EUR','EUR')!=='36.00 €')throw new RuntimeException('Currency symbol failed');
foreach(['30 * 60 * 1000',"dialog.dataset.enabled === '0'"] as $rule)if(!str_contains($reminderJs,$rule))throw new RuntimeException('Reminder interval/toggle missing');
foreach(['data-admin-order-default-tab','data-admin-order-renewal-alert','received_fiat_actual_amount'] as $rule)if(!str_contains($indexSource,$rule))throw new RuntimeException('Paid renewal/edit metadata missing');
$template=file_get_contents($app.'/templates/profil/orders.tpl');
if(!str_contains($template,'orders-user-awaiting-spinner')||!str_contains($template,'fa fa-search'))throw new RuntimeException('Customer waiting/detail presentation absent');
echo "PAYMENT_WORKFLOW_30MIN_GLOBAL_TOGGLE_CURRENCY_RENEWAL_AND_CUSTOMER_UI_OK\n";

if(!str_contains($css,'body.admin-app-page .admin-crypto-quick__purpose{')||!str_contains($css,'min-height:0;padding:1px 4px;gap:4px;font-size:9px'))throw new RuntimeException('Shared purpose badge dimensions absent');
echo "ORDER_AND_TOPUP_PURPOSE_BADGES_SHARE_COMPACT_DIMENSIONS_OK\n";

if(!str_contains($quickSource,"'bi-check-lg':'bi-wallet2'"))throw new RuntimeException('Completed order check absent');
echo "COMPLETED_ORDER_GREEN_CHECK_OK\n";

$polish=file_get_contents($web.'/assets/css/customer-polish.css');
foreach(['customer-history-table','width:calc(100% + 302px)','customer-topup-pending-pulse','settings-view .settings-card'] as $rule)if(!str_contains($polish,$rule))throw new RuntimeException('Customer CSS rule missing');
require_once $app.'/vendor/autoload.php';
$smarty=new \Smarty\Smarty();
$smarty->setTemplateDir($app.'/templates');
$compile=sys_get_temp_dir().'/subme-customer-compile-'.getmypid();
mkdir($compile,0700,true);$smarty->setCompileDir($compile);
try{
 $count=0;
 foreach(file(__DIR__.'/files.txt',FILE_IGNORE_NEW_LINES|FILE_SKIP_EMPTY_LINES) as $file){
  if(!str_starts_with($file,'dashboard-panel/templates/'))continue;
  $smarty->createTemplate(substr($file,strlen('dashboard-panel/templates/')))->compileTemplateSource();$count++;
 }
 echo "CUSTOMER_TEMPLATES_COMPILED count=$count\n";
}finally{foreach(glob($compile.'/*')?:[] as $file)if(is_file($file))unlink($file);rmdir($compile);}
echo "CUSTOMER_POLISH_ASSETS_AND_TEMPLATE_SYNTAX_OK\n";

$approvalSource=file_get_contents($app.'/admin/bootstrap.php');
foreach(["$"."appliedRenewal = $"."db->select_user", "Unable to verify the settled renewal payment.", "AND r.status = 'applied'"] as $rule)if(!str_contains($approvalSource,$rule))throw new RuntimeException('Settled renewal archival fix missing');
echo "SETTLED_RENEWAL_ARCHIVE_GUARD_INSTALLED\n";

$paymentTemplate=file_get_contents($app.'/templates/profil/orders_payment.tpl');
foreach(['payment-summary-item--amount','payment-method-start--crypto-only','payment-method-start__balance-note','bi bi-wallet2','payment_summary_amount_display'] as $rule)if(!str_contains($paymentTemplate,$rule))throw new RuntimeException('Checkout template rule missing');
foreach(['customer-crypto-method-pulse','payment-summary-bar{display:grid','payment-method-start__button--balance{border-color:#2d8d5e','payment-method-start__crypto-only-note'] as $rule)if(!str_contains($polish,$rule))throw new RuntimeException('Checkout style rule missing');
foreach(['pl','en'] as $language){$catalog=require $app.'/locales/'.$language.'.php';foreach(['payment_crypto_only_notice','payment_balance_available_notice'] as $key)if(empty($catalog['messages'][$key]))throw new RuntimeException('Checkout translation missing');}
echo "COMPACT_CHECKOUT_METHOD_NOTICES_BALANCE_STYLES_CURRENCY_OK\n";
