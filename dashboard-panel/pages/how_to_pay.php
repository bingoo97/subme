<?php

$smarty->assign('payment_help_links', [
    [
        'href' => '/instructions',
        'icon' => 'fa-book',
        'title' => localization_translate($t, 'instructions'),
    ],
    [
        'href' => '/faq',
        'icon' => 'fa-question-circle',
        'title' => localization_translate($t, 'faq'),
    ],
]);

$smarty->display('how_to_pay.tpl');

?>
