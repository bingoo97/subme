<?php
declare(strict_types=1);
require_once __DIR__.'/bootstrap.php';
require_once __DIR__.'/provider_browser.php';
admin_send_security_headers();
header('Content-Type: application/json; charset=utf-8');header('Cache-Control: no-store, private');header('Referrer-Policy: no-referrer');
ini_set('display_errors','0');admin_start_session();
function provider_browser_reply(array $data,int $status=200): never {http_response_code($status);echo json_encode($data,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);exit;}
$db=Mysql_ks::get_instance();$user=admin_load_session_user($db);$locale=admin_current_locale();$rateKey='';$networkStarted=false;
try{
 if(!$user)throw new RuntimeException('session');
 if(!admin_provider_browser_local())throw new RuntimeException('local_only');
 if(($_SERVER['REQUEST_METHOD']??'')!=='POST')throw new RuntimeException('config');
 foreach($_POST as $value)if(!is_string($value))throw new RuntimeException('config');
 if(!admin_csrf_is_valid($_POST['_csrf']??''))throw new RuntimeException('session');
 if((int)($_SERVER['CONTENT_LENGTH']??0)>8192)throw new RuntimeException('limit');
 $origin=(string)($_SERVER['HTTP_ORIGIN']??'');
 if($origin!==''&&$origin!=='http://'.$_SERVER['HTTP_HOST'])throw new RuntimeException('forbidden');
 $action=$_POST['action']??'';
 if(!in_array($action,['connect','complete','lookup','cancel'],true))throw new RuntimeException('config');
 $settings=admin_app_settings($db);if(empty($settings['provider_remote_login_enabled']))throw new RuntimeException('disabled');
 $orderId=(int)($_POST['order_id']??0);$providerId=(int)($_POST['provider_id']??0);
 if($orderId<=0)throw new RuntimeException('config');
 $ctx=admin_provider_browser_context($db,$user,$providerId,$orderId);$providerId=(int)$ctx['provider']['id'];
 $url=admin_provider_remote_resolve_url((string)$ctx['provider']['dashboard_url'],$ctx['config']['login_path']);
 $rateKey=hash('sha256',(getenv('DB_NAME')?:'reseller_v2').':'.parse_url($url,PHP_URL_HOST));
 $payload=['owner'=>$ctx['owner'],'binding'=>$ctx['binding']];
 $connectionConfig=$ctx['config'];
 if(!empty($connectionConfig['subscription_list_path']))$connectionConfig['subscription_list_url']=admin_provider_remote_resolve_url((string)$ctx['provider']['dashboard_url'],$connectionConfig['subscription_list_path']);
 session_write_close();
 if($action==='connect'){
  $gate=admin_provider_remote_browser('gate',['key'=>$rateKey,'kind'=>'test','owner'=>'limit','binding'=>'limit']);
  if(($gate['code']??'')!=='allowed')throw new RuntimeException('rate_limited');
  $credentials=admin_provider_remote_unseal($ctx['row']['credentials_encrypted'],$providerId);
  $networkStarted=true;
  $response=admin_provider_remote_browser('connect',$payload+['url'=>$url,'config'=>$connectionConfig,'credentials'=>$credentials]);
  unset($credentials);
 }else{
  $challenge=(string)($_POST['challenge']??'');if(!preg_match('/^[a-f0-9]{48}$/D',$challenge))throw new RuntimeException('expired');
  $payload['challenge']=$challenge;
  if($action==='lookup'){
   if($orderId<=0)throw new RuntimeException('config');
   // Identity comes only from the current, paid order; frontend cannot choose a different customer.
   $payload['identity']=$ctx['identity'];$operation='lookup';
  }elseif($action==='complete'){
   $answer=(string)($_POST['answer']??'');if($answer===''||strlen($answer)>128)throw new RuntimeException('config');
   $payload['answer']=$answer;$operation='connect_complete';$networkStarted=true;
  }else $operation='cancel';
  $response=admin_provider_remote_browser($operation,$payload);
 }
 $code=(string)($response['code']??'connection');
 $allowed=['connected','found','not_found','ambiguous','table_missing','no_identity','captcha_required','captcha_rejected','expired','success','rejected','unconfirmed','cancelled','manual','busy','form','config','baseline','origin','destination','limit','connection','rate_limited'];
 if(!in_array($code,$allowed,true))throw new RuntimeException('connection');
 $pl=$locale==='pl';
 $labels=[
  'connected'=>['Połączenie i logowanie potwierdzone. Możesz wyszukać subskrypcję.','Connection and login confirmed. You can look up the subscription.'],
  'found'=>['Znaleziono subskrypcję tego klienta.','The customer subscription was found.'],
  'not_found'=>['Nie znaleziono pasującej subskrypcji na przeszukanej liście. Sprawdź dane linku lub listę w panelu providera.','No matching subscription was found on the searched list. Check the assigned URL or the list in the provider panel.'],
  'ambiguous'=>['Dopasowanie nie jest jednoznaczne. Sprawdź dane w panelu providera.','The match is ambiguous. Verify the data in the provider panel.'],
  'table_missing'=>['Nie rozpoznano listy lub wyszukiwarki subskrypcji. Ten panel wymaga dopasowania adaptera.','The subscription list or search could not be identified. This panel needs an adapter.'],
  'no_identity'=>['Brak danych klienta do wyszukania w tym zamówieniu.','No customer identifiers are available for this order.'],
  'unconfirmed'=>['Nie udało się jednoznacznie potwierdzić wyniku. Sprawdź panel providera.','The result could not be confirmed reliably. Check the provider panel.'],
  'captcha_required'=>['Wpisz kod z obrazka, aby dokończyć logowanie.','Enter the image code to complete the login.'],
  'manual'=>['Ten panel wymaga ręcznego logowania lub CAPTCHA/2FA w przeglądarce.','This panel requires manual login or browser CAPTCHA/2FA.']
 ];
 $result=['ok'=>in_array($code,['connected','found','not_found','ambiguous','table_missing','no_identity','cancelled'],true),'code'=>$code,'message'=>isset($labels[$code])?$labels[$code][$pl?0:1]:admin_provider_remote_text($code,$locale)];
 if(!empty($response['ocr_used'])&&$code==='connected')$result['message']=$pl?'Połączenie i logowanie potwierdzone. Kod CAPTCHA odczytano automatycznie. Możesz wyszukać subskrypcję.':'Connection and login confirmed. CAPTCHA was read automatically. You can look up the subscription.';
 if(!empty($response['ocr_fallback'])&&$code==='captcha_required')$result['message']=$pl?'Odczyt jest niepewny. Wpisz kod z obrazka, aby dokończyć logowanie.':'The reading is uncertain. Enter the image code to finish logging in.';
 if($code==='ambiguous'&&(int)($response['count']??0)>1)$result['message']=$pl?'Znaleziono kilka pasujących subskrypcji. Sprawdź, która dotyczy tego zamówienia.':'Several matching subscriptions were found. Verify which one belongs to this order.';
 if(in_array($code,['connected','captcha_required'],true)&&preg_match('/^[a-f0-9]{48}$/D',(string)($response['challenge']??'')))$result+=['challenge'=>$response['challenge'],'expires_in'=>min(120,max(0,(int)($response['expires_in']??120)))];
 if($code==='captcha_required'&&preg_match('~^data:image/png;base64,[a-zA-Z0-9+/=]+$~D',(string)($response['image']??'')))$result['image']=$response['image'];
 if($code==='found'||$code==='ambiguous'){
  $result['count']=min(1000,max(0,(int)($response['count']??0)));$result['candidates']=[];$result['truncated']=!empty($response['truncated']);
  foreach(array_slice(is_array($response['candidates']??null)?$response['candidates']:[],0,20) as $index=>$candidate){
   if(!is_array($candidate))continue;$item=['key'=>'match-'.($index+1),'conflict'=>!empty($candidate['conflict'])];
   foreach(['source_id'=>64,'title'=>160,'handle'=>128,'username'=>128,'email'=>254,'expires'=>80,'status'=>80] as $field=>$limit)$item[$field]=mb_substr(preg_replace('/[\x00-\x1f\x7f]/u',' ',is_string($candidate[$field]??null)?$candidate[$field]:''),0,$limit,'UTF-8');
   $result['candidates'][]=$item;
  }
 }
}catch(Throwable $error){
 $code=in_array($error->getMessage(),['session','forbidden','local_only','unpaid','link_required','missing','changed_url','config','disabled','url','destination','origin','rate_limited','expired','busy','browser_unavailable','connection'],true)?$error->getMessage():'storage';
 $extra=['local_only'=>'Ta funkcja jest dostępna tylko lokalnie.','unpaid'=>'Najpierw zatwierdź płatność zamówienia.','link_required'=>$locale==='pl'?'Najpierw przypisz i zapisz poprawny link URL w zamówieniu.':'Assign and save a valid delivery URL in the order first.'];
 $result=['ok'=>false,'code'=>$code,'message'=>$extra[$code]??admin_provider_remote_text($code,$locale)];
}finally{
 unset($credentials);
 if($rateKey!==''&&$networkStarted&&in_array($result['code']??'', ['rejected','captcha_rejected','rate_limited'],true)){
  try{admin_provider_remote_browser('backoff',['key'=>$rateKey,'kind'=>'test','owner'=>'limit','binding'=>'limit']);}catch(Throwable $ignored){}
 }
}
provider_browser_reply($result,in_array($result['code'],['session','forbidden','local_only'],true)?403:200);
