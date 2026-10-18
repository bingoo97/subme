<?php
declare(strict_types=1);
require_once __DIR__.'/bootstrap.php';
require_once __DIR__.'/panel_subscription.php';
admin_send_security_headers();admin_start_session();
header('Content-Type: application/json; charset=utf-8');header('Cache-Control: no-store');
$db=Mysql_ks::get_instance();$user=admin_load_session_user($db);
function panel_reply(array $data,int $status=200):void{http_response_code($status);echo json_encode($data,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);exit;}
if(!$user||!admin_user_can_access_route($user,'dashboard'))panel_reply(['ok'=>false,'message'=>'Unauthorized.'],401);
if(($_SERVER['REQUEST_METHOD']??'')!=='POST'){header('Allow: POST');panel_reply(['ok'=>false,'message'=>'POST required.'],405);}
if((int)($_SERVER['CONTENT_LENGTH']??0)>4096)panel_reply(['ok'=>false,'message'=>'Request too large.'],413);
foreach($_POST as $v)if(!is_string($v))panel_reply(['ok'=>false,'message'=>'Invalid input.'],422);
if(!admin_csrf_is_valid($_POST['_csrf']??''))panel_reply(['ok'=>false,'message'=>admin_panel_tiers_text('session')],403);
$action=(string)($_POST['action']??'');
if(!in_array($action,['load','create','check','cancel'],true))panel_reply(['ok'=>false,'message'=>'Invalid action.'],422);
$now=microtime(true);$last=(float)($_SESSION['panel_tiers_'.$action]??0);
if($now-$last<($action==='create'?5:2))panel_reply(['ok'=>false,'message'=>admin_panel_tiers_text('busy')],429);
$_SESSION['panel_tiers_'.$action]=$now;
$GLOBALS['panel_tiers_locale']=admin_current_locale();
if(session_status()===PHP_SESSION_ACTIVE)session_write_close();
try{
    if(!app_panel_tiers_installed($db))throw new DomainException('disabled');
    if($action==='cancel'){
        $requestId=filter_var($_POST['request_id']??'',FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
        if($requestId===false)throw new DomainException('cannot_cancel');
        app_panel_tiers_cancel($db,$requestId);
    }
    if($action==='create')app_panel_tiers_create($db,(int)$user['id'],strtoupper((string)($_POST['asset']??'')),(int)($_POST['months']??0));
    if($action==='load'){
        $config=app_panel_tiers_config($db);
        if(empty($config['enabled']))throw new DomainException('disabled');
        $db->query("UPDATE panel_subscription_config SET usage_synced_at=UTC_TIMESTAMP() WHERE id=1 AND (usage_synced_at IS NULL OR usage_synced_at<DATE_SUB(UTC_TIMESTAMP(),INTERVAL 60 SECOND))");
        if($db->affected_rows===1)app_panel_tiers_capture($db);
    }
    $scan=$action==='check'?app_panel_tiers_process($db):['ok'=>true];
    $state=admin_panel_tiers_state($db);$state['scan_ok']=$scan['ok'];panel_reply($state);
}catch(DomainException $e){panel_reply(['ok'=>false,'message'=>admin_panel_tiers_text($e->getMessage())],422);}
catch(Throwable $e){panel_reply(['ok'=>false,'message'=>admin_panel_tiers_text('storage')],503);}
