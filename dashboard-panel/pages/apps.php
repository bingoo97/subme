<?php

$appsUrl = '';
$appSettings = app_fetch_settings($db);
if (is_array($appSettings) && !empty($appSettings['apps_url'])) {
    $appsUrl = trim((string)$appSettings['apps_url']);
}
$applicationInstructionsEnabled = app_application_instructions_enabled($appSettings);
app_ensure_system_content_pages_runtime($db);
$currentStaticPageLocale = app_static_page_normalize_locale($currentLocale ?? ($user['locale_code'] ?? 'en'));

$apps = app_apps_seed_data($t, $applicationInstructionsEnabled);

$appsFallbackToGlobalUrl = false;

foreach ($apps as $index => $app) {
    $directUrl = '';
    if (!empty($app['url'])) {
        $directUrl = trim((string)$app['url']);
    } elseif ($appsUrl !== '') {
        $directUrl = $appsUrl;
        $appsFallbackToGlobalUrl = true;
    }

    $apps[$index]['url'] = $directUrl;
    $apps[$index]['uses_global_apps_url'] = ($directUrl !== '' && $directUrl === $appsUrl);
}

$staticPage = app_static_page_find_for_route($db, 'apps', $currentStaticPageLocale);
$staticPageBody = is_array($staticPage) && !empty($staticPage['id'])
    ? (string)($staticPage['body'] ?? '')
    : app_static_page_body_apps($t);

$smarty->assign(
    'static_page_body_html',
    app_render_static_page_body(
        $staticPageBody,
        [
            'apps_table' => app_render_apps_table_markup($apps, $t),
            'apps_fallback_link' => app_render_apps_fallback_link_markup($appsUrl, $appsFallbackToGlobalUrl, $t),
        ]
    )
);
$smarty->display('static_page.tpl');

?>
