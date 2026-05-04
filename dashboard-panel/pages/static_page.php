<?php

$staticPageLocale = isset($currentLocale) ? (string)$currentLocale : (string)($_SESSION['lang'] ?? 'en');
$staticPageRow = isset($customStaticPage) && is_array($customStaticPage) ? $customStaticPage : app_static_page_find_for_route($db, (string)$site, $staticPageLocale);

if (is_array($staticPageRow) && !empty($staticPageRow['id'])) {
    $smarty->assign('static_page_body_html', (string)($staticPageRow['body'] ?? ''));
    $smarty->display('static_page.tpl');
}

?>
