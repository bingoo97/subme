document.addEventListener('DOMContentLoaded',()=>{
 for(const root of document.querySelectorAll('[data-provider-browser]')){
  const open=root.querySelector('[data-provider-browser-open]'),search=root.querySelector('[data-provider-browser-search]'),captcha=root.querySelector('[data-provider-browser-captcha]'),answer=root.querySelector('[data-provider-browser-answer]'),complete=root.querySelector('[data-provider-browser-complete]'),target=root.querySelector('[data-provider-browser-result]');
  const pl=root.dataset.locale==='pl';let challenge='',busy=false,expiry,revision=0;
  const matches=root.querySelector('[data-provider-browser-matches]');
  const show=(code,message)=>{
   const success=['connected','found'].includes(code),waiting=['pending','searching'].includes(code),error=['rejected','captcha_rejected','storage','connection'].includes(code);
   target.className='admin-provider-remote__result '+(success?'is-success':waiting?'is-pending':error?'is-error':'is-warning');
   const icon=document.createElement('i');icon.className='bi '+(success?'bi-check-circle-fill':waiting?'bi-arrow-repeat':error?'bi-x-circle-fill':'bi-exclamation-triangle-fill');icon.setAttribute('aria-hidden','true');
   const label=document.createElement('span');label.textContent=message||code;target.replaceChildren(icon,label);target.hidden=false;
  };
  const cancel=()=>{
   if(!challenge)return;const body=new FormData();body.set('action','cancel');body.set('_csrf',root.dataset.csrf);body.set('order_id',root.dataset.orderId);body.set('provider_id',root.dataset.providerId);body.set('challenge',challenge);challenge='';
   fetch('/admin/provider_browser.php',{method:'POST',body,credentials:'same-origin',cache:'no-store',keepalive:true}).catch(()=>{});
  };
  const clearMatches=()=>{matches.replaceChildren();matches.hidden=true;};
  const renderMatches=result=>{
   clearMatches();if(!['ambiguous','found'].includes(result.code)||!Array.isArray(result.candidates)||!result.candidates.length)return;
   const element=(tag,text,className)=>{const node=document.createElement(tag);if(text)node.textContent=text;if(className)node.className=className;return node;};
   const heading=element('div',pl?'Dopasowane subskrypcje':'Matching subscriptions','admin-provider-matches__heading');
   const help=element('p',pl?'Wybierz właściwy wiersz. Na tym etapie niczego nie zmieniamy u providera.':'Choose the correct row. No provider data is changed at this stage.','admin-provider-matches__hint');
   const wrap=element('div','','admin-provider-matches__scroll'),table=element('table'),head=element('thead'),headRow=element('tr'),body=element('tbody');
   const labels=pl?['Linia / subskrypcja','Klient','Ważność','Status','Wybór']:['Line / subscription','Customer','Expiry','Status','Select'];
   labels.forEach(label=>{const th=element('th',label);th.scope='col';headRow.append(th);});head.append(headRow);table.append(head,body);wrap.append(table);
   const buttons=[];
   result.candidates.slice(0,20).forEach((candidate,index)=>{
    const row=element('tr'),cells=labels.map(label=>{const td=element('td');td.dataset.label=label;row.append(td);return td;});
    cells[0].append(element('strong',candidate.source_id?'#'+candidate.source_id:(pl?'Dopasowanie ':'Match ')+(index+1)),element('span',candidate.title||'—','admin-provider-matches__secondary'));
    cells[1].append(element('strong',candidate.username|| (candidate.handle?'@'+candidate.handle:'—')),element('span',candidate.email||'—','admin-provider-matches__secondary'));
    cells[2].textContent=candidate.expires||'—';cells[3].textContent=candidate.conflict?(pl?'Sprawdź email':'Check email'):(candidate.status||'—');
    const button=element('button',pl?'Wybierz':'Select','btn btn-outline-dark btn-sm');button.type='button';button.setAttribute('aria-pressed','false');button.setAttribute('aria-label',(pl?'Wybierz subskrypcję ':'Select subscription ')+(candidate.source_id||index+1));
    if(candidate.conflict){button.disabled=true;button.title=pl?'Email jest sprzeczny z danymi tego zamówienia.':'The email conflicts with the customer in this order.';row.classList.add('is-conflict');}
    button.addEventListener('click',()=>{
     if(busy)return;buttons.forEach(item=>{item.row.classList.remove('is-selected');item.button.textContent=pl?'Wybierz':'Select';item.button.setAttribute('aria-pressed','false');});
     row.classList.add('is-selected');button.textContent=pl?'Wybrano':'Selected';button.setAttribute('aria-pressed','true');
     show('found',(pl?'Wybrano subskrypcję':'Selected subscription')+(candidate.source_id?' #'+candidate.source_id:'')+'.');
    });
    buttons.push({row,button});cells[4].append(button);body.append(row);
   });
   matches.append(heading,help,wrap);
   if(result.truncated)matches.append(element('p',pl?'Pokazano pierwszych 20 dopasowań. Zawęź listę w panelu providera, jeśli właściwego wiersza nie ma tutaj.':'Showing the first 20 matches. Narrow the provider list if the correct row is missing.','admin-provider-matches__hint'));
   matches.hidden=false;
  };
  const request=async action=>{
   if(busy)return;const startedAt=revision;busy=true;clearMatches();open.disabled=true;complete.disabled=true;if(search)search.disabled=true;captcha.hidden=true;
   show(action==='lookup'?'searching':'pending',pl?(action==='lookup'?'Automatycznie wyszukuję subskrypcję…':'Sprawdzam połączenie i logowanie…'):(action==='lookup'?'Searching for the subscription…':'Checking connection and login…'));
   const data=new FormData();data.set('action',action);data.set('_csrf',root.dataset.csrf);data.set('order_id',root.dataset.orderId);data.set('provider_id',root.dataset.providerId);
   if(challenge)data.set('challenge',challenge);if(action==='complete')data.set('answer',answer.value);
   const controller=new AbortController(),timeout=setTimeout(()=>controller.abort(),45000);
   clearTimeout(expiry);
   try{
    const response=await fetch('/admin/provider_browser.php',{method:'POST',body:data,credentials:'same-origin',cache:'no-store',signal:controller.signal});
    const result=await response.json();if(startedAt!==revision){challenge=result.challenge||'';cancel();return;}show(result.code,result.message);renderMatches(result);
    if(result.code==='connected'&&/^[a-f0-9]{48}$/.test(result.challenge||'')){
     challenge=result.challenge;if(search)search.disabled=false;
     expiry=setTimeout(()=>{challenge='';if(search)search.disabled=true;show('expired',pl?'Połączenie wygasło. Sprawdź połączenie ponownie.':'Connection expired. Check the connection again.');},Math.max(0,Math.min(120,result.expires_in||120))*1000);
    }else if(result.code==='captcha_required'&&/^[a-f0-9]{48}$/.test(result.challenge||'')&&/^data:image\/png;base64,[a-zA-Z0-9+/=]+$/.test(result.image||'')){
     challenge=result.challenge;root.querySelector('[data-provider-browser-image]').src=result.image;answer.value='';captcha.hidden=false;answer.focus();
     expiry=setTimeout(()=>{cancel();captcha.hidden=true;show('expired',pl?'Kod wygasł. Sprawdź połączenie ponownie.':'Code expired. Check the connection again.');},120000);
    }else challenge='';
   }catch{cancel();show('connection',pl?'Nie udało się połączyć. Spróbuj ponownie za chwilę.':'Connection failed. Try again shortly.');}
   finally{clearTimeout(timeout);busy=false;open.disabled=false;complete.disabled=false;if(!challenge&&search)search.disabled=true;}
  };
  open.addEventListener('click',()=>{cancel();request('connect');});
  search?.addEventListener('click',()=>request('lookup'));
  complete.addEventListener('click',()=>{if(answer.value.trim())request('complete');else answer.focus();});
  answer.addEventListener('keydown',event=>{if(event.key==='Enter'){event.preventDefault();complete.click();}});
  root.closest('.modal')?.addEventListener('hidden.bs.modal',()=>{revision++;clearTimeout(expiry);cancel();clearMatches();target.replaceChildren();target.hidden=true;captcha.hidden=true;answer.value='';root.querySelector('[data-provider-browser-image]').removeAttribute('src');if(search)search.disabled=true;});
  window.addEventListener('pagehide',()=>{revision++;clearTimeout(expiry);cancel();});
 }
});
