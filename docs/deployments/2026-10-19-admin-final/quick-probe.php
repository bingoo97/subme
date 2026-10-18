<?php
declare(strict_types=1);
require $argv[1].'/admin/bootstrap.php';require $argv[1].'/admin/crypto_quick.php';
$db=Mysql_ks::get_instance();
foreach(['new','expired','cancelled','payout'] as $filter){
 try{$rows=admin_crypto_quick_rows($db,$filter);$html=admin_crypto_quick_html($rows,$filter,admin_crypto_quick_labels('pl'),'probe',admin_load_messages('pl'));echo json_encode(['filter'=>$filter,'rows'=>count($rows),'render_ok'=>true]),PHP_EOL;}
 catch(Throwable $e){echo get_class($e).': '.$e->getMessage().' at '.basename($e->getFile()).':'.$e->getLine(),PHP_EOL;exit(1);}
}
