(() => {
    'use strict';
    const init = () => {
        const panel = document.querySelector('[data-crypto-quick]');
        const wizard = panel?.querySelector('[data-crypto-create]');
        if (!wizard) return;
        const copy = JSON.parse(wizard.dataset.copy);
        const get = name => wizard.querySelector(`[data-create-${name}]`);
        const search = get('search'), results = get('results'), customer = get('customer');
        const product = get('product'), order = get('order'), amount = get('amount');
        const assets = get('assets'), submit = get('submit'), notice = get('notice');
        const list = panel.querySelector('[data-crypto-quick-list]');
        let step = 'user', userId = 0, purpose = '', assetId = '', payload = null;
        let timer = null, reading = null, revision = 0, busy = false;
        const showNotice = (text = '', error = false) => {
            notice.textContent = text; notice.hidden = !text;
            notice.classList.toggle('is-error', error);
        };
        const stopRead = () => { ++revision; if (reading) reading.abort(); reading = null; clearTimeout(timer); };
        const ready = () => {
            const choice = purpose === 'topup' ? Number(amount.value) >= 1 && Number(amount.value) <= 200 : purpose === 'new_order' ? !!product.value : !!order.value;
            submit.disabled = busy || !userId || !assetId || !choice || !!payload?.has_pending_payment;
        };
        const screen = next => {
            step = next;
            wizard.querySelectorAll('[data-create-screen]').forEach(node => { node.hidden = node.dataset.createScreen !== step; });
            customer.hidden = step === 'user';
            wizard.querySelectorAll('[data-create-step]').forEach((node,i) => {
                const active = i === ['user','purpose','details'].indexOf(step);
                node.classList.toggle('is-active',active);
                if(active)node.setAttribute('aria-current','step');else node.removeAttribute('aria-current');
            });
            ready();
        };
        const hide = () => {
            if (busy) return;
            stopRead(); wizard.hidden = true; list.hidden = false; panel.classList.remove('is-creating');
        };
        const open = () => {
            stopRead(); userId=0;purpose='';assetId='';payload=null;
            search.value=''; results.replaceChildren(); customer.replaceChildren(); amount.value='';
            const result=panel.querySelector('[data-crypto-quick-result]'); if(result)result.hidden=true;
            wizard.hidden=false;list.hidden=true;panel.classList.add('is-creating');get('wallet').hidden=true;showNotice();screen('user');search.focus();
        };
        const read = async params => {
            stopRead(); const token = revision; reading = new AbortController(); const request=reading;
            const timeout=setTimeout(()=>request.abort(),12000);
            try {
                const response=await fetch('/admin/search.php?'+new URLSearchParams(params),{credentials:'same-origin',signal:request.signal,headers:{Accept:'application/json'}});
                const data=await response.json();
                if(token!==revision)return null;
                if(!response.ok||!data.ok)throw new Error(copy.error);
                return data;
            } finally { clearTimeout(timeout); if(token===revision)reading=null; }
        };
        const options = (select, items, idKey) => {
            select.replaceChildren();
            items.forEach(item=>select.add(new Option(item.label,String(item[idKey]))));
            select.disabled=!items.length;
        };
        const details = mode => {
            purpose=mode;screen('details');showNotice();
            get('order-mode').hidden=mode==='topup';
            get('product-wrap').hidden=mode!=='new_order';
            get('order-wrap').hidden=mode!=='existing_order';
            get('amount-wrap').hidden=mode!=='topup';
            get('help').textContent=copy[mode==='topup'?'topup_help':mode==='new_order'?'new_help':'order_help'];
            wizard.querySelectorAll('[data-create-mode]').forEach(button=>{
                button.classList.toggle('is-active',button.dataset.createMode===mode);
                button.setAttribute('aria-pressed',String(button.dataset.createMode===mode));
            });
            if(mode==='existing_order'&&!order.options.length || mode==='new_order'&&!product.options.length)showNotice(copy.empty,true);
            ready();
        };
        panel.addEventListener('click',event=>{
            if(event.target.closest('[data-crypto-create-open]'))open();
            if(event.target.closest('[data-crypto-quick-filter], [data-admin-topbar-flyout-close]'))hide();
        });
        get('back').addEventListener('click',()=>{
            if(busy)return;
            stopRead();showNotice();
            get('wallet').hidden=true;
            if(step==='user')hide();else screen(step==='details'?'purpose':'user');
        });
        search.addEventListener('input',()=>{
            stopRead();results.replaceChildren();showNotice();
            const query=search.value.trim();if(query.replace(/^@/,'').length<2)return;
            timer=setTimeout(async()=>{
                showNotice(copy.loading);
                try {
                    const data=await read({action:'crypto_quick_create_search',q:query});
                    if(!data)return;
                    results.innerHTML=data.html;showNotice();
                    results.querySelectorAll('[data-admin-payment-customer-select] span').forEach(node=>{node.textContent=copy.choose;});
                }catch(error){if(error.name!=='AbortError')showNotice(copy.error,true);}
            },300);
        });
        results.addEventListener('click',async event=>{
            const button=event.target.closest('[data-admin-payment-customer-select]');if(!button)return;
            userId=Number(button.dataset.customerId);payload=null;assetId='';screen('purpose');showNotice(copy.loading);
            customer.replaceChildren();
            const card=button.closest('.admin-search-user-card').cloneNode(true);
            card.querySelector('.admin-search-user-card__actions')?.remove();
            card.querySelectorAll('a').forEach(link=>{link.removeAttribute('href');});
            customer.append(card);
            wizard.querySelectorAll('[data-create-purpose]').forEach(node=>{node.disabled=true;});
            try {
                const data=await read({action:'crypto_quick_create_options',customer_id:userId});if(!data)return;
                payload=data;
                const currency={EUR:'€',USD:'$',GBP:'£',PLN:'zł'}[data.currency?.code]||data.currency?.symbol||data.currency?.code||'';
                get('amount-label').textContent=copy.amount+(currency?' ('+currency+')':'');
                options(product,data.product_options||[],'product_id');options(order,data.orders||[],'id');
                assets.replaceChildren();
                (data.items||[]).forEach(item=>{
                    const label=document.createElement('label');label.className='admin-crypto-create__asset';
                    const input=document.createElement('input');input.type='radio';input.name='quick_create_asset';input.value=String(item.id);
                    const text=document.createElement('span');text.textContent=[item.code,item.network_label||item.name].filter(Boolean).join(' · ')+(item.has_existing_assignment?' · '+copy.assigned:'');
                    label.append(input,text);assets.append(label);
                });
                wizard.querySelectorAll('[data-create-purpose]').forEach(node=>{node.disabled=!!data.has_pending_payment||!data.items?.length||(node.dataset.createPurpose==='order'&&!data.can_order);});
                get('wallet').hidden=!!data.items?.length||!data.can_wallet;
                showNotice(data.has_pending_payment?copy.pending:!data.items?.length?copy.no_wallet:'',true);
            }catch(error){if(error.name!=='AbortError')showNotice(copy.error,true);}
        });
        wizard.querySelectorAll('[data-create-purpose]').forEach(button=>button.addEventListener('click',()=>details(button.dataset.createPurpose==='topup'?'topup':'new_order')));
        wizard.querySelectorAll('[data-create-mode]').forEach(button=>button.addEventListener('click',()=>details(button.dataset.createMode)));
        assets.addEventListener('change',event=>{assetId=event.target.value;ready();});
        [product,order,amount].forEach(node=>{node.addEventListener('input',ready);node.addEventListener('change',ready);});
        get('form').addEventListener('submit',async event=>{
            event.preventDefault();ready();if(submit.disabled)return;
            busy=true;ready();wizard.setAttribute('aria-busy','true');
            wizard.querySelectorAll('button,input,select').forEach(node=>{node.disabled=true;});showNotice(copy.loading);
            const abort=new AbortController();const timeout=setTimeout(()=>abort.abort(),25000);
            let uncertain=true;
            try {
                const body=new URLSearchParams({action:'crypto_quick_create_submit',_csrf:wizard.dataset.csrf,customer_id:userId,purpose,asset_id:assetId,product_id:purpose==='new_order'?product.value:'0',order_id:purpose==='existing_order'?order.value:'0',amount:purpose==='topup'?amount.value:''});
                const response=await fetch('/admin/search.php',{method:'POST',body,credentials:'same-origin',signal:abort.signal,headers:{Accept:'application/json'}});
                const data=await response.json();uncertain=false;
                if(!response.ok||!data.ok)throw new Error(data.message==='pending_crypto_payment'?copy.pending:(data.message||copy.error));
                busy=false;hide();
                panel.dispatchEvent(new CustomEvent('crypto-quick-created',{detail:data.crypto_quick}));
                const result=panel.querySelector('[data-crypto-quick-result]');result.textContent=copy.success;result.dataset.tone='success';result.hidden=false;
            }catch(error){
                if(uncertain){busy=false;hide();panel.dispatchEvent(new CustomEvent('crypto-quick-created'));const result=panel.querySelector('[data-crypto-quick-result]');result.textContent=copy.uncertain;result.dataset.tone='error';result.hidden=false;}
                else showNotice(error.message||copy.error,true);
            }finally {
                clearTimeout(timeout);busy=false;wizard.removeAttribute('aria-busy');
                wizard.querySelectorAll('button,input,select').forEach(node=>{node.disabled=false;});ready();
            }
        });
    };
    if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',init);else init();
})();
