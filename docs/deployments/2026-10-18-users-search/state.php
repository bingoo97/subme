<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') exit(1);
[$self,$action,$app,$backup]=$argv;
require $app.'/config/mysql.php';
$db=Mysql_ks::get_instance();
$flags=['provider_remote_login_enabled','provider_remote_subscriptions_enabled'];
function settings(Mysql_ks $db):array {
    $row=$db->select_user('SELECT * FROM app_settings WHERE id=1');
    if(!is_array($row)||$db->error)throw new RuntimeException('Cannot read target settings');
    return $row;
}
function fingerprint(array $row,array $keys):string {
    $values=array_intersect_key($row,array_flip($keys));ksort($values);
    return hash('sha256',json_encode($values,JSON_THROW_ON_ERROR));
}
function provider_fingerprint(Mysql_ks $db):string {
    $rows=$db->select_full_user('SELECT * FROM provider_remote_login_configs ORDER BY provider_id');
    if($db->error)throw new RuntimeException('Cannot verify provider configuration');
    return hash('sha256',json_encode($rows,JSON_THROW_ON_ERROR));
}
$row=settings($db);
$identity=hash('sha256',(string)$db->select_user('SELECT DATABASE() AS name')['name']);
if($action==='snapshot'){
    foreach($flags as $flag)if(!array_key_exists($flag,$row))throw new RuntimeException('Provider foundation is missing');
    $keys=array_values(array_diff(array_keys($row),$flags));
    $schema=[];foreach(['app_settings','outbound_email_queue'] as $table){
        $definition=$db->select_user('SHOW CREATE TABLE '.$table);
        if(!$definition||$db->error)throw new RuntimeException('Required target table is missing');
        $schema[$table]=$definition;
    }
    file_put_contents($backup.'/schema-before.json',json_encode($schema,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR));
    file_put_contents($backup.'/state-before.json',json_encode(['identity'=>$identity,'keys'=>$keys,'settings_hash'=>fingerprint($row,$keys),'provider_hash'=>provider_fingerprint($db)],JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR));
    echo json_encode(['snapshot_ok'=>true,'database_identity'=>$identity,'panel_paid_until_present'=>!empty($row['admin_panel_subscription_paid_until'])]),PHP_EOL;
}elseif($action==='disable'){
    $preserveTimestamp=array_key_exists('updated_at',$row)?',updated_at=updated_at':'';
    if(!$db->query('UPDATE app_settings SET provider_remote_login_enabled=0,provider_remote_subscriptions_enabled=0'.$preserveTimestamp.' WHERE id=1'))throw new RuntimeException('Cannot disable provider switches');
    echo "PROVIDER_SWITCHES_OFF_NO_MIGRATION\n";
}elseif($action==='verify'){
    $before=json_decode(file_get_contents($backup.'/state-before.json'),true,16,JSON_THROW_ON_ERROR);
    if(!hash_equals($before['identity'],$identity))throw new RuntimeException('Target database identity changed');
    if(!hash_equals($before['settings_hash'],fingerprint($row,$before['keys'])))throw new RuntimeException('Unrelated target settings changed');
    if(!hash_equals($before['provider_hash'],provider_fingerprint($db)))throw new RuntimeException('Provider credentials changed');
    foreach($flags as $flag){
        $column=$db->select_user("SHOW COLUMNS FROM app_settings LIKE '".$flag."'");
        if((int)($row[$flag]??-1)!==0||(int)($column['Default']??-1)!==0)throw new RuntimeException('Provider switch is not OFF/default OFF');
    }
    echo "TARGET_IDENTITY_SETTINGS_CREDENTIALS_PRESERVED_PROVIDER_OFF\n";
}else throw new RuntimeException('Unknown deployment action');
