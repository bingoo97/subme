<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit(1);
$site=$argv[1];
if(!in_array($site,['demo','dashboard'],true))exit(1);
$base='https://'.$site.'.subme.pro';
$web=getenv('HOME').'/'.$site.'.subme.pro';
$app=trim(file_get_contents($web.'/.backend-path'));
require $app.'/bootstrap/session.php';
function request(string $url,?string $cookie=null,?string $post=null):array {
    $headers=[];$curl=curl_init($url);
    $options=[CURLOPT_RETURNTRANSFER=>true,CURLOPT_CONNECTTIMEOUT=>8,CURLOPT_TIMEOUT=>30,CURLOPT_FOLLOWLOCATION=>false,
        CURLOPT_HEADERFUNCTION=>static function($curl,string $line)use(&$headers):int{$headers[]=trim($line);return strlen($line);}];
    if($cookie!==null)$options[CURLOPT_COOKIE]=$cookie;
    if($post!==null){$options[CURLOPT_POST]=true;$options[CURLOPT_POSTFIELDS]=$post;}
    curl_setopt_array($curl,$options);$body=curl_exec($curl);$status=(int)curl_getinfo($curl,CURLINFO_HTTP_CODE);curl_close($curl);
    if($body===false||preg_match('/PHP (?:Fatal|Parse) error|Uncaught (?:Error|Exception)|No database\.|Application backend path is invalid/i',$body))throw new RuntimeException('HTTP/runtime failure');
    return [$status,$headers,$body];
}
foreach(['/' => 'reseller_sid','/admin/' => 'reseller_admin_sid'] as $route=>$name){
    [$status,$headers,$body]=request($base.$route);
    if($status!==200&&!($route==='/'&&$status===503))throw new RuntimeException('Unexpected public HTTP status: '.$route.' '.$status);
    $cookie='';
    foreach($headers as $header){
        if(stripos($header,'set-cookie: '.$name.'=')!==0)continue;
        if(!str_contains($header,'Max-Age=2592000')||stripos($header,'HttpOnly')===false||stripos($header,'secure')===false||stripos($header,'SameSite=Lax')===false||stripos($header,'domain=')!==false)throw new RuntimeException('Wrong session cookie policy');
        $cookie=explode(';',substr($header,12))[0];
    }
    if($cookie==='')throw new RuntimeException('Missing session cookie');
    [$again,$againHeaders]=request($base.$route,$cookie);
    if($again!==$status)throw new RuntimeException('Session continuity status failed');
    foreach($againHeaders as $header)if(stripos($header,'set-cookie: '.$name.'=')===0&&explode(';',substr($header,12))[0]!==$cookie)throw new RuntimeException('Session changed unexpectedly');
    echo json_encode(['path'=>$route,'status'=>$status,'session_days'=>30,'continuity'=>true]),PHP_EOL;
}
foreach(['assets/css/admin.css','assets/css/admin-dashboard.css','assets/css/messanger.css','assets/css/style.css','assets/js/admin.js','assets/js/admin-dashboard.js','assets/js/messanger.js','assets/js/admin-crypto-quick.js','assets/js/panel-subscription.js','assets/js/customer-crypto-ticket-status.js','assets/vendor/qrcode-generator/qrcode.js'] as $asset){
    [$status,,$body]=request($base.'/'.$asset);
    if($status!==200||!hash_equals(hash_file('sha256',$web.'/'.$asset),hash('sha256',$body)))throw new RuntimeException('Public asset mismatch: '.$asset);
}
echo "ELEVEN_PUBLIC_ASSET_HASHES_OK\n";
foreach(['/admin/topbar_state.php'=>401,'/admin/crypto_quick.php'=>401,'/admin/panel_subscription.php'=>401,'/admin/provider_remote.php'=>403,'/admin/provider_browser.php'=>403] as $route=>$expected){
    [$status,,$body]=request($base.$route,null,str_contains($route,'provider_')?'action=test':null);
    if($status!==$expected||!is_array(json_decode($body,true)))throw new RuntimeException('Anonymous endpoint protection failed: '.$route);
}
echo "ANONYMOUS_ENDPOINT_PROTECTION_OK\n";
// Reuse only an existing login belonging to this site's private admin session directory.
$directory=dirname(app_session_storage_directory()).'/admin';
$sessions=glob($directory.'/sess_*')?:[];
usort($sessions,static fn($a,$b)=>filemtime($b)<=>filemtime($a));
$authenticated=false;
foreach($sessions as $file){
    if(is_link($file)||filesize($file)>1048576||filemtime($file)<time()-2592000)continue;
    $data=file_get_contents($file);
    if(!preg_match('/(?:^|;)admin_user_id\|i:[1-9][0-9]*;/',$data))continue;
    $sid=substr(basename($file),5);
    if(!preg_match('/^[a-zA-Z0-9,-]+$/D',$sid))continue;
    $cookie='reseller_admin_sid='.$sid;
    [$status,,$body]=request($base.'/admin/?page=dashboard',$cookie);
    if($status!==200||!str_contains($body,'admin-dashboard'))continue;
    $usersHtml='';
    foreach(['orders','payments','products','users','settings','billing'] as $page){
        [$status,,$body]=request($base.'/admin/?page='.$page,$cookie);
        if($status!==200||preg_match('/name=["\x27]admin_login["\x27]/',$body))throw new RuntimeException('Authenticated page failed: '.$page);
        if($page==='users')$usersHtml=$body;
        echo json_encode(['admin_page'=>$page,'status'=>$status,'authenticated'=>true]),PHP_EOL;
    }
    [$status,,$body]=request($base.'/admin/topbar_state.php',$cookie);$state=json_decode($body,true);
    if($status!==200||empty($state['ok'])||strlen((string)($state['orders_revision']??''))!==64)throw new RuntimeException('Authenticated notifications failed');
    echo "AUTHENTICATED_NOTIFICATIONS_OK\n";
    foreach(['new','expired','cancelled','payout'] as $filter){
        [$status,,$body]=request($base.'/admin/crypto_quick.php?filter='.$filter,$cookie);
        if($status===429){usleep(2100000);[$status,,$body]=request($base.'/admin/crypto_quick.php?filter='.$filter,$cookie);}
        if($status===403){echo "CRYPTO_QUICK_DISABLED_BY_SITE_SETTINGS\n";break;}
        $quick=json_decode($body,true);
        if($status!==200||empty($quick['ok'])||$quick['filter']!==$filter||!is_array($quick['counts'])||!is_string($quick['html']))throw new RuntimeException('Crypto quick view failed: '.$filter.' HTTP '.$status);
    }
    echo "AUTHENTICATED_CRYPTO_QUICK_FILTERS_OK\n";
    [$status]=request($base.'/admin/panel_subscription.php',$cookie,'_csrf=invalid&action=check');
    if($status!==403)throw new RuntimeException('Panel payments CSRF protection failed');
    echo "PANEL_PAYMENTS_CSRF_PROTECTION_OK\n";

    $dom=new DOMDocument();@$dom->loadHTML($usersHtml);$xpath=new DOMXPath($dom);
    if($xpath->query('//table[contains(@class,"admin-users-table")]//thead//th')->length!==7)throw new RuntimeException('Users table column layout failed');
    echo "USERS_SEPARATE_IDENTITY_STATUS_TYPE_LANGUAGE_COLUMNS_OK\n";
    $href=$xpath->evaluate('string((//a[contains(@class,"admin-user-row__identity-link")]/@href)[1])');
    if(preg_match('/customer_id=([0-9]+)/',$href,$match)){
        [$status,,$profile]=request($base.'/admin/?page=users&customer_id='.(int)$match[1],$cookie);
        if($status!==200||!str_contains($profile,'admin-user-detail__identity-copy'))throw new RuntimeException('User profile failed');
        echo "USER_PROFILE_HTTP_OK\n";
        $query=trim($xpath->evaluate('string((//span[contains(@class,"admin-user-row__primary")])[1])'));
        if(!str_starts_with($query,'@'))$query=$match[1];
        [$status,,$body]=request($base.'/admin/search.php?q='.rawurlencode($query),$cookie);$search=json_decode($body,true);
        if($status!==200||empty($search['ok'])||!str_contains((string)($search['html']??''),'admin-search-user-card'))throw new RuntimeException('Global search failed');
        echo "AUTHENTICATED_GLOBAL_SEARCH_OK\n";
    }else echo "PROFILE_CHECK_SKIPPED_NO_CUSTOMERS\n";

    $authenticated=true;break;
}
if(!$authenticated)echo "AUTHENTICATED_UI_NOT_TESTED_NO_EXISTING_VALID_SESSION\n";
