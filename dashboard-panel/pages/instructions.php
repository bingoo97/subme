<?php

$appsUrl = '';
$appSettings = app_fetch_settings($db);
if (is_array($appSettings) && !empty($appSettings['apps_url'])) {
    $appsUrl = trim((string)$appSettings['apps_url']);
}
$applicationInstructionsEnabled = app_application_instructions_enabled($appSettings);

$instructionGuides = [
    [
        'href' => '/instruction-trust-wallet',
        'icon' => 'fa-shield',
        'title' => localization_translate($t, 'instructions_trust_wallet'),
        'group' => 'payments',
    ],
    [
        'href' => '/instruction-revolut',
        'icon' => 'fa-university',
        'title' => localization_translate($t, 'instructions_revolut'),
        'group' => 'payments',
    ],
    [
        'href' => '/instruction-crypto-exchange',
        'icon' => 'fa-exchange',
        'title' => localization_translate($t, 'instructions_crypto_exchange'),
        'group' => 'payments',
    ],
    [
        'href' => '/instruction-smart-iptv',
        'icon' => 'fa-tv',
        'title' => localization_translate($t, 'instructions_smart_iptv'),
        'group' => 'applications',
    ],
    [
        'href' => '/instruction-ott-player',
        'icon' => 'fa-play-circle',
        'title' => localization_translate($t, 'instructions_ott_player'),
        'group' => 'applications',
    ],
    [
        'href' => '/instruction-newlook',
        'icon' => 'fa-eye',
        'title' => localization_translate($t, 'instructions_newlook'),
        'group' => 'applications',
    ],
];

if (!$applicationInstructionsEnabled) {
    $instructionGuides = array_values(array_filter($instructionGuides, static function (array $guide): bool {
        return (string)($guide['group'] ?? '') !== 'applications';
    }));
}

if ($site === 'instructions') {
    $smarty->assign('instruction_guides', $instructionGuides);
    $smarty->display('instructions.tpl');
    return;
}

$guideMap = [
    'instruction-trust-wallet' => localization_translate($t, 'instructions_trust_wallet'),
    'instruction-revolut' => localization_translate($t, 'instructions_revolut'),
    'instruction-crypto-exchange' => localization_translate($t, 'instructions_crypto_exchange'),
    'instruction-smart-iptv' => localization_translate($t, 'instructions_smart_iptv'),
    'instruction-ott-player' => localization_translate($t, 'instructions_ott_player'),
    'instruction-newlook' => localization_translate($t, 'instructions_newlook'),
];

if (isset($guideMap[$site])) {
    if (in_array($site, ['instruction-smart-iptv', 'instruction-ott-player', 'instruction-newlook'], true) && !$applicationInstructionsEnabled) {
        header('Location: /instructions');
        exit;
    }
    $smarty->assign('instruction_guide_title', $guideMap[$site]);
    $smarty->assign('instruction_guide_site', $site);
    $smarty->assign('apps_url', $appsUrl);
    $smarty->display('instruction_guide.tpl');
}

?>
