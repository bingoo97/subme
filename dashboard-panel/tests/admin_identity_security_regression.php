<?php
declare(strict_types=1);
if (!str_contains((string)getenv('DB_NAME'),'_test_') || !getenv('APP_CRYPTO_CACHE_DIR')) throw new RuntimeException('Disposable database/cache required.');
require_once dirname(__DIR__).'/admin/bootstrap.php';
$db=Mysql_ks::get_instance();
if (($argv[1]??'')==='--quick') {
    admin_start_session();$_SESSION['admin_csrf_token']='fixture';$_SESSION['admin_locale']='pl';if((int)$argv[2])$_SESSION['admin_user_id']=(int)$argv[2];
    $_SERVER['REQUEST_METHOD']=$argv[4]??'GET';$_GET=['filter'=>$argv[3]??'new'];if(($argv[5]??'')==='throttled')$_SESSION['crypto_quick_new']=microtime(true);
    ob_start();register_shutdown_function(static function(){ $body=ob_get_clean();if(session_status()===PHP_SESSION_ACTIVE)session_destroy();echo json_encode(['status'=>http_response_code()?:200,'data'=>json_decode($body,true)]);});
    require dirname(__DIR__).'/admin/crypto_quick_endpoint.php';exit;
}
if (($argv[1]??'')==='--request') {
    admin_start_session(); $_SESSION['admin_user_id']=(int)$argv[2]; $_SESSION['admin_csrf_token']='fixture'; $_SESSION['admin_locale']='pl';
    $_GET=['page'=>$argv[6]??'settings'];if(($_GET['page']??'')==='payments'){$_GET['payment_type']='crypto';$_GET['payment_id']='112';}if($argv[3]==='mode')$_GET['admin']='true';
    $_POST=json_decode(base64_decode($argv[4]),true);
    $_SERVER['REQUEST_METHOD']=$_POST?'POST':'GET';$_SERVER['HTTP_HOST']='localhost';$_SERVER['SCRIPT_NAME']='/admin/index.php';$_SERVER['DOCUMENT_ROOT']=dirname(__DIR__,2).'/public_html';
    $errors=[];set_error_handler(static function($level,$message)use(&$errors){if(error_reporting()&$level)$errors[]=$message;return false;});
    ob_start();register_shutdown_function(static function()use(&$errors){$body=ob_get_clean();if(session_status()===PHP_SESSION_ACTIVE)session_destroy();echo json_encode(['status'=>http_response_code()?:200,'errors'=>$errors,'owner_mode'=>str_contains($body,'id="panelAutoEnabled"'),'edit_lower'=>str_contains($body,'id="adminEditModal3"'),'edit_peer'=>str_contains($body,'id="adminEditModal2"'),'edit_owner'=>str_contains($body,'id="adminEditModal1"'),'own_password'=>str_contains($body,'id="adminOwnPasswordModal"'),'html'=>(($GLOBALS['argv'][5]??'')==='html'?$body:null)]);});
    require dirname(__DIR__).'/admin/index.php';exit;
}
$n=0;
function ck($yes,$text){global $n;if(!$yes)throw new RuntimeException($text);$n++;}
function sql($q){global $db;if(!$db->query($q))throw new RuntimeException($db->error);}
function req($id,$mode=false,$post=[],$html=false,$route='settings'){$p=proc_open([PHP_BINARY,__FILE__,'--request',(string)$id,$mode?'mode':'normal',base64_encode(json_encode($post)),$html?'html':'json',$route],[1=>['pipe','w'],2=>['pipe','w']],$pipes);$out=stream_get_contents($pipes[1]);$err=stream_get_contents($pipes[2]);foreach($pipes as $pipe)fclose($pipe);$exit=proc_close($p);$r=json_decode($out,true);if($exit||!is_array($r)||$r['errors'])throw new RuntimeException('Request render failure: '.substr(strip_tags($r['html']??$out),-1200).$err);return $r;}
foreach (['crypto','bank','crypto_topup'] as $type) {
    foreach (admin_payment_status_options($type) as $status) {
        foreach (['pl','en'] as $locale) {
            $help = admin_payment_status_guidance($type,$status,$locale);
            ck(in_array($help['tone'],['warning','info','danger','success','neutral'],true) && $help['title'] !== '' && $help['text'] !== '', 'Every payment type/status has localized guidance');
        }
    }
}
ck(str_contains(admin_payment_status_guidance('crypto','approved','pl')['text'],'główny portfel'),'Approved crypto explains withdrawal before archive');
ck(!str_contains(admin_payment_status_guidance('bank','approved','pl')['text'],'portfel'),'Bank guidance does not mention crypto wallets');
ck(str_contains(admin_payment_status_guidance('crypto_topup','pending','pl')['text'],'starszy'),'Legacy top-up has its own explanation');
ck(admin_payment_status_guidance('crypto','legacy_unknown','pl')['tone']==='neutral','Unknown historical status gets safe neutral fallback');
$statusMessages=require dirname(__DIR__).'/admin/locales/pl.php';
foreach ([
 [['status'=>'active','payment_status'=>'paid','fulfillment_status'=>'delivered'],'success','Zrealizowane'],
 [['status'=>'pending_payment','payment_status'=>'unpaid','fulfillment_status'=>'pending'],'warning','Oczekuje na płatność'],
 [['status'=>'pending_payment','payment_status'=>'paid','fulfillment_status'=>'pending'],'warning','Oczekuje na aktywację'],
 [['status'=>'cancelled','payment_status'=>'paid','fulfillment_status'=>'cancelled'],'danger','Anulowane'],
 [['status'=>'expired','payment_status'=>'paid','fulfillment_status'=>'delivered'],'danger','Wygasłe'],
 [['status'=>'refunded','payment_status'=>'refunded'],'danger','Zwrócone'],
 [['status'=>'archived','payment_status'=>'paid','fulfillment_status'=>'delivered'],'neutral','Zarchiwizowane'],
] as [$row,$tone,$label]) {
 $badge=admin_search_order_status_badge($row,$statusMessages);
 ck($badge['tone']===$tone&&$badge['label']===$label,'Search status prioritises payment/fulfilment and terminal order states');
}
app_fetch_settings($db);admin_ensure_user_runtime_columns($db);
sql('SET FOREIGN_KEY_CHECKS=0');sql("INSERT IGNORE INTO app_settings(id,site_name) VALUES(1,'Security fixture')");sql("UPDATE app_settings SET default_currency_id=(SELECT id FROM currencies WHERE code='EUR' LIMIT 1) WHERE id=1");
sql("INSERT INTO admin_roles(id,name,slug,access_level) VALUES(901,'Fixture Boss','fixture-boss',1000),(902,'Fixture Operator','fixture-operator',500),(903,'Fixture Limited','fixture-limited',100) ON DUPLICATE KEY UPDATE access_level=VALUES(access_level)");
$hash=$db->escape(password_hash('fixture-current',PASSWORD_DEFAULT));
foreach([[1,'admin',901],[2,'secondboss',901],[3,'operator',902],[4,'limited',903],[5,'inactiveboss',901]] as [$id,$login,$role])sql("INSERT INTO admin_users(id,role_id,login_name,email,public_handle,password_hash,password_hash_algorithm,status) VALUES({$id},{$role},'{$login}','{$login}@example.invalid','fixture-{$login}','{$hash}','password_hash','".($id===5?'inactive':'active')."')");
$a=admin_admin_user_find($db,1);$boss=admin_admin_user_find($db,2);$low=admin_admin_user_find($db,3);
ck(admin_user_can_use_admin_mode($a),'Owner has Admin Mode');
foreach([$boss,$low,[],array_merge($a,['login_name'=>'Admin']),array_merge($a,['status'=>'inactive']),array_merge($a,['role_access_level'=>500])] as $u)ck(!admin_user_can_use_admin_mode($u),'Only exact active owner has mode');
function inputFor($id){global $db;$r=admin_admin_user_find($db,$id);return array_intersect_key($r,array_flip(['login_name','email','public_handle','role_id','status']))+['password'=>'','repeat_password'=>''];}
function snapshot($id){global $db;return $db->select_user('SELECT login_name,email,role_id,status,password_hash FROM admin_users WHERE id='.(int)$id);}
$ownerBefore=snapshot(1);$peerBefore=snapshot(2);
ck(admin_update_admin_user($db,1,inputFor(1),$a)['ok'],'Owner can save own profile without changing identity');
$rename=inputFor(1);$rename['login_name']='renamedowner';ck(!admin_update_admin_user($db,1,$rename,$a)['ok'],'Owner login remains immutable');
foreach([[$a,2],[$boss,1],[$boss,2],[$low,1],[$low,2]] as [$actor,$id]){
 $in=inputFor($id)+[];$in['password']=$in['repeat_password']='fixture-new';
 ck(!admin_update_admin_user($db,$id,$in,$actor)['ok'],'Peer/self reset blocked');
 ck(!admin_delete_admin_user($db,$id,$actor)['ok'],'Peer/self deletion blocked');
}
foreach(['admin','ADMIN',' Admin '] as $login){$in=inputFor(3);$in['login_name']=$login;ck(!admin_update_admin_user($db,3,$in,$boss)['ok'],'Reserved login update blocked');
 $in['password']=$in['repeat_password']='fixture-new';ck(!admin_create_admin_user($db,$in,2)['ok'],'Reserved login creation blocked');}
$in=inputFor(3);$in['role_id']=901;$in['password']=$in['repeat_password']='fixture-new';ck(!admin_update_admin_user($db,3,$in,$boss)['ok'],'Cannot promote lower role to peer and gain credentials');
$in=inputFor(2);$in['role_id']=902;ck(!admin_update_admin_user($db,2,$in,$a)['ok'],'Owner cannot demote peer to bypass password protection');
$in=inputFor(2);$in['email']='takeover@example.invalid';ck(!admin_update_admin_user($db,2,$in,$a)['ok'],'Peer recovery email takeover blocked');
ck(snapshot(1)===$ownerBefore&&snapshot(2)===$peerBefore,'Protected account state and hashes unchanged');
$in=inputFor(3);$in['password']=$in['repeat_password']='fixture-lower-new';ck(admin_update_admin_user($db,3,$in,$boss)['ok'],'Boss may reset lower role');ck(password_verify('fixture-lower-new',snapshot(3)['password_hash']),'Lower password really changed');
ck(!admin_update_admin_user($db,2,inputFor(2),array_merge($low,['role_access_level'=>9999]))['ok'],'Helper reloads actor privileges from DB');
$own=['current_password'=>'wrong','new_password'=>'fixture-own-new','repeat_password'=>'fixture-own-new','admin_user_id'=>1];ck(!admin_change_own_password($db,2,$own)['ok'],'Current password required');
$own['current_password']='fixture-current';ck(admin_change_own_password($db,2,$own)['ok'],'Boss changes own password');ck(password_verify('fixture-own-new',snapshot(2)['password_hash'])&&snapshot(1)===$ownerBefore,'Forged target ID ignored');
$own['current_password']='fixture-lower-new';ck(admin_change_own_password($db,3,$own)['ok'],'Lower role can change own password');
$owner=req(1,true,[],true);ck($owner['status']===200&&$owner['owner_mode'],'Owner mode renders');
foreach([2,3] as $id){$r=req($id,true);ck($r['status']===403&&!$r['owner_mode'],'Direct mode GET blocked');$r=req($id,true,['_csrf'=>'fixture','admin_save_panel_subscription_settings'=>'1','panel_auto_enabled'=>'1']);ck($r['status']===403,'Forged mode POST blocked');}
$doc=new DOMDocument();$old=libxml_use_internal_errors(true);$doc->loadHTML($owner['html']);libxml_clear_errors();libxml_use_internal_errors($old);$xp=new DOMXPath($doc);
$crypto=$doc->getElementById('admin-settings-crypto-pane');$features=$doc->getElementById('admin-settings-features-pane');
ck((bool)$crypto&&strpos($owner['html'],'id="admin-settings-features-tab"')<strpos($owner['html'],'id="admin-settings-crypto-tab"'),'Crypto tab follows Features');
foreach(['swap_converter_enabled','hinkal_private_send_enabled','mixer_enabled'] as $id){$node=$doc->getElementById($id);ck($node&&$node->getAttribute('form')==='adminSettingsFeaturesForm','Moved crypto control retains form ownership');ck($xp->query('ancestor::*[@id="admin-settings-crypto-pane"]',$node)->length===1,'Control is inside Crypto pane');}
ck(!str_contains(strtolower($owner['html']),'rango'),'No retired Rango UI or script rendered');
$post=['_csrf'=>'fixture','admin_save_feature_settings'=>'1'];
foreach($xp->query('//*[@name and (ancestor::form[@id="adminSettingsFeaturesForm"] or @form="adminSettingsFeaturesForm")]') as $node){
 if(!in_array($node->tagName,['input','select','textarea'],true)||$node->hasAttribute('disabled'))continue;
 $type=$node->getAttribute('type');if(in_array($type,['checkbox','radio'],true)&&!$node->hasAttribute('checked'))continue;
 $values=[];if($node->tagName==='select'){$opts=$xp->query('.//option[@selected]',$node);if(!$opts->length)$opts=$xp->query('.//option[1]',$node);foreach($opts as $o)$values[]=$o->getAttribute('value');}else $values[]=($node->tagName==='textarea'?$node->textContent:($node->hasAttribute('value')?$node->getAttribute('value'):($type==='checkbox'?'on':'')));
 $name=$node->getAttribute('name');foreach($values as $value){if(str_ends_with($name,'[]'))$post[substr($name,0,-2)][]=$value;else $post[$name]=$value;}
}
$before=$db->select_user('SELECT swap_converter_enabled,hinkal_private_send_enabled,mixer_enabled,registration_enabled,default_currency_id FROM app_settings WHERE id=1');
$post['swap_converter_integrator']='fixture-integrator';$post['swap_converter_fee_percent']='1.25';$post['hinkal_private_send_fee_percent']='1.5';$post['hinkal_private_send_fee_wallet']='0x1111111111111111111111111111111111111111';$post['mixer_fee_percent']='1.75';req(1,true,$post);
$after=$db->select_user('SELECT swap_converter_enabled,hinkal_private_send_enabled,mixer_enabled,registration_enabled,default_currency_id,swap_converter_integrator,swap_converter_fee_percent,hinkal_private_send_fee_percent,mixer_fee_percent FROM app_settings WHERE id=1');
ck(array_intersect_key($after,$before)===$before,'Crypto save retains all original feature switches and currency');
ck($after['swap_converter_integrator']==='fixture-integrator'&&(float)$after['swap_converter_fee_percent']===1.25&&(float)$after['hinkal_private_send_fee_percent']===1.5&&(float)$after['mixer_fee_percent']===1.75,'Moved LI.FI/Hinkal/Swapper settings save through real handler: '.json_encode($after));
unset($post['contact_form_enabled']);req(1,true,$post);$retained=$db->select_user('SELECT swap_converter_integrator,swap_converter_fee_percent,hinkal_private_send_fee_percent,mixer_fee_percent,contact_form_enabled FROM app_settings WHERE id=1');
ck((int)$retained['contact_form_enabled']===0&&$retained['swap_converter_integrator']==='fixture-integrator'&&(float)$retained['swap_converter_fee_percent']===1.25&&(float)$retained['hinkal_private_send_fee_percent']===1.5&&(float)$retained['mixer_fee_percent']===1.75,'Features save retains moved Crypto values');
$normalHtml=req(2,false,[],true)['html'];
foreach (['customer_type_switch_enabled','page_guidance_enabled','payment_test_mode_notice_enabled'] as $field) {
    ck(!str_contains($normalHtml,'id="'.$field.'"') && (bool)$doc->getElementById($field),'Test-only switches are hidden outside Admin Mode');
}
ck(str_contains($normalHtml,'id="crypto_auto_accept_enabled"'),'Auto-accept toggle is available in ordinary Settings');
ck(!str_contains($normalHtml,'id="crypto_auto_accept_min_amount_percent"'),'Advanced auto-accept thresholds remain protected');
$protectedBefore=$db->select_user('SELECT customer_type_switch_enabled,page_guidance_enabled,payment_test_mode_notice_enabled,crypto_auto_accept_window_minutes,crypto_auto_accept_min_amount_percent FROM app_settings WHERE id=1');
$standardPost=$post;$standardPost['crypto_auto_accept_enabled']='1';$standardPost['crypto_auto_accept_min_amount_percent']='50';$standardPost['crypto_auto_accept_window_minutes']='5';
foreach (['customer_type_switch_enabled','page_guidance_enabled','payment_test_mode_notice_enabled'] as $field) $standardPost[$field]=empty($protectedBefore[$field])?'1':'';
req(2,false,$standardPost);
ck((int)$db->select_user('SELECT crypto_auto_accept_enabled FROM app_settings WHERE id=1')['crypto_auto_accept_enabled']===1,'Ordinary admin can enable auto-accept');
ck($db->select_user('SELECT customer_type_switch_enabled,page_guidance_enabled,payment_test_mode_notice_enabled,crypto_auto_accept_window_minutes,crypto_auto_accept_min_amount_percent FROM app_settings WHERE id=1')===$protectedBefore,'Forged normal POST cannot change protected test switches or thresholds');
ck(!str_contains(req(2,false,[],true)['html'],'data-crypto-quick-settings-link'),'Auto-accept recommendation is absent when enabled');
unset($standardPost['crypto_auto_accept_enabled']);req(2,false,$standardPost);
ck((int)$db->select_user('SELECT crypto_auto_accept_enabled FROM app_settings WHERE id=1')['crypto_auto_accept_enabled']===0,'Ordinary admin can disable auto-accept');
$offHtml=req(2,false,[],true)['html'];ck(str_contains($offHtml,'data-crypto-quick-settings-link')&&str_contains($offHtml,'/admin/?page=settings#crypto_auto_accept_enabled'),'Disabled auto-accept shows direct standard Settings link');

foreach(['provider_remote_login_enabled','provider_remote_subscriptions_enabled'] as $id){ck((bool)$doc->getElementById($id),'Owner mode displays provider switch');ck(!str_contains($normalHtml,'id="'.$id.'"'),'Normal Settings hides provider switch');}
$post['provider_remote_login_enabled']=$post['provider_remote_subscriptions_enabled']='on';req(1,true,$post);
$flags=$db->select_user('SELECT provider_remote_login_enabled,provider_remote_subscriptions_enabled FROM app_settings WHERE id=1');ck((int)$flags['provider_remote_login_enabled']===1&&(int)$flags['provider_remote_subscriptions_enabled']===1,'Owner mode can enable provider switches');
unset($post['provider_remote_login_enabled'],$post['provider_remote_subscriptions_enabled']);req(2,false,$post);
ck($db->select_user('SELECT provider_remote_login_enabled,provider_remote_subscriptions_enabled FROM app_settings WHERE id=1')===$flags,'Normal save cannot clear hidden provider switches');
req(1,true,$post);$post['provider_remote_login_enabled']=$post['provider_remote_subscriptions_enabled']='on';req(2,false,$post);
$flags=$db->select_user('SELECT provider_remote_login_enabled,provider_remote_subscriptions_enabled FROM app_settings WHERE id=1');ck((int)$flags['provider_remote_login_enabled']===0&&(int)$flags['provider_remote_subscriptions_enabled']===0,'Forged normal POST cannot enable provider switches');
$normal=req(2);ck(!$normal['owner_mode']&&$normal['edit_lower']&&$normal['edit_peer']&&!$normal['edit_owner'],'Normal Boss gets self profile and lower-role management, never owner');ck($normal['own_password'],'Own-password dialog available');
ck(req(3)['own_password'],'Lower role gets own-password dialog without Settings access');
$postOwn=['_csrf'=>'fixture','admin_change_password'=>'1','current_password'=>'fixture-own-new','new_password'=>'fixture-self-post','repeat_password'=>'fixture-self-post','admin_user_id'=>'1'];
req(3,false,$postOwn);ck(password_verify('fixture-self-post',snapshot(3)['password_hash']),'Lower role own-password POST works without Settings access');ck(snapshot(1)===$ownerBefore,'Own-password POST ignores another target ID');
$postOwn['_csrf']='invalid';$postOwn['current_password']='fixture-self-post';$postOwn['new_password']=$postOwn['repeat_password']='fixture-forged';req(3,false,$postOwn);ck(password_verify('fixture-self-post',snapshot(3)['password_hash']),'Own-password POST rejects invalid CSRF');
$in=inputFor(3)+['_csrf'=>'fixture','admin_update_admin_user'=>'1','admin_user_id'=>'3'];$in['password']=$in['repeat_password']='fixture-post-new';ck(req(2,false,$in)['status']===302,'Boss can reset lower role from normal Settings');ck(password_verify('fixture-post-new',snapshot(3)['password_hash']),'Normal Settings POST changes lower password');
$in['_csrf']='invalid';$in['password']=$in['repeat_password']='fixture-forged';req(2,false,$in);ck(password_verify('fixture-post-new',snapshot(3)['password_hash']),'Invalid CSRF does not change password');
$in=inputFor(1)+['_csrf'=>'fixture','admin_update_admin_user'=>'1','admin_user_id'=>'1'];$in['password']=$in['repeat_password']='fixture-forged';req(2,false,$in);ck(snapshot(1)===$ownerBefore,'Direct non-mode POST cannot reset owner');
require_once dirname(__DIR__).'/admin/crypto_quick.php';
$btcAsset=(int)$db->select_user("SELECT id FROM crypto_assets WHERE code='BTC' LIMIT 1")['id'];$eur=(int)$db->select_user("SELECT id FROM currencies WHERE code='EUR' LIMIT 1")['id'];
sql("INSERT INTO customers(id,email,public_handle,password_hash,status) VALUES(1,'client@example.invalid','<script>fixture</script>','unused','active')");
for($i=100;$i<112;$i++)sql("INSERT INTO crypto_deposit_requests(id,customer_id,crypto_asset_id,wallet_address_id,requested_fiat_amount,fiat_currency_id,exchange_rate,requested_crypto_amount,status,requested_at,expires_at) VALUES({$i},1,{$btcAsset},{$i},29,{$eur},50000,0.00058,'pending',NOW(),DATE_ADD(NOW(),INTERVAL 1 HOUR))");
foreach([112=>'approved',113=>'cancelled',114=>'expired',115=>'archived'] as $id=>$status)sql("INSERT INTO crypto_deposit_requests(id,customer_id,crypto_asset_id,wallet_address_id,requested_fiat_amount,fiat_currency_id,exchange_rate,requested_crypto_amount,received_crypto_amount,received_fiat_amount,status,requested_at,expires_at) VALUES({$id},1,{$btcAsset},{$id},29,{$eur},50000,0.00058,0.00057,28.5,'{$status}',NOW(),DATE_ADD(NOW(),INTERVAL 1 HOUR))");
sql("UPDATE crypto_deposit_requests SET expires_at=DATE_SUB(NOW(),INTERVAL 1 MINUTE) WHERE id=100");
$queryCount=Mysql_ks::$licznik;$counts=admin_crypto_quick_counts($db);
ck(Mysql_ks::$licznik-$queryCount===1,'Badge uses one aggregate query');
$expiryFixture=[['id'=>999,'asset_code'=>'BTC','customer_id'=>1,'status'=>'pending','requested_at'=>date('Y-m-d H:i:s'),'expires_at'=>date('Y-m-d H:i:s',time()+3600),'requested_crypto_amount'=>'.002','requested_fiat_amount'=>100,'currency_code'=>'EUR']];
$expiryHtml=admin_crypto_quick_html($expiryFixture,'new');
preg_match('/data-crypto-quick-expires="(\d+)"/',$expiryHtml,$expiryMatch);
ck(abs((int)$expiryMatch[1]-time()-3600)<=1,'One-hour runtime ticket renders exactly one hour without UTC offset');
ck($counts===['new'=>11,'expired'=>2,'cancelled'=>1,'payout'=>1],'Badge counts all matching requests beyond ten row limit');
foreach ([['new'=>1,'expired'=>2,'payout'=>3,'cancelled'=>4],['expired'=>2,'payout'=>3,'cancelled'=>4],['payout'=>3,'cancelled'=>4],['cancelled'=>4],[]] as $i=>$state) {
    ck(admin_crypto_quick_priority($state)===['new','expired','payout','cancelled','new'][$i],'Badge follows requested category priority and empty fallback');
}
ck(str_contains(admin_crypto_quick_labels('pl')['cancelled_help'],'72h'),'Cancelled hint states actual 72-hour retention');
$queryCount=Mysql_ks::$licznik;$rows=admin_crypto_quick_rows($db,'new');ck(Mysql_ks::$licznik-$queryCount===1,'Quick overview uses one bounded SQL query');ck(count($rows)===10&&(int)$rows[0]['id']===111&&(int)$rows[9]['id']===102,'Quick overview limits to newest ten client requests');
ck(array_column(admin_crypto_quick_rows($db,'expired'),'id')===['114','100'],'Expiry filter includes elapsed pending requests');
ck(array_column(admin_crypto_quick_rows($db,'cancelled'),'id')===['113'],'Cancelled filter is isolated');
sql("INSERT INTO crypto_wallet_addresses(id,crypto_asset_id,address,network_code) VALUES(112,{$btcAsset},'1BoatSLRHtKNngkdXEeobR76b53LETtpyT','bitcoin')");
sql("INSERT INTO crypto_wallet_addresses(id,crypto_asset_id,address,network_code) VALUES(114,{$btcAsset},'3J98t1WpEZ73CNmQviecrnyiWrnqRhWNLy','bitcoin')");
foreach(['new','expired','cancelled','payout'] as $category) {
 $menuHtml=admin_crypto_quick_html(admin_crypto_quick_rows($db,$category),$category,admin_crypto_quick_labels('pl'),'fixture');
 ck(str_contains($menuHtml,'data-crypto-payment-more')&&str_contains($menuHtml,'aria-expanded="false"'),'Every category has a collapsed circular actions trigger');
 ck(str_contains($menuHtml,'data-crypto-quick-accept')===($category==='new'),'Only New offers the acceptance modal');
 if($category==='new'){
  ck(str_contains($menuHtml,'type="button" class="admin-crypto-quick__action admin-crypto-quick__action--accept"'),'Accept action cannot submit before modal confirmation');
  ck(strpos($menuHtml,'data-crypto-quick-accept')<strpos($menuHtml,'Szczegóły płatności'),'Acceptance precedes payment details');
  preg_match('/data-admin-crypto-accept-payload="([^"]+)"/',$menuHtml,$payloadMatch);
  $acceptPayload=json_decode(html_entity_decode($payloadMatch[1],ENT_QUOTES,'UTF-8'),true);
  ck($acceptPayload['payment_id']===(int)$rows[0]['id']&&$acceptPayload['requested_fiat_amount']==='29.00'&&$acceptPayload['requested_crypto_amount']==='0.00058','Modal receives the selected request and accurate requested amounts');
 }
 if(in_array($category,['new','expired'],true)) ck(str_contains($menuHtml,'name="quick_action" value="cancel"'),'Open/expired categories can cancel through existing handler');
 if(in_array($category,['expired','cancelled'],true)) ck(str_contains($menuHtml,'name="quick_action" value="renew"'),'Expired/cancelled requests can renew through existing handler');
 if($category==='cancelled') ck(!str_contains($menuHtml,'name="quick_action" value="cancel"')&&!str_contains($menuHtml,'value="archive"'),'Cancelled requests do not offer cancel/archive');
 ck(str_contains($menuHtml,'name="quick_action" value="delete"')===in_array($category,['expired','cancelled'],true),'Only expired/cancelled categories offer permanent deletion');
 if($category==='cancelled') ck(str_contains($menuHtml,'data-admin-confirm=')&&strpos($menuHtml,'value="delete"')>strpos($menuHtml,'value="renew"'),'Delete follows other actions and requires existing confirmation');
}
$elapsedRows=admin_crypto_quick_rows($db,'expired');$elapsedRows=array_values(array_filter($elapsedRows,fn($r)=>(int)$r['id']===100));
ck(!str_contains(admin_crypto_quick_html($elapsedRows,'expired'), 'name="quick_action" value="renew"'),'Elapsed pending requests do not offer renewal before actual expired status');
$paidRows=admin_crypto_quick_rows($db,'payout');ck(array_column($paidRows,'id')===['112'],'To withdraw shows approved unarchived customer deposits only');
$html=admin_crypto_quick_html($paidRows,'payout',admin_crypto_quick_labels('pl'),'fixture');ck(str_contains($html,'0.00057 BTC')&&!str_contains($html,'0.00058 BTC'),'Confirmed view uses actual received amount');
$precise=$paidRows;$precise[0]['received_crypto_amount']='123456789.123456789';ck(str_contains(admin_crypto_quick_html($precise,'payout'),'123456789.123456789 BTC'),'Crypto amount keeps native decimal precision');ck(str_contains(admin_crypto_quick_html([],'new'),'admin-crypto-quick__empty'),'Empty list has a deliberate illustrated state');
ck(!str_contains($html,'<script>')&&str_contains($html,'&lt;script&gt;'),'User content is escaped');
function quickReq($id,$filter='new',$method='GET',$throttled=false){ $p=proc_open([PHP_BINARY,__FILE__,'--quick',(string)$id,$filter,$method,$throttled?'throttled':'normal'],[1=>['pipe','w'],2=>['pipe','w']],$pipes);$out=stream_get_contents($pipes[1]);$err=stream_get_contents($pipes[2]);foreach($pipes as $pipe)fclose($pipe);$exit=proc_close($p);$r=json_decode($out,true);if($exit||!is_array($r))throw new RuntimeException('Quick endpoint: '.$out.$err);return $r; }
ck(quickReq(1,'new','GET',true)['status']===429,'Rapid repeated requests are rate limited');
ck(quickReq(0)['status']===401,'Quick endpoint rejects anonymous access');ck(quickReq(4)['status']===403,'Quick endpoint enforces payment permission');ck(quickReq(1,'invalid')['status']===422,'Unknown filter rejected');ck(quickReq(1,'new','POST')['status']===405,'Quick endpoint is read-only GET');
$r=quickReq(1,'new');ck($r['status']===200&&$r['data']['filter']==='new'&&substr_count($r['data']['html'],'class="admin-crypto-quick__item ')===10,'Authenticated quick endpoint returns ten rows');
ck($r['data']['counts']===$counts,'Overview endpoint returns fresh badge counts');
ck(str_contains($r['data']['html'],'Nowe'),'Endpoint preserves locale after releasing session lock');
$markup=req(1,false,[],true)['html'];ck(str_contains($markup,'id="adminCryptoQuick"')&&str_contains($markup,'bi bi-wallet2'),'Crypto-enabled header renders customer payment flyout');
ck(str_contains($markup,'class="admin-user-chip" data-bs-toggle="modal" data-bs-target="#adminOwnPasswordModal"')&&!str_contains($markup,'<i class="bi bi-key"'),'Avatar opens own password modal without a key button');
sql('UPDATE app_settings SET crypto_payments_enabled=0 WHERE id=1');ck(quickReq(1)['status']===403,'Disabled crypto rejects endpoint');ck(!str_contains(req(1,false,[],true)['html'],'id="adminCryptoQuick"'),'Disabled crypto hides flyout');sql('UPDATE app_settings SET crypto_payments_enabled=1 WHERE id=1');
ck(!str_contains($html,'<small>#')&&str_contains($html,'admin-crypto-quick__title'),'Visible identity row keeps date without payment ID');
ck(str_contains($html,'data-crypto-payment-more')&&str_contains($html,'role="group"')&&str_contains($html,'aria-expanded="false"')&&str_contains($html,'hidden>'),'Payout actions are collapsed behind an accessible menu');
ck(str_contains($html,'name="_csrf" value="fixture"')&&str_contains($html,'name="quick_action" value="archive"'),'Archive uses existing CSRF-protected Payments form');
ck(str_contains($html,'https://')&&str_contains($html,'rel="noopener noreferrer"'),'Explorer link is external and protected');
sql("UPDATE crypto_deposit_requests SET status='pending' WHERE id=112");$pendingDetail=req(1,false,[],true,'payments')['html'];ck(str_contains($pendingDetail,'admin-payment-accept-cta')&&str_contains($pendingDetail,'bi bi-chevron-double-right')&&str_contains($pendingDetail,'data-admin-crypto-accept-trigger'),'Pending detail retains crypto acceptance modal with animated right chevron');ck(!str_contains($pendingDetail,'admin-payment-archive-cta'),'Unpaid payment has no withdrawal archive prompt');sql("UPDATE crypto_deposit_requests SET status='approved' WHERE id=112");
$detail=req(1,false,[],true,'payments')['html'];ck(!str_contains($detail,'admin-payment-accept-cta'),'Approved payment stops acceptance animation');ck(str_contains($detail,'admin-payment-archive-cta')&&str_contains($detail,'Przenieś do Archiwum')&&str_contains($detail,'bi bi-chevron-double-right'),'Approved crypto detail displays archive CTA with right chevron');
$archive=['_csrf'=>'invalid','payment_type'=>'crypto','payment_id'=>'112','admin_payment_quick_action'=>'1','quick_action'=>'archive','payment_scope'=>'archived'];
req(1,false,$archive,false,'payments');ck($db->select_user('SELECT status FROM crypto_deposit_requests WHERE id=112')['status']==='approved','Archive form rejects invalid CSRF');
$archive['_csrf']='fixture';$beforeArchive=$db->select_user('SELECT received_crypto_amount,received_fiat_amount FROM crypto_deposit_requests WHERE id=112');
ck(req(1,false,$archive,false,'payments')['status']===302,'Archive form submits through actual Payments handler');
ck(admin_crypto_quick_rows($db,'payout')===[],'Archived payment disappears from To withdraw');ck(!str_contains(req(1,false,[],true,'payments')['html'],'admin-payment-archive-cta'),'Archived payment detail has no pulsing archive CTA');
ck($db->select_user('SELECT received_crypto_amount,received_fiat_amount FROM crypto_deposit_requests WHERE id=112')===$beforeArchive,'Archiving preserves received amounts');
sql("UPDATE crypto_deposit_requests SET status='approved' WHERE id=112");
foreach([116=>'expired',117=>'pending',118=>'approved',119=>'pending',120=>'expired'] as $id=>$state) {
 sql("INSERT INTO crypto_deposit_requests(id,customer_id,crypto_asset_id,wallet_address_id,requested_fiat_amount,fiat_currency_id,exchange_rate,requested_crypto_amount,status,requested_at,expires_at) VALUES({$id},1,{$btcAsset},{$id},29,{$eur},50000,0.00058,'{$state}',NOW(),".($id===119?'DATE_ADD(NOW(),INTERVAL 1 HOUR)':'DATE_SUB(NOW(),INTERVAL 1 HOUR)').")");
}
sql("INSERT INTO customer_balance_runtime_events(customer_id,source_type,source_key,direction,amount) VALUES(1,'payment_approval','crypto:120','credit',29)");
$deleteForm=['_csrf'=>'invalid','payment_type'=>'crypto','payment_id'=>'116','admin_payment_quick_action'=>'1','quick_action'=>'delete','payment_scope'=>'expired'];
req(1,false,$deleteForm,false,'payments');ck((bool)$db->select_user('SELECT id FROM crypto_deposit_requests WHERE id=116'),'Expired delete rejects invalid CSRF');
$deleteForm['_csrf']='fixture';
foreach([116,117,118,119,120] as $id) {
 $deleteForm['payment_id']=(string)$id;req(1,false,$deleteForm,false,'payments');
 ck((bool)$db->select_user('SELECT id FROM crypto_deposit_requests WHERE id='.$id)===!in_array($id,[116,117],true),'Expired/elapsed delete succeeds; approved, unexpired and credited records survive');
}
sql("DELETE FROM customer_balance_runtime_events WHERE source_type='payment_approval' AND source_key='crypto:120'");sql('DELETE FROM crypto_deposit_requests WHERE id IN (118,119,120)');
ck(str_contains(admin_crypto_quick_labels('pl')['expired_help'],'przenieś płatność do anulowanych'),'Expired hint explains cancel/wait/delete choices');
if(getenv('APP_UI_EXPORT')==='1'){sql("UPDATE app_settings SET crypto_auto_accept_enabled=0 WHERE id=1");sql("UPDATE customers SET public_handle='demo1' WHERE id=1");file_put_contents('/tmp/subme-crypto-quick-ui.html',req(1,false,[],true)['html']);file_put_contents('/tmp/subme-crypto-payment-detail-ui.html',req(1,false,[],true,'payments')['html']);sql("UPDATE crypto_deposit_requests SET status='pending' WHERE id=112");file_put_contents('/tmp/subme-crypto-payment-pending-ui.html',req(1,false,[],true,'payments')['html']);sql("UPDATE crypto_deposit_requests SET status='approved' WHERE id=112");$_SESSION['admin_locale']='pl';$responses=[];foreach(['new','expired','cancelled','payout'] as $f)$responses[$f]=['ok'=>true,'filter'=>$f,'counts'=>admin_crypto_quick_counts($db),'html'=>admin_crypto_quick_html(admin_crypto_quick_rows($db,$f),$f,admin_crypto_quick_labels('pl'),'fixture')];file_put_contents('/tmp/subme-crypto-quick-responses.json',json_encode($responses));}
echo "PASS {$n} administrator identity, mode, role, password and request authorization assertions\n";
