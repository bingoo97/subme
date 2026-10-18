<?php
declare(strict_types=1);
require_once __DIR__.'/provider_remote.php';

function admin_provider_browser_local(): bool
{
    return in_array(strtolower((string)($_SERVER['HTTP_HOST']??'')), ['localhost:8088','127.0.0.1:8088'],true)
        && is_file(dirname(__DIR__).'/storage/provider-remote-private/connection.local');
}

// Parse locally only. The material URL is never fetched or forwarded to the search input.
function admin_provider_browser_delivery_link(string $value): ?array
{
    $url=trim($value);
    if($url===''||strlen($url)>2048||preg_match('/[\x00-\x20\x7f\\\\]/',$url)||!filter_var($url,FILTER_VALIDATE_URL))return null;
    $parts=parse_url($url);
    if(!$parts||!in_array(strtolower($parts['scheme']??''),['http','https'],true)||empty($parts['host'])||isset($parts['user'])||isset($parts['pass']))return null;
    $username=null;
    foreach(explode('&',$parts['query']??'') as $pair){
        [$key,$raw]=array_pad(explode('=',$pair,2),2,'');$key=urldecode($key);
        if(!preg_match('/^username(?:$|\[)/i',$key))continue;
        if(strcasecmp($key,'username')!==0||$username!==null||preg_match('/%(?![a-f0-9]{2})/i',$raw))return null;
        $username=urldecode($raw);
        if($username===''||strlen($username)>128||!preg_match('//u',$username)||preg_match('/[\s\x00-\x1f\x7f]/u',$username))return null;
    }
    return ['username'=>$username];
}

function admin_provider_browser_context(Mysql_ks $db,array $user,int $providerId,int $orderId): array
{
    if(!admin_user_can_access_route($user,'products'))throw new RuntimeException('forbidden');
    $identity=[];$order=null;
    if($orderId>0){
        if(!admin_user_can_access_route($user,'orders'))throw new RuntimeException('forbidden');
        $order=$db->select_user('SELECT o.id,o.payment_status,o.status,o.delivery_link,c.public_handle,c.email,p.provider_id,p.product_type
            FROM orders o INNER JOIN customers c ON c.id=o.customer_id INNER JOIN products p ON p.id=o.product_id WHERE o.id='.$orderId.' LIMIT 1');
        if(empty($order['id']))throw new RuntimeException('missing');
        if(($order['payment_status']??'')!=='paid' || in_array($order['status']??'',['cancelled','refunded'],true))throw new RuntimeException('unpaid');
        $link=admin_provider_browser_delivery_link((string)($order['delivery_link']??''));
        if($link===null)throw new RuntimeException('link_required');
        if(!admin_product_type_uses_expiry($order['product_type']??''))throw new RuntimeException('config');
        if($providerId>0 && $providerId!==(int)$order['provider_id'])throw new RuntimeException('config');
        $providerId=(int)$order['provider_id'];
        foreach(['handle'=>'public_handle','email'=>'email'] as $key=>$field){
            $value=trim((string)($order[$field]??''));
            if($value!=='' && strlen($value)<=2048)$identity[$key]=$value;
        }
        // A provider's line login identifies the subscription, independently of the Subme account handle/email.
        if($link['username']!==null)$identity=['username'=>$link['username']];
    }
    $provider=$db->select_user('SELECT id,name,dashboard_url,is_active FROM product_providers WHERE id='.$providerId.' LIMIT 1');
    if(empty($provider['id']) || empty($provider['is_active']))throw new RuntimeException('missing');
    admin_provider_remote_url((string)$provider['dashboard_url']);
    $row=admin_provider_remote_row($db,$providerId);
    if(!$row)throw new RuntimeException('missing');
    $config=json_decode($row['config_json'],true,16,JSON_THROW_ON_ERROR);
    if(!hash_equals($config['dashboard_hash']??'',hash('sha256',(string)$provider['dashboard_url'])))throw new RuntimeException('changed_url');
    return ['provider'=>$provider,'row'=>$row,'config'=>$config,'identity'=>$identity,
        'owner'=>(getenv('DB_NAME')?:'reseller_v2').':'.$user['id'].':'.($orderId>0?'order:'.$orderId:'provider:'.$providerId),
        'binding'=>hash('sha256',(getenv('DB_NAME')?:'reseller_v2').$row['config_json'].$row['credentials_encrypted'].$provider['dashboard_url'].json_encode([$orderId,$identity,$order['payment_status']??'',hash('sha256',(string)($order['delivery_link']??''))]))];
}

function admin_provider_browser_render(int $providerId,int $orderId,string $csrf,string $locale): void
{
    if(!admin_provider_browser_local())return;
    $pl=$locale==='pl'; ?>
    <section class="admin-provider-remote admin-provider-browser" data-provider-browser data-provider-id="<?php echo $providerId; ?>" data-order-id="<?php echo $orderId; ?>" data-csrf="<?php echo admin_e($csrf); ?>" data-locale="<?php echo admin_e($locale); ?>">
        <div class="admin-provider-remote__heading"><span class="admin-provider-remote__icon"><i class="bi bi-plug" aria-hidden="true"></i></span><div><h4><?php echo $pl?'Panel providera':'Provider panel'; ?></h4><p><?php echo $pl?'Automatyczne połączenie i wyszukiwanie subskrypcji.':'Automatic connection and subscription lookup.'; ?></p></div></div>
        <div class="admin-provider-remote__actions">
            <button type="button" class="btn btn-dark btn-sm" data-provider-browser-open><i class="bi bi-plug" aria-hidden="true"></i> <?php echo $pl?($orderId>0?'1. Sprawdź połączenie':'Sprawdź połączenie'):($orderId>0?'1. Check connection':'Check connection'); ?></button>
            <?php if($orderId>0): ?>

            <button type="button" class="btn btn-outline-dark btn-sm" data-provider-browser-search disabled><i class="bi bi-search" aria-hidden="true"></i> <?php echo $pl?'2. Wyszukaj subskrypcję':'2. Find subscription'; ?></button><?php endif; ?>
        </div>
        <p class="admin-provider-remote__hint"><?php echo $pl?'Jeśli przypisany link zawiera username, wyszukujemy po tym loginie. Przedłużanie subskrypcji dodamy w kolejnym etapie.':'When the assigned URL contains username, we search by that login. Subscription renewal will be added in the next stage.'; ?></p>
        <div data-provider-browser-captcha hidden class="admin-provider-connection__captcha"><img data-provider-browser-image alt="CAPTCHA" referrerpolicy="no-referrer"><label><?php echo $pl?'Kod z obrazka':'Image code'; ?><input class="form-control form-control-sm" data-provider-browser-answer maxlength="128" autocomplete="off"></label><button type="button" class="btn btn-dark btn-sm" data-provider-browser-complete><?php echo $pl?'Potwierdź kod':'Confirm code'; ?></button></div>
        <div class="admin-provider-remote__result" data-provider-browser-result role="status" aria-live="polite" hidden></div>
        <div class="admin-provider-matches" data-provider-browser-matches hidden></div>
    </section>
<?php }
