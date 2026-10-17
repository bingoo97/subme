<?php
declare(strict_types=1);
if (strpos((string)getenv('DB_NAME'), '_test_') === false) throw new RuntimeException('Isolated _test_ database required.');
require_once dirname(__DIR__) . '/admin/bootstrap.php';
if (($argv[1] ?? '') === '--endpoint') {
    $request=json_decode(base64_decode($argv[2]),true);
    admin_start_session();
    $_SESSION=['admin_user_id'=>$request['admin']??1,'admin_csrf_token'=>'audit-csrf','admin_locale'=>'pl','id'=>$request['customer']??0];
    $_SERVER['REQUEST_METHOD']=$request['method']??'GET';
    $_POST=$request['post']??[];
    ob_start();
    register_shutdown_function(static function():void {
        $body=ob_get_clean();$closed=session_status()!==PHP_SESSION_ACTIVE;
        if ($closed && session_id()!=='') session_start();
        if (session_status()===PHP_SESSION_ACTIVE) session_destroy();
        echo json_encode(['status'=>http_response_code()?:200,'body'=>$body,'session_closed'=>$closed]);
    });
    require dirname(__DIR__).($request['legacy']??false?'/check_payment.php':'/admin/crypto_status.php');
    exit;
}
function request_endpoint(array $request):array {
    $process=proc_open([PHP_BINARY,__FILE__,'--endpoint',base64_encode(json_encode($request))],[1=>['pipe','w'],2=>['pipe','w']],$pipes);
    $out=stream_get_contents($pipes[1]);$err=stream_get_contents($pipes[2]);foreach($pipes as $pipe)fclose($pipe);
    if(proc_close($process)!==0 || !is_array($result=json_decode($out,true)))throw new RuntimeException($out.$err);
    return $result;
}
$checks=0;
function endpoint_check(bool $condition,string $label):void {global $checks;if(!$condition)throw new RuntimeException($label);$checks++;}
endpoint_check(request_endpoint(['admin'=>0])['status']===401,'Status endpoint requires authentication');
endpoint_check(request_endpoint(['admin'=>3])['status']===403,'Status endpoint requires settings permission');
endpoint_check(request_endpoint([])['status']===405,'Status refresh requires POST');
endpoint_check(request_endpoint(['method'=>'POST'])['status']===419,'Status refresh requires CSRF');
$rows=admin_crypto_preview_network_status_rows([],false);
$rows[0]['message']='<script>alert(1)</script>';
admin_crypto_accept_preview_cache_write('crypto_preview_network_status_rows_v4',['rows'=>$rows]);
$ok=request_endpoint(['method'=>'POST','post'=>['_csrf'=>'audit-csrf']]);
$payload=json_decode($ok['body'],true);
endpoint_check($ok['status']===200 && !empty($payload['ok']),'Authorized cached refresh succeeds');
endpoint_check(strpos($payload['html'],'<script>')===false && strpos($payload['html'],'&lt;script&gt;')!==false,'Status HTML escapes provider messages');
endpoint_check($ok['session_closed'],'Refresh releases session before provider checks');
endpoint_check(request_endpoint(['legacy'=>true])['status']===405,'Legacy poller rejects GET');
endpoint_check(request_endpoint(['legacy'=>true,'method'=>'POST'])['status']===401,'Legacy poller requires customer session');
endpoint_check(request_endpoint(['legacy'=>true,'method'=>'POST','customer'=>1])['status']===410,'Current schema disables obsolete legacy polling');
echo "PASS: {$checks} crypto endpoint/auth/session assertions\n";
