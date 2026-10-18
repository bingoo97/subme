<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/admin/provider_remote.php';
$checks=0;
function remote_check(bool $condition,string $label):void {global $checks;if(!$condition)throw new RuntimeException($label);$checks++;}
function remote_throws(callable $f,string $code):void {try{$f();}catch(RuntimeException $e){remote_check($e->getMessage()===$code,'Expected '.$code);return;}throw new RuntimeException('Missing exception '.$code);}
$url='https://provider.example.invalid/login';
$config=admin_provider_remote_config(['mode'=>'html','use_username'=>1,'use_password'=>1,'username_by'=>'id','username_field'=>'username','password_by'=>'id','password_field'=>'password','submit_by'=>'id','submit_field'=>'_submit','success_kind'=>'id','success_value'=>'account-menu','failure_kind'=>'id','failure_value'=>'login-error'],$url);
$credentials=['username'=>'fixture-user','password'=>' password with spaces '];
remote_check(!$config['captcha_ocr']&&!$config['captcha_ocr_digits'],'OCR is opt-in for existing/new provider configuration');
$ocrConfig=admin_provider_remote_config(['use_username'=>1,'use_password'=>1,'captcha_ocr'=>1,'captcha_ocr_digits'=>1],$url);
remote_check($ocrConfig['captcha_ocr']&&$ocrConfig['captcha_ocr_digits'],'OCR settings are independent of automatic CAPTCHA discovery');
$html='<form action="/xc/login_check" method="post"><input type="hidden" name="_csrf_token" value="fresh-fixture-token"><input id="username" name="_username"><input id="password" name="_password" type="password"><button type="submit" id="_submit" name="_submit">Sign in</button></form>';
$before=['status'=>200,'url'=>$url,'body'=>$html];
[$action,$post]=admin_provider_remote_form($before,$config,$credentials);
remote_check($action==='https://provider.example.invalid/xc/login_check','Relative form action');
remote_check($post['_username']===$credentials['username']&&$post['_password']===$credentials['password'],'ID resolves real names; exact password');
remote_check($post['_csrf_token']==='fresh-fixture-token'&&array_key_exists('_submit',$post),'Hidden token and submit button');
$after=['status'=>200,'url'=>'https://provider.example.invalid/dashboard','body'=>'<div id="account-menu">Account</div>'];
$requests=[];
$transport=static function(string $url,?array $post)use(&$requests,$before,$after):array{$requests[]=[$url,$post];return $post===null?$before:$after;};
remote_check(admin_provider_remote_run($config,$credentials,$url,$transport)==='success','HTML login success');
remote_check(count($requests)===2,'Exactly one GET and one login POST');
foreach ([['status'=>401,'url'=>$url,'body'=>''],['status'=>200,'url'=>$url,'body'=>'<p id="login-error">Rejected</p>']] as $failed) {
 remote_check(admin_provider_remote_run($config,$credentials,$url,static fn($url,$post)=>$post===null?$before:$failed)==='rejected','Explicit rejection');
}
remote_check(admin_provider_remote_run($config,$credentials,$url,static fn($url,$post)=>$post===null?$before:['status'=>200,'url'=>$url,'body'=>'<p>OK</p>'])==='unconfirmed','200 alone is not success');
remote_check(admin_provider_remote_run($config,$credentials,$url,static fn($url,$post)=>$post===null?$before:['status'=>200,'url'=>$url,'body'=>$html.'<div id="account-menu"></div>'])==='unconfirmed','Login form still visible prevents false success');
$manual=$config;$manual['captcha']=true;$called=false;
remote_check(admin_provider_remote_run($manual,$credentials,$url,static function()use(&$called){$called=true;return [];})==='manual'&&!$called,'Configured CAPTCHA sends no HTTP login');
$manual=$config;$manual['two_factor']=true;
remote_check(admin_provider_remote_run($manual,$credentials,$url,static fn()=>[])==='manual','2FA requires manual flow');
remote_check(admin_provider_remote_run($config,$credentials,$url,static fn()=>['status'=>200,'url'=>$url,'body'=>$html.'<img alt="Captcha">'])==='manual','Unconfigured CAPTCHA detected before POST');
remote_throws(static fn()=>admin_provider_remote_run($config,$credentials,$url,static fn()=>['status'=>200,'url'=>$url,'body'=>'<div id="account-menu"></div>'.$html]),'baseline');
foreach (['ftp://provider.example.invalid','https://user:pass@provider.example.invalid','https://provider.example.invalid:0','http://provider.example.invalid:65536','https://provider.example.invalid/#login','https://provider.example.invalid/\\x'] as $bad) remote_throws(static fn()=>admin_provider_remote_url($bad),'url');
foreach (['http://provider.example.invalid:2052','https://provider.example.invalid:8443','http://provider.example.invalid:8880','https://provider.example.invalid:65535'] as $custom) {
 remote_check(admin_provider_remote_url($custom)===$custom.'/','HTTP/HTTPS preserve custom port');
 remote_check(admin_provider_remote_resolve_url($custom.'/xc/login','../check')===$custom.'/check','Relative form action preserves protocol and port');
 remote_check(admin_provider_remote_resolve_url($custom.'/login','//'.parse_url($custom,PHP_URL_HOST).':'.parse_url($custom,PHP_URL_PORT).'/lines/users')===$custom.'/lines/users','Protocol-relative URL inherits provider protocol and port');
}
remote_check(admin_provider_remote_url('HTTP://provider.example.invalid:80')==='http://provider.example.invalid/','Default HTTP port normalized');
remote_check(admin_provider_remote_resolve_url('https://provider.example.invalid:443/login','https://provider.example.invalid/check')==='https://provider.example.invalid/check','Default HTTPS port is the same origin');
foreach (['http://provider.example.invalid:2053/login','http://provider.example.invalid/login','https://provider.example.invalid:2052/login'] as $otherOrigin) remote_throws(static fn()=>admin_provider_remote_resolve_url('http://provider.example.invalid:2052/login',$otherOrigin),'origin');
$customBase='http://provider.example.invalid:2052/login';
[$customAction]=admin_provider_remote_form(['url'=>$customBase,'body'=>$html],$config,$credentials);
remote_check($customAction==='http://provider.example.invalid:2052/xc/login_check','Login POST keeps HTTP custom port');
remote_check(admin_provider_remote_run($config,$credentials,$customBase,static fn($u,$p)=>['status'=>200,'url'=>$u,'body'=>$p===null?$html:'<div id="account-menu">Account</div>'])==='success','HTTP custom-port login workflow');
foreach (['https://localhost','https://127.0.0.1','https://[::1]'] as $bad) remote_throws(static fn()=>admin_provider_remote_url($bad),'destination');
remote_throws(static fn()=>admin_provider_remote_resolve_url($url,'https://other.example.invalid/login'),'origin');
remote_throws(static fn()=>admin_provider_remote_resolve_url($url,'//other.example.invalid/login'),'origin');
remote_check(admin_provider_remote_resolve_url('https://provider.example.invalid/xc/login','../check')==='https://provider.example.invalid/check','Relative path normalization');
foreach (['127.0.0.1','10.0.0.1','100.64.0.1','169.254.169.254','172.20.0.1','192.168.1.1','0.0.0.0','198.18.0.1','192.0.2.1','224.0.0.1','::1','::ffff:127.0.0.1','fc00::1','fe80::1','2001:db8::1','2002:7f00:1::'] as $ip) remote_check(!admin_provider_remote_public_ip($ip),'Reject special network');
foreach (['1.1.1.1','8.8.8.8','2606:4700:4700::1111'] as $ip) remote_check(admin_provider_remote_public_ip($ip),'Public address accepted');
foreach ([str_replace('method="post"','method="get"',$html),str_replace('method="post"','',$html)] as $bad) remote_throws(static fn()=>admin_provider_remote_form(['url'=>$url,'body'=>$bad],$config,$credentials),'post');
remote_throws(static fn()=>admin_provider_remote_form(['url'=>$url,'body'=>$html.$html],$config,$credentials),'form');
remote_throws(static fn()=>admin_provider_remote_form(['url'=>$url,'body'=>str_replace('name="_username"','',$html)],$config,$credentials),'form');
remote_throws(static fn()=>admin_provider_remote_form(['url'=>$url,'body'=>str_replace('/xc/login_check','https://other.example.invalid/login',$html)],$config,$credentials),'origin');
remote_check(!admin_provider_remote_match(['url'=>$url,'body'=>'<script>Account ready</script>'],'text','Account ready'),'Script content is not text evidence');
remote_check(!admin_provider_remote_match(['url'=>'https://provider.example.invalid/dashboard-fake','body'=>''],'url','/dashboard'),'URL marker boundary');
$automatic=admin_provider_remote_config(['use_username'=>1,'use_password'=>1],$url);
remote_check($automatic['mode']==='auto' && $automatic['success_kind']==='auto' && $automatic['success_value']==='', 'Automatic confirmation is default without a custom marker');
remote_throws(static fn()=>admin_provider_remote_config(['use_username'=>1,'success_kind'=>'id'], $url),'config');
remote_throws(static fn()=>admin_provider_remote_config(['use_username'=>1,'failure_kind'=>'auto'], $url),'config');
$automatic=array_replace($config,['success_kind'=>'auto','success_value'=>'']);
foreach (['<form><button name="admin_logout" type="submit">Exit</button></form>', '<a href="/logout">Log out</a>', '<button>Wyloguj się</button>', '<button aria-label="Sign out"><svg></svg></button>', '<a href="?action=logout">Exit</a>', '<input type="submit" name="sign_out" value="Exit">', '<button>Abmelden</button>'] as $authenticated) {
 remote_check(admin_provider_remote_run($automatic,$credentials,$url,static fn($u,$p)=>$p===null?$before:['status'=>200,'url'=>$url,'body'=>$authenticated])==='success','Auto confirms logout control after login at same URL');
}
foreach (['<div class="d-none"><button>Logout</button></div>','<div class="modal"><button>Logout</button></div>','<p>Wyloguj się</p>', '<script>logout</script>', '<button hidden>Logout</button>', '<div style="display:none"><button>Logout</button></div>', '<template><button>Logout</button></template>', '<a href="https://other.example.invalid/logout">Logout</a>', '<a href="#">Logout</a>', '<input type="hidden" name="logout">', '<button disabled>Logout</button>', '<button>Learn about logout</button>', '<a href="/help/logout-guide">Help</a>'] as $notAuthenticated) {
 // A help URL must not be mistaken for an actual logout action.
 remote_check(!admin_provider_remote_match(['url'=>$url,'body'=>$notAuthenticated],'auto',''),'Auto rejects ambiguous, hidden, external or non-actionable evidence');
}
remote_throws(static fn()=>admin_provider_remote_run($automatic,$credentials,$url,static fn()=>['status'=>200,'url'=>$url,'body'=>$html.'<button name="logout">Exit</button>']),'baseline');
remote_check(admin_provider_remote_run($automatic,$credentials,$url,static fn($u,$p)=>$p===null?$before:['status'=>200,'url'=>$url,'body'=>'<input autocomplete="one-time-code"><button name="admin_logout">Exit</button>'])==='unconfirmed','OTP challenge is not completed authentication');
$stillLogin=str_replace(' id="_submit" name="_submit"','',$html).'<button name="admin_logout">Exit</button>';
remote_check(admin_provider_remote_run($automatic,$credentials,$url,static fn($u,$p)=>$p===null?$before:['status'=>200,'url'=>$url,'body'=>$stillLogin])==='unconfirmed','Credential fields still present block success even if submit selector disappeared');
remote_check(admin_provider_remote_run($automatic,$credentials,$url,static fn($u,$p)=>$p===null?$before:['status'=>200,'url'=>'https://provider.example.invalid/dashboard','body'=>'<h1>Dashboard</h1>'])==='unconfirmed','200, a new URL and dashboard title alone do not prove authentication');
$discover=admin_provider_remote_config(['use_username'=>1,'use_password'=>1],$url);
$zoki='<form method="post"><input type="hidden" name="_csrf" value="fresh"><input id="identity" name="identity"><input type="password" name="password"><button name="admin_login" type="submit">Zaloguj się</button></form>';
$zokiBefore=['status'=>200,'url'=>$url,'body'=>$zoki];
$detected=admin_provider_remote_discover($zokiBefore,$discover);
remote_check($detected['username_field']==='identity' && $detected['password_field']==='password' && $detected['submit_field']==='admin_login','Auto discovers real login and named submit fields');
$calls=[];
remote_check(admin_provider_remote_run($discover,$credentials,$url,static function($u,$p)use(&$calls,$zokiBefore){$calls[]=$p;return $p===null?$zokiBefore:['status'=>200,'url'=>$u,'body'=>'<button name="admin_logout">Exit</button>'];})==='success','URL and credentials alone support HTML end to end');
remote_check(count($calls)===2 && $calls[1]['identity']===$credentials['username'] && $calls[1]['_csrf']==='fresh' && array_key_exists('admin_login',$calls[1]),'Auto sends one POST with actual names, fresh CSRF and submit name');
foreach ([str_replace('admin_login','register',$zoki),str_replace('type="password"','type="password" autocomplete="new-password"',$zoki),$zoki.$zoki,str_replace('method="post"','',$zoki),str_replace('name="identity"','',$zoki),str_replace('</form>','<input type="password" name="confirmation"></form>',$zoki),'<form method="post"><input name="one"><input name="two"><input type="password" name="password"><button type="submit">Login</button></form>'] as $ambiguous) {
 remote_throws(static fn()=>admin_provider_remote_discover(['url'=>$url,'body'=>$ambiguous],$discover),'browser_required');
}
$captchaCalls=0;
remote_throws(static fn()=>admin_provider_remote_run($discover,$credentials,$url,static fn()=>['status'=>200,'url'=>$url,'body'=>$zoki.'<img alt="Captcha">']),'browser_required');
$failureCalls=0;
remote_check(admin_provider_remote_run($discover,$credentials,$url,static function($u,$p)use(&$failureCalls,$zokiBefore){$failureCalls++;return $p===null?$zokiBefore:['status'=>200,'url'=>$u,'body'=>$zokiBefore['body']];})==='unconfirmed' && $failureCalls===2,'Failed credential POST never triggers browser retry');
$inspectCalls=[];$browserCalled=false;
$inspection=admin_provider_remote_inspect($url,static function($u,$p)use(&$inspectCalls,$zokiBefore){$inspectCalls[]=$p;return $zokiBefore;},static function()use(&$browserCalled){$browserCalled=true;return [];});
remote_check($inspection['transport']==='html' && $inspection['config']['username_field']==='identity' && $inspection['config']['mode']==='auto','Inspect recommends detected fields and auto mode');
remote_check($inspectCalls===[null] && !$browserCalled,'HTML inspection uses one GET and no browser or credential POST');
remote_check(!str_contains(json_encode($inspection),'fresh') && !isset($inspection['username'],$inspection['password'],$inspection['body']),'Inspection returns no hidden token, credentials or HTML');
$browserCalls=[];
$inspection=admin_provider_remote_inspect($url,static fn()=>['status'=>200,'url'=>$url,'body'=>'<div id="app"></div>'],static function($u,$c)use(&$browserCalls){$browserCalls[]=$c;return ['code'=>'detected','config'=>['username_by'=>'id','username_field'=>'login','password_by'=>'name','password_field'=>'pass','captcha'=>true,'captcha_input'=>'input.code','captcha_image'=>'img.code','username_selector'=>'never-persist-this','password'=>'must-not-return']];});
remote_check($inspection['transport']==='browser' && $inspection['config']['captcha'] && $inspection['config']['username_field']==='login','Inspection falls back to rendered browser discovery');
remote_check(count($browserCalls)===1 && !isset($browserCalls[0]['username'],$browserCalls[0]['password']),'Browser inspection receives no credentials');
remote_check(!str_contains(json_encode($inspection),'must-not-return') && !str_contains(json_encode($inspection),'never-persist-this'),'Browser metadata is strictly allowlisted');
foreach (['Invalid username or password','Nieprawidłowy login lub hasło','Ungültiges Passwort'] as $message) {
 remote_check(admin_provider_remote_feedback(['url'=>$url,'body'=>'<div role="alert">'.$message.'</div>'])==='rejected','Recognizes credential errors without exposing provider response');
 remote_check(admin_provider_remote_run($config,$credentials,$url,static fn($u,$p)=>$p===null?$before:['status'=>200,'url'=>$url,'body'=>'<div role="alert">'.$message.'</div>'.$html])==='rejected','Wrong login/password with HTTP 200 is rejected, not successful');
}
foreach (['Invalid CAPTCHA','CAPTCHA expired','Błędny kod z obrazka'] as $message) remote_check(admin_provider_remote_feedback(['url'=>$url,'body'=>'<div class="alert-danger">'.$message.'</div>'])==='captcha_rejected','Recognizes incorrect or expired CAPTCHA');
remote_check(admin_provider_remote_feedback(['url'=>$url,'body'=>'<div hidden role="alert">Invalid password</div><div class="modal"><div role="alert">Invalid password</div></div><p>Invalid password</p>'])==='','Hidden/help prose is not an authentication error');
remote_check(admin_provider_remote_run($config,$credentials,$url,static fn($u,$p)=>$p===null?$before:['status'=>429,'url'=>$url,'body'=>''])==='rate_limited','Remote throttling after one POST does not retry');
$initialCalls=0;
remote_check(admin_provider_remote_run($config,$credentials,$url,static function()use(&$initialCalls,$url){$initialCalls++;return ['status'=>429,'url'=>$url,'body'=>''];})==='rate_limited' && $initialCalls===1,'Remote 429 before login sends no credentials');
$initialCalls=0;
remote_check(admin_provider_remote_run($discover,$credentials,$url,static function()use(&$initialCalls,$url,$zoki){$initialCalls++;return ['status'=>200,'url'=>$url,'body'=>$zoki.'<input autocomplete="one-time-code">'];})==='manual' && $initialCalls===1,'OTP uses no browser fallback and no credential POST');
remote_check(admin_provider_remote_run($automatic,$credentials,$url,static fn($u,$p)=>$p===null?$before:['status'=>200,'url'=>$url,'body'=>'<button name="logout">Exit</button><input name="captcha_code">'])==='unconfirmed','A remaining CAPTCHA field prevents success');
$inlineCaptcha=$zoki.'<label>Captcha<input autocomplete="one-time-code" placeholder="Enter code"></label><img alt="Captcha" src="data:image/png;base64,fixture">';
remote_throws(static fn()=>admin_provider_remote_run($discover,$credentials,$url,static fn()=>['status'=>200,'url'=>$url,'body'=>$inlineCaptcha]),'browser_required');
$imageInspectCalls=0;
$imageInspection=admin_provider_remote_inspect($url,static fn()=>['status'=>200,'url'=>$url,'body'=>$inlineCaptcha],static function()use(&$imageInspectCalls){$imageInspectCalls++;return ['code'=>'detected','config'=>['captcha'=>true,'captcha_input'=>'input[placeholder="Enter code"]','captcha_image'=>'img[alt="Captcha"]']];});
remote_check($imageInspectCalls===1&&$imageInspection['transport']==='browser'&&$imageInspection['config']['captcha'],'Image CAPTCHA with one-time-code autocomplete uses browser detection instead of blanket OTP rejection');
putenv('PROVIDER_REMOTE_SECRET_KEY='.bin2hex(random_bytes(32)));
$cipher=admin_provider_remote_seal($credentials,4);
remote_check(!str_contains($cipher,$credentials['username'])&&!str_contains($cipher,'password with spaces'),'Secrets not stored as plaintext');
remote_check(admin_provider_remote_unseal($cipher,4)===$credentials,'Encrypted roundtrip');
remote_throws(static fn()=>admin_provider_remote_unseal($cipher,5),'storage');
remote_throws(static fn()=>admin_provider_remote_unseal(substr($cipher,0,-4).'AAAA',4),'storage');
putenv('PROVIDER_REMOTE_SECRET_KEY');
echo "PASS: {$checks} provider HTML/security/credential assertions\n";
