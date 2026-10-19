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
