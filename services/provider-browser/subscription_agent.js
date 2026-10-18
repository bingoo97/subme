(()=>{
 const visible=el=>!!el?.getClientRects().length&&getComputedStyle(el).visibility!=='hidden'&&!el.closest('[hidden],[aria-hidden="true"],template,noscript');
 const all=(selector,root=document)=>[...root.querySelectorAll(selector)].filter(visible);
 const danger=/create|register|signup|sign.up|reset|forgot|delete|remove|renew|extend|activate|utwórz|usuń|przedłuż|dodaj|nowa linia/i;
 const blockedURL=url=>danger.test(url.pathname)||[...url.searchParams].some(([k,v])=>/^(action|do|task|operation|cmd|view|mode|page)$/i.test(k)&&danger.test(v));
 const nativeValue=(el,value)=>{Object.getOwnPropertyDescriptor(HTMLInputElement.prototype,'value').set.call(el,value);el.dispatchEvent(new Event('input',{bubbles:true}));el.dispatchEvent(new Event('change',{bubbles:true}));};
 const one=list=>list.length===1?list[0]:null;
 const norm=(type,value)=>{
  value=String(value||'').trim();if(type==='username')return value;if(type==='handle')return value.replace(/^@/,'').toLocaleLowerCase();
  if(type==='email')return value.toLocaleLowerCase();
  try{const u=new URL(value);return ['http:','https:'].includes(u.protocol)?u.href:'';}catch{return '';}
 };
 const heading=label=>/e.?mail/i.test(label)?'email':/user|handle|login|nazwa (?:użytkownika|konta)|użytkownik|benutzer/i.test(label)?'handle':/url|link|playlist|m3u|adres/i.test(label)?'url':'';
 function usernameValues(cell,declared){
  const values=[],content=cell.textContent.trim();
  if(declared==='handle'){
   // Read the username itself, never a second value from a combined username/password cell.
   const first=document.createTreeWalker(cell,NodeFilter.SHOW_TEXT).nextNode()?.textContent.trim()||'';
   for(const value of [content,first])if(value&&value.length<=128&&!/\s/.test(value)){values.push(value);if(value.startsWith('@'))values.push(value.slice(1));}
  }
  if(declared==='identity')for(const match of content.matchAll(/(?:^|\s)@([\p{L}\p{N}_.+\-]+)/gu))values.push(match[1],'@'+match[1]);
  for(const value of [...cell.querySelectorAll('a[href]')].map(a=>a.href).concat(content)){
   try{
    const url=new URL(value);if(!['http:','https:'].includes(url.protocol)||url.username||url.password)continue;
    const entries=[...url.searchParams].filter(([key])=>/^username(?:$|\[)/i.test(key));
    if(entries.length===1&&/^username$/i.test(entries[0][0])&&entries[0][1]&&entries[0][1].length<=128&&!/\s/.test(entries[0][1]))values.push(entries[0][1]);
   }catch{}
  }
  return values;
 }
 function tables(cfg={}){
  const roots=cfg.subscription_table_selector?all(cfg.subscription_table_selector):all('table,[role="grid"],[role="table"]');
  const found=[];
  for(const root of roots.slice(0,20)){
   const headers=[...root.querySelectorAll('thead th,thead td,[role="columnheader"]')].filter(el=>el.closest('table,[role="grid"],[role="table"]')===root).map(el=>el.textContent.trim());
   if(!headers.length)continue;
   const types=headers.map(heading);
   const subscriptions=headers.some(h=>/expir|wygas|ważn|subscr|abonn|valid|end date|endzeit/i.test(h));
   if(subscriptions)headers.forEach((label,index)=>{if(!types[index]&&/produkt|product|account|konto|customer|klient/i.test(label))types[index]='identity';});
   const identity=types.filter(Boolean).length;
   if(!identity||!subscriptions&&!cfg.subscription_table_selector)continue;
   const rows=all('tbody tr,[role="row"]',root).filter(row=>row.closest('table,[role="grid"],[role="table"]')===root&&!row.querySelector('th,[role="columnheader"]'));
   found.push({root,types,headers,rows:rows.slice(0,1000),limited:rows.length>1000,score:identity*2+(subscriptions?5:0)});
  }
  found.sort((a,b)=>b.score-a.score);if(!found.length)return null;
  const top=found.filter(t=>t.score===found[0].score);
  if(top.length===1)return top[0];
  // Several status sections can contain the same list schema. Read all visible rows, never filter one guessed section.
  if(top.every(t=>JSON.stringify(t.headers)===JSON.stringify(top[0].headers))){
   const rows=top.flatMap(t=>t.rows);return {...top[0],rows:rows.slice(0,1000),limited:rows.length>1000||top.some(t=>t.limited),composite:true};
  }
  return null;
 }
 function matching(table,identity){
  if(identity.username)identity={username:identity.username};
  let matches=0,conflict=false;const candidates=[];
  const text=(value,limit=160)=>[...String(value||'').replace(/\s+/g,' ').trim()].slice(0,limit).join('');
  for(const row of table.rows){
   const cells=[...row.querySelectorAll('td,[role="cell"],[role="gridcell"]')];let matched=false,rowConflict=false;
   for(let index=0;index<cells.length;index++){
    const declared=table.types[index];
    for(const type of identity.username?(['identity','handle','url'].includes(declared)?['username']:[]):declared==='identity'?['handle','email','url']:declared==='handle'?['handle','email']:[declared]){
    if(!type||!identity[type])continue;
    const cell=cells[index];const values=type==='username'?usernameValues(cell,declared):type==='url'?[...cell.querySelectorAll('a[href]')].map(a=>a.href).concat(cell.textContent.trim()):type==='email'?(cell.textContent.match(/[\p{L}\p{N}._%+\-]+@[\p{L}\p{N}.\-]+\.[\p{L}]{2,}/gu)||[]):declared==='identity'?[...cell.textContent.matchAll(/(?:^|\s)@([\p{L}\p{N}_.\-]+)/gu)].map(m=>m[1]):cell.textContent.split(/[^\p{L}\p{N}_@.\-]+/u);
    const normalized=values.map(v=>norm(type,v)).filter(Boolean),target=norm(type,identity[type]);
    if(normalized.includes(target))matched=true;
    else if(type==='email'&&normalized.length)rowConflict=true;
    }
   }
   if(matched){
    matches++;if(rowConflict)conflict=true;
    if(candidates.length<20){
     const value=pattern=>{const index=table.headers.findIndex(h=>pattern.test(h));return index>=0?cells[index]?.textContent||'':'';};
     const email=cells.map((cell,i)=>['email','handle','identity'].includes(table.types[i])?(cell.textContent.match(/[\p{L}\p{N}._%+\-]+@[\p{L}\p{N}.\-]+\.[\p{L}]{2,}/u)||[])[0]||'':'').find(Boolean)||'';
     const handle=cells.map((cell,i)=>table.types[i]==='identity'?(cell.textContent.match(/(?:^|\s)@([\p{L}\p{N}_.\-]+)/u)||[])[1]||'':table.types[i]==='handle'?cell.textContent.trim().split(/\s+/).map(v=>v.replace(/^@/,'')).find(v=>/^[\p{L}\p{N}_.\-]+$/u.test(v))||'':'').find(Boolean)||'';
     let title=value(/produkt|product|subscription|subskry|package|pakiet/i);
     if(email)title=title.replace(email,'');if(handle)title=title.replace('@'+handle,'');
     title=title.replace(/https?:\/\/\S+/gi,'').replace(/(?:password|hasło|haslo|token)\s*[:=].*/i,'');
     candidates.push({key:'match-'+matches,source_id:text(value(/^(id|line.?id|subscription.?id)$/i),64),title:text(title),handle:text(handle,128),username:text(identity.username||'',128),email:text(email,254),expires:text(value(/expir|wygas|ważn|valid|end date|endzeit/i),80),status:text(value(/^(status|state|stan)$/i),80),conflict:rowConflict});
    }
   }
  }
  return {code:matches>1||conflict?'ambiguous':matches===1?'found':'not_found',count:matches,candidates,truncated:matches>candidates.length};
 }
 function listLink(){
  // Responsive navigation may be collapsed in the worker; its safe list links still describe the site structure.
  const anchors=[...document.querySelectorAll('a[href]')].filter(el=>!el.closest('template,noscript')&&(visible(el)||el.closest('nav,aside,[role="navigation"],.sidebar,#adminSidebar')));
  const links=anchors.map(el=>{
   try{const url=new URL(el.href);const label=el.textContent.trim();if(url.origin!==location.origin||!['http:','https:'].includes(url.protocol)||url.hash||url.href===location.href||blockedURL(url)||danger.test(label))return null;
    const route=url.pathname+' '+url.search;
    const score=/subscr|subskry|abonn|lines|linie/i.test(route)?12:/subscr|subskry|abonn|lines|linie/i.test(label)?10:/zamów|zamow|orders/i.test(route)?9:/zamów|zamow|orders/i.test(label)?7:/\b(users|customers|clients)\b/i.test(route)?5:/^(users|użytkownicy|klienci|customers|clients|benutzer)$/i.test(label)?4:0;
    // Prefer the unfiltered parent list to dashboard links prefiltered to expired/active rows.
    const specificity=Math.min(10,[...url.searchParams].length)*.05+Math.min(10,url.pathname.split('/').filter(Boolean).length)*.01;
    return score?{url:url.href,score:score-specificity}:null;
   }catch{return null;}
  }).filter(Boolean).sort((a,b)=>b.score-a.score);
  const unique=[...new Map(links.map(row=>[row.url,row])).values()];return unique.length&&(!unique[1]||unique[0].score>unique[1].score)?unique[0].url:'';
 }
 function searchInput(table,cfg){
  if(table.composite)return null;
  if(cfg.subscription_search_selector)return one(all(cfg.subscription_search_selector));
  const scopes=[];for(let node=table.root.parentElement;node;node=node.parentElement)scopes.push(node);
  for(const scope of scopes){
   const candidates=all('input',scope).filter(el=>!el.disabled&&!el.readOnly&&!el.closest('nav,[role="banner"],.admin-topbar')&&!el.form?.querySelector('input[type="password"]')&&['text','email','search'].includes(el.type)&&(el.type==='search'||el.inputMode==='search'||el.enterKeyHint==='search'||/search|filter|query|szukaj|wyszuk|suche|buscar|recherch/i.test(el.name+' '+el.id+' '+el.placeholder+' '+(el.getAttribute('aria-label')||''))||el.name==='q')).map(el=>{
    const label=el.placeholder+' '+(el.getAttribute('aria-label')||'')+' '+el.name+' '+el.id;
    return {el,score:(/lines|subscription|subskry|abonn|linie/i.test(label)?10:0)+(el.type==='search'?2:0)+(el.inputMode==='search'?1:0)+(el.enterKeyHint==='search'?1:0)+(table.root.id&&el.getAttribute('aria-controls')===table.root.id?6:0)};
   }).sort((a,b)=>b.score-a.score);
   if(candidates.length)return !candidates[1]||candidates[0].score>candidates[1].score?candidates[0].el:null;
  }
  return null;
 }
 const signature=table=>table.rows.map(row=>row.textContent).join('\n').slice(0,60000);
 const pause=ms=>new Promise(resolve=>setTimeout(resolve,ms));
 async function search(cfg,identity,options={}){
  if(identity.username)identity={username:identity.username};
  const table=tables(cfg);if(!table)return {code:'table_missing',navigate:listLink()};
  const current=matching(table,identity);if(current.code!=='not_found')return current;
  const input=searchInput(table,cfg);if(!input||input.tagName!=='INPUT')return {code:'table_missing'};
  const queries=['username','email','handle','url'].filter(type=>identity[type]&&norm(type,identity[type])).sort((a,b)=>Number(table.types.includes(b))-Number(table.types.includes(a)));
  const available=[...new Map(queries.map(type=>[norm(type,identity[type]),type])).values()];
  if(!available.length)return {code:'no_identity'};
  const unique=available.filter(type=>!(options.searched||[]).includes(type));
  if(!unique.length)return {code:!table.limited?'not_found':'unconfirmed'};
  let changedCount=0;
  for(const type of unique.slice(0,3)){
   const before=signature(tables(cfg)||table);nativeValue(input,identity[type]);
   const form=input.form;
   const submit=form?one(all('button,input[type="submit"]',form).filter(el=>/^(search|szukaj|wyszukaj|suchen|rechercher)$/i.test((el.getAttribute('aria-label')||el.getAttribute('title')||el.value||el.textContent).trim()))):null;
   if(submit&&!submit.disabled){
    const target=new URL(submit.getAttribute('formaction')||form.getAttribute('action')||location.href,location.href);
    if(target.origin!==location.origin||blockedURL(target))return {code:'table_missing'};
    let navigation='',blocked=false;
    const guard=event=>{
     if(event.target!==form||event.defaultPrevented)return;event.preventDefault();
     if((submit.getAttribute('formmethod')||form.method).toLowerCase()!=='get'||!input.name){blocked=true;return;}
     const fields=new FormData(form);if(submit.name)fields.append(submit.name,submit.value);
     if([...fields.values()].some(v=>typeof v!=='string')){blocked=true;return;}
     target.search=new URLSearchParams(fields).toString();navigation=target.href;
    };
    document.addEventListener('submit',guard);submit.click();document.removeEventListener('submit',guard);
    if(blocked)return {code:'table_missing'};
    if(navigation)return {code:'search_navigation',navigate:navigation,searched:[...(options.searched||[]),...unique.slice(0,unique.indexOf(type)+1)]};
   }
   const end=Date.now()+5000;let changed=false,stable=0,last='';
   while(Date.now()<end){
    await pause(250);const latest=tables(cfg);if(!latest)continue;
    const result=matching(latest,identity);if(result.code!=='not_found')return result;
    const snapshot=signature(latest);changed=changed||snapshot!==before;
    if(snapshot===last)stable++;else stable=0;last=snapshot;
    const loading=all('[aria-busy="true"],[role="progressbar"],.dataTables_processing,.dt-processing,.spinner-border,.spinner-grow').length>0||latest.rows.some(row=>/^(loading|processing|ładowanie|przetwarzanie|wczytywanie|laden)[.\s…]*$/i.test(row.textContent.trim()));
    if(changed&&stable>=2&&!loading){changedCount++;break;}
   }
  }
  return {code:changedCount===unique.length&&!table.limited?'not_found':'unconfirmed'};
 }
 return {tables,matching,search,listLink,norm,searchInput};
})()
