<?php
declare(strict_types=1);
if(!str_contains((string)getenv('DB_NAME'),'_test_'))throw new RuntimeException('Disposable database required.');
require_once dirname(__DIR__).'/bootstrap/localization.php';require_once dirname(__DIR__).'/admin/bootstrap.php';require_once dirname(__DIR__).'/admin/customer_import_lib.php';
$db=Mysql_ks::get_instance();app_crypto_network_budget(0,0);app_ensure_customer_runtime_columns($db);$checks=0;
function statusCheck(bool $ok,string $message):void{global $checks;if(!$ok)throw new RuntimeException($message);$checks++;}
$hash=password_hash('status-security-password',PASSWORD_BCRYPT,['cost'=>4]);
foreach(['blocked','inactive'] as $state){$email='status-security-'.$state.'@example.invalid';$db->insert(['email','public_handle','password_hash','password_hash_algorithm','status'],[$email,'status-security-'.$state,$hash,'password_hash',$state],'customers');}
function statusLogin(Mysql_ks $db,array $get,array $post):array{
 $site='login';$user=['logged'=>0];$t=[];$_GET=$get;$_POST=$post;$_SESSION=['__csrf_frontend'=>'fixture'];
 $smarty=new class{public array $values=[];public array $templates=[];public function assign($key,$value){$this->values[$key]=$value;}public function display($template){$this->templates[]=$template;}};
 require dirname(__DIR__).'/pages/login.php';return ['session'=>$_SESSION,'values'=>$smarty->values,'templates'=>$smarty->templates];
}
$blocked=app_find_customer_by_email($db,'status-security-blocked@example.invalid');
$r=statusLogin($db,[],['login'=>1,'email'=>$blocked['email'],'password'=>'status-security-password','_csrf'=>'fixture']);
statusCheck(empty($r['session']['id'])&&!in_array('login_correct.tpl',$r['templates'],true)&&isset($r['values']['alert_error']),'Correct password cannot authenticate blocked account');
$r=statusLogin($db,['aktywuj_user'=>$blocked['id']],[]);
statusCheck(app_customer_is_blocked(app_find_customer_by_id($db,(int)$blocked['id']))&&isset($r['values']['alert_error']),'Old activation URL does not unblock customer and shows error');
app_activate_customer($db,(int)$blocked['id']);statusCheck(app_customer_is_blocked(app_find_customer_by_id($db,(int)$blocked['id'])),'Activation helper independently protects blocked state');
$inactive=app_find_customer_by_email($db,'status-security-inactive@example.invalid');$r=statusLogin($db,['aktywuj_user'=>$inactive['id']],[]);statusCheck(app_customer_is_active(app_find_customer_by_id($db,(int)$inactive['id'])),'Existing inactive-account activation still works');
foreach(['0'=>'inactive','1'=>'active','2'=>'blocked','3'=>'inactive','unknown'=>'inactive'] as $value=>$expected){$p=admin_customer_import_parse("email,status\nstatus@example.invalid,$value");statusCheck($p['ok']&&$p['items'][0]['status']===$expected,'Default status mapping '.$value);}
statusCheck(!admin_customer_import_parse("INSERT INTO users(email,status) VALUES ('first@example.invalid',2); INSERT INTO other_users(email,status) VALUES ('second@example.invalid',1);")['ok'],'Mixed SQL tables cannot silently merge');
$p=admin_customer_import_parse("INSERT INTO `old_db`.`users` (`email`,`status`) VALUES ('qualified@example.invalid',2);");statusCheck($p['ok']&&$p['items'][0]['status']==='blocked','Qualified SQL table and status 2 parse');
$p=admin_customer_import_parse("email,is_active,status\npriority@example.invalid,1,2");statusCheck($p['ok']&&$p['mapping']['status']===2&&$p['items'][0]['status']==='blocked','Explicit status column has priority over generic active flag');
echo "PASS $checks status/login security checks.\n";
