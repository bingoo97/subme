<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') exit(1);
[$self,$action,$app,$backup]=$argv;
require $app.'/config/mysql.php';
$db=Mysql_ks::get_instance();
function provider_deploy_settings(Mysql_ks $db): array {
    $row=$db->select_user('SELECT * FROM app_settings WHERE id=1 LIMIT 1');
    if(!is_array($row)||!isset($row['id'])||$db->error)throw new RuntimeException('Cannot read target settings');
    return $row;
}
function provider_deploy_hash(array $settings): string {
    unset($settings['provider_remote_login_enabled'],$settings['provider_remote_subscriptions_enabled']);
    ksort($settings);return hash('sha256',json_encode($settings,JSON_THROW_ON_ERROR));
}
function provider_deploy_config_count(Mysql_ks $db): int {
    $exists=$db->select_user("SELECT COUNT(*) AS n FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name='provider_remote_login_configs'");
    return (int)$exists['n']?(int)$db->select_user('SELECT COUNT(*) AS n FROM provider_remote_login_configs')['n']:0;
}
$settings=provider_deploy_settings($db);
if($action==='snapshot'){
    $snapshot=['settings_hash'=>provider_deploy_hash($settings),'config_count'=>provider_deploy_config_count($db),
        'previous_flags'=>array_intersect_key($settings,array_flip(['provider_remote_login_enabled','provider_remote_subscriptions_enabled']))];
    file_put_contents($backup.'/provider-state-before.json',json_encode($snapshot,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR));
    file_put_contents($backup.'/settings-schema-before.json',json_encode($db->select_user('SHOW CREATE TABLE app_settings'),JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR));
    echo "TARGET_SETTINGS_SNAPSHOTTED\n";
}elseif($action==='migrate'){
    $stage=$argv[4];
    $sql=file_get_contents($stage.'/dashboard-panel/migrations/040_provider_remote_login.sql');
    $sql=preg_replace('/^\s*--[^\n]*$/m','',$sql);
    foreach(preg_split('/;\s*(?:\r?\n|$)/',$sql) as $statement){
        if(trim($statement)==='')continue;
        $result=$db->query($statement);
        if($result===false)throw new RuntimeException('Provider schema migration failed');
        if($result instanceof mysqli_result)$result->free();
    }
    // Only these two explicitly requested preferences change. Preserve any automatic settings timestamp.
    $timestamp=array_key_exists('updated_at',$settings)?',updated_at=updated_at':'';
    if(!$db->query('UPDATE app_settings SET provider_remote_login_enabled=0,provider_remote_subscriptions_enabled=0'.$timestamp.' WHERE id=1'))throw new RuntimeException('Cannot disable provider module');
    echo "PROVIDER_SCHEMA_READY_BOTH_SWITCHES_OFF\n";
}elseif($action==='verify'){
    $before=json_decode(file_get_contents($backup.'/provider-state-before.json'),true,16,JSON_THROW_ON_ERROR);
    if(!hash_equals($before['settings_hash'],provider_deploy_hash($settings)))throw new RuntimeException('Unrelated target settings changed');
    if(provider_deploy_config_count($db)!==$before['config_count'])throw new RuntimeException('Provider credentials table changed unexpectedly');
    foreach(['provider_remote_login_enabled','provider_remote_subscriptions_enabled'] as $flag){
        if(!array_key_exists($flag,$settings)||(int)$settings[$flag]!==0)throw new RuntimeException('Provider switch is not OFF');
        $column=$db->select_user("SHOW COLUMNS FROM app_settings LIKE '".$flag."'");
        if((int)($column['Default']??-1)!==0)throw new RuntimeException('Provider switch schema default is not OFF');
    }
    echo "PROVIDER_OFF_OTHER_SETTINGS_AND_CREDENTIALS_UNCHANGED\n";
}else throw new RuntimeException('Unknown deployment action');
