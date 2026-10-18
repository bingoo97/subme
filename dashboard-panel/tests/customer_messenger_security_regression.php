<?php
/** Exercise the actual customer endpoint using disposable accounts and a separate database. */
declare(strict_types=1);
if (strpos((string)getenv('DB_NAME'), '_test_') === false) throw new RuntimeException('Separate _test_ database required.');
if (($argv[1] ?? '') === '--endpoint') {
    $request=json_decode(base64_decode($argv[2]),true);
    session_start();
    $_SESSION=['id'=>$request['customer'] ?? 1,'__csrf_frontend'=>'fixture-csrf','lang'=>'en'];
    $_SERVER['REQUEST_METHOD']=$request['method'] ?? 'GET';
    $_SERVER['HTTP_HOST']='localhost';
    $_SERVER['REQUEST_URI']='/check_chat.php';
    $_GET=array_merge(['format'=>'json'],$request['get'] ?? []);
    $_POST=$request['post'] ?? [];
    chdir(dirname(__DIR__));
    ob_start();
    register_shutdown_function(static function(): void {
        $out=ob_get_clean();$closed=session_status()!==PHP_SESSION_ACTIVE;
        if($closed && session_id()!=='')session_start();
        if(session_status() === PHP_SESSION_ACTIVE) session_destroy();
        echo json_encode(['session_closed'=>$closed,'status'=>http_response_code() ?: 200,'body'=>json_decode((string)$out,true),'raw'=>json_decode((string)$out,true)===null?$out:null]);
    });
    require dirname(__DIR__).'/check_chat.php'; exit;
}
require_once dirname(__DIR__).'/admin/bootstrap.php';
$db=Mysql_ks::get_instance();$checks=0;
function ck(bool $ok,string $label): void {global $checks;if(!$ok)throw new RuntimeException($label);$checks++;}
function sql(string $q): void {global $db;if(!$db->query($q))throw new RuntimeException($db->error.': '.$q);}
function endpoint(array $r): array {
    $p=proc_open([PHP_BINARY,__FILE__,'--endpoint',base64_encode(json_encode($r))],[1=>['pipe','w'],2=>['pipe','w']],$pipes);
    $out=stream_get_contents($pipes[1]);$err=stream_get_contents($pipes[2]);foreach($pipes as $pipe)fclose($pipe);
    $code=proc_close($p);$result=json_decode($out,true);
    if($code || !is_array($result) || $result['raw']!==null)throw new RuntimeException('Endpoint failed: '.$out.$err);
    return $result;
}
function post(string $a,int $c=1,array $extra=[]): array {return endpoint(['method'=>'POST','post'=>array_merge(['action'=>$a,'conversation_id'=>$c,'format'=>'json','_csrf'=>'fixture-csrf'],$extra)]);}
function messages(): int {global $db;return (int)$db->select_user('SELECT COUNT(*) n FROM support_messages')['n'];}
chat_ensure_message_interactions_runtime($db);admin_app_settings($db);
sql('SET FOREIGN_KEY_CHECKS=0');
sql("INSERT INTO admin_users(id,role_id,login_name,email,password_hash) VALUES(1,1,'fixture-admin','admin@example.invalid','unused')");
sql("INSERT INTO customers(id,email,password_hash,status,locale_code) VALUES(1,'one@example.invalid','unused','active','en'),(2,'two@example.invalid','unused','active','en')");
sql("INSERT INTO support_conversations(id,conversation_type,customer_id) VALUES(1,'live_chat',1),(2,'live_chat',2),(3,'group_chat',NULL),(4,'group_chat',NULL)");
sql("INSERT INTO support_conversation_members(conversation_id,participant_key,participant_type,customer_id,invite_status) VALUES(3,'customer:1','customer',1,'accepted'),(4,'customer:1','customer',1,'pending')");
sql("INSERT IGNORE INTO app_settings(id) VALUES(1)");
sql("UPDATE app_settings SET maintenance_mode=0,support_chat_enabled=1,customer_messenger_enabled=1,customer_global_group_enabled=0,demo_messenger_showcase_enabled=0,site_url='http://example.invalid'");
foreach(['send','read','upload','voice_upload','delete_message','toggle_reaction','create_group','invite_to_group','respond_group_invite','leave_group','remove_group','delete_group','set_group_email_notifications','set_group_retention','participant_profile','start_direct_chat','validate_group_email','link_preview'] as $a) {
    $r=endpoint(['get'=>['action'=>$a,'_csrf'=>'fixture-csrf']]);ck($r['status']===405,'GET rejected: '.$a.' '.json_encode($r));
    ck(empty(endpoint(['method'=>'POST','post'=>['action'=>$a,'format'=>'json']])['body']['ok']),'CSRF required: '.$a);
}
ck(messages()===0,'Rejected requests leave messages unchanged');
ck(empty(endpoint(['customer'=>0])['body']['ok']),'Anonymous denied');
$preview=post('link_preview',1,['url'=>'']);
ck(!empty($preview['body']['ok']) && $preview['session_closed'],'Preview releases the customer session before external I/O');
foreach([2,999] as $c) {
    $r=endpoint(['get'=>['action'=>'fetch','conversation_id'=>$c]]);ck($r['status']===403,'Foreign, pending or missing conversation hidden: '.$c.' '.json_encode($r));
    ck(post('send',$c,['tresc'=>'private'])['status']===403,'No unintended fallback from inaccessible conversation');
}
ck(empty(post('send',4,['tresc'=>'pending blocked'])['body']['ok']) && messages()===0,'Pending invite cannot send');
foreach(['   ',str_repeat('x',40001)] as $text) ck(post('send',1,['tresc'=>$text])['status']===422,'Empty and oversized rejected');
ck(post('upload')['status']===422,'Missing image rejected');ck(post('voice_upload')['status']===422,'Missing voice rejected');
$r=post('send',1,['tresc'=>'<script>alert(1)</script> fixture']);
ck(!empty($r['body']['ok']) && messages()===1,'Support message sent exactly once');
$body=json_encode($r['body']);ck(strpos($body,'<script>')===false && strpos($body,'alert(1) fixture')!==false,'Message HTML sanitized');
$r=post('send',3,['tresc'=>'group fixture']);ck(!empty($r['body']['ok']) && messages()===2,'Accepted group member sends');
$id=(int)$db->select_user('SELECT MAX(id) id FROM support_messages')['id'];
ck(empty(endpoint(['customer'=>2,'method'=>'POST','post'=>['action'=>'delete_message','message_id'=>$id,'conversation_id'=>2,'format'=>'json','_csrf'=>'fixture-csrf']])['body']['ok']) && messages()===2,'Cannot delete another customer message');
sql("ALTER TABLE support_messages ADD CONSTRAINT fixture_fail CHECK (message_body <> 'must fail')");
$r=post('send',1,['tresc'=>'must fail']);ck($r['status']===500 && empty($r['body']['ok']) && messages()===2,'Database failure reported instead of false delivery');
sql('ALTER TABLE support_messages DROP CHECK fixture_fail');
ck(!empty(post('send',1,['tresc'=>'retry'])['body']['ok']) && messages()===3,'Explicit retry succeeds');
sql('UPDATE app_settings SET customer_messenger_enabled=0');
ck(post('send',3,['tresc'=>'disabled group'])['status']===403 && messages()===3,'Disabling Messenger cannot redirect a group message to Support');
echo 'PASS '.$checks." customer Messenger security and delivery assertions\n";
