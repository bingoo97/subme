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
if (!str_contains($css,'background:#f1f3f6;color:#5d6a7d;box-shadow:0 5px 16px rgba(25,39,58,.16),0 2px 5px rgba(25,39,58,.06);') || !str_contains($css,'body:has(.modal.show,.modal-backdrop,dialog[open]') || !str_contains($js,'function initMobileNavigation()')) throw new RuntimeException('Mobile navigation assets missing');
echo "MOBILE_NAVIGATION_8_ROUTES_PERMISSIONS_GREY_STYLE_OVERLAY_RULES_OK\n";

foreach (['width:65px;height:65px','border:2px solid #f1f3f6','font-size:26px','padding-bottom:calc(99px'] as $rule) if (!str_contains($css,$rule)) throw new RuntimeException('Navigation style missing');
echo "NAVIGATION_65PX_ICON_26PX_MATCHED_BORDER_SHADOW_OK\n";
