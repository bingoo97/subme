import assert from 'node:assert/strict';
import {chromium} from 'playwright-core';
import {challengeState,discover,prepare,submit} from './engine.mjs';
let checks=0;const check=(value,label)=>{assert.ok(value,label);checks++;};
const browser=await chromium.launch({headless:true,chromiumSandbox:true});
try{
 const context=await browser.newContext({serviceWorkers:'block'}),page=await context.newPage();let posts=0,images=0,lastPost;
 const credentials={username:'fixture-user',password:'fixture-password'};
 const config=()=>({mode:'auto',use_username:true,use_password:true,success_kind:'auto',success_value:'',failure_kind:'id',failure_value:''});
 let labelOnly=false,oneTimeCode=false,broken=false;
 const fixture=()=>`<meta name="viewport" content="width=device-width,initial-scale=1"><style>form{width:300px}input{display:block;margin:8px}img{width:120px;height:40px;object-fit:cover}.image{display:flex;align-items:center;justify-content:center;width:120px;height:40px;position:relative;overflow:hidden;background:white;border:1px solid #ddd;border-radius:6px}.overlay{position:absolute;inset:0;opacity:0;pointer-events:none}</style>
 <form><label>Username<input autocomplete="username" placeholder="Enter username"></label><label>Password<input type="password" autocomplete="current-password"></label>
 ${labelOnly?'<label>Captcha':''}<input ${labelOnly?'':'placeholder="Enter code"'} ${oneTimeCode?'autocomplete="one-time-code"':''}>${labelOnly?'</label>':''}
 <div class="image" title="Click to refresh"><img alt="Captcha" draggable="false"><div class="overlay"><svg width="24" height="24"></svg></div></div><button type="submit" disabled>Sign In</button></form><canvas id="decoration" width="10" height="10"></canvas>
 <script>
 const form=document.querySelector('form'),inputs=form.querySelectorAll('input'),image=form.querySelector('img');
 const canvas=document.createElement('canvas');canvas.width=240;canvas.height=80;const ctx=canvas.getContext('2d');ctx.fillStyle='white';ctx.fillRect(0,0,240,80);ctx.fillStyle='#ab1234';ctx.font='30px sans-serif';ctx.fillText('TEST42',20,50);
 image.src=${broken?"'data:image/png;base64,broken'":"canvas.toDataURL('image/png')"};
 form.addEventListener('input',()=>form.querySelector('button').disabled=!(inputs[0].value&&inputs[1].value&&inputs[2].value.length===6));
 form.addEventListener('submit',async e=>{e.preventDefault();const response=await fetch('/api/login',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({username:inputs[0].value,password:inputs[1].value,captcha:inputs[2].value})});const result=await response.json();document.body.innerHTML=result.ok?'<a href="/logout">Exit</a>':'<div role="alert">Invalid CAPTCHA</div>';});
 </script>`;
 await page.route('http://provider.example.invalid:2052/**',async route=>{
  const request=route.request();if(request.resourceType()==='image')images++;
  if(request.method()==='POST'){posts++;lastPost=request.postDataJSON();await route.fulfill({contentType:'application/json',body:JSON.stringify({ok:lastPost.captcha==='TEST42'})});}
  else await route.fulfill({contentType:'text/html',body:fixture()});
 });
 const url='http://provider.example.invalid:2052/login';
 await page.goto(url);const detected=await discover(page,config());
 check(detected.captcha&&!!detected.captcha_image&&!!detected.captcha_input,'Data URI image with overlay is detected with anonymous code input');
 check(posts===0&&await page.locator('input[type=password]').inputValue()==='','Detection sends and fills no credentials');
 let cfg=config(),challenge=await prepare(page,cfg,credentials,url);
 check(challenge.code==='captcha_required'&&/^data:image\/png;base64,/.test(challenge.image),'Image is displayed through a memory-only PNG');
 const bytes=Buffer.from(challenge.image.split(',')[1],'base64');
 check(bytes.readUInt32BE(16)===120&&bytes.readUInt32BE(20)===40,'CAPTCHA crop is the rendered image, not the provider page');
 check(posts===0&&images===0,'Inline image makes no image network request and no login submission');
 await assert.rejects(submit(page,cfg,''),/config/);checks++;
 check(posts===0,'Empty answer never submits login');
 check(await submit(page,cfg,'TEST42')==='success'&&posts===1,'Manual code completes anonymous JavaScript login with disabled submit');
 check(lastPost.username===credentials.username&&lastPost.password===credentials.password&&lastPost.captcha==='TEST42','Entered code and credentials use the same browser session');
 cfg=config();await prepare(page,cfg,credentials,url);const before=posts;
 check(await submit(page,cfg,'WRONG0')==='captcha_rejected'&&posts===before+1,'Wrong code returns a clear error with no retry');
 labelOnly=true;oneTimeCode=true;cfg=config();challenge=await prepare(page,cfg,credentials,url);
 check(challenge.code==='captcha_required','Wrapped Captcha label works without placeholder/name/id, including one-time-code autocomplete');
 check(await submit(page,cfg,'TEST42')==='success','Label-only CAPTCHA login succeeds after manual answer');
 await page.goto(url);await page.evaluate(()=>{const otp=document.createElement('input');otp.name='otp';document.querySelector('form').append(otp);});
 check((await challengeState(page)).manual,'Real OTP remains manual even alongside an image CAPTCHA');
 await page.goto(url);await page.evaluate(()=>{const img=document.querySelector('img').cloneNode();document.querySelector('form').append(img);});
 check((await challengeState(page)).manual,'Two CAPTCHA images are not guessed');
 await page.goto(url);await page.evaluate(()=>{document.querySelector('img').hidden=true;});
 check((await challengeState(page)).manual,'Hidden CAPTCHA image cannot confirm the challenge');
 await page.goto(url);await page.evaluate(()=>{const widget=document.createElement('div');widget.className='h-captcha';widget.dataset.sitekey='fixture';document.body.append(widget);});
 check((await challengeState(page)).manual,'Third-party CAPTCHA is not bypassed as an image challenge');
 broken=true;const beforeBroken=posts;
 await assert.rejects(prepare(page,config(),credentials,url),/timeout/i);checks++;
 check(posts===beforeBroken,'Unreadable or broken image never submits credentials');
 await context.close();console.log(`PASS: ${checks} data-URI/image-CAPTCHA/labels/OTP/no-retry assertions`);
}finally{await browser.close();}
