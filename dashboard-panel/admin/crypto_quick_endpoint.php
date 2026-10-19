<?php
declare(strict_types=1);
require_once __DIR__.'/bootstrap.php';
require_once __DIR__.'/crypto_quick.php';
admin_send_security_headers();admin_start_session();
header('Content-Type: application/json; charset=utf-8');header('Cache-Control: no-store');
function crypto_quick_reply(array $data,int $status=200):void{http_response_code($status);echo json_encode($data,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);exit;}
$db=Mysql_ks::get_instance();$user=admin_load_session_user($db);
if(!$user)crypto_quick_reply(['ok'=>false],401);
if(!admin_user_can_access_route($user,'payments'))crypto_quick_reply(['ok'=>false],403);
if(($_SERVER['REQUEST_METHOD']??'')!=='GET'){header('Allow: GET');crypto_quick_reply(['ok'=>false],405);}
$settings=$db->select_user('SELECT crypto_payments_enabled FROM app_settings WHERE id=1');
if(empty($settings['crypto_payments_enabled']))crypto_quick_reply(['ok'=>false],403);
$filter=$_GET['filter']??'new';
if(!is_string($filter)||!in_array($filter,['new','expired','cancelled','payout'],true))crypto_quick_reply(['ok'=>false],422);
$reminder=($_GET['reminder']??'')==='1';
$last=(float)($_SESSION['crypto_quick_'.$filter]??0);$now=microtime(true);
if($now-$last<2){header('Retry-After: 2');crypto_quick_reply(['ok'=>false],429);}
$locale=admin_current_locale();$labels=admin_crypto_quick_labels($locale);$messages=admin_load_messages($locale);$csrf=admin_csrf_token();
$_SESSION['crypto_quick_'.$filter]=$now;session_write_close();
try{
if($reminder){
    crypto_quick_reply(admin_crypto_reminder_view($db,$csrf,$messages,null,$locale));
}
$rows=admin_crypto_quick_rows($db,$filter);crypto_quick_reply(['ok'=>true,'filter'=>$filter,'counts'=>admin_crypto_quick_counts($db),'html'=>admin_crypto_quick_html($rows,$filter,$labels,$csrf,$messages)]);}
catch(Throwable $e){crypto_quick_reply(['ok'=>false],503);}
