<?php
declare(strict_types=1);
if (strpos((string)getenv('DB_NAME'),'_test_')===false) throw new RuntimeException('Isolated test database required');
require dirname(__DIR__).'/admin/bootstrap.php';
if (($argv[1]??'')==='--endpoint') {
    $data=json_decode(base64_decode($argv[2]),true);
    admin_start_session();
    $_SESSION=['admin_user_id'=>$data['user']??1,'admin_csrf_token'=>'fixture-csrf','admin_locale'=>'pl'];
    $_SERVER['REQUEST_METHOD']=$data['method']??'POST';$_POST=$data['post']??[];
    ob_start();
    register_shutdown_function(static function(){
        $body=ob_get_clean();$status=http_response_code()?:200;
        if(session_status()===PHP_SESSION_ACTIVE)session_destroy();
        elseif(session_id()!==''){session_start();session_destroy();}
        echo json_encode(['status'=>$status,'payload'=>json_decode($body,true)]);
    });
    require dirname(__DIR__).'/admin/provider_remote_endpoint.php';exit;
}
putenv('PROVIDER_REMOTE_SECRET_KEY='.bin2hex(random_bytes(32)));
$db=Mysql_ks::get_instance();admin_provider_remote_ensure_schema($db);
function rq(string $sql):void{global $db;if(!$db->query($sql))throw new RuntimeException($db->error);}
function cnt(string $table):int{global $db;return(int)$db->select_user("SELECT COUNT(*) AS n FROM {$table}")['n'];}
$checks=0;
function ck(bool $value,string $label):void{global $checks;if(!$value)throw new RuntimeException($label);$checks++;}
function endpoint(array $post,array $options=[]):array{
    $p=proc_open([PHP_BINARY,__FILE__,'--endpoint',base64_encode(json_encode($options+['post'=>$post]))],[1=>['pipe','w'],2=>['pipe','w']],$pipes);
    $out=stream_get_contents($pipes[1]);$err=stream_get_contents($pipes[2]);foreach($pipes as $pipe)fclose($pipe);
    if(proc_close($p)!==0||!is_array($r=json_decode($out,true)))throw new RuntimeException($out.$err);return $r;
}
rq("INSERT INTO currencies(id,code,name,symbol) VALUES(1,'USD','Dollar','$') ON DUPLICATE KEY UPDATE code=VALUES(code)");
rq("INSERT INTO app_settings(id,site_name,site_title,site_url,support_email,default_currency_id) VALUES(1,'Provider fixture','Provider fixture','https://example.invalid','fixture@example.invalid',1) ON DUPLICATE KEY UPDATE provider_remote_login_enabled=0,provider_remote_subscriptions_enabled=0");
rq("INSERT INTO admin_roles(id,name,slug,access_level) VALUES(1,'Fixture manager','fixture-manager',1000),(3,'Denied','fixture-denied',100) ON DUPLICATE KEY UPDATE access_level=VALUES(access_level)");
rq("INSERT INTO admin_users(id,role_id,login_name,email,password_hash) VALUES(1,1,'fixture','fixture@example.invalid','unused'),(3,3,'denied','denied@example.invalid','unused')");
rq("INSERT INTO product_providers(id,name,slug,dashboard_url,is_active) VALUES(4,'Fixture provider','fixture-provider','https://provider.example.invalid/login',1)");
$logs=cnt('customer_activity_logs');$base=['_csrf'=>'fixture-csrf','provider_id'=>'4','action'=>'load'];
ck(endpoint($base,['user'=>0])['status']===403,'Anonymous denied');
ck(endpoint($base,['user'=>3])['status']===403,'Missing product permission denied');
ck(endpoint($base,['method'=>'GET'])['status']===405,'POST only');
ck(endpoint(['_csrf'=>'bad']+$base)['status']===403,'CSRF required');
ck(endpoint(['action'=>['bad']]+$base)['status']===422,'Malformed fields rejected without warnings');
ck(endpoint(['provider_id'=>'999']+$base)['status']===404,'Missing provider');
ck(endpoint($base)['payload']['config']===null,'Missing configuration is explicit');
$test=['action'=>'test']+$base;
ck(endpoint($test)['status']===409,'Global OFF enforced on backend');
ck(endpoint(['action'=>'inspect']+$base)['status']===409,'Global OFF also blocks inspection');
rq('UPDATE app_settings SET provider_remote_login_enabled=1 WHERE id=1');
ck(endpoint($test)['payload']['code']==='missing','No login attempt without configuration');
$save=['action'=>'save','mode'=>'html','use_username'=>'1','use_password'=>'1','username'=>'fixture-user','password'=>' fixture secret ','username_by'=>'id','username_field'=>'username','password_by'=>'id','password_field'=>'password','submit_by'=>'id','submit_field'=>'_submit','success_kind'=>'id','success_value'=>'account-menu','failure_kind'=>'id','failure_value'=>'login-error','captcha'=>'1']+$base;
ck(endpoint($save)['payload']['ok']===true,'Encrypted configuration saved');
$row=admin_provider_remote_row($db,4);
ck(!str_contains(json_encode($row),'fixture secret')&&!str_contains(json_encode($row),'fixture-user'),'DB does not store plaintext credentials');
$load=endpoint($base)['payload'];
ck($load['username']==='fixture-user'&&$load['has_password']===true&&!isset($load['password']),'Password never returned');
ck(endpoint(['password'=>'']+$save)['payload']['ok']===true,'Empty password preserves existing secret');
$row=admin_provider_remote_row($db,4);ck(admin_provider_remote_unseal($row['credentials_encrypted'],4)['password']===' fixture secret ','Password bytes retained');
ck(endpoint($test)['payload']['code']==='manual','CAPTCHA returns manual result without sending credentials');
$twoFactor=['mode'=>'browser','two_factor'=>'1','captcha'=>'0']+$save;
ck(endpoint($twoFactor)['payload']['ok']===true,'2FA preference saves without exposing credentials');
ck(endpoint($test)['payload']['code']==='manual','Known 2FA returns manual before launching a browser');
$ocrSave=['captcha_ocr'=>'1','captcha_ocr_digits'=>'1']+$twoFactor;
ck(endpoint($ocrSave)['payload']['ok']===true,'OCR preference can be saved per provider without schema changes');
$ocrLoad=endpoint($base)['payload'];
ck($ocrLoad['config']['captcha_ocr']===true&&$ocrLoad['config']['captcha_ocr_digits']===true,'OCR opt-in and numeric mode survive load/save');
ck(endpoint($test)['payload']['code']==='manual','OCR opt-in never bypasses configured 2FA');
ck(endpoint($save)['payload']['ok']===true,'Fixture configuration restored');
ck(cnt('customer_activity_logs')===$logs,'Login tests do not append activity logs');
$before=admin_provider_remote_row($db,4);
ck(endpoint(['action'=>'inspect','login_path'=>'https://other.example.invalid/login']+$base)['payload']['code']==='origin','Inspection validates destination before any request');
ck(admin_provider_remote_row($db,4)===$before && cnt('customer_activity_logs')===$logs,'Inspection does not change configuration or add history');

endpoint(['success_value'=>'']+$save);ck(admin_provider_remote_row($db,4)===$before,'Invalid configuration does not replace saved data');
$autoSave=['mode'=>'auto','success_kind'=>'auto','success_value'=>'','captcha'=>'0']+$save;
ck(endpoint($autoSave)['payload']['ok']===true,'Automatic discovery saved without a custom success marker');
ck(endpoint($base)['payload']['config']['mode']==='auto','Automatic mode survives load/save');
rq("UPDATE product_providers SET dashboard_url='https://other.example.invalid/login' WHERE id=4");
ck(endpoint($test)['payload']['code']==='changed_url','URL change invalidates login configuration');
rq("DELETE FROM product_providers WHERE id=4");ck(admin_provider_remote_row($db,4)===null,'Provider deletion removes credential configuration');
echo "PASS: {$checks} provider endpoint/permission/storage/no-history assertions\n";
