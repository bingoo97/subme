import assert from 'node:assert/strict';
import http from 'node:http';
import {chromium} from 'playwright-core';
import {restrictedProxy,prepare,submit} from './engine.mjs';
import {lookupSubscription} from './subscriptions.mjs';
let checks=0;const check=(v,label)=>{assert.ok(v,label);checks++;};
const credentials={username:'fixture-user',password:' secret with spaces '};
const config={use_username:true,use_password:true,username_by:'id',username_field:'username',password_by:'id',password_field:'password',submit_by:'id',submit_field:'_submit',success_kind:'auto',success_value:'',failure_kind:'id',failure_value:'login-error',captcha:false};
const html='<form action="/login" method="post"><input type="hidden" name="_csrf_token" value="fresh-fixture-token"><input id="username" name="_username"><input id="password" name="_password" type="password"><button type="submit" id="_submit" name="_submit">Sign in</button></form>';
    // Exercise Chromium's real HTTP proxy, cookie session and list discovery on a nonstandard port.
    // Only this fixture redirects a validated synthetic public IP to a local test server.
    let loginPosts=0,searchReads=0;
    const fixture=http.createServer(async(req,res)=>{
        res.setHeader('Content-Type','text/html; charset=utf-8');
        const url=new URL(req.url,'http://provider.example.invalid:2052');
        if(url.pathname==='/landing'){res.end('<a href="/login">Sign in</a>');return;}
        if(url.pathname==='/login'&&req.method==='POST'){
            let body='';for await(const chunk of req)body+=chunk;
            const post=new URLSearchParams(body);loginPosts++;
            if(post.get('_username')!==credentials.username||post.get('_password')!==credentials.password){res.writeHead(401);res.end('Rejected');return;}
            res.writeHead(302,{'Set-Cookie':'fixture_session=ok; Path=/; HttpOnly','Location':'/dashboard'});res.end();return;
        }
        if(url.pathname==='/login'){res.end(html);return;}
        if(!req.headers.cookie?.includes('fixture_session=ok')){res.writeHead(401);res.end();return;}
        const logout='<a href="/logout" aria-label="Sign out">Exit</a>';
        if(url.pathname==='/lines/users'){
            searchReads++;res.end(logout+'<table><thead><tr><th>ID</th><th>Username</th><th>Expires</th></tr></thead><tbody><tr><td>42</td><td>CaseLogin42</td><td>2027-01-01</td></tr></tbody></table>');return;
        }
        res.end(logout+'<a href="/lines/users">Lines</a>');
    });
    await new Promise(resolve=>fixture.listen(0,'127.0.0.1',resolve));
    const httpProxy=await restrictedProxy('http://provider.example.invalid:2052',{
        lookup:async()=>[{address:'1.1.1.1',family:4}],
        request:(options,callback)=>http.request({...options,hostname:'127.0.0.1',port:fixture.address().port},callback)
    });
    const httpBrowser=await chromium.launch({headless:true,chromiumSandbox:true,proxy:{server:httpProxy.url,bypass:'<-loopback>'}});
    try{
        const context=await httpBrowser.newContext({serviceWorkers:'block'}),httpPage=await context.newPage();
        const httpConfig={...config,mode:'auto',success_kind:'auto',success_value:''};
        check((await prepare(httpPage,httpConfig,credentials,'http://provider.example.invalid:2052/landing')).code==='ready','HTTP custom port discovers login from landing page');
        check(await submit(httpPage,httpConfig)==='success'&&loginPosts===1,'HTTP proxy login preserves POST, redirect and cookie session');
        check(httpPage.url()==='http://provider.example.invalid:2052/dashboard','Login keeps configured HTTP protocol and port');
        const found=await lookupSubscription({page:httpPage,config:httpConfig},{username:'CaseLogin42'});
        check(found.code==='found'&&found.candidates[0].source_id==='42'&&searchReads===1,'HTTP custom port discovers subscription list and exact line username');
    }finally{await httpBrowser.close();await httpProxy.close();await new Promise(resolve=>fixture.close(resolve));}
console.log(`PASS: ${checks} Chromium HTTP/custom-port/login/cookies/subscription assertions`);
