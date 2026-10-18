// Volatile limits only: no identities, credentials, outcomes or attempt history are persisted.
export class OperationGate {
    constructor(now=()=>Date.now()) { this.now=now; this.until=new Map(); this.window=0; this.count=0; }
    reserve(key,kind) {
        const now=this.now();
        for(const [id,end] of this.until)if(end<=now)this.until.delete(id);
        if(now>=this.window){this.window=now+10000;this.count=0;}
        if((this.until.get(key)||0)>now || this.count>=8)return {code:'rate_limited'};
        if(this.until.size>=512)return {code:'busy'};
        this.until.set(key,now+(kind==='inspect'?3000:5000));this.count++;
        return {code:'allowed'};
    }
    backoff(key) { this.until.set(key,this.now()+30000);return {code:'allowed'}; }
}

export function carriesCredentials(body,credentials={}) {
    if(!body)return false;
    let values=[];
    const walk=(value,depth=0)=>{
        if(depth>5 || values.length>100)return;
        if(value && typeof value==='object')for(const item of Object.values(value))walk(item,depth+1);
        else if(typeof value==='string')values.push(value);
    };
    try{walk(JSON.parse(body));}catch{values=[...new URLSearchParams(body).values()];}
    const secret=credentials.password || credentials.username;
    return !!secret && values.includes(secret);
}

export function readOnlyTarget(value){
    const url=new URL(value),destructive=/create|register|signup|reset|forgot|delete|remove|renew|extend|activate|utwórz|usuń|przedłuż|dodaj/i;
    return !destructive.test(url.pathname)&&![...url.searchParams].some(([k,v])=>/^(action|do|task|operation|cmd|view|mode|page)$/i.test(k)&&destructive.test(v));
}

export function allowRequest(policy,request,{inspect=false,credentials={}}={}) {
    const fail=code=>{policy.failure=code;throw new Error(code);};
    if(carriesCredentials(new URL(request.url()).searchParams.toString(),credentials))fail('manual');
    if(inspect && !['GET','HEAD','OPTIONS'].includes(request.method()))return false;
    if(policy.readOnly){
        if(!['GET','HEAD','OPTIONS'].includes(request.method())||!readOnlyTarget(request.url()))return false;
    }
    if(++policy.requestCount>100 || (request.postDataBuffer()?.length||0)>65536)fail('limit');
    if(carriesCredentials(request.postData(),credentials)) {
        if(!policy.allowSubmit || request.method()!=='POST')fail('manual');
        if(++policy.credentialPosts>1)fail('limit');
    }
    return !['font','media'].includes(request.resourceType());
}
