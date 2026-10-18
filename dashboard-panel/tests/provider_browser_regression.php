<?php
declare(strict_types=1);
if(strpos((string)getenv('DB_NAME'),'_test_')===false)throw new RuntimeException('Isolated test database required');
require dirname(__DIR__).'/admin/bootstrap.php';
if(($argv[1]??'')==='--endpoint'){
 $data=json_decode(base64_decode($argv[2]),true);admin_start_session();
 $_SESSION=['admin_user_id'=>$data['user']??1,'admin_csrf_token'=>'fixture-csrf','admin_locale'=>'pl'];
 $_SERVER['HTTP_HOST']=$data['host']??'localhost:8088';$_SERVER['HTTP_ORIGIN']=$data['origin']??'http://localhost:8088';
 $_SERVER['REQUEST_METHOD']=$data['method']??'POST';$_SERVER['HTTP_X_SUBME_PROVIDER_EXTENSION']=$data['extension']??'1';
 $_POST=$data['post']??[];ob_start();
 register_shutdown_function(static function(){
  $body=ob_get_clean();$status=http_response_code()?:200;
  if(session_status()===PHP_SESSION_ACTIVE)session_destroy();elseif(session_id()!==''){session_start();session_destroy();}
  echo json_encode(['status'=>$status,'payload'=>json_decode($body,true)]);
 });require dirname(__DIR__).'/admin/provider_browser_endpoint.php';exit;
}
putenv('PROVIDER_REMOTE_SECRET_KEY='.bin2hex(random_bytes(32)));
$db=Mysql_ks::get_instance();admin_provider_remote_ensure_schema($db);$checks=0;
function q(string $sql):void{global $db;if(!$db->query($sql))throw new RuntimeException($db->error);}
function ck(bool $ok,string $label):void{global $checks;if(!$ok)throw new RuntimeException($label);$checks++;}
function ep(array $post,array $options=[]):array{
 $p=proc_open([PHP_BINARY,__FILE__,'--endpoint',base64_encode(json_encode($options+['post'=>$post]))],[1=>['pipe','w'],2=>['pipe','w']],$pipes);
 $out=stream_get_contents($pipes[1]);$err=stream_get_contents($pipes[2]);foreach($pipes as $pipe)fclose($pipe);
 if(proc_close($p)!==0||!is_array($r=json_decode($out,true)))throw new RuntimeException('Endpoint fixture failed: '.$err);return $r;
}
q("INSERT INTO currencies(id,code,name,symbol) VALUES(1,'USD','Dollar','$')");
q("INSERT INTO app_settings(id,site_name,site_title,site_url,support_email,default_currency_id,provider_remote_login_enabled) VALUES(1,'Fixture','Fixture','https://example.invalid','fixture@example.invalid',1,1)");
q("INSERT INTO admin_roles(id,name,slug,access_level) VALUES(1,'Fixture','fixture',1000),(3,'Denied','denied',100)");
q("INSERT INTO admin_users(id,role_id,login_name,email,password_hash) VALUES(1,1,'fixture','fixture@example.invalid','unused'),(3,3,'denied','denied@example.invalid','unused')");
q("INSERT INTO product_providers(id,name,slug,dashboard_url,is_active) VALUES(4,'Fixture','fixture','https://provider.example.invalid/login',1),(5,'Other','other','https://other.example.invalid',1)");
q("INSERT INTO products(id,provider_id,name,slug,currency_id,product_type) VALUES(10,4,'Fixture','fixture',1,'subscription')");
q("INSERT INTO customers(id,email,public_handle,password_hash) VALUES(10,'client@example.invalid','client','unused')");
q("INSERT INTO orders(id,customer_id,product_id,currency_id,payment_status,status,delivery_link) VALUES(100,10,10,1,'paid','pending_activation','https://stream.example.invalid/client'),(101,10,10,1,'unpaid','pending_payment',NULL)");
$config=['mode'=>'auto','use_username'=>'1','use_password'=>'1','username'=>'fixture','password'=>' fixture password ','success_kind'=>'auto'];
admin_provider_remote_save($db,4,$config,'https://provider.example.invalid/login');
$base=['action'=>'lookup','challenge'=>str_repeat('a',48),'order_id'=>'100','provider_id'=>'0','_csrf'=>'fixture-csrf'];
ck(ep($base,['user'=>0])['payload']['code']==='session','Anonymous rejected');
ck(ep($base,['user'=>3])['payload']['code']==='forbidden','Missing permission rejected');
ck(ep($base,['host'=>'dashboard.example.invalid'])['payload']['code']==='local_only','Local only even with a valid session');
ck(ep($base,['origin'=>'https://evil.invalid'])['payload']['code']==='forbidden','Cross-origin rejected');
ck(ep(['_csrf'=>'wrong']+$base)['payload']['code']==='session','CSRF enforced');
ck(ep(['order_id'=>['100']]+$base)['payload']['code']==='config','Malformed fields rejected');
ck(ep(['order_id'=>'101']+$base)['payload']['code']==='unpaid','Unpaid order rejected');
ck(ep(['provider_id'=>'5']+$base)['payload']['code']==='config','Different provider rejected');
ck(ep(['action'=>'connect','order_id'=>'0','provider_id'=>'4']+$base)['payload']['code']==='config','Order connection cannot bypass the assigned-link requirement through a provider-only request');
$ctx=admin_provider_browser_context($db,['id'=>1,'role_access_level'=>1000,'role_slug'=>'fixture'],0,100);
ck($ctx['identity']===['handle'=>'client','email'=>'client@example.invalid'],'Links without username retain account identifiers without forwarding full material URLs');
foreach(['','not-a-url','javascript:alert(1)','https://user:secret@stream.example.invalid/get.php?username=x','https://stream.example.invalid/get.php?username=','https://stream.example.invalid/get.php?username=first&username=second','https://stream.example.invalid/get.php?username=first&USERNAME=second','https://stream.example.invalid/get.php?username[]=first','https://stream.example.invalid/get.php?username=invalid%ZZ','https://stream.example.invalid/get.php?username=invalid%00','https://stream.example.invalid/get.php?username=one+two'] as $badLink)ck(admin_provider_browser_delivery_link($badLink)===null,'Malformed/ambiguous delivery links rejected');
$fixtureLink='http://stream.example.invalid:8880/get.php?username=CaseLogin%2B42&password=never-forward-material-password&type=m3u_plus&output=ts';
ck(admin_provider_browser_delivery_link($fixtureLink)===['username'=>'CaseLogin+42'],'Material URL parsed without HTTP requests, retaining port, case and percent-encoded username semantics');
q("UPDATE orders SET delivery_link='".$db->escape($fixtureLink)."' WHERE id=100");
$linked=admin_provider_browser_context($db,['id'=>1,'role_access_level'=>1000],0,100);
ck($linked['identity']===['username'=>'CaseLogin+42']&&!str_contains(json_encode($linked['identity']),'password'),'Provider line username replaces Subme handle/email; password and URL never reach search');
q("UPDATE orders SET delivery_link='".$db->escape(str_replace('never-forward-material-password','changed-material-password',$fixtureLink))."' WHERE id=100");
ck(admin_provider_browser_context($db,['id'=>1,'role_access_level'=>1000],0,100)['binding']!==$linked['binding'],'Any change to the saved material link invalidates an existing connection binding');
q("UPDATE orders SET delivery_link=NULL WHERE id=100");
ck(ep($base)['payload']['code']==='link_required','Removing the assigned URL blocks lookup on the backend');
ck(ep(['action'=>'connect']+$base)['payload']['code']==='link_required','No assigned URL blocks connection before any provider network request');
q("UPDATE orders SET delivery_link='".$db->escape($fixtureLink)."' WHERE id=100");
ck(ep($base)['payload']['code']==='expired','Unknown RAM session cannot start a search');
ck(!str_contains(json_encode(ep($base)),'fixture password'),'Search never returns a password');
$before=$db->select_user('SELECT COUNT(*) n FROM customer_activity_logs')['n'];
ck(ep(['action'=>'issue']+$base)['payload']['code']==='config','Credential handoff endpoint removed');
ck(ep(['action'=>'claim']+$base)['payload']['code']==='config','No browser credential retrieval endpoint');
$urls=$config+['subscription_list_path'=>'/subscriptions','subscription_renew_path'=>'/subscriptions/renew'];
admin_provider_remote_save($db,4,$urls,'https://provider.example.invalid/login');
$stored=json_decode(admin_provider_remote_row($db,4)['config_json'],true);
ck($stored['subscription_list_path']==='/subscriptions'&&$stored['subscription_renew_path']==='/subscriptions/renew','Optional URLs saved without opening renewal page');
try{admin_provider_remote_config(['subscription_list_path'=>'https://other.invalid/subscriptions']+$config,'https://provider.example.invalid/login');ck(false,'Other origin should fail');}catch(RuntimeException $e){ck($e->getMessage()==='origin','List URL is restricted to provider origin');}
try{admin_provider_remote_config(['subscription_renew_path'=>'http://provider.example.invalid/create']+$config,'https://provider.example.invalid/login');ck(false,'HTTP should fail');}catch(RuntimeException $e){ck($e->getMessage()==='url','Renewal URL requires HTTPS');}
q("UPDATE orders SET payment_status='refunded' WHERE id=100");ck(ep($base)['payload']['code']==='unpaid','Payment rechecked before each search');
q("UPDATE orders SET payment_status='paid',status='cancelled' WHERE id=100");ck(ep($base)['payload']['code']==='unpaid','Cancelled paid order rejected');
q("UPDATE orders SET status='pending_activation' WHERE id=100");
q("UPDATE product_providers SET dashboard_url='https://changed.example.invalid' WHERE id=4");ck(ep($base)['payload']['code']==='changed_url','Configuration URL binding enforced');
q("UPDATE product_providers SET dashboard_url='https://provider.example.invalid/login',is_active=0 WHERE id=4");ck(ep($base)['payload']['code']==='missing','Inactive provider rejected');
q("UPDATE product_providers SET is_active=1 WHERE id=4");
q('UPDATE app_settings SET provider_remote_login_enabled=0 WHERE id=1');ck(ep($base)['payload']['code']==='disabled','OFF enforced before search');
ck(ep(['action'=>'connect']+$base)['payload']['code']==='disabled','OFF enforced before native login');
ck($db->select_user('SELECT COUNT(*) n FROM customer_activity_logs')['n']===$before,'No login or search activity history');
$_SERVER['HTTP_HOST']='localhost:8088';ob_start();admin_provider_browser_render(0,100,'fixture-csrf','pl');$markup=ob_get_clean();
ck(str_contains($markup,'data-provider-browser-search')&&!str_contains($markup,'<select')&&!str_contains($markup,'Chrome'),'UI has automatic search without identifier selector');
$_SERVER['HTTP_HOST']='dashboard.example.invalid';ob_start();admin_provider_browser_render(0,100,'fixture-csrf','pl');ck(ob_get_clean()==='','Native browser UI cannot render on deployment hosts');
echo "PASS: {$checks} server-provider endpoint/paid-order/OFF/no-history assertions\n";
