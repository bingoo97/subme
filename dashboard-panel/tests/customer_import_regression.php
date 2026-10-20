<?php
declare(strict_types=1);
if(!str_contains((string)getenv('DB_NAME'),'_test_'))throw new RuntimeException('Disposable test database required.');
require_once dirname(__DIR__).'/admin/bootstrap.php';require_once dirname(__DIR__).'/admin/customer_import_lib.php';
$db=Mysql_ks::get_instance();app_crypto_network_budget(0,0);
if(($argv[1]??'')==='--http-session'){
 admin_start_session();$_SESSION=['admin_user_id'=>1,'admin_locale'=>'pl','admin_csrf_token'=>'fixture'];$sid=session_id();session_write_close();echo $sid;exit;
}
if(($argv[1]??'')==='--endpoint'){
 $request=json_decode(base64_decode($argv[2]),true);$dir=app_crypto_runtime_dir().'/sessions';if(!is_dir($dir))mkdir($dir,0700,true);session_save_path($dir);session_start();
 $_SESSION=['admin_user_id'=>$request['actor']??1,'admin_locale'=>'pl','admin_csrf_token'=>'fixture'];$_POST=$request['post']??[];$_GET=[];$_SERVER['REQUEST_METHOD']=$request['method']??'POST';$_SERVER['REMOTE_ADDR']='127.0.0.1';
 ob_start();register_shutdown_function(static function(){echo json_encode(['status'=>http_response_code()?:200,'data'=>json_decode(ob_get_clean(),true)]);});require dirname(__DIR__).'/admin/customer_import.php';exit;
}
$checks=0;
function endpoint(array $request):array{
 $p=proc_open([PHP_BINARY,__FILE__,'--endpoint',base64_encode(json_encode($request))],[1=>['pipe','w'],2=>['pipe','w']],$pipes);$out=stream_get_contents($pipes[1]);$err=stream_get_contents($pipes[2]);foreach($pipes as $f)fclose($f);$code=proc_close($p);$result=json_decode($out,true);ck(!$code&&is_array($result),'Endpoint returns clean JSON: '.$out.$err);return $result;
}

function ck(bool $ok,string $label):void{global $checks;if(!$ok)throw new RuntimeException($label);$checks++;}
function sql(string $q):void{global $db;if(!$db->query($q))throw new RuntimeException($db->error);}
function countRows(string $t):int{global $db;return (int)$db->select_user('SELECT COUNT(*) n FROM '.$t)['n'];}
function finishJob(string $token):array{global $db;$batches=0;do{$s=admin_customer_import_batch($db,$token,1,'203.0.113.25');ck($s['processed']<=$s['total'],'Bounded progress');$batches++;}while(!$s['done']&&$batches<500);ck(!empty($s['done']),'Job completes');return $s;}
$hash=password_hash('original-password',PASSWORD_BCRYPT,['cost'=>4]);
$p=admin_customer_import_parse("email@example.invalid\nuser@example.invalid");ck(count($p['items'])===2&&!$p['has_header'],'An email containing the word email is not a header');
$bcryptB=substr_replace($hash,'2b',1,2);ck(admin_customer_import_password($bcryptB)['mode']==='hash'&&password_verify('original-password',$bcryptB),'External bcrypt 2b remains compatible');
$p=admin_parse_customer_import_source("a@example.invalid b@example.invalid,c@example.invalid;d@example.invalid\ne@example.invalid");ck($p['ok']&&count($p['items'])===5,'Mixed email separators');
$p=admin_parse_customer_import_source('a@example.invalid,broken,b@example.invalid,A@example.invalid');ck(count($p['items'])===2&&$p['duplicates']===1&&$p['invalid']===1,'Invalid tokens and duplicate emails are counted');
$p=admin_parse_customer_import_source("customer_id;user_email;passwd;is_enabled;ip;country\n10;jane@example.invalid;old;1;8.8.8.8;US\n11;mark@example.invalid;old2;0;9.9.9.9;DE");ck($p['items'][0]['status']==='active'&&$p['items'][1]['status']==='inactive'&&$p['items'][0]['password']==='old','CSV aliases, password and enabled status');
$p=admin_customer_import_parse("email\tpassword\tis_blocked\nx@example.invalid\tsecret,with;punctuation\t1\ny@example.invalid\tsecond\t0");ck($p['items'][0]['password']==='secret,with;punctuation'&&$p['items'][0]['status']==='blocked'&&$p['items'][1]['status']==='active','Tabs dominate incidental punctuation and blocked is inverted');
$sql="INSERT INTO `customers` (`id`,`email_address`,`password`,`status`) VALUES (1,'sql@example.invalid','pa,ss''word\\\\ok',1),(2,'hash@example.invalid','".$hash."','blocked'); DROP TABLE customers;";
$p=admin_customer_import_parse($sql);ck($p['ok']&&count($p['items'])===2&&$p['items'][0]['password']==="pa,ss'word\\ok"&&$p['items'][1]['password_mode']==='hash','SQL literals and escaped credentials are read as data');
ck(!admin_customer_import_parse('DROP TABLE customers;')['ok'],'SQL without column-labelled INSERT is rejected');
ck(!admin_customer_import_parse("INSERT INTO t(email,password) VALUES ('a@example.invalid',CONCAT('x','y'));")['ok'],'SQL expressions are never evaluated');
$p=admin_customer_import_parse('<table><tr><th>Email</th><th>Status</th></tr><tr><td>html@example.invalid</td><td>active</td></tr></table>');ck($p['items'][0]['email']==='html@example.invalid','Pasted HTML table');
$p=admin_customer_import_parse("email,password,status\nutf@example.invalid,\"with,comma\",mystery");ck($p['items'][0]['password']==='with,comma'&&$p['items'][0]['status']==='inactive'&&$p['unknown_status']===1,'Quoted fields and unknown statuses');
$p=admin_customer_import_parse("id,mail,secret,enabled\n1,map@example.invalid,old,0",['password_column'=>2,'numeric_status'=>'zero_active']);ck($p['items'][0]['password_mode']==='plain'&&$p['items'][0]['status']==='active','Manual mapping and numeric status override');
ck(admin_customer_import_password(md5('old'))['mode']==='generated','Unknown MD5 cannot masquerade as plaintext');
ck(admin_customer_import_password('$hello-world')['mode']==='plain','Ordinary dollar-prefixed plaintext remains usable');
ck(admin_customer_import_password($hash)['mode']==='hash','Compatible bcrypt is preserved');
ck(admin_customer_import_password(str_replace('$04$','$31$',$hash))['mode']==='generated','Excessive bcrypt work factor is not imported');
if(defined('PASSWORD_ARGON2ID')){$argon=password_hash('old',PASSWORD_ARGON2ID);ck(admin_customer_import_password($argon)['mode']==='hash','Compatible Argon2 is preserved');}
$many=[];for($i=0;$i<5000;$i++)$many[]='bulk'.$i.'@example.invalid';$p=admin_customer_import_parse(implode(' ',$many));ck(count($p['items'])===5000,'5000 emails in one line');
ck(!admin_customer_import_parse(str_repeat('x',10*1024*1024+1))['ok'],'Oversized input rejected');
app_ensure_customer_runtime_columns($db);sql('INSERT IGNORE INTO app_settings(id) VALUES(1)');
sql("UPDATE app_settings SET smtp_host='smtp.example.invalid',smtp_port=587,smtp_username='fixture',smtp_password='unused',site_url='http://localhost:8088',default_currency_id=2,customer_global_group_enabled=0");
sql("INSERT INTO admin_users(id,role_id,login_name,email,password_hash) VALUES(1,1,'import-admin','admin@example.invalid','unused')");
sql("INSERT INTO product_providers(id,name,slug,is_active) VALUES(1,'Fixture provider','fixture',1)");
app_ensure_email_template_runtime_rows($db);
$options=['locale_code'=>'pl','status'=>'active','customer_type'=>'client','visible_provider_ids'=>[1],'send_password_email'=>true];
$source="email,password,status,ip,country\nplain@example.invalid,original-password,active,8.8.8.8,US\nhash@example.invalid,\"{$hash}\",inactive,9.9.9.9,DE\nnew@example.invalid,,active,,\nmd5@example.invalid,".md5('old').",blocked,,";
$prepared=admin_customer_import_prepare($db,$source,$options,1);ck($prepared['ok']&&$prepared['can_start'],'Preview prepares a job');
ck(countRows('customers')===0&&countRows('outbound_email_queue')===0,'Preview never creates accounts or emails');
ck(!str_contains(json_encode($prepared),'original-password')&&!str_contains(json_encode($prepared),$hash),'Preview does not expose plaintext or stored hashes');
$file=admin_customer_import_job_dir().'/'.$prepared['job'].'.enc';ck(!str_contains(file_get_contents($file),'plain@example.invalid')&&!str_contains(file_get_contents($file),'original-password'),'Private job source is encrypted');
try{admin_customer_import_batch($db,$prepared['job'],99,'');ck(false,'Other admin denied');}catch(RuntimeException $e){ck(true,'Job ownership enforced');}
$result=finishJob($prepared['job']);ck($result['created']===4&&$result['failed']===0&&$result['queued']===2,'Only generated passwords are queued for email');
$plain=app_find_customer_by_email($db,'plain@example.invalid');$preserved=app_find_customer_by_email($db,'hash@example.invalid');
ck(app_verify_customer_password($plain,'original-password')&&app_verify_customer_password($preserved,'original-password'),'Plain and bcrypt credentials authenticate unchanged');
ck($plain['ip_address']===null&&$plain['country_code']===null&&$plain['last_login_at']===null,'Source IP/country and administrator IP never become customer login data');
ck($preserved['status']==='inactive'&&app_find_customer_by_email($db,'md5@example.invalid')['status']==='blocked','Imported account statuses retained');
ck(app_customer_provider_is_visible($db,(int)$plain['id'],1)&&app_customer_provider_is_visible($db,(int)$preserved['id'],1),'Selected provider visibility is assigned');
$queue=$db->select_full_user('SELECT * FROM outbound_email_queue');foreach($queue as $mail)ck($mail['status']==='pending'&&(int)$mail['attempt_count']===0,'Mail is deferred, never delivered by importer');
$again=admin_customer_import_batch($db,$prepared['job'],1,'');ck($again['done']&&countRows('customers')===4&&countRows('outbound_email_queue')===2,'Replayed completed batch does not duplicate accounts or emails');
$repeat=admin_customer_import_prepare($db,$source,$options,1);$repeat=finishJob($repeat['job']);ck($repeat['skipped']===4&&$repeat['created']===0&&countRows('outbound_email_queue')===2,'Existing customers are skipped without overwriting or emailing');
$_SESSION=['customer_login_state_needs_sync'=>1];$_SERVER['GEOIP_COUNTRY_CODE']='AT';app_update_customer_login_state($db,(int)$plain['id'],date('Y-m-d H:i:s'),'8.8.8.8');
$login=app_find_customer_by_email($db,'plain@example.invalid');ck($login['ip_address']==='8.8.8.8'&&$login['country_code']==='AT'&&!empty($login['last_login_at']),'Actual first login stores IP and local GeoIP country');
ck(app_customer_login_country('127.0.0.1')===''&&app_customer_login_country('192.168.1.1')==='','Private IP does not invent a country');
$read=admin_customer_import_batch($db,$prepared['job'],1,'',true);ck($read['done']&&countRows('customers')===4,'Status polling is read-only');
$decrypted=json_decode(admin_mixer_decrypt_secret(file_get_contents($file)),true);ck($decrypted['items']===[]&&$decrypted['options']===[],'Finished job removes source credentials');
$oldHash=$plain['password_hash'];$newSource="email,password,status,balance_amount,role_id,customer_type\nplain@example.invalid,changed,blocked,99999,1,boss";
$skip=admin_customer_import_prepare($db,$newSource,$options,1);$skip=finishJob($skip['job']);$unchanged=app_find_customer_by_email($db,'plain@example.invalid');
ck($skip['skipped']===1&&$unchanged['password_hash']===$oldHash&&$unchanged['status']==='active'&&(float)$unchanged['balance_amount']===0.0,'Existing account permissions, balance and password are never overwritten');
$locked=admin_customer_import_prepare($db,'lock@example.invalid',array_merge($options,['send_password_email'=>false]),1);$held=fopen(admin_customer_import_job_dir().'/'.$locked['job'].'.lock','c');flock($held,LOCK_EX);
ck(!empty(admin_customer_import_batch($db,$locked['job'],1,'')['busy']),'Concurrent batch receives busy instead of duplicate writes');flock($held,LOCK_UN);fclose($held);
$lockFile=admin_customer_import_job_dir().'/'.$locked['job'].'.enc';$expired=json_decode(admin_mixer_decrypt_secret(file_get_contents($lockFile)),true);$expired['expires']=time()-1;admin_customer_import_write($lockFile,$expired);
try{admin_customer_import_batch($db,$locked['job'],1,'');ck(false,'Expired job rejected');}catch(RuntimeException $e){ck(true,'Expired job rejected');}
$r=endpoint(['post'=>['action'=>'prepare','import_source_text'=>'csrf@example.invalid','_csrf'=>'bad']]);ck($r['status']===403,'CSRF rejects prepare');
$r=endpoint(['actor'=>0,'post'=>['action'=>'prepare','import_source_text'=>'anon@example.invalid','_csrf'=>'fixture']]);ck($r['status']===403,'Anonymous import rejected');
$r=endpoint(['method'=>'GET','post'=>['action'=>'prepare','_csrf'=>'fixture']]);ck($r['status']===403,'Read-only GET cannot import');
$r=endpoint(['post'=>['action'=>'prepare','import_source_text'=>['bad'],'_csrf'=>'fixture']]);ck($r['status']===422,'Malformed source arrays rejected');
$r=endpoint(['post'=>['action'=>'prepare','import_source_text'=>'endpoint@example.invalid','_csrf'=>'fixture']]);ck($r['status']===200&&!empty($r['data']['job'])&&countRows('customers')===4,'Endpoint preview creates no customer');
$sqlOff="UPDATE app_settings SET smtp_host=''";sql($sqlOff);
$noMail=admin_customer_import_prepare($db,'no-mail@example.invalid',$options,1);ck(!$noMail['can_start'],'Unconfigured SMTP blocks import requiring generated-password email');
try{admin_customer_import_batch($db,$noMail['job'],1,'');ck(false,'SMTP gate enforced server-side');}catch(RuntimeException $e){ck(countRows('customers')===4,'SMTP gate enforced server-side');}
$failed=admin_customer_import_row($db,['email'=>'mail-fail@example.invalid','password'=>'','password_mode'=>'generated','status'=>'active'],$options,1,'');
ck($failed['kind']==='failed'&&!app_find_customer_by_email($db,'mail-fail@example.invalid')&&countRows('outbound_email_queue')===2,'Queue failure rolls back the account and mail together');
sql("UPDATE app_settings SET smtp_host='smtp.example.invalid'");
$source="email,password\n";for($i=0;$i<5000;$i++)$source.='same@domain'.$i.'.invalid,"'.$hash.'"' . "\n";
$started=microtime(true);$big=admin_customer_import_prepare($db,$source,array_merge($options,['send_password_email'=>false]),1);$first=admin_customer_import_batch($db,$big['job'],1,'');ck($first['processed']<=25&&!$first['done'],'Large import is bounded per request');
$big=finishJob($big['job']);ck($big['created']===5000&&$big['failed']===0,'Full 5000-account import with repeated handle base completes');
ck(countRows('customers')===5004&&countRows('outbound_email_queue')===2,'Large import never sends or queues unnecessary password emails');
$duplicates=(int)$db->select_user('SELECT COUNT(*) n FROM (SELECT public_handle FROM customers GROUP BY public_handle HAVING COUNT(*)>1) d')['n'];ck($duplicates===0,'5000 colliding handle bases produce unique handles');
echo 'PASS '.$checks.' import checks; 5000 accounts in '.round(microtime(true)-$started,2)."s. No real email sent.\n";
if(getenv('APP_UI_EXPORT')==='1'){
 sql("UPDATE app_settings SET admin_panel_subscription_paid_until='2035-12-31',maintenance_mode=0");
 $_SESSION=['admin_user_id'=>1,'admin_locale'=>'pl','admin_csrf_token'=>'fixture'];$_SERVER['HTTP_HOST']='localhost';$_SERVER['REQUEST_METHOD']='GET';$_SERVER['REQUEST_URI']='/admin/?page=users';$_GET=['page'=>'users'];$_POST=[];
 $dir='/tmp/subme-customer-import-preview';if(!is_dir($dir))mkdir($dir,0700,true);ob_start();require dirname(__DIR__).'/admin/index.php';file_put_contents($dir.'/index.html',ob_get_clean());
}
