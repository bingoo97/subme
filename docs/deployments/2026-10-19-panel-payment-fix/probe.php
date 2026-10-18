<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit(1);
$app=$argv[1];require $app.'/admin/bootstrap.php';
foreach(['app_panel_tiers_transfer_assignment','app_panel_tiers_close_window'] as $name)if(!function_exists($name))throw new RuntimeException('Missing corrected payment helper');
$GLOBALS['panel_tiers_locale']='pl';$csrfToken='read-only-render-probe';$messages=admin_load_messages('pl');
ob_start();require $app.'/admin/views/panel_tiers_modal.php';$html=ob_get_clean();
foreach(['data-panel-ended','data-panel-payment-body','data-panel-cancel-label','admin-panel-tier-modal__actions','Anuluj płatność i wróć','Wstecz - zmień sposób płatności'] as $marker)if(!str_contains(html_entity_decode($html,ENT_QUOTES,'UTF-8'),$marker))throw new RuntimeException('Updated modal marker missing');
$db=Mysql_ks::get_instance();$config=app_panel_tiers_config($db);
if(!empty($config['enabled'])){$state=admin_panel_tiers_state($db);if(empty($state['ok']))throw new RuntimeException('Payment state read failed');}
echo json_encode(['modal_render'=>true,'new_expiry_view'=>true,'fresh_invoice_helpers'=>true,'automation_enabled'=>(bool)($config['enabled']??0),'read_only'=>true]),PHP_EOL;
