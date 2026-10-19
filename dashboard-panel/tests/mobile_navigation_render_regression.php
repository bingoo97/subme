<?php
declare(strict_types=1);
function admin_t(array $messages, string $key, string $fallback): string { return $fallback; }
function admin_e(string $value): string { return htmlspecialchars($value, ENT_QUOTES, 'UTF-8'); }
function admin_user_can_access_route(array $user, string $route): bool { return in_array($route, $user['routes'], true); }
function renderNavigation(string $route, int $paymentEditorId = 0, bool $orderShowCreate = false, array $routes = ['dashboard','orders','payments']): string {
    $messages = []; $adminUser = ['routes' => $routes];
    ob_start(); require __DIR__ . '/../admin/partials/mobile-navigation.php'; return ob_get_clean();
}
$cases = [
    ['dashboard', 0, false, 'orders', 'link', 'clipboard-check'],
    ['orders', 0, false, 'payments', 'link', 'wallet2'],
    ['orders', 0, true, 'dashboard', 'back', 'arrow-left'],
    ['payments', 0, false, 'orders', 'link', 'clipboard-check'],
    ['payments', 123, false, 'payments', 'back', 'arrow-left'],
    ['users', 0, false, 'dashboard', 'back', 'arrow-left'],
    ['settings', 0, false, 'dashboard', 'back', 'arrow-left'],
];
foreach ($cases as [$route,$id,$create,$target,$mode,$icon]) {
    $html = renderNavigation($route,$id,$create);
    foreach (['href="/admin/?page='.$target.'"', 'data-navigation-mode="'.$mode.'"', 'bi-'.$icon, 'aria-label='] as $expected) {
        if (!str_contains($html,$expected)) throw new RuntimeException('Incorrect mobile navigation: '.$route);
    }
}
$html = renderNavigation('orders',0,false,['dashboard','orders']);
if (!str_contains($html,'data-navigation-mode="back"') || str_contains($html,'page=payments')) throw new RuntimeException('Shortcut bypasses route permissions');
echo "PASS: 8 mobile navigation render/permission scenarios\n";
