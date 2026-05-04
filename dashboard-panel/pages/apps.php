<?php

$appsUrl = '';
$appSettings = app_fetch_settings($db);
if (is_array($appSettings) && !empty($appSettings['apps_url'])) {
    $appsUrl = trim((string)$appSettings['apps_url']);
}
$applicationInstructionsEnabled = app_application_instructions_enabled($appSettings);

$apps = [
    [
        'name' => 'NewLook 4',
        'platform' => 'Android',
        'description' => localization_translate($t, 'apps_newlook_description', 'NewLook 4 player for Android devices.'),
        'url' => '',
        'download_url' => 'https://tinyurl.com/3av56p84',
        'logo' => '/img/new_look.png',
        'instruction_url' => '/instruction-newlook',
    ],
    [
        'name' => 'NewLook 2',
        'platform' => 'Android',
        'description' => localization_translate($t, 'apps_newlook2_description', 'NewLook 2 IPTV player for Android devices.'),
        'url' => '',
        'download_url' => 'https://tinyurl.com/yc37sjfa',
        'logo' => '/img/new_look.png',
        'instruction_url' => '/instruction-newlook',
    ],
    [
        'name' => 'OTT-Player',
        'platform' => 'Android / iOS / Samsung / LG',
        'description' => localization_translate($t, 'apps_ottplayer_description', 'OTTPlayer for various platforms including Smart TVs.'),
        'url' => '',
        'download_url' => 'https://tinyurl.com/46c2hb3s',
        'logo' => '/img/ott/logo-ott.jpg',
        'instruction_url' => '/instruction-ott-player',
    ],
 //   [
 //       'name' => 'Smart IPTV',
 //       'platform' => 'Samsung / LG / Android / iOS',
 //       'description' => localization_translate($t, 'apps_smartiptv_description', 'Smart IPTV for Smart TVs and mobile devices.'),
 //       'url' => 'https://siptv.app/howto/',
 //       'download_url' => '',
 //       'logo' => '/img/smart_logo_t.png',
 //       'instruction_url' => '/instruction-smart-iptv',
 //   ],
 //   [
 //       'name' => 'SS-IPTV',
 //       'platform' => 'Samsung / LG',
 //       'description' => localization_translate($t, 'apps_ssiptv_description', 'SS-IPTV for Smart TVs.'),
 //       'url' => 'https://ss-iptv.com/en/users/playlist',
 //       'download_url' => '',
 //       'logo' => '/img/ssiptv-iptv-icon.png',
 //       'instruction_url' => '/instruction-smart-iptv',
 //   ],
];

$appsFallbackToGlobalUrl = false;

foreach ($apps as $index => $app) {
    $directUrl = '';
    if (!empty($app['download_url'])) {
        $directUrl = trim((string)$app['download_url']);
    } elseif (!empty($app['url'])) {
        $directUrl = trim((string)$app['url']);
    } elseif ($appsUrl !== '') {
        $directUrl = $appsUrl;
        $appsFallbackToGlobalUrl = true;
    }

    $apps[$index]['url'] = $directUrl;
    $apps[$index]['uses_global_apps_url'] = ($directUrl !== '' && $directUrl === $appsUrl && empty($app['download_url']));
}

if (!$applicationInstructionsEnabled) {
    foreach ($apps as $index => $app) {
        $apps[$index]['instruction_url'] = '';
    }
}

$smarty->assign('apps', $apps);
$smarty->assign('apps_url', $appsUrl);
$smarty->assign('apps_fallback_to_global_url', $appsFallbackToGlobalUrl);
$smarty->display('apps.tpl');

?>
