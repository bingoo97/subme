import http from 'node:http';
import crypto from 'node:crypto';
import {chromium} from 'playwright-core';
import {restrictedProxy,validURL,prepare,submit,discover,challengeState} from './engine.mjs';
import {OperationGate,allowRequest} from './guard.mjs';
import {providerHandler} from './protocol.mjs';
import {lookupSubscription} from './subscriptions.mjs';
import {autoCaptchaLogin} from './auto_captcha.mjs';
const token=process.env.PROVIDER_BROWSER_TOKEN;
if (!token || token.length<32) process.exit(1);
const sessions=new Map();
let starting=false;
const gate=new OperationGate();
async function close(id) {
    const session=sessions.get(id); if(!session)return;
    sessions.delete(id); clearTimeout(session.timer);
    await session.browser.close().catch(()=>{}); await session.proxy.close().catch(()=>{});
}
async function start(data,inspect=false,connection=false) {
    if (starting) throw new Error('busy');
    starting=true;
    let browser,proxy,id,policy,expired=false;
    const deadline=setTimeout(()=>{expired=true;browser?.close().catch(()=>{});proxy?.close().catch(()=>{});},30000);
    try {
        for(const [activeId,session] of sessions)if(session.owner===data.owner && !session.busy)await close(activeId);
        if(sessions.size>=1)throw new Error('busy');
        const url=validURL(data.url);
        proxy=await restrictedProxy(url.origin);
        browser=await chromium.launch({headless:true,chromiumSandbox:true,timeout:15000,proxy:{server:proxy.url,bypass:'<-loopback>'},args:['--disable-quic','--disable-background-networking','--disable-features=WebRtcHideLocalIpsWithMdns','--force-webrtc-ip-handling-policy=disable_non_proxied_udp','--disk-cache-size=0']});
        if(expired)throw new Error('limit');
        const context=await browser.newContext({acceptDownloads:false,serviceWorkers:'block',ignoreHTTPSErrors:false,viewport:{width:1440,height:960}});
        policy={requestCount:0,credentialPosts:0,allowSubmit:false,failure:''};
        data.config.networkPolicy=policy;
        await context.routeWebSocket('**/*',socket=>socket.close());
        await context.route('**/*',async route=>{
            try {
                const request=route.request(); validURL(request.url(),url.origin);
                if(!allowRequest(policy,request,{inspect,credentials:data.credentials})){await route.abort();return;}
                await route.continue();
            } catch {
                await route.abort().catch(()=>{});
                if(policy.failure){if(id)close(id).catch(()=>{});else browser.close().catch(()=>{});}
            }
        });
        const page=await context.newPage(); page.setDefaultTimeout(5000);
        context.on('page',popup=>{if(popup!==page)popup.close().catch(()=>{});});
        if(inspect) {
            await page.goto(url.href,{waitUntil:'domcontentloaded',timeout:15000});
            if((await challengeState(page)).manual){await browser.close();await proxy.close();return {code:'manual'};}
            const detected=await discover(page,data.config);
            await browser.close();await proxy.close();
            return {code:'detected',config:detected};
        }
        let outcome=await prepare(page,data.config,data.credentials,url.href);
        outcome=await autoCaptchaLogin(page,data.config,outcome);
        if(expired)throw new Error('limit');
        if(policy.failure)throw new Error(policy.failure);
        if(outcome.code==='manual') { await browser.close();await proxy.close();return outcome; }
        let authenticated=false;
        if(outcome.code==='ready'||outcome.ocr_used) {
            const code=outcome.code==='ready'?await submit(page,data.config):outcome.code;
            if(!connection||code!=='success'){await browser.close();await proxy.close();return {code,...(outcome.ocr_used?{ocr_used:true}:{})};}
            authenticated=true;
        }
        id=crypto.randomBytes(24).toString('hex');
        const timer=setTimeout(()=>close(id),120000);timer.unref();
        sessions.set(id,{browser,proxy,page,config:data.config,owner:data.owner,binding:data.binding,timer,busy:false,connection,authenticated,until:Date.now()+120000,policy});
        return {...(authenticated?{code:'connected',...(outcome.ocr_used?{ocr_used:true}:{})}:outcome),challenge:id,expires_in:120};
    } catch(error) {
        if(id)await close(id);
        else {await browser?.close().catch(()=>{});await proxy?.close().catch(()=>{});}
        throw expired?new Error('limit'):policy?.failure?new Error(policy.failure):error;
    } finally {clearTimeout(deadline);starting=false;}
}
const server=http.createServer(providerHandler({token,sessions,start,close,gate,submit,lookup:lookupSubscription}));
server.requestTimeout=35000;server.headersTimeout=5000;
server.listen(3210,'0.0.0.0');
process.on('SIGTERM',async()=>{await Promise.all([...sessions.keys()].map(close));server.close(()=>process.exit(0));});
