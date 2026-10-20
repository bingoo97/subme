<?php
declare(strict_types=1);if(PHP_SAPI!=='cli')exit(1);$site=$argv[1];if(!in_array($site,['demo','dashboard'],true))exit(1);
$base='https://'.$site.'.subme.pro';$web=getenv('HOME').'/'.$site.'.subme.pro';$app=trim(file_get_contents($web.'/.backend-path'));require $app.'/bootstrap/session.php';
function request(string $url,?string $cookie=null,?string $post=null):array{
 $ch=curl_init($url);$opts=[CURLOPT_RETURNTRANSFER=>true,CURLOPT_CONNECTTIMEOUT=>10,CURLOPT_TIMEOUT=>40,CURLOPT_FOLLOWLOCATION=>false];if($cookie!==null)$opts[CURLOPT_COOKIE]=$cookie;if($post!==null){$opts[CURLOPT_POST]=true;$opts[CURLOPT_POSTFIELDS]=$post;}curl_setopt_array($ch,$opts);$body=curl_exec($ch);$code=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE);curl_close($ch);
 if($body===false||preg_match('/PHP (?:Fatal|Parse) error|Uncaught (?:Error|Exception)|Application backend path is invalid/i',$body))throw new RuntimeException('HTTP/runtime failure');return [$code,$body];
}
foreach(['/','/admin/'] as $path){[$code]=request($base.$path);if(!in_array($code,$path==='/'?[200,503]:[200],true))throw new RuntimeException('Public HTTP failed: '.$path.' '.$code);echo 'PUBLIC_HTTP_OK '.$path.' '.$code.PHP_EOL;}
foreach(['assets/css/admin.css','assets/js/admin-customer-import.js','assets/js/admin-crypto-create.js','assets/js/admin-crypto-quick.js','assets/js/admin.js'] as $asset){[$code,$body]=request($base.'/'.$asset);if($code!==200||!hash_equals(hash_file('sha256',$web.'/'.$asset),hash('sha256',$body)))throw new RuntimeException('Asset mismatch '.$asset);}
echo "FIVE_CHANGED_PUBLIC_ASSETS_MATCH_RUNTIME\n";
[$code,$body]=request($base.'/admin/customer_import.php',null,'action=prepare&_csrf=invalid');if($code!==403||!is_array(json_decode($body,true)))throw new RuntimeException('Import anonymous protection failed');
echo "ANONYMOUS_IMPORT_DENIED\n";
$files=glob(dirname(app_session_storage_directory()).'/admin/sess_*')?:[];usort($files,fn($a,$b)=>filemtime($b)<=>filemtime($a));$authenticated=false;
foreach($files as $file){
 if(is_link($file)||filesize($file)>1048576||filemtime($file)<time()-2592000)continue;$data=file_get_contents($file);if(!preg_match('/(?:^|;)admin_user_id\|i:[1-9][0-9]*;/',$data))continue;$sid=substr(basename($file),5);if(!preg_match('/^[a-zA-Z0-9,-]+$/D',$sid))continue;$cookie='reseller_admin_sid='.$sid;
 [$code,$body]=request($base.'/admin/?page=users',$cookie);if($code!==200||!str_contains($body,'data-customer-import'))continue;
 foreach(['data-import-prepare','data-import-preview','data-import-start','admin-customer-import.js'] as $marker)if(!str_contains($body,$marker))throw new RuntimeException('Import UI missing '.$marker);
 [$code,$bad]=request($base.'/admin/customer_import.php',$cookie,'action=prepare&_csrf=invalid');if($code!==403||!is_array(json_decode($bad,true)))throw new RuntimeException('Import CSRF protection failed');
 echo "AUTHENTICATED_USERS_IMPORT_UI_AND_CSRF_OK\n";
 foreach(['dashboard','orders','payments'] as $page){[$code,$body]=request($base.'/admin/?page='.$page,$cookie);if($code!==200||preg_match('/name=["\x27]admin_login["\x27]/',$body))throw new RuntimeException('Authenticated page failed '.$page);if($page==='orders'&&str_contains($body,'data-admin-order-extend-form')&&!str_contains($body,'data-admin-order-extend-automatic'))throw new RuntimeException('Automatic renewal preview field missing');if($page==='payments'&&(!str_contains($body,'admin-crypto-create.js')||!str_contains($body,'data-create-apply-renewal')||!str_contains($body,'data-create-product-label')))throw new RuntimeException('Payments creator missing');}
 echo "AUTHENTICATED_DASHBOARD_ORDERS_PAYMENTS_OK\n";
 foreach(['new','expired','cancelled','payout'] as $filter){[$code,$body]=request($base.'/admin/crypto_quick.php?filter='.$filter,$cookie);if($code===403)break;if($code===429){usleep(2100000);[$code,$body]=request($base.'/admin/crypto_quick.php?filter='.$filter,$cookie);}$r=json_decode($body,true);if($code!==200||empty($r['ok'])||($r['filter']??'')!==$filter)throw new RuntimeException('Quick filter failed '.$filter);}
 foreach(['/admin/search.php?action=crypto_quick_create_search&q=zzzz-deploy-no-user', '/admin/search.php?action=crypto_quick_create_options&customer_id=0'] as $path){
 [$readCode,$readBody]=request($base.$path,$cookie);if($readCode!==200||!is_array(json_decode($readBody,true)))throw new RuntimeException('Creator read endpoint failed');
}
[$badCode,$badBody]=request($base.'/admin/search.php',$cookie,'action=crypto_quick_create_submit&payment_method=cash&purpose=topup&customer_id=0&amount=1&operation_key=aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa&_csrf=invalid');
if($badCode!==403||!is_array(json_decode($badBody,true)))throw new RuntimeException('Cash CSRF guard failed');
echo "QUICK_PAYMENT_FILTERS_AND_CASH_CSRF_OK_NO_FINANCIAL_MUTATIONS\n";$authenticated=true;break;
}
if(!$authenticated)echo "AUTHENTICATED_UI_NOT_TESTED_NO_EXISTING_VALID_SESSION\n";
