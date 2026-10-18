import fs from 'node:fs/promises';
import assert from 'node:assert/strict';
import {chromium} from 'playwright-core';
let checks=0;const ck=(v,label)=>{assert.ok(v,label);checks++;};
const browser=await chromium.launch({headless:true,chromiumSandbox:true});
try{
 const context=await browser.newContext({viewport:{width:900,height:900}}),page=await context.newPage();let requests=0;
 const fixture='<meta name="viewport" content="width=device-width,initial-scale=1"><div class="modal" style="display:block;position:static"><section data-provider-browser data-order-id="100" data-provider-id="0" data-csrf="fixture" data-locale="pl"><button type="button" data-provider-browser-open>Sprawdź połączenie</button><button type="button" data-provider-browser-search disabled>Wyszukaj subskrypcję</button><div data-provider-browser-captcha hidden><img data-provider-browser-image><input data-provider-browser-answer><button data-provider-browser-complete>Potwierdź</button></div><div data-provider-browser-result hidden></div><div data-provider-browser-matches hidden></div></section></div>';
 const candidates=[{key:'match-1',source_id:'201',title:'Course A',handle:'fixture',email:'fixture@example.invalid',expires:'2027-01-01',status:'Active',conflict:false},{key:'match-2',source_id:'202',title:'<img src=x onerror=alert(1)> Course B',username:'CaseLogin42',handle:'different-account',email:'fixture@example.invalid',expires:'2028-01-01',status:'Expired',conflict:false}];
 await page.route('https://subme.example.invalid/**',async route=>{
  if(route.request().method()==='POST'){
   requests++;const connect=route.request().postData().includes('connect');
   await route.fulfill({contentType:'application/json',body:JSON.stringify(connect?{ok:true,code:'connected',challenge:'a'.repeat(48),expires_in:120,message:'Połączenie potwierdzone'}:{ok:true,code:'ambiguous',message:'Znaleziono kilka pasujących subskrypcji',count:2,candidates})});
  }else await route.fulfill({contentType:'text/html',body:fixture});
 });
 await page.goto('https://subme.example.invalid/admin/');
 await page.addStyleTag({content:await fs.readFile('/tmp/subme-provider-ui.css','utf8')});
 await page.addScriptTag({content:await fs.readFile('/tmp/subme-provider-ui.js','utf8')});
 await page.evaluate(()=>document.dispatchEvent(new Event('DOMContentLoaded')));
 await page.locator('[data-provider-browser-open]').click();await page.locator('[data-provider-browser-search]:enabled').waitFor();
 ck(requests===1,'Connection happens only on deliberate click');
 await page.locator('[data-provider-browser-search]').click();await page.locator('[data-provider-browser-matches] tbody tr').nth(1).waitFor();
 ck(await page.locator('[data-provider-browser-matches] tbody tr').count()===2,'Multiple results render a real table');
 ck(await page.locator('[data-provider-browser-matches] img').count()===0,'Remote strings are text, never executable HTML');
 const lineText=await page.locator('[data-provider-browser-matches] tbody tr').nth(1).innerText();
 ck(lineText.includes('CaseLogin42')&&!lineText.includes('@CaseLogin42')&&!lineText.includes('different-account'),'Provider line username is displayed exactly, ahead of the account handle');
 await page.getByRole('button',{name:'Wybierz subskrypcję 202',exact:true}).click();
 ck(await page.locator('[data-provider-browser-matches] tr.is-selected').count()===1&&await page.locator('[data-provider-browser-matches] button[aria-pressed="true"]').count()===1,'Selection is single and accessible');
 ck((await page.locator('[data-provider-browser-result]').innerText()).includes('#202')&&requests===2,'Selection changes the current modal only, without any request or provisioning');
 await page.setViewportSize({width:390,height:844});
 ck(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+1),'Selection list fits a narrow phone viewport');
 await page.evaluate(()=>document.querySelector('.modal').dispatchEvent(new Event('hidden.bs.modal')));
 ck(await page.locator('[data-provider-browser-matches]').isHidden(),'Closing the modal removes transient matches and selection');
 await context.close();console.log(`PASS: ${checks} selection UI/XSS/no-write/mobile/cleanup assertions`);
}finally{await browser.close();}
