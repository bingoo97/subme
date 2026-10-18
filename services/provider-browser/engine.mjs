import http from 'node:http';
import net from 'node:net';
import dns from 'node:dns/promises';
import {carriesCredentials} from './guard.mjs';

export function publicIP(ip) {
    const type = net.isIP(ip);
    if (!type) return false;
    const blocked = new net.BlockList();
    if (type === 4) {
        for (const [range,bits] of [['0.0.0.0',8],['10.0.0.0',8],['100.64.0.0',10],['127.0.0.0',8],['169.254.0.0',16],['172.16.0.0',12],['192.0.0.0',24],['192.0.2.0',24],['192.168.0.0',16],['198.18.0.0',15],['198.51.100.0',24],['203.0.113.0',24],['224.0.0.0',4],['240.0.0.0',4]]) blocked.addSubnet(range,bits,'ipv4');
        return !blocked.check(ip,'ipv4');
    }
    const global = new net.BlockList(); global.addSubnet('2000::',3,'ipv6');
    blocked.addSubnet('2001::',23,'ipv6'); blocked.addSubnet('2001:db8::',32,'ipv6'); blocked.addSubnet('2002::',16,'ipv6');
    return global.check(ip,'ipv6') && !blocked.check(ip,'ipv6');
}

export function validURL(value, origin) {
    if(typeof value!=='string'||value.length>2048||/[\x00-\x20\x7f\\]/.test(value))throw new Error('origin');
    const url = new URL(value);
    if (!['http:','https:'].includes(url.protocol) || url.username || url.password || value.includes('#') || url.port==='0' || (origin && url.origin !== origin)) throw new Error('origin');
    if (net.isIP(url.hostname) || !/^(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,63}$/.test(url.hostname)) throw new Error('destination');
    return url;
}

export async function restrictedProxy(origin,{lookup=dns.lookup,connect=net.connect,request=http.request}={}) {
    const target = validURL(origin), host = target.hostname;
    const port = Number(target.port || (target.protocol==='https:'?443:80));
    const sockets = new Set();
    const resolve=async()=>{
        const records=await lookup(host,{all:true,verbatim:true});
        if(!records.length || records.some(row=>!publicIP(row.address)))throw new Error('destination');
        return records[0];
    };
    const deny=res=>{res.writeHead(403,{'Connection':'close'});res.end();};
    const server = http.createServer(async(req,res) => {
        let upstream;
        try {
            if(target.protocol!=='http:')throw new Error('origin');
            const url=validURL(req.url,target.origin);
            if(req.headers.host!==target.host||req.headers.upgrade)throw new Error('origin');
            const record=await resolve();
            if(req.destroyed)return;
            const headers={...req.headers,host:target.host,connection:'close'};
            for(const key of ['proxy-authorization','proxy-connection','keep-alive','upgrade',...(req.headers.connection||'').toLowerCase().split(',').map(v=>v.trim()).filter(Boolean)])delete headers[key];
            headers.host=target.host;headers.connection='close';
            // Numeric validated IP prevents a second DNS lookup/rebinding. Preserve the provider Host header and port.
            upstream=request({hostname:record.address,family:record.family,port,method:req.method,path:url.pathname+url.search,headers,agent:false},response=>{
                response.once('error',()=>res.destroy());
                res.writeHead(response.statusCode,response.headers);response.pipe(res);
            });
            upstream.on('socket',socket=>{sockets.add(socket);socket.once('close',()=>sockets.delete(socket));});
            upstream.setTimeout(8000,()=>upstream.destroy());
            upstream.on('error',()=>{if(!res.headersSent){res.writeHead(502);res.end();}else res.destroy();});
            req.once('aborted',()=>upstream.destroy());
            res.once('close',()=>upstream.destroy());req.pipe(upstream);
        } catch {if(!res.headersSent)deny(res);else res.destroy();upstream?.destroy();}
    });
    server.on('connection', socket => { sockets.add(socket); socket.on('close',()=>sockets.delete(socket)); socket.on('error',()=>{}); });
    server.on('connect', async (req,client,head) => {
        let upstream;
        try {
            if (target.protocol!=='https:' || req.url !== host + ':' + port) throw new Error('origin');
            const record = await resolve();
            if (client.destroyed) return;
            upstream = connect({host:record.address,port,family:record.family});
            sockets.add(upstream); upstream.on('close',()=>sockets.delete(upstream));
            const timeout = setTimeout(()=>upstream.destroy(),8000);
            upstream.once('connect',()=>{
                clearTimeout(timeout);
                if (client.destroyed) { upstream.destroy(); return; }
                client.write('HTTP/1.1 200 Connection Established\r\n\r\n');
                if (head.length) upstream.write(head);
                upstream.pipe(client); client.pipe(upstream);
            });
            upstream.on('error',()=>{ clearTimeout(timeout); client.destroy(); });
            client.on('close',()=>upstream.destroy());
        } catch { client.end('HTTP/1.1 403 Forbidden\r\nConnection: close\r\n\r\n'); upstream?.destroy(); }
    });
    await new Promise((resolve,reject)=>{ server.once('error',reject);server.listen(0,'127.0.0.1',resolve); });
    return {url:'http://127.0.0.1:'+server.address().port,close:async()=>{for(const s of sockets)s.destroy();await new Promise(resolve=>server.close(resolve));}};
}

const escapeCSS = value => String(value).replace(/[^a-z0-9_-]/gi,c=>'\\'+c.codePointAt(0).toString(16)+' ');
export function fieldSelector(config,type) {
    if (config[type+'_selector']) return config[type+'_selector'];
    const value = config[type+'_field'];
    if (!value) throw new Error('form');
    return config[type+'_by']==='id' ? 'input#'+escapeCSS(value) : 'input[name="'+escapeCSS(value)+'"]';
}
async function uniqueVisible(locator) {
    if (await locator.count() !== 1 || !await locator.isVisible()) throw new Error('form');
    return locator;
}
export async function marker(page,kind,value) {
    if (kind==='auto') {
        return page.evaluate(()=>{
            let checked=0;
            for(const el of document.querySelectorAll('a,button,input[type="submit"],input[type="button"]')) {
                if(++checked>1000)break;
                if(!el.getClientRects().length || getComputedStyle(el).visibility==='hidden' || el.disabled || el.closest('[hidden],[aria-hidden="true"],template,noscript') || el.getAttribute('aria-disabled')==='true')continue;
                let logoutUrl=false;
                if(el.tagName==='A') {
                    const href=el.getAttribute('href');
                    if(!href || href==='#')continue;
                    try{
                        const u=new URL(href,location.href);if(u.origin!==location.origin || !['http:','https:'].includes(u.protocol))continue;
                        logoutUrl=/^(?:logout|log_out|signout|sign_out|wyloguj|abmelden)(?:\.(?:php|html?))?$/i.test(decodeURIComponent(u.pathname.replace(/\/$/,'').split('/').pop()));
                        for(const [key,value] of u.searchParams) if((/^(?:logout|signout|sign_out|wyloguj)$/i.test(key) && ['', '1', 'true'].includes(value.toLowerCase())) || (['action','do','task'].includes(key.toLowerCase()) && /^(?:logout|signout|sign_out|wyloguj)$/i.test(value)))logoutUrl=true;
                    }catch{continue;}
                }
                const label=(el.getAttribute('aria-label') || (el.tagName==='INPUT'?el.value:el.textContent) || '').trim().replace(/\s+/g,' ');
                if(logoutUrl || /^(?:wyloguj(?:\s+się)?|log\s*out|sign\s*out|abmelden|déconnexion|se\s+déconnecter|cerrar\s+sesión|sair|esci|выйти)$/iu.test(label)
                    || /(?:^|[^a-z0-9])(?:logout|log_out|signout|sign_out|wyloguj|abmelden)(?:$|[^a-z0-9])/i.test((el.getAttribute('name')||'')+' '+(el.id||'')))return true;
            }
            return false;
        });
    }
    if (!value) return false;
    if (kind==='url') {
        const path=new URL(page.url()).pathname;
        return path===value || path.startsWith(value.replace(/\/$/,'')+'/');
    }
    const locator=kind==='text'?page.getByText(value,{exact:false}):page.locator(kind==='id'?'#'+escapeCSS(value):'.'+escapeCSS(value));
    for (const item of await locator.all()) if (await item.isVisible()) return true;
    return false;
}

export async function feedback(page) {
    return page.locator('[role="alert"],.alert-danger,.alert-error,.error,.error-message,.invalid-feedback,.text-danger,.text-destructive,[id$="-error"],[id$="_error"]').evaluateAll(nodes=>{
        for(const node of nodes.slice(0,100)) {
            if(!node.getClientRects().length || getComputedStyle(node).visibility==='hidden' || node.closest('[hidden],[aria-hidden="true"]'))continue;
            const text=node.textContent.trim().slice(0,2000);
            if(/(?:captcha|kod(?:u)?\s+(?:z\s+obrazka|weryfikacyjny)).{0,100}(?:invalid|incorrect|wrong|expired|błęd|niepraw|wygas|ungültig)|(?:invalid|incorrect|wrong|expired|błęd|niepraw|wygas|ungültig).{0,100}(?:captcha|kod(?:u)?\s+(?:z\s+obrazka|weryfikacyjny))/iu.test(text))return 'captcha_rejected';
            if(/(?:invalid|incorrect|wrong|błęd|niepraw|ungültig|falsch).{0,100}(?:credentials|password|username|login|email|hasł|dane|passwort|benutzer)|(?:credentials|password|username|login|email|hasł|dane|passwort|benutzer).{0,100}(?:invalid|incorrect|wrong|błęd|niepraw|ungültig|falsch)|(?:authentication|login|logowanie)\s+(?:failed|denied|nieudane)|user\s+not\s+found/iu.test(text))return 'rejected';
        }
        return '';
    });
}

export async function challengeState(page) {
    return page.evaluate(()=>{
        const visible=el=>!!el.getClientRects().length && getComputedStyle(el).visibility!=='hidden' && !el.closest('[hidden],[aria-hidden="true"],template,noscript');
        if([...document.querySelectorAll('iframe,[data-sitekey],.g-recaptcha,.h-captcha,.cf-turnstile')].some(el=>!el.closest('template,noscript') && (el.tagName!=='IFRAME'||/recaptcha|hcaptcha|turnstile/i.test(el.src))))return {manual:true};
        const inputs=[...document.querySelectorAll('input')].slice(0,200).filter(visible);
        const forms=[...new Set(inputs.filter(el=>el.type==='password'&&el.form).map(el=>el.form))];
        const label=el=>[el.name,el.id,el.placeholder,el.getAttribute('aria-label'),...[...(el.labels||[])].map(l=>l.textContent)].join(' ');
        const codes=inputs.filter(el=>!el.disabled&&!el.readOnly&&['text','search','tel','number'].includes(el.type) && (!forms.length||forms.length!==1||el.form===forms[0]) && /captcha|enter\s+(?:the\s+)?code|kod\s+z\s+obrazka/i.test(label(el)));
        const graphics=[...document.querySelectorAll('img,canvas')].slice(0,200).filter(visible);
        const marked=graphics.filter(el=>/captcha/i.test((el.alt||'')+' '+el.id+' '+el.className+' '+(el.getAttribute('aria-label')||'')));
        const relevant=forms.length===1?marked.filter(el=>forms[0].contains(el)):marked;
        // A decorative canvas/logo elsewhere must not make a clear CAPTCHA ambiguous.
        const images=relevant.length?relevant:marked.length?marked:codes.length===1?graphics.filter(el=>el.tagName==='CANVAS'&&codes[0].parentElement?.contains(el)):[];
        const otp=inputs.filter(el=>/^(?:otp|totp|two_factor|two_factor_code|2fa_code)$/i.test(el.name)||el.autocomplete==='one-time-code');
        if(otp.some(el=>!codes.includes(el)||/^(?:otp|totp|two_factor|two_factor_code|2fa_code)$/i.test(el.name)))return {manual:true};
        if(!codes.length&&!images.length)return {};
        if(codes.length!==1||images.length!==1)return {manual:true};
        const selector=el=>{const parts=[];for(let n=el;n?.nodeType===1;n=n.parentElement){const peers=n.parentElement?[...n.parentElement.children].filter(p=>p.tagName===n.tagName):[n];parts.unshift(n.tagName.toLowerCase()+':nth-of-type('+(peers.indexOf(n)+1)+')');}return parts.join(' > ');};
        return {captcha:true,captcha_input:selector(codes[0]),captcha_image:selector(images[0])};
    });
}
export async function discover(page,config) {
        // Read the rendered DOM only; never guess among multiple login forms.
        const read=()=>page.evaluate(({useUsername,usePassword})=>{
            const visible=el=>!!el.getClientRects().length && getComputedStyle(el).visibility!=='hidden' && !el.closest('[hidden],[aria-hidden="true"],template');
            const path=el=>{
                const parts=[];
                for(let node=el;node && node.nodeType===1;node=node.parentElement){
                    const tag=node.tagName.toLowerCase();
                    const peers=node.parentElement?[...node.parentElement.children].filter(s=>s.tagName===node.tagName):[node];
                    parts.unshift(tag+':nth-of-type('+(peers.indexOf(node)+1)+')');
                }
                return parts.join(' > ');
            };
            const candidates=[];
            const forms=[...document.forms];
            // Some JavaScript login screens have no form element.
            const roots=forms.length?[...forms,...([...document.querySelectorAll('input')].some(el=>!el.form&&visible(el)&&el.type==='password')?[document.body]:[])]:[document.body];
            for(const root of roots) {
                const inputs=[...root.querySelectorAll('input')].filter(e=>visible(e)&&!e.disabled&&(root!==document.body||!forms.length||!e.form));
                const passwords=inputs.filter(e=>e.type==='password');
                if((usePassword&&passwords.length!==1)||(!usePassword&&passwords.length))continue;
                const users=inputs.filter(e=>['text','email','tel'].includes(e.type)&&!/captcha|otp|totp|verification|one.?time|code/i.test(e.name+' '+e.id+' '+e.autocomplete+' '+e.placeholder+' '+(e.getAttribute('aria-label')||'')+' '+[...(e.labels||[])].map(l=>l.textContent).join(' ')));
                const preferred=users.filter(e=>e.autocomplete==='username'||/^(?:_?username|_?user|login|identity|email|user_email|login_email)$/i.test(e.name)||e.type==='email');
                const usernames=preferred.length===1?preferred:users;
                if(useUsername&&usernames.length!==1)continue;
                let buttons=[...root.querySelectorAll('button,input[type="submit"]')].filter(e=>visible(e)&&e.type==='submit'&&(root!==document.body||!forms.length||!e.form));
                if(!buttons.length)buttons=[...root.querySelectorAll('button,[role="button"]')].filter(e=>visible(e)&&(root!==document.body||!forms.length||!e.form)&&/^(?:log\s*in|sign\s*in|zaloguj(?:\s+się)?|anmelden|connexion|login)$/i.test(e.textContent.trim()));
                if(buttons.length!==1)continue;
                const purpose=(root.getAttribute('action')||'')+' '+(root.id||'')+' '+(buttons[0].name||'')+' '+buttons[0].textContent+' '+(buttons[0].value||'');
                if(/register|registration|signup|sign[ _-]?up|create[ _-]?(?:an?[ _-]?)?account|reset[ _-]?password|password[ _-]?reset|forgot|utwórz[ _-]?konto|zarejestruj/i.test(purpose) || passwords.some(e=>e.autocomplete==='new-password'))continue;
                const out={submit_selector:path(buttons[0])};
                if(useUsername)out.username_selector=path(usernames[0]);
                if(usePassword)out.password_selector=path(passwords[0]);
                for(const [type,el] of [['username',useUsername?usernames[0]:null],['password',usePassword?passwords[0]:null],['submit',buttons[0]]]) if(el){
                    out[type+'_by']=el.name?'name':'id';out[type+'_field']=el.name||el.id||'';
                }
                candidates.push(out);
            }
            return candidates.length===1?candidates[0]:null;
        },{useUsername:!!config.use_username,usePassword:!!config.use_password});
        // Allow late-rendered React/Vue forms without submitting anything.
        let detected,followed=false;
        for(let step=0;step<2;step++){
            const end=Date.now()+7000;
            do {detected=await read();if(detected)break;await page.waitForTimeout(150);}while(Date.now()<end);
            if(detected)break;
            if((await challengeState(page)).manual)throw Error('manual');
            if(step===1)break;
            // Follow one uniquely identified sign-in link, never a guessed URL or a credential retry.
            const link=await page.evaluate(()=>{
                const urls=[...document.querySelectorAll('a[href]')].filter(el=>el.getClientRects().length&&getComputedStyle(el).visibility!=='hidden').map(el=>{
                    try{const u=new URL(el.href);return u.origin===location.origin&&['http:','https:'].includes(u.protocol)&&u.href!==location.href&&!/register|signup|reset|forgot|delete|logout/i.test(u.href+' '+el.textContent)&&(/^(log\s*in|sign\s*in|zaloguj(?: się)?|anmelden|connexion|login)$/i.test(el.textContent.trim())||/\/(login|signin|sign-in)(?:\.php)?\/?$/i.test(u.pathname))?u.href:'';}catch{return '';}
                }).filter(Boolean);
                const unique=[...new Set(urls)];return unique.length===1?unique[0]:'';
            });
            if(!link)break;
            await page.goto(validURL(link,new URL(page.url()).origin).href,{waitUntil:'domcontentloaded',timeout:10000});followed=true;
        }
        if(!detected)throw new Error('form');
        const challenge=await challengeState(page);
        if(challenge.captcha)Object.assign(detected,challenge);
        if(followed){const u=new URL(page.url());detected.login_path=u.pathname+u.search;}
        return detected;
}
export async function prepare(page,config,credentials,url,{navigate=true}={}) {
    if (config.two_factor) return {code:'manual'};
    if (navigate) await page.goto(url,{waitUntil:'domcontentloaded',timeout:15000});
    if (config.mode==='auto') Object.assign(config,await discover(page,config));
    const challenge=await challengeState(page);
    if(challenge.manual)return {code:'manual'};
    if(challenge.captcha)Object.assign(config,challenge);
    for (const type of ['username','password']) if (config['use_'+type]) {
        const field=page.locator(fieldSelector(config,type));
        await field.waitFor({state:'visible',timeout:10000});
        await uniqueVisible(field);
    }
    if (await marker(page,config.success_kind,config.success_value)) throw new Error('baseline');
    for (const type of ['username','password']) if (config['use_'+type]) await page.locator(fieldSelector(config,type)).fill(credentials[type]);
    if (config.captcha) {
        const input=await uniqueVisible(page.locator(config.captcha_input));
        if (await input.evaluate(e=>e.tagName!=='INPUT' || e.type==='password')) throw new Error('form');
        const image=await uniqueVisible(page.locator(config.captcha_image));
        if (await image.evaluate(e=>!['IMG','CANVAS'].includes(e.tagName))) throw new Error('form');
        if(await image.evaluate(e=>e.tagName==='IMG'))await page.waitForFunction(selector=>{
            const el=document.querySelector(selector);return el instanceof HTMLImageElement&&el.complete&&el.naturalWidth>0;
        },config.captcha_image,{timeout:5000});
        const box=await image.boundingBox();
        if (!box || box.width>600 || box.height>300 || box.width<8 || box.height<8) throw new Error('form');
        const bytes=await image.screenshot({type:'png',timeout:5000});
        if (bytes.length>262144) throw new Error('limit');
        return {code:'captcha_required',image:'data:image/png;base64,'+bytes.toString('base64')};
    }
    return {code:'ready'};
}
export async function submit(page,config,answer='',{timeout=8000}={}) {
    if(config.networkPolicy?.failure)return config.networkPolicy.failure;
    if (config.captcha) {
        if (!answer || answer.length>128) throw new Error('config');
        await (await uniqueVisible(page.locator(config.captcha_input))).fill(answer);
    }
    let button;
    if (config.submit_selector) button=page.locator(config.submit_selector);
    else if (config.submit_field) button=page.locator(config.submit_by==='id'?'#'+escapeCSS(config.submit_field):'[name="'+escapeCSS(config.submit_field)+'"]');
    else button=page.locator('button[type="submit"],input[type="submit"]');
    button=await uniqueVisible(button);
    if (!await button.isEnabled()) throw new Error('form');
    let httpFailure='',authResponded=false,authRespondedAt=0;
    const existingFeedback=await feedback(page);
    const existingFailure=await marker(page,config.failure_kind,config.failure_value);
    const credentials={password:config.use_password?await page.locator(fieldSelector(config,'password')).inputValue():''};
    const target=await button.evaluate(el=>el.form ? new URL(el.getAttribute('formaction')||el.form.getAttribute('action')||location.href,location.href).href : '');
    const onResponse=response=>{
        const request=response.request();
        if(!authResponded && request.method()==='POST' && ['document','xhr','fetch'].includes(request.resourceType())
            && (request.url()===target || /(?:^|\/)(?:login|signin|sign-in|auth)(?:\/|$|\?)/i.test(new URL(request.url()).pathname) || carriesCredentials(request.postData(),credentials))) {
            authResponded=true;
            authRespondedAt=Date.now();
            if(response.status()===429)httpFailure='rate_limited';
            else if([401,403].includes(response.status()) && !httpFailure)httpFailure='rejected';
        }
    };
    page.on('response',onResponse);
    try {
    if(config.networkPolicy)config.networkPolicy.allowSubmit=true;
    await button.click({timeout:8000,noWaitAfter:true});
    // Wait on DOM evidence, not a fixed sleep; this also handles SPA state changes.
    const end=Date.now()+timeout;
    while(Date.now()<end) {
        if(config.networkPolicy?.failure)return config.networkPolicy.failure;
        try {
        const error=await feedback(page);
        if(error && (error!==existingFeedback || (authResponded && Date.now()-authRespondedAt>=400)))return error;
        if(httpFailure && Date.now()-authRespondedAt>=400)return httpFailure;
        if ((!existingFailure || (authResponded && Date.now()-authRespondedAt>=400)) && await marker(page,config.failure_kind,config.failure_value)) return 'rejected';
        if (!httpFailure && await marker(page,config.success_kind,config.success_value)) {
            const remainingChallenge=await challengeState(page);
            if(remainingChallenge.manual || remainingChallenge.captcha)return 'manual';
            let loginPresent=false;
            for(const type of ['username','password']) if(config['use_'+type]) {
                for(const field of await page.locator(fieldSelector(config,type)).all()) if(await field.isVisible())loginPresent=true;
            }
            for(const field of await page.locator('input[autocomplete="one-time-code"],input[name="otp"],input[name="totp"],input[name="two_factor"],input[name="two_factor_code"],input[name="2fa_code"]').all()) if(await field.isVisible())loginPresent=true;
            if (!loginPresent) return 'success';
        }
        } catch(error) {
            if(!/Execution context was destroyed|Cannot find context|most likely because of a navigation/i.test(error.message))throw error;
        }
        await page.waitForTimeout(250);
    }
    return 'unconfirmed';
    } finally {page.off('response',onResponse);}
}
