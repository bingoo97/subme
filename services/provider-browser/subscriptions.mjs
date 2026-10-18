import fs from 'node:fs/promises';
import {validURL,marker,challengeState} from './engine.mjs';
import {readOnlyTarget} from './guard.mjs';
const source=await fs.readFile(new URL('./subscription_agent.js',import.meta.url),'utf8');
const statuses=new Set(['found','not_found','ambiguous','table_missing','no_identity','unconfirmed']);

// One authenticated, in-memory browser session; no cookie export, persistence or provider writes.
export async function lookupSubscription(session,identity){
 const {page,config}=session,origin=validURL(page.url()).origin;
 if(session.policy)session.policy.readOnly=true;
 const cdp=await page.context().newCDPSession(page);
 const progress={listVisited:false,searched:[]};
 try{
  if(config.subscription_list_url){
   const target=validURL(config.subscription_list_url,origin);
   if(target.href!==page.url())await page.goto(target.href,{waitUntil:'domcontentloaded',timeout:10000});
   progress.listVisited=true;
  }
  for(let navigation=0;navigation<=4;navigation++){
   validURL(page.url(),origin);
   if((await challengeState(page)).manual)return {code:'manual'};
   if(!await marker(page,config.success_kind||'auto',config.success_value||''))return {code:'unconfirmed'};
   const {frameTree}=await cdp.send('Page.getFrameTree');
   const {executionContextId}=await cdp.send('Page.createIsolatedWorld',{frameId:frameTree.frame.id,worldName:'subme-subscription-reader'});
   await cdp.send('Runtime.evaluate',{expression:'globalThis.__submeReader='+source,contextId:executionContextId});
   const evaluated=await cdp.send('Runtime.evaluate',{expression:`globalThis.__submeReader.search(${JSON.stringify(config)},${JSON.stringify(identity)},${JSON.stringify(progress)})`,contextId:executionContextId,awaitPromise:true,returnByValue:true,timeout:20000});
   if(evaluated.exceptionDetails)throw Error('config');
   const result=evaluated.result?.value;
   if(result?.navigate){
    if(navigation===4)return {code:'unconfirmed'};
    const url=validURL(result.navigate,origin);
    if(!readOnlyTarget(url.href))return {code:'table_missing'};
    if(result.code==='search_navigation'){
     const next=result.searched;
     if(!Array.isArray(next)||next.length<=progress.searched.length||next.length>3||new Set(next).size!==next.length||next.some(type=>!['username','handle','email','url'].includes(type)))throw Error('limit');
     progress.searched=next;
    }else{
     if(progress.listVisited)return {code:'table_missing'};
     progress.listVisited=true;
    }
    await page.goto(url.href,{waitUntil:'domcontentloaded',timeout:10000});continue;
   }
   if(!statuses.has(result?.code))return {code:'unconfirmed'};
   const response={code:result.code,...(Number.isInteger(result.count)?{count:Math.min(1000,Math.max(0,result.count))}:{})};
   if(['found','ambiguous'].includes(result.code)){
    response.candidates=Array.isArray(result.candidates)?result.candidates.slice(0,20):[];response.truncated=!!result.truncated;
   }

   return response;
  }
  return {code:'unconfirmed'};
 }finally{await cdp.detach().catch(()=>{});}
}
