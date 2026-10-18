<?php
declare(strict_types=1);
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/provider_remote.php';
admin_send_security_headers();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, private');
header('Referrer-Policy: no-referrer');
ini_set('display_errors', '0');
admin_start_session();
$db = Mysql_ks::get_instance();
$user = admin_load_session_user($db);
function provider_remote_reply(array $result, int $status = 200): void {
    http_response_code($status);
    echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}
if (!$user || !admin_user_can_access_route($user, 'products')) {
    $code=$user?'forbidden':'session';
    provider_remote_reply(['ok'=>false,'code'=>$code,'message'=>admin_provider_remote_text($code)],403);
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Allow: POST'); provider_remote_reply(['ok'=>false,'message'=>'POST required.'],405); }
foreach ($_POST as $value) if (!is_string($value)) provider_remote_reply(['ok'=>false,'message'=>admin_provider_remote_text('config')],422);
if (!admin_csrf_is_valid($_POST['_csrf'] ?? '')) provider_remote_reply(['ok'=>false,'code'=>'session','message'=>admin_provider_remote_text('session')],403);
if ((int)($_SERVER['CONTENT_LENGTH'] ?? 0) > 16384) provider_remote_reply(['ok'=>false,'message'=>admin_provider_remote_text('limit')],413);
$locale = admin_current_locale();
$providerId = (int)($_POST['provider_id'] ?? 0);
$action = (string)($_POST['action'] ?? '');
if (!in_array($action,['load','save','inspect','test','test_complete','test_cancel'],true) || $providerId <= 0) provider_remote_reply(['ok'=>false,'message'=>admin_provider_remote_text('config')],422);
$lock = '';
$rateKey = '';
$networkStarted = false;
try {
    admin_provider_remote_ensure_schema($db);
    $provider = $db->select_user('SELECT id,dashboard_url,is_active FROM product_providers WHERE id=' . $providerId . ' LIMIT 1');
    if (empty($provider['id'])) provider_remote_reply(['ok'=>false,'message'=>admin_provider_remote_text('missing')],404);
    $settings = $db->select_user('SELECT provider_remote_login_enabled FROM app_settings WHERE id=1');
    if (in_array($action, ['inspect','test','test_complete'],true) && empty($settings['provider_remote_login_enabled'])) provider_remote_reply(['ok'=>false,'code'=>'disabled','message'=>admin_provider_remote_text('disabled')],409);
    // No attempt timestamps, result records, activity logs or cookie files are written.
    $database = $db->select_user('SELECT DATABASE() AS name');
    $lock = 'provider-remote:' . substr(hash('sha256',($database['name']??'').':'.$providerId),0,44);
    $held = $db->select_user("SELECT GET_LOCK('".$db->escape($lock)."',0) AS acquired");
    if ((int)($held['acquired']??0) !== 1) { $lock=''; throw new RuntimeException('busy'); }
    if ($action === 'inspect') {
        if (empty($provider['is_active'])) throw new RuntimeException('config');
        $url = admin_provider_remote_resolve_url((string)$provider['dashboard_url'], trim((string)($_POST['login_path'] ?? '')));
        session_write_close();
        $rateKey = hash('sha256',($database['name']??'').':'.parse_url($url,PHP_URL_HOST));
        $gate = admin_provider_remote_browser('gate',['key'=>$rateKey,'kind'=>'inspect','owner'=>'limit','binding'=>'limit']);
        if (($gate['code']??'')!=='allowed') throw new RuntimeException(($gate['code']??'')==='busy'?'busy':'rate_limited');
        $networkStarted = true;
        $result = admin_provider_remote_inspect($url, admin_provider_remote_transport(), static function($target,$config) use ($user,$providerId) {
            return admin_provider_remote_browser('inspect', ['url'=>$target,'config'=>$config,'owner'=>(string)$user['id'].':'.$providerId,'binding'=>hash('sha256',$target)]);
        });
        $result['message'] = $locale === 'pl' ? 'Panel rozpoznany. Opcje uzupełnione — zapisz konfigurację.' : 'Panel detected. Settings filled in — save the configuration.';
    } elseif ($action === 'save') {
        admin_provider_remote_save($db,$providerId,$_POST,(string)$provider['dashboard_url']);
        $result=['ok'=>true,'code'=>'saved','message'=>admin_provider_remote_text('saved')];
    } else {
        $row=admin_provider_remote_row($db,$providerId);
        if (!$row) {
            if ($action!=='load') throw new RuntimeException('missing');
            $result=['ok'=>true,'config'=>null,'username'=>'','has_password'=>false];
        } else {
            $config=json_decode($row['config_json'],true,16,JSON_THROW_ON_ERROR);
            $credentials=admin_provider_remote_unseal($row['credentials_encrypted'],$providerId);
            if ($action==='load') $result=['ok'=>true,'config'=>$config,'username'=>$credentials['username']??'','has_password'=>($credentials['password']??'')!==''];
            else {
                if (!hash_equals($config['dashboard_hash']??'',hash('sha256',(string)$provider['dashboard_url']))) throw new RuntimeException('changed_url');
                if (empty($provider['is_active'])) throw new RuntimeException('config');
                session_write_close();
                $rateKey = hash('sha256',($database['name']??'').':'.parse_url((string)$provider['dashboard_url'],PHP_URL_HOST));
                if ($action==='test' && (empty($config['two_factor']) && (empty($config['captcha']) || ($config['mode']??'html')!=='html' || !empty($config['captcha_ocr'])))) {
                    $gate = admin_provider_remote_browser('gate',['key'=>$rateKey,'kind'=>'test','owner'=>'limit','binding'=>'limit']);
                    if (($gate['code']??'')!=='allowed') throw new RuntimeException(($gate['code']??'')==='busy'?'busy':'rate_limited');
                    $networkStarted = true;
                }
                $automaticCode = !empty($config['two_factor']) && $action==='test' ? 'manual' : null;
                if ($automaticCode===null && ($config['mode'] ?? 'html') === 'auto' && $action === 'test') {
                    try {
                        $automaticCode = admin_provider_remote_run($config,$credentials,(string)$provider['dashboard_url'],admin_provider_remote_transport());
                    } catch (RuntimeException $error) {
                        // Fallback is allowed only during discovery, before any credential POST.
                        if ($error->getMessage() !== 'browser_required') throw $error;
                    }
                }
                if ($automaticCode !== null) {
                    $result=['ok'=>$automaticCode==='success','code'=>$automaticCode,'message'=>admin_provider_remote_text($automaticCode,$locale)];
                } elseif (in_array($config['mode'] ?? 'html', ['auto','browser'], true) || !empty($config['captcha_ocr'])) {
                    $binding=hash('sha256',$row['config_json'].$row['credentials_encrypted'].$provider['dashboard_url']);
                    $payload=['owner'=>(string)$user['id'].':'.$providerId,'binding'=>$binding];
                    if ($action==='test') {
                        $payload+=['url'=>admin_provider_remote_resolve_url((string)$provider['dashboard_url'],$config['login_path']),'config'=>$config,'credentials'=>$credentials];
                        $operation='start';
                    } else {
                        $payload+=['challenge'=>(string)($_POST['challenge']??''),'answer'=>(string)($_POST['answer']??'')];
                        $operation=$action==='test_cancel'?'cancel':'complete';
                    }
                    $response=admin_provider_remote_browser($operation,$payload);
                    if ($operation==='complete') $networkStarted = true;
                    $code=(string)$response['code'];
                    $validCodes=['captcha_required','captcha_rejected','expired','rate_limited','success','rejected','unconfirmed','cancelled','manual','busy','form','config','baseline','origin','destination','limit','connection'];
                    if (!in_array($code,$validCodes,true)) throw new RuntimeException('connection');
                    $result=['ok'=>$code==='success','code'=>$code,'message'=>admin_provider_remote_text($code,$locale)];
                    if (!empty($response['ocr_used']) && $code==='success') $result['message']=$locale==='pl'?'Logowanie potwierdzone. Kod CAPTCHA odczytano automatycznie.':'Login confirmed. CAPTCHA was read automatically.';
                    if (!empty($response['ocr_fallback']) && $code==='captcha_required') $result['message']=$locale==='pl'?'Odczyt jest niepewny. Wpisz kod z obrazka, aby dokończyć logowanie.':'The reading is uncertain. Enter the image code to finish logging in.';
                    if ($code==='captcha_required' && preg_match('/^[a-f0-9]{48}$/D',(string)($response['challenge']??'')) && preg_match('~^data:image/png;base64,[a-zA-Z0-9+/=]+$~D',(string)($response['image']??''))) {
                        $result+=['challenge'=>$response['challenge'],'image'=>$response['image'],'expires_in'=>120];
                    }
                } else {
                    if ($action!=='test') throw new RuntimeException('config');
                    $code=admin_provider_remote_run($config,$credentials,(string)$provider['dashboard_url'],admin_provider_remote_transport());
                    $result=['ok'=>$code==='success','code'=>$code,'message'=>admin_provider_remote_text($code,$locale)];
                }
            }
        }
    }
} catch (Throwable $e) {
    $allowed=['disabled','missing','changed_url','config','url','destination','origin','dns','connection','limit','form','post','manual','unconfirmed','baseline','busy','storage','browser_unavailable','rate_limited','expired','captcha_rejected'];
    $code=in_array($e->getMessage(),$allowed,true)?$e->getMessage():'storage';
    $result=['ok'=>false,'code'=>$code,'message'=>admin_provider_remote_text($code,$locale)];
} finally {
    unset($credentials,$row,$_POST['password']);
    if ($rateKey!=='' && $networkStarted && in_array($result['code']??'', ['rejected','captcha_rejected','rate_limited'],true)) {
        try { admin_provider_remote_browser('backoff',['key'=>$rateKey,'kind'=>'test','owner'=>'limit','binding'=>'limit']); } catch (Throwable $ignored) { /* No persistent fallback or retry. */ }
    }
    if ($lock!=='') $db->query("SELECT RELEASE_LOCK('".$db->escape($lock)."')");
}
provider_remote_reply($result);
