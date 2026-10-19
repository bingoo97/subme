<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit(1);
[$self,$action,$site,$stage]=$argv;
if(!in_array($site,['demo','dashboard'],true))throw new RuntimeException('Unexpected site');
$home=getenv('HOME');$app="$home/.subme-apps/$site-subme-pro/dashboard-panel";$web="$home/$site.subme.pro";
$backup="$home/.subme-deploy-backups/mobile-navigation-20261019-01/$site";
require $app.'/config/mysql.php';$db=Mysql_ks::get_instance();
function row(Mysql_ks $db,string $sql):array {$r=$db->select_user($sql);if($db->error)throw new RuntimeException('Target query failed');return $r??[];}
function settingsHash(array $r):string {ksort($r);return hash('sha256',json_encode($r,JSON_THROW_ON_ERROR));}
function providersHash(Mysql_ks $db):string {$r=$db->select_full_user('SELECT * FROM provider_remote_login_configs ORDER BY provider_id');if($db->error)throw new RuntimeException('Provider snapshot failed');return hash('sha256',json_encode($r,JSON_THROW_ON_ERROR));}
function panelHash(Mysql_ks $db):string {$r=row($db,'SELECT * FROM panel_subscription_config WHERE id=1');unset($r['last_scan_at'],$r['usage_synced_at']);ksort($r);return hash('sha256',json_encode($r,JSON_THROW_ON_ERROR));}
$identity=(string)row($db,'SELECT SHA2(DATABASE(),256) AS identity')['identity'];
$settings=row($db,'SELECT * FROM app_settings WHERE id=1');
if($action==='preflight'){
 $panel=row($db,"SELECT COUNT(*) n FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='panel_subscription_config'");
 echo json_encode(['site'=>$site,'db_identity'=>$identity,'panel_tables_present'=>(bool)$panel['n'],'provider_login'=>(int)($settings['provider_remote_login_enabled']??-1),'provider_subscriptions'=>(int)($settings['provider_remote_subscriptions_enabled']??-1),'status_column'=>row($db,"SHOW COLUMNS FROM crypto_deposit_requests LIKE 'status'")['Type']]),PHP_EOL;
}elseif($action==='snapshot'){
 if(!is_dir($backup)&&!mkdir($backup,0700,true))throw new RuntimeException('Backup directory failed');
 if(file_exists($backup.'/database.sql.gz'))throw new RuntimeException('Backup already exists');
 $property=new ReflectionProperty($db,'db');$conn=$property->getValue($db);
 $conn->query('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');$conn->query('START TRANSACTION WITH CONSISTENT SNAPSHOT');
 $out=gzopen($backup.'/database.sql.gz','wb6');if(!$out)throw new RuntimeException('Cannot create backup');
 gzwrite($out,"SET FOREIGN_KEY_CHECKS=0;\n");$tables=0;$rows=0;
 try{
  $tableRows=$db->select_full_user("SHOW FULL TABLES WHERE Table_type='BASE TABLE'");
  foreach($tableRows as $t){$name=(string)array_values($t)[0];$quoted='`'.str_replace('`','``',$name).'`';
   $definition=row($db,'SHOW CREATE TABLE '.$quoted);gzwrite($out,'DROP TABLE IF EXISTS '.$quoted.";\n".array_values($definition)[1].";\n");
   $columns=$db->select_full_user('SHOW COLUMNS FROM '.$quoted);$fields=[];foreach($columns as $c)if(!str_contains((string)$c['Extra'],'GENERATED'))$fields[]='`'.str_replace('`','``',(string)$c['Field']).'`';
   $result=$conn->query('SELECT '.implode(',',$fields).' FROM '.$quoted,MYSQLI_USE_RESULT);if(!$result)throw new RuntimeException('Backup read failed');
   while($values=$result->fetch_row()){$values=array_map(static fn($v)=>$v===null?'NULL':"'".$conn->real_escape_string((string)$v)."'",$values);if(gzwrite($out,'INSERT INTO '.$quoted.' ('.implode(',',$fields).') VALUES ('.implode(',',$values).");\n")===false)throw new RuntimeException('Backup write failed');$rows++;}
   $result->free();$tables++;
  }
  gzwrite($out,"SET FOREIGN_KEY_CHECKS=1;\n");
 }finally{gzclose($out);$conn->query('ROLLBACK');}
 chmod($backup.'/database.sql.gz',0600);
 file_put_contents($backup.'/state-before.json',json_encode(['identity'=>$identity,'settings_hash'=>settingsHash($settings),'provider_hash'=>providersHash($db),'panel_hash'=>panelHash($db)],JSON_THROW_ON_ERROR));
 echo json_encode(['site'=>$site,'backup_tables'=>$tables,'backup_rows'=>$rows,'backup_bytes'=>filesize($backup.'/database.sql.gz'),'db_identity'=>$identity]),PHP_EOL;
}elseif($action==='verify'){
 $before=json_decode(file_get_contents($backup.'/state-before.json'),true,32,JSON_THROW_ON_ERROR);
 if($identity!==$before['identity']||settingsHash($settings)!==$before['settings_hash']||providersHash($db)!==$before['provider_hash']||panelHash($db)!==$before['panel_hash'])throw new RuntimeException('Protected database state changed');
 foreach(['provider_remote_login_enabled','provider_remote_subscriptions_enabled'] as $key)if((int)$settings[$key]!==0)throw new RuntimeException('Provider is not OFF');
 if(row($db,"SHOW COLUMNS FROM crypto_deposit_requests LIKE 'status'")['Type']!=='varchar(32)')throw new RuntimeException('Status migration failed');
 echo "DATABASE_IDENTITY_SETTINGS_PROVIDER_CONFIG_PRESERVED\n";
}else throw new RuntimeException('Invalid action');
