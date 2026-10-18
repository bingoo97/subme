import assert from 'node:assert/strict';
import {chromium} from 'playwright-core';
import {parseCaptchaTSV,recognizeCaptcha} from './ocr.mjs';
import {autoCaptchaLogin} from './auto_captcha.mjs';
import {prepare,submit} from './engine.mjs';
import {EventEmitter} from 'node:events';
import {PassThrough} from 'node:stream';
let checks=0;const check=(value,label)=>{assert.ok(value,label);checks++;};
const header='level\tpage_num\tblock_num\tpar_num\tline_num\tword_num\tleft\ttop\twidth\theight\tconf\ttext';
const tsv=(value,confidence=97)=>header+'\n5\t1\t1\t1\t1\t1\t0\t0\t200\t60\t'+confidence+'\t'+value+'\n';
check(parseCaptchaTSV(tsv('563924'))==='563924','Confident single code accepted');
for(const value of [tsv('563924',91),tsv('563924',0),tsv('563924','NaN'),tsv('563924',101),tsv('abc$123'),tsv('12'),tsv('563924')+tsv('999999').split('\n')[1],header+'\n',tsv('x'.repeat(13))])check(parseCaptchaTSV(value)===null,'Uncertain/invalid/multiple fragments never become an answer');
check(parseCaptchaTSV(tsv('aB13'),{digits:true})===null,'Digits-only mode does not guess letters as numbers');
check(parseCaptchaTSV(tsv('563924'),{length:5})===null,'Explicit code length respected');
check(await recognizeCaptcha(Buffer.from('not a PNG'))===null,'Non-PNG data rejected before launching OCR');
const browser=await chromium.launch({headless:true,chromiumSandbox:true});
try{
 const context=await browser.newContext({serviceWorkers:'block'}),page=await context.newPage();
 await page.setContent('<canvas width="300" height="80"></canvas>');
 const data=await page.evaluate(()=>{const c=document.querySelector('canvas'),ctx=c.getContext('2d');ctx.fillStyle='white';ctx.fillRect(0,0,300,80);ctx.fillStyle='black';ctx.font='bold 48px monospace';ctx.fillText('563924',25,58);return c.toDataURL('image/png');});
 const bytes=Buffer.from(data.split(',')[1],'base64');
 let killed=false,spawnOptions;
 const fake=({output='',fail=false,hang=false}={})=>(file,args,options)=>{
  spawnOptions={file,args,options};const child=new EventEmitter();child.stdin=new PassThrough();child.stdout=new PassThrough();
  child.kill=()=>{killed=true;child.emit('close',137);};
  child.stdin.once('finish',()=>queueMicrotask(()=>{if(fail){child.emit('error',new Error('fixture spawn failure'));return;}if(!hang){child.stdout.write(output);child.emit('close',0);}}));return child;
 };
 check(await recognizeCaptcha(bytes,{}, {spawnProcess:fake({output:tsv('563924')})})==='563924','Bounded OCR output can produce one candidate');
 check(spawnOptions.options.shell===false&&spawnOptions.options.env.OMP_THREAD_LIMIT==='1'&&!('PROVIDER_BROWSER_TOKEN' in spawnOptions.options.env)&&spawnOptions.args[0]==='stdin'&&spawnOptions.args[1]==='stdout','OCR has one thread, no shell/files or inherited worker secret');
 check(await recognizeCaptcha(bytes,{}, {spawnProcess:fake({fail:true})})===null,'Missing/unavailable OCR falls back without failing login');
 killed=false;check(await recognizeCaptcha(bytes,{}, {spawnProcess:fake({output:'x'.repeat(17000)})})===null&&killed,'Oversized output kills OCR and returns no answer');
 killed=false;const began=Date.now();
 check(await recognizeCaptcha(bytes,{}, {spawnProcess:fake({hang:true})})===null&&killed&&Date.now()-began<3500,'Stalled OCR is killed within the fixed time budget');
 check(await recognizeCaptcha(bytes,{digits:true,length:6})==='563924','Installed local Tesseract reads a synthetic clear code through stdin/stdout');
 const huge=Buffer.from(bytes);huge.writeUInt32BE(601,16);
 check(await recognizeCaptcha(huge)===null,'Oversized crop rejected before launching OCR');
 let posts=0,lastPost;
 await page.route('http://provider.example.invalid:2052/**',async route=>{
  if(route.request().method()==='POST'){
   posts++;lastPost=route.request().postDataJSON();await route.fulfill({contentType:'application/json',body:JSON.stringify({ok:lastPost.captcha==='563924'})});return;
  }
  await route.fulfill({contentType:'text/html',body:`<form><input autocomplete="username"><input type="password"><label>Captcha<input placeholder="Enter code" maxlength="6" pattern="[0-9]{6}"></label><img alt="Captcha" width="300" height="80" src="${data}"><button type="submit" disabled>Sign in</button></form><script>
  const f=document.querySelector('form'),fields=f.querySelectorAll('input');f.addEventListener('input',()=>f.querySelector('button').disabled=!(fields[0].value&&fields[1].value&&fields[2].value.length===6));f.addEventListener('submit',async e=>{e.preventDefault();const r=await fetch('/api/login',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({username:fields[0].value,password:fields[1].value,captcha:fields[2].value})});document.body.innerHTML=(await r.json()).ok?'<a href="/logout">Exit</a>':'<div role="alert">Invalid CAPTCHA</div>';});</script>`});
 });
 const config=()=>({mode:'auto',use_username:true,use_password:true,success_kind:'auto',success_value:'',failure_kind:'id',failure_value:'',captcha_ocr:true,captcha_ocr_digits:true});
 const credentials={username:'fixture-user',password:'fixture-pass'},url='http://provider.example.invalid:2052/login';
 let cfg=config(),outcome=await prepare(page,cfg,credentials,url);
 const automated=await autoCaptchaLogin(page,cfg,outcome);
 check(automated.code==='success'&&automated.ocr_used&&posts===1,'Confident OCR completes one login automatically');
 check(lastPost.username===credentials.username&&lastPost.password===credentials.password&&lastPost.captcha==='563924','OCR code is filled in the original authenticated browser flow');
 check(!JSON.stringify(automated).includes('563924')&&!automated.image,'OCR code/image not returned in success metadata');
 cfg=config();outcome=await prepare(page,cfg,credentials,url);const before=posts;
 const fallback=await autoCaptchaLogin(page,cfg,outcome,{recognize:async()=>null});
 check(fallback.code==='captcha_required'&&fallback.ocr_fallback&&posts===before,'Uncertain reading offers manual entry without a credential POST');
 check(await submit(page,cfg,'563924')==='success'&&posts===before+1,'Manual entry works in the same session after an uncertain OCR result');
 cfg={...config(),captcha_ocr:false};outcome=await prepare(page,cfg,credentials,url);let called=false;
 check((await autoCaptchaLogin(page,cfg,outcome,{recognize:async()=>{called=true;return '563924';}})).code==='captcha_required'&&!called,'Disabled OCR never runs the recognizer');
 cfg=config();outcome=await prepare(page,cfg,credentials,url);const beforeRotate=posts;
 const rotated=await autoCaptchaLogin(page,cfg,outcome,{recognize:async()=>{await page.locator('img').evaluate(el=>el.style.opacity='.5');return '563924';}});
 check(rotated.ocr_fallback&&posts===beforeRotate,'Changed image during OCR never submits a stale answer');
 cfg=config();outcome=await prepare(page,cfg,credentials,url);const beforeWrong=posts;
 const wrong=await autoCaptchaLogin(page,cfg,outcome,{recognize:async()=> '000000'});
 check(wrong.code==='captcha_rejected'&&wrong.ocr_used&&posts===beforeWrong+1,'Incorrect automatic answer sends one POST, with no retry');
 called=false;
 check((await autoCaptchaLogin(page,{...config(),two_factor:true},outcome,{recognize:async()=>{called=true;return '563924';}})).code==='captcha_required'&&!called,'OCR never handles configured 2FA');
 await context.close();console.log(`PASS: ${checks} local-OCR/confidence/opt-in/session/fallback/no-retry assertions`);
}finally{await browser.close();}
