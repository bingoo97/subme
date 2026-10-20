<?php
declare(strict_types=1);
if(!str_contains((string)getenv('DB_NAME'),'_test_'))throw new RuntimeException('Disposable database required.');
require_once dirname(__DIR__).'/admin/bootstrap.php';require_once dirname(__DIR__).'/admin/customer_import_lib.php';
$db=Mysql_ks::get_instance();app_crypto_network_budget(0,0);$checks=0;
function bulkCheck(bool $ok,string $why):void{global $checks;if(!$ok)throw new RuntimeException($why);$checks++;}
function parseCheck(string $source,array $options=[]):array{$p=admin_customer_import_parse($source,$options);bulkCheck(!empty($p['ok']),'Parser: '.($p['message']??''));return $p;}
foreach(['status','state','account_status'] as $header)foreach(['auto','zero_active','one_active'] as $rule){$p=parseCheck("email,$header\nblocked@example.invalid,2",['numeric_status'=>$rule]);bulkCheck($p['items'][0]['status']==='blocked'&&$p['unknown_status']===0,'Explicit status 2 stays blocked for '.$header.'/'.$rule);}
foreach(["email,status\ndup@example.invalid,1\nDUP@example.invalid,2","email,status\ndup@example.invalid,2\ndup@example.invalid,1"] as $source){$p=parseCheck($source);bulkCheck(count($p['items'])===1&&$p['items'][0]['status']==='blocked'&&$p['duplicates']===1,'Blocked duplicate wins independent of order');}
$p=parseCheck("email\tpassword\tstatus\ntrailing@example.invalid\tsecret\t");bulkCheck(count($p['items'])===1,'Trailing empty TSV field is retained');
bulkCheck(!admin_customer_import_parse("email,password,status\nmissing@example.invalid,secret")['ok'],'Truncated columns cannot silently drop blocked status');
$p=parseCheck("email,password,status\ntext@example.invalid,INSERT INTO users,2");bulkCheck($p['items'][0]['password']==='INSERT INTO users'&&$p['items'][0]['status']==='blocked','SQL-like password in CSV is data');
$p=parseCheck("email,password,status\nquoted@example.invalid,\"first\nsecond,part\",2");bulkCheck($p['items'][0]['password_mode']==='generated'&&$p['items'][0]['status']==='blocked','Quoted multiline CSV does not shift status');
$p=parseCheck("INSERT INTO `old_db`.`users` (`email`,`status`) VALUES ('qualified@example.invalid',2);");bulkCheck($p['items'][0]['status']==='blocked','Qualified SQL table name accepted');
foreach(['\'unterminated','CONCAT(\'x\',\'y\')','SLEEP(10)','(SELECT 2)'] as $value)bulkCheck(!admin_customer_import_parse("INSERT INTO users (email,status) VALUES ('bad@example.invalid',$value);")['ok'],'SQL expressions/truncated strings rejected');
$p=parseCheck('<table><tr><th>Email</th><th>Status</th></tr><tr><td>first@example.invalid</td><td>2</td></tr></table><div><table><tr><td>other@example.invalid</td></tr></table></div>');bulkCheck(count($p['items'])===1&&$p['items'][0]['status']==='blocked','Only first HTML table is imported');
bulkCheck(!admin_customer_import_parse("email,status\n".str_repeat("max@example.invalid,2\n",10001))['ok'],'More than 10000 source rows rejected even with duplicates');
foreach(["\n",' ',",",';'] as $sep){$list=[];for($i=0;$i<5000;$i++)$list[]='plain'.$i.'@bulk.invalid';$p=parseCheck(implode($sep,$list));bulkCheck(count($p['items'])===5000&&$p['passwords']['generated']===5000,'5000 TXT addresses with separator '.json_encode($sep));}
$options=['locale_code'=>'pl','status'=>'active','customer_type'=>'client','visible_provider_ids'=>[1],'send_password_email'=>false];
$hash=password_hash('bulk-original-password',PASSWORD_BCRYPT,['cost'=>4]);$countsBefore=(int)$db->select_user('SELECT COUNT(*) n FROM customers')['n'];$queueBefore=(int)$db->select_user('SELECT COUNT(*) n FROM outbound_email_queue')['n'];
$formats=['csv'=>5000,'sql'=>5000,'tsv'=>5000,'html'=>5000,'console'=>500,'spaces'=>500,'txt'=>500];
$totalCreated=0;$totalBlocked=0;
foreach($formats as $format=>$size){
 $source=match($format){'csv'=>"id,email,password_hash,status,role_id,balance_amount,ip\r\n",'tsv'=>"id\temail\tpassword_hash\tstatus\trole_id\tbalance_amount\tip\n",'sql'=>"-- SQL table export\nCREATE TABLE ignored_fixture(id INT);\nINSERT INTO `old_db`.`users` (`id`,`email`,`password_hash`,`status`,`role_id`,`balance_amount`,`ip`) VALUES ",'html'=>'<table><thead><tr><th>id</th><th>email</th><th>password_hash</th><th>status</th><th>role_id</th><th>balance_amount</th><th>ip</th></tr></thead><tbody>','console'=>"+-----+----------------+----------------+--------+\n| id | email | password_hash | status |\n+-----+----------------+----------------+--------+\n",'spaces'=>"id  email  password_hash  status\n",default=>''};
 $blocked=0;
 for($i=0;$i<$size;$i++){$email=$format.$i.'@bulk.invalid';$status=$i%3;$blocked+=(int)($status===2);$fields=[$i,$email,$hash,$status,1,999999,'8.8.8.8'];
  $source.=match($format){'csv'=>implode(',',$fields)."\r\n",'tsv'=>implode("\t",$fields)."\n",'sql'=>'('.$i.", '$email', '$hash', $status, 1, 999999, '8.8.8.8')".($i===$size-1?';':',')."\n",'html'=>'<tr><td>'.implode('</td><td>',$fields).'</td></tr>','console'=>'| '.implode(' | ',array_slice($fields,0,4))." |\n",'spaces'=>implode('  ',array_slice($fields,0,4))."\n",default=>$email.($i%2?' ':"\n")};
 }
 if($format==='html')$source.='</tbody></table>';if($format==='sql')$source.="\nDROP TABLE customers;";
 $p=parseCheck($source);bulkCheck(count($p['items'])===$size,'Complete '.$format.' source recognized');
 $blocked=$format==='txt'?0:$blocked;bulkCheck(count(array_filter($p['items'],fn($item)=>$item['status']==='blocked'))===$blocked,'Blocked count parsed for '.$format);
 $start=microtime(true);$prepared=admin_customer_import_prepare($db,$source,$options,1);bulkCheck(!empty($prepared['can_start']),'Job starts '.$format);$batches=0;
 do{$state=admin_customer_import_batch($db,$prepared['job'],1,'127.0.0.1');bulkCheck(!empty($state['ok'])&&$state['processed']<=$size,'Batch progress '.$format);$batches++;}while(!$state['done']&&$batches<1000);
 bulkCheck($state['done']&&$state['created']===$size&&$state['failed']===0,'Full import '.$format.' completes');
 $rows=$db->select_full_user("SELECT * FROM customers WHERE email LIKE '".$db->escape($format)."%@bulk.invalid'");bulkCheck(count($rows)===$size,'Database count '.$format);
 foreach($rows as $row){bulkCheck($row['ip_address']===null&&(float)$row['balance_amount']===0.0,'Source cannot set IP/balance');if($row['status']==='blocked'){bulkCheck(!app_customer_is_active($row)&&app_customer_is_blocked($row),'Blocked is denied by login predicate');}if($format!=='txt')bulkCheck(app_verify_customer_password($row,'bulk-original-password'),'Preserved password authenticates');}
 $actual=(int)$db->select_user("SELECT COUNT(*) n FROM customers WHERE email LIKE '".$db->escape($format)."%@bulk.invalid' AND status='blocked'")['n'];bulkCheck($actual===$blocked,'Blocked accounts persisted '.$format);
 $again=admin_customer_import_prepare($db,$source,$options,1);bulkCheck($again['existing']===$size,'Repeated source recognizes existing '.$format);
 $totalCreated+=$size;$totalBlocked+=$blocked;echo strtoupper($format).": $size accounts, $blocked blocked; ".round(microtime(true)-$start,2)."s\n";
}
$blocked=app_find_customer_by_email($db,'sql2@bulk.invalid');app_activate_customer($db,(int)$blocked['id']);$after=app_find_customer_by_id($db,(int)$blocked['id']);bulkCheck(app_customer_is_blocked($after),'Old activation helper cannot unblock imported customer');
$inactive=app_find_customer_by_email($db,'sql0@bulk.invalid');app_activate_customer($db,(int)$inactive['id']);bulkCheck(app_customer_is_active(app_find_customer_by_id($db,(int)$inactive['id'])),'Normal inactive activation remains available');
bulkCheck((int)$db->select_user('SELECT COUNT(*) n FROM customers')['n']===$countsBefore+$totalCreated,'Exact total across all formats; SQL DROP never executes');
bulkCheck((int)$db->select_user('SELECT COUNT(*) n FROM outbound_email_queue')['n']===$queueBefore,'No real email queued or sent in bulk tests');
echo "PASS $checks bulk checks; $totalCreated imported accounts, $totalBlocked preserved blocks. Disposable database only.\n";
