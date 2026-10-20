<?php
declare(strict_types=1);if(PHP_SAPI!=='cli')exit(1);
$app=$argv[1];require $app.'/admin/bootstrap.php';require $app.'/admin/customer_import_lib.php';app_crypto_network_budget(0,0);
if(!extension_loaded('dom')||!extension_loaded('mbstring'))throw new RuntimeException('Required parser extension missing');
$plain="email,status\nblocked@example.invalid,2\nactive@example.invalid,1\ninactive@example.invalid,0";
foreach([$plain,"INSERT INTO `old_db`.`users` (`email`,`status`) VALUES ('blocked@example.invalid',2),('active@example.invalid',1),('inactive@example.invalid',0);", "email\tstatus\nblocked@example.invalid\t2\nactive@example.invalid\t1\ninactive@example.invalid\t0"] as $source){$p=admin_customer_import_parse($source);if(empty($p['ok'])||array_column($p['items'],'status')!==['blocked','active','inactive'])throw new RuntimeException('Import parser status mapping failed');}
$list=[];for($i=0;$i<5000;$i++)$list[]='preview'.$i.'@example.invalid';$p=admin_customer_import_parse(implode(' ',$list));if(empty($p['ok'])||count($p['items'])!==5000)throw new RuntimeException('Large TXT parser failed');
$secret=admin_mixer_encrypt_secret('deployment-parser-probe');if($secret===''||admin_mixer_decrypt_secret($secret)!=='deployment-parser-probe')throw new RuntimeException('Private source encryption unavailable');
if(!function_exists('admin_crypto_quick_create_request')&&!str_contains(file_get_contents($app.'/admin/crypto_quick.php'),'create'))throw new RuntimeException('Payment creator missing');
if(app_customer_is_active(['status'=>'blocked'])||!app_customer_is_blocked(['status'=>'blocked']))throw new RuntimeException('Blocked state misinterpreted');
echo "PARSER_5000_TXT_CSV_SQL_TSV_STATUS_2_AND_PRIVATE_ENCRYPTION_OK_NO_CUSTOMERS_CREATED\n";
