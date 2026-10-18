import assert from 'node:assert/strict';
import {chromium} from 'playwright-core';
import {publicIP,validURL,prepare,submit,fieldSelector,restrictedProxy,marker,discover,feedback} from './engine.mjs';
import http from 'node:http';
let checks=0;
function check(value,label){assert.ok(value,label);checks++;}
for(const ip of ['127.0.0.1','10.1.2.3','169.254.169.254','172.20.0.2','192.168.1.2','100.64.0.1','198.18.0.1','192.0.2.1','0.0.0.0','224.0.0.1','::1','::ffff:127.0.0.1','fe80::1','fc00::1','2001:db8::1','2002:7f00:1::'])check(!publicIP(ip),'Blocks special/private address');
for(const ip of ['1.1.1.1','8.8.8.8','2606:4700:4700::1111'])check(publicIP(ip),'Accepts public address');
for(const url of ['ftp://example.com','https://user:pass@example.com','https://example.com:0','https://example.com:65536','https://127.0.0.1','https://localhost']){assert.throws(()=>validURL(url));checks++;}
assert.throws(()=>validURL('https://other.example','https://provider.example'));checks++;
const proxy=await restrictedProxy('https://provider.example.invalid');
const proxyStatus=await new Promise((resolve,reject)=>{const u=new URL(proxy.url);const r=http.request({host:u.hostname,port:u.port,method:'CONNECT',path:'127.0.0.1:443'});r.on('connect',(res,socket)=>{socket.destroy();resolve(res.statusCode);});r.on('error',reject);r.end();});
check(proxyStatus===403,'Proxy rejects private tunnel before DNS');await proxy.close();
const b=await chromium.launch({headless:true,chromiumSandbox:true});
try {
    const credentials={username:'fixture-user',password:' secret with spaces '};
    const config={use_username:true,use_password:true,username_by:'id',username_field:'username',password_by:'id',password_field:'password',submit_by:'id',submit_field:'_submit',success_kind:'id',success_value:'account-menu',failure_kind:'id',failure_value:'login-error',captcha:false};
    const ctx=await b.newContext({serviceWorkers:'block'}); const page=await ctx.newPage();
    let submitted='',postCount=0,resultStatus=200; let resultHTML='<div id="account-menu">Account</div>';
    const html='<form action="/xc/login_check" method="post"><input type="hidden" name="_csrf_token" value="fresh-fixture-token"><input id="username" name="_username"><input id="password" name="_password" type="password"><button type="submit" id="_submit" name="_submit">Sign in</button></form>';
    await page.route('https://provider.example.invalid/**',async route=>{
        if(route.request().method()==='POST'){submitted=route.request().postData();postCount++;await route.fulfill({status:resultStatus,contentType:'text/html',body:resultHTML});}
        else await route.fulfill({contentType:'text/html',body:html});
    });
    check((await prepare(page,config,credentials,'https://provider.example.invalid/login')).code==='ready','HTML form prepared');
    check(await submit(page,config)==='success','HTML POST login confirmed');
    const values=new URLSearchParams(submitted);
    check(values.get('_username')===credentials.username&&values.get('_password')===credentials.password,'Uses actual form names and exact password');
    check(values.get('_csrf_token')==='fresh-fixture-token'&&values.has('_submit'),'Fresh CSRF and submit field retained');
    const automatic={...config,success_kind:'auto',success_value:''};
    resultHTML='<form><button name="admin_logout" type="submit">Exit</button></form>';
    check((await prepare(page,automatic,credentials,'https://provider.example.invalid/login')).code==='ready','Auto has no marker configuration requirement');
    check(await submit(page,automatic)==='success','Auto recognizes authenticated logout control');
    for(const fixture of ['<p>Logout</p>','<a href="https://other.example.invalid/logout">Logout</a>','<button hidden>Logout</button>','<button disabled>Logout</button>','<a href="/help/logout-guide">Help</a>','<a href="#">Logout</a>','<div aria-hidden="true"><button>Logout</button></div>']) {
        await page.setContent(fixture);check(!await marker(page,'auto',''),'Auto rejects ambiguous/hidden/external controls');
    }
    for(const fixture of ['<button>Wyloguj się</button>','<a href="/logout">Exit</a>','<a href="?action=logout">Exit</a>','<input type="submit" name="admin_logout">','<button aria-label="Sign out"><svg></svg></button>']) {
        await page.setContent(fixture);check(await marker(page,'auto',''),'Auto accepts real logout controls across panel styles');
    }
    resultHTML=html.replace(' id="_submit" name="_submit"','')+'<button name="admin_logout">Exit</button>';
    await prepare(page,automatic,credentials,'https://provider.example.invalid/login');
    check(await submit(page,automatic)==='unconfirmed','Logout plus remaining login inputs does not confirm authentication');
    resultHTML='<input autocomplete="one-time-code"><button name="admin_logout">Exit</button>';
    await prepare(page,automatic,credentials,'https://provider.example.invalid/login');
    check(await submit(page,automatic)==='manual','OTP challenge with logout requires a manual step');
    for(const [wrong,status,message,expected] of [
        [{...credentials,username:'wrong-user'},401,'Invalid username or password','rejected'],
        [{...credentials,password:'wrong-password'},200,'Nieprawidłowy login lub hasło','rejected'],
        [credentials,403,'Invalid CAPTCHA','captcha_rejected'],
        [credentials,429,'Please wait','rate_limited'],
    ]) {
        resultStatus=status;resultHTML='<div role="alert">'+message+'</div>'+html;
        await prepare(page,automatic,wrong,'https://provider.example.invalid/login');const count=postCount;
        check(await submit(page,automatic)===expected,'Wrong credentials/CAPTCHA and HTTP throttling return the right result');
        check(postCount===count+1,'Failure sends one credential POST with no retry');
    }
    resultStatus=200;
    const manualContext=await b.newContext();const manualPage=await manualContext.newPage();let manualPosts=0,hiddenWidget=false;
    await manualPage.route('https://provider.example.invalid/**',async route=>{if(route.request().method()==='POST')manualPosts++;await route.fulfill({contentType:'text/html',body:html+'<div class="h-captcha" data-sitekey="fixture"'+(hiddenWidget?' hidden':'')+'>Challenge</div>'});});
    check((await prepare(manualPage,{...config,mode:'auto'},credentials,'https://provider.example.invalid/login')).code==='manual','Third-party CAPTCHA requires manual login');
    check(manualPosts===0 && await manualPage.locator('#password').inputValue()==='','Unsolved widget never receives credentials');
    hiddenWidget=true;
    check((await prepare(manualPage,{...config,mode:'auto'},credentials,'https://provider.example.invalid/login')).code==='manual' && manualPosts===0,'Invisible CAPTCHA widgets also block automatic credential submission');
    await manualContext.close();
    await ctx.close();
    const ctx2=await b.newContext({serviceWorkers:'block'});const spa=await ctx2.newPage();
    const spaConfig={...config,mode:'auto',success_kind:'auto',success_value:'',username_by:'name',username_field:'username',password_by:'name',password_field:'password',submit_field:'',captcha:true,captcha_input:'input[placeholder="Enter code"]',captcha_image:'#captcha'};
    let posted,captchaPosts=0;
    const spaHTML=`<div id="login-error" role="alert">Invalid username or password</div><form><input id="username" name="username"><input id="password" type="password" name="password"><input placeholder="Enter code" maxlength="6"><canvas id="captcha" width="120" height="40"></canvas><button type="submit" disabled>Sign in</button></form><script>
    const f=document.querySelector('form'),c=f.querySelector('[placeholder]'),btn=f.querySelector('button');const cx=document.querySelector('canvas').getContext('2d');cx.fillStyle='white';cx.fillRect(0,0,120,40);cx.fillStyle='black';cx.font='22px sans-serif';cx.fillText('123456',5,28);
    f.addEventListener('input',()=>{btn.disabled=!(f.username.value&&f.password.value&&c.value.length===6);});
    f.addEventListener('submit',async e=>{e.preventDefault();const r=await fetch('/api/login',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({username:f.username.value,password:f.password.value,captcha:c.value})});const data=await r.json();document.body.innerHTML=data.ok?'<button name="admin_logout">Exit</button>':'<div id="login-error" role="alert">Invalid CAPTCHA</div>';});
    </script>`;
    await spa.route('https://provider.example.invalid/**',async route=>{
        if(route.request().method()==='POST'){posted=route.request().postDataJSON();captchaPosts++;await route.fulfill({contentType:'application/json',body:JSON.stringify({ok:posted.captcha==='123456'})});}
        else await route.fulfill({contentType:'text/html',body:spaHTML});
    });
    await spa.goto('https://provider.example.invalid/login');
    const suggested=await discover(spa,spaConfig);
    check(suggested.username_field==='username' && suggested.password_field==='password' && suggested.captcha,'Inspection discovers JS fields and CAPTCHA');
    check(await spa.locator('input[name="username"]').inputValue()==='' && posted===undefined,'Inspection does not fill or submit credentials');
    spaConfig.username_field='wrong';spaConfig.password_field='wrong';spaConfig.captcha=false;
    const challenge=await prepare(spa,spaConfig,credentials,'https://provider.example.invalid/login');
    check(await feedback(spa)==='rejected','Previous login error is present before a fresh successful attempt');
    check(challenge.code==='captcha_required'&&challenge.image.startsWith('data:image/png;base64,'),'SPA returns an in-memory CAPTCHA image');
    check(posted===undefined,'No login submission before manual CAPTCHA');
    check(await submit(spa,spaConfig,'123456')==='success','SPA JSON login works after manual fixture code');
    check(posted.username===credentials.username&&posted.password===credentials.password,'React-style fields and JSON handled by browser');
    await prepare(spa,spaConfig,credentials,'https://provider.example.invalid/login');const wrongCodeCount=captchaPosts;
    check(await submit(spa,spaConfig,'000000')==='captcha_rejected','Wrong manual image code is clearly rejected');
    check(captchaPosts===wrongCodeCount+1,'Wrong CAPTCHA does not cause another credential submission');
    await ctx2.close();
    const anonymousContext=await b.newContext();const anonymous=await anonymousContext.newPage();
    const anonymousConfig={...config,mode:'auto',success_kind:'auto',success_value:'',submit_field:''};
    await anonymous.route('https://provider.example.invalid/**',route=>route.fulfill({contentType:'text/html',body:'<input autocomplete="username"><input type="password"><button type="button" onclick="document.body.innerHTML=\'<button name=logout>Exit</button>\'">Log in</button>'}));
    check((await prepare(anonymous,anonymousConfig,credentials,'https://provider.example.invalid/login')).code==='ready','Auto supports JavaScript screens without a form, names or IDs');
    check(await anonymous.locator(anonymousConfig.password_selector).inputValue()===credentials.password,'Anonymous password field is correctly filled');
    check(await submit(anonymous,anonymousConfig)==='success','Anonymous JavaScript login is automatically confirmed');
    await anonymous.setContent('<form action="/register" method="post"><input name="username"><input type="password" autocomplete="new-password"><button type="submit">Create account</button></form>');
    await assert.rejects(discover(anonymous,anonymousConfig),/form/);checks++;
    await anonymous.unroute('https://provider.example.invalid/**');
    let followPosts=0;
    await anonymous.route('https://provider.example.invalid/**',async route=>{
        if(route.request().method()==='POST')followPosts++;
        const landing=new URL(route.request().url()).pathname==='/landing';
        await route.fulfill({contentType:'text/html',body:landing?'<a href="/login">Sign in</a><a href="/register">Create account</a>':'<form><input name="site-search"></form><input autocomplete="username"><input type="password"><button type="button">Log in</button>'});
    });
    await anonymous.goto('https://provider.example.invalid/landing');
    const followed=await discover(anonymous,anonymousConfig);
    check(followed.login_path==='/login'&&followPosts===0,'Auto follows one unique login link without a credential retry');
    check(!!followed.username_selector&&!!followed.password_selector,'Formless login can coexist with another search form');
    await anonymousContext.close();
    check(fieldSelector({username_by:'id',username_field:'react:id'},'username').includes('\\3a '),'Escapes special selector characters');
} finally {await b.close();}
console.log(`PASS: ${checks} browser HTML/SPA/CAPTCHA/network assertions`);
