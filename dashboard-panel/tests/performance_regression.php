<?php
/** Disposable database only. Query budgets and concurrency, with no external calls. */
declare(strict_types=1);
if (strpos((string)getenv('DB_NAME'), '_test_')===false) throw new RuntimeException('Separate test database required.');
require_once dirname(__DIR__).'/admin/bootstrap.php';
$db=Mysql_ks::get_instance();
if (($argv[1]??'')==='--maintenance') {
    app_run_read_maintenance_once($db,'concurrency-test',60,static function()use($db):void {
        $db->query("UPDATE customers SET balance_amount=balance_amount+1 WHERE id=1");
        usleep(100000);
    });exit;
}
if (($argv[1]??'')==='--asset-seed') {
    $before=Mysql_ks::$licznik;admin_ensure_core_crypto_assets($db);
    echo Mysql_ks::$licznik-$before;exit;
}
if (($argv[1]??'')==='--cycle-busy') {
    $result=app_run_maintenance_cycle($db);
    if (empty($result['ok']) || empty($result['busy']) || $result['steps']!==[]) exit(2);
    echo "Skipped overlapping cycle";exit;
}
$checks=0;
function ck(bool $v,string $m):void {global $checks;if(!$v)throw new RuntimeException($m);$checks++;}
function sql(string $q):void {global $db;if(!$db->query($q))throw new RuntimeException($db->error.': '.$q);}
app_ensure_customer_runtime_columns($db);chat_ensure_group_chat_runtime($db);app_fetch_settings($db);
sql('SET FOREIGN_KEY_CHECKS=0');sql('INSERT IGNORE INTO app_settings(id) VALUES(1)');
sql("UPDATE app_settings SET support_chat_enabled=1,customer_messenger_enabled=1,customer_global_group_enabled=1,site_url='http://example.invalid'");
sql("INSERT INTO admin_users(id,role_id,login_name,email,password_hash) VALUES(1,1,'perf-fixture','perf@example.invalid','unused')");
for($i=1;$i<=200;$i++)sql("INSERT INTO customers(id,email,public_handle,password_hash,status,locale_code,last_login_at,last_seen_at) VALUES({$i},'perf{$i}@example.invalid','perf{$i}','unused','active','en',".($i<=5?'NOW(),NOW()':"DATE_SUB(NOW(),INTERVAL 2 DAY),DATE_SUB(NOW(),INTERVAL 2 DAY)").")");
$settings=app_fetch_settings($db);$c=chat_sync_global_group_members($db,$settings);$id=(int)$c['id'];
$before=Mysql_ks::$licznik;$start=microtime(true);chat_sync_global_group_members($db,$settings);$queries=Mysql_ks::$licznik-$before;
echo 'Global chat / 200 customers: '.$queries.' queries, '.round((microtime(true)-$start)*1000,2)." ms\n";
if (($argv[1]??'')==='--baseline')exit;
ck($queries<=30,'Stable global-group sync has a constant query budget');
$oldJoin=$db->select_user("SELECT joined_at FROM support_conversation_members WHERE conversation_id={$id} AND customer_id=1")['joined_at'];
sql("UPDATE support_conversation_members SET joined_at=DATE_SUB(NOW(),INTERVAL 1 DAY),global_chat_blocked=1,can_post=0 WHERE conversation_id={$id} AND customer_id=1");
$oldJoin=$db->select_user("SELECT joined_at FROM support_conversation_members WHERE conversation_id={$id} AND customer_id=1")['joined_at'];
chat_sync_global_group_members($db,$settings);
$r=$db->select_user("SELECT * FROM support_conversation_members WHERE conversation_id={$id} AND customer_id=1");
ck($r['joined_at']===$oldJoin && (int)$r['global_chat_blocked']===1 && (int)$r['can_post']===0,'Stable membership preserves join time and moderation block');
sql("UPDATE customers SET status='blocked' WHERE id=2");chat_sync_global_group_members($db,$settings);
ck($db->select_user("SELECT invite_status FROM support_conversation_members WHERE conversation_id={$id} AND customer_id=2")['invite_status']==='left','Blocked account loses membership immediately');
sql("UPDATE customers SET status='active' WHERE id=2");chat_sync_global_group_members($db,$settings);
ck($db->select_user("SELECT invite_status FROM support_conversation_members WHERE conversation_id={$id} AND customer_id=2")['invite_status']==='accepted','Reactivated account rejoins immediately');
$noticeCount=(int)$db->select_user("SELECT COUNT(*) n FROM support_messages WHERE conversation_id={$id} AND message_body LIKE '[system_notice]%dołączył do nas!'")['n'];
chat_sync_global_group_members($db,$settings);
ck((int)$db->select_user("SELECT COUNT(*) n FROM support_messages WHERE conversation_id={$id} AND message_body LIKE '[system_notice]%dołączył do nas!'")['n']===$noticeCount,'Repeated sync does not duplicate join notices');
$_SESSION=[];$start=time()-300;
app_update_customer_login_state($db,200,date('Y-m-d H:i:s',$start),'127.0.0.1');
app_update_customer_login_state($db,200,date('Y-m-d H:i:s',$start+20),'127.0.0.1');
app_update_customer_login_state($db,200,date('Y-m-d H:i:s',$start+40),'127.0.0.1');
ck((int)$_SESSION['customer_last_seen_touch_ts']===$start,'Frequent polling cannot postpone the next database presence touch');
app_update_customer_login_state($db,200,date('Y-m-d H:i:s',$start+65),'127.0.0.1');
ck($db->select_user('SELECT last_seen_at FROM customers WHERE id=200')['last_seen_at']===date('Y-m-d H:i:s',$start+65),'Presence refreshes after one minute despite continuous activity');
app_sync_builtin_email_templates($db,false);
$template=$db->select_user('SELECT id,template_key FROM email_templates ORDER BY id LIMIT 1');$templateId=(int)$template['id'];
sql("UPDATE email_templates SET subject='Custom subject' WHERE id={$templateId}");
sql("UPDATE email_template_translations SET subject='Custom translation' WHERE template_id={$templateId}");
$before=Mysql_ks::$licznik;app_sync_builtin_email_templates($db,false);$emailQueries=Mysql_ks::$licznik-$before;
ck($emailQueries===2,'Existing email templates and translations use two batch reads');
ck($db->select_user("SELECT subject FROM email_templates WHERE id={$templateId}")['subject']==='Custom subject','Default sync preserves edited email subject');
ck($db->select_user("SELECT subject FROM email_template_translations WHERE template_id={$templateId} LIMIT 1")['subject']==='Custom translation','Default sync preserves edited translations');
sql("DELETE FROM email_template_translations WHERE template_id={$templateId} AND locale_code='pl'");
app_sync_builtin_email_templates($db,false);
ck(!empty($db->select_user("SELECT id FROM email_template_translations WHERE template_id={$templateId} AND locale_code='pl'")),'Missing translation is restored without overwriting existing languages');
app_sync_builtin_email_templates($db,true);
ck($db->select_user("SELECT subject FROM email_templates WHERE id={$templateId}")['subject']===app_email_builtin_templates()[$template['template_key']]['subject'],'Explicit default reset still overwrites templates');
echo "Email template sync: {$emailQueries} queries
";
$children=[];for($i=0;$i<6;$i++){$p=proc_open([PHP_BINARY,__FILE__,'--maintenance'],[1=>['pipe','w'],2=>['pipe','w']],$pipes);$children[]=[$p,$pipes];}
foreach($children as [$p,$pipes]){$out=stream_get_contents($pipes[1]);$err=stream_get_contents($pipes[2]);foreach($pipes as $pipe)fclose($pipe);ck(proc_close($p)===0,'Concurrent cleanup completes without waiting: '.$out.$err);}
ck((float)$db->select_user('SELECT balance_amount FROM customers WHERE id=1')['balance_amount']===1.0,'Concurrent read cleanup runs once across workers');
$ran=false;ck(!app_run_read_maintenance_once($db,'concurrency-test',60,static function()use(&$ran):void{$ran=true;})&&!$ran,'Another request skips recent cleanup');
$before=Mysql_ks::$licznik;chat_purge_expired_messages($db,168);$first=Mysql_ks::$licznik-$before;
$before=Mysql_ks::$licznik;chat_purge_expired_messages($db,168);$second=Mysql_ks::$licznik-$before;
ck($second===0,'Duplicate retention cleanup performs zero SQL');echo "Retention: first {$first} / repeated {$second} queries\n";
try {app_run_read_maintenance_once($db,'failure-test',60,static function():void{throw new RuntimeException('fixture');});}catch(RuntimeException $e){}
ck(app_run_read_maintenance_once($db,'failure-test',60,static function():void{}),'A failed cleanup remains retryable and releases its lock');
admin_ensure_core_crypto_assets($db);
sql("UPDATE crypto_assets SET name='Custom Bitcoin',is_active=0,rate_currency_code='EUR' WHERE code='BTC'");
$p=proc_open([PHP_BINARY,__FILE__,'--asset-seed'],[1=>['pipe','w'],2=>['pipe','w']],$pipes);
$out=stream_get_contents($pipes[1]);$err=stream_get_contents($pipes[2]);foreach($pipes as $pipe)fclose($pipe);
ck(proc_close($p)===0 && (int)$out<=2,'Configured core assets use one batch read plus schema check instead of nine upserts: '.$err);
$asset=$db->select_user("SELECT name,is_active,rate_currency_code FROM crypto_assets WHERE code='BTC'");
ck($asset['name']==='Custom Bitcoin' && (int)$asset['is_active']===0 && $asset['rate_currency_code']==='EUR','Asset seed preserves configured names, activation and quote currency');
// An inbox must bound expensive group summaries before hydrating them.
for($i=0;$i<25;$i++) {
    sql("INSERT INTO support_conversations(conversation_type,group_name,subject,status) VALUES('group_chat','Performance fixture','Fixture','open')");
    $groupId=(int)$db->id();
    chat_add_group_member($db,$groupId,['participant_key'=>chat_participant_key_for_admin(1),'participant_type'=>'admin','customer_id'=>0,'admin_user_id'=>1],'accepted','member',0,1);
}
$allGroups=chat_admin_group_conversation_rows($db,1);
$limitedGroups=chat_admin_group_conversation_rows($db,1,3);
ck(count($allGroups)>=26 && count($limitedGroups)===3,'Inbox queries only the requested number of groups');
ck((int)$limitedGroups[0]['id']===$id,'Pinned global group remains first in bounded inbox');
$withoutGlobal=chat_admin_group_conversation_rows($db,1,3,false);
ck(count($withoutGlobal)===3 && !in_array('global_group',array_column($withoutGlobal,'conversation_type'),true),'Disabled global group does not consume a visible inbox slot');

// Large archived history must not be counted for a sidebar open badge.
$values=[];
foreach(array_merge(['pending','awaiting_review','awaiting_confirmation'],array_fill(0,3000,'archived')) as $i=>$status) {
    $values[]="(1,1,".($i+1).",10,1,100,0.1,'{$status}')";
}
sql("INSERT INTO crypto_deposit_requests(customer_id,crypto_asset_id,wallet_address_id,requested_fiat_amount,fiat_currency_id,exchange_rate,requested_crypto_amount,status) VALUES".implode(',',$values));
$old=admin_payment_summary($db,0,true)['open_total'];
ck(admin_payment_open_count($db,true)===$old,'Lightweight sidebar count matches full summary including legacy requests');
ck(admin_payment_open_count($db,false)===admin_payment_summary($db,0,false)['open_total'],'Disabled bank payments retain matching count');
$plan=$db->select_user("EXPLAIN SELECT COUNT(*) FROM crypto_deposit_requests WHERE status IN ('pending','awaiting_review','awaiting_confirmation')");
ck(in_array($plan['type'],['range','ref'],true) && (int)$plan['rows']<100,'Sidebar count uses selective index instead of archived history');
echo 'Open count: '.(int)$old.'; index estimate '.(int)$plan['rows']." rows / 3003 fixture requests\n";

// Hold the actual database lock in one connection and attempt a second cycle.
sql("SELECT GET_LOCK(CONCAT('subme-maint:', LEFT(SHA2(DATABASE(),256),40)),0)");
try {
    $p=proc_open([PHP_BINARY,__FILE__,'--cycle-busy'],[1=>['pipe','w'],2=>['pipe','w']],$pipes);
    $out=stream_get_contents($pipes[1]);$err=stream_get_contents($pipes[2]);foreach($pipes as $pipe)fclose($pipe);
    ck(proc_close($p)===0 && str_contains($out,'Skipped overlapping cycle'),'Overlapping maintenance skips without executing jobs: '.$err);
} finally { sql("SELECT RELEASE_LOCK(CONCAT('subme-maint:', LEFT(SHA2(DATABASE(),256),40)))"); }
echo "PASS {$checks} performance and cleanup assertions\n";
