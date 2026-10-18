import crypto from 'node:crypto';
export function providerHandler({token,sessions,start,close,gate,submit,lookup}) {
 const allowed=new Set(['busy','form','config','baseline','origin','destination','limit','manual','rate_limited','captcha_rejected','expired']);
 const authenticated=req=>{
  const actual=Buffer.from((req.headers.authorization||'').replace(/^Bearer /,''));const expected=Buffer.from(token);
  return actual.length===expected.length && crypto.timingSafeEqual(actual,expected);
 };
 return async(req,res)=>{
    res.setHeader('Content-Type','application/json');res.setHeader('Cache-Control','no-store');
    if(!authenticated(req)||req.method!=='POST'){req.resume();res.setHeader('Connection','close');res.writeHead(403);res.end('{"code":"forbidden"}');return;}
    if(!['/gate','/backoff','/connect','/lookup','/connect_complete','/start','/inspect','/complete','/cancel'].includes(req.url)){req.resume();res.setHeader('Connection','close');res.writeHead(404);res.end('{"code":"config"}');return;}
    let body='';
    try {
        req.setEncoding('utf8');
        await new Promise((resolve,reject)=>{
            let bytes=0,oversized=false;
            req.on('data',chunk=>{
                if(oversized)return;
                bytes+=Buffer.byteLength(chunk);
                if(bytes>32768){oversized=true;res.setHeader('Connection','close');reject(new Error('limit'));return;}
                body+=chunk;
            });
            req.once('end',resolve);req.once('error',reject);
        });
        const data=JSON.parse(body);
        if(!data || typeof data!=='object' || typeof data.owner!=='string'||typeof data.binding!=='string' || data.owner.length>160 || data.binding.length>128)throw new Error('config');
        let result;
        if(req.url==='/gate'||req.url==='/backoff') {
            if(!/^[a-f0-9]{64}$/.test(data.key||'') || !['test','inspect'].includes(data.kind))throw new Error('config');
            result=req.url==='/gate'?gate.reserve(data.key,data.kind):gate.backoff(data.key);
        }
        else if(req.url==='/start')result=await start(data);
        else if(req.url==='/connect')result=await start(data,false,true);
        else if(req.url==='/inspect')result=await start(data,true);
        else {
            const session=sessions.get(data.challenge);
            if(!session){result={code:req.url==='/cancel'?'cancelled':'expired'};res.end(JSON.stringify(result));return;}
            if(session.owner!==data.owner||session.binding!==data.binding)throw new Error('config');
            if(['/lookup','/connect_complete'].includes(req.url)&&!session.connection)throw new Error('config');
            if(req.url==='/lookup'&&(!session.authenticated||!lookup||!data.identity||typeof data.identity!=='object'||Array.isArray(data.identity)||Object.entries(data.identity).some(([k,v])=>!['username','handle','email','url'].includes(k)||typeof v!=='string'||v.length>(k==='username'?128:2048))))throw new Error('config');
            if(req.url==='/connect_complete'&&session.authenticated)throw new Error('expired');
            if(session.busy)throw new Error('busy');
            session.busy=true;
            let keep=false;
            const deadline=req.url!=='/cancel'?setTimeout(()=>{session.timedOut=true;close(data.challenge).catch(()=>{});},30000):null;
            deadline?.unref();
            try {
                if(req.url==='/lookup')result=await lookup(session,data.identity);
                else result={code:req.url==='/cancel'?'cancelled':await submit(session.page,session.config,String(data.answer||''))};
                if(req.url!=='/cancel' && !sessions.has(data.challenge))throw new Error('expired');
                if(req.url==='/connect_complete'&&result.code==='success'){
                    session.authenticated=true;keep=true;result={code:'connected',challenge:data.challenge,expires_in:Math.max(0,Math.floor((session.until-Date.now())/1000))};
                }
            }
            catch(error){if(session.timedOut)throw new Error('limit');if(!sessions.has(data.challenge))throw new Error('expired');throw error;}
            finally{clearTimeout(deadline);if(keep)session.busy=false;else await close(data.challenge);}
        }
        res.end(JSON.stringify(result));
    }catch(error){res.end(JSON.stringify({code:allowed.has(error.message)?error.message:'connection'}));}
};
}
