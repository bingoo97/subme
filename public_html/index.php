<?php
require __DIR__ . '/_bootstrap_backend.php';

require $appRoot . '/config/mysql.php';
require $appRoot . '/bootstrap/session.php';
app_start_session();

require $appRoot . '/vendor/autoload.php';
$smarty = new \Smarty\Smarty;
require $appRoot . '/config/config.php';

if (isset($settings['technical_break']) && $settings['technical_break'] == 1) {
    http_response_code(503);
    header('Retry-After: 1800');
    $smarty->assign('html_lang', 'en');
    $smarty->display('maintenance.tpl');
} else {
    include $appRoot . '/config/switch.php';
}
?>
