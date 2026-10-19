<?php
// Shortcuts apply to list views; details and forms keep normal back navigation.
$mobileNavigationMode = 'back';
$mobileNavigationRoute = $route === 'payments' && $paymentEditorId > 0 ? 'payments' : 'dashboard';
$mobileNavigationIcon = 'arrow-left';
$mobileNavigationLabel = admin_t($messages, 'mobile_navigation_back', 'Go back');
$mobileNavigationShortcut = null;
if ($route === 'dashboard' || ($route === 'payments' && $paymentEditorId <= 0)) {
    $mobileNavigationShortcut = 'orders';
} elseif ($route === 'orders' && empty($orderShowCreate)) {
    $mobileNavigationShortcut = 'payments';
}
if ($mobileNavigationShortcut !== null && admin_user_can_access_route($adminUser, $mobileNavigationShortcut)) {
    $mobileNavigationMode = 'link';
    $mobileNavigationRoute = $mobileNavigationShortcut;
    $mobileNavigationIcon = $mobileNavigationRoute === 'orders' ? 'clipboard-check' : 'wallet2';
    $mobileNavigationLabel = $mobileNavigationRoute === 'orders'
        ? admin_t($messages, 'mobile_navigation_orders', 'Go to orders')
        : admin_t($messages, 'mobile_navigation_payments', 'Go to payments');
}
?>
<a class="admin-mobile-navigation"
   href="/admin/?page=<?php echo admin_e($mobileNavigationRoute); ?>"
   data-admin-mobile-navigation data-navigation-mode="<?php echo admin_e($mobileNavigationMode); ?>"
   aria-label="<?php echo admin_e($mobileNavigationLabel); ?>" title="<?php echo admin_e($mobileNavigationLabel); ?>">
    <i class="bi bi-<?php echo admin_e($mobileNavigationIcon); ?>" aria-hidden="true"></i>
</a>
