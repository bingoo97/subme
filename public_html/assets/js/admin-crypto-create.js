(() => {
    'use strict';
    const initWizard = (wizard, instance) => {
        const panel = wizard.closest('[data-payment-create-panel], [data-crypto-quick]');
        if (!panel) return;
        const pageMode = panel.hasAttribute('data-payment-create-panel');
        const copy = JSON.parse(wizard.dataset.copy);
        const get = name => wizard.querySelector(`[data-create-${name}]`);
        const search = get('search'), results = get('results'), customer = get('customer');
        const product = get('product'), order = get('order'), amount = get('amount');
        const assets = get('assets'), submit = get('submit'), notice = get('notice');
        const list = panel.querySelector(pageMode ? '[data-payment-create-idle]' : '[data-crypto-quick-list]');
        const result = panel.querySelector(pageMode ? '[data-payment-create-result]' : '[data-crypto-quick-result]');
        const toggle = panel.querySelector('[data-crypto-create-open]');
        const notifyCreated = detail => {
            const flyout = pageMode ? document.querySelector('[data-crypto-quick]') : panel;
            if (flyout) flyout.dispatchEvent(new CustomEvent('crypto-quick-created', {detail, bubbles:true}));
            else if (pageMode) refreshPageList();
        };
        let pageListRequest = null, pageListRevision = 0;
        const refreshPageList = async () => {
            if (!pageMode) return;
            const target = document.querySelector('[data-payment-list-results]');
            if (!target) return;
            const url = new URL('/admin/', window.location.origin);
            url.search = window.location.search;
            url.searchParams.set('page', 'payments');
            pageListRequest?.abort();
            const token = ++pageListRevision;
            const abort = new AbortController(); pageListRequest = abort;
            const timeout = setTimeout(() => abort.abort(), 12000);
            try {
                const response = await fetch(url, {credentials:'same-origin', signal:abort.signal, headers:{Accept:'text/html'}});
                if (!response.ok) return;
                const fresh = new DOMParser().parseFromString(await response.text(), 'text/html');
                const rows = fresh.querySelector('[data-payment-list-results]');
                if (!rows || token !== pageListRevision) return;
                target.innerHTML = rows.innerHTML;
                target.setAttribute('data-payment-list-refreshed', '');
                document.querySelectorAll('[data-payment-scope]').forEach(filter => {
                    let count = filter.querySelector('.admin-payments-filter__count');
                    const next = fresh.querySelector('[data-payment-scope="' + filter.dataset.paymentScope + '"] .admin-payments-filter__count');
                    if (next) {
                        if (!count) { count = document.createElement('span'); count.className = 'admin-payments-filter__count'; filter.append(count); }
                        count.textContent = next.textContent;
                    } else count?.remove();
                });
            } catch (_) { /* The saved ticket remains available through its details link. */ }
            finally { clearTimeout(timeout); if (token === pageListRevision) pageListRequest = null; }
        };
        if (pageMode) document.addEventListener('crypto-quick-created', refreshPageList);
        const feedback = (text, tone, requestId = 0) => {
            result.replaceChildren();
            const message = document.createElement('span'); message.textContent = text; result.append(message);
            if (pageMode) {
                if (requestId > 0) {
                    const link = document.createElement('a'); link.className = 'btn btn-outline-dark btn-sm';
                    link.href = '/admin/?page=payments&payment_type=crypto&payment_id=' + requestId;
                    link.textContent = copy.payment_details; result.append(link);
                }
                const all = document.createElement('a'); all.className = 'btn btn-light btn-sm';
                all.href = '/admin/?page=payments&payment_type_filter=crypto&payment_scope=new';
                all.textContent = copy.payment_list; result.append(all);
            }
            result.dataset.tone = tone; result.hidden = false;
        };
        let step = 'user', userId = 0, purpose = '', assetId = '', payload = null;
        let timer = null, reading = null, revision = 0, busy = false;
        const showNotice = (text = '', error = false) => {
            notice.textContent = text; notice.hidden = !text;
            notice.classList.toggle('is-error', error);
        };
        const stopRead = () => { ++revision; if (reading) reading.abort(); reading = null; clearTimeout(timer); };
        const ready = () => {
            // Hidden fields must not block native form validation after changing purpose.
            amount.disabled = busy || purpose !== 'topup';
            product.disabled = busy || purpose !== 'new_order' || !product.options.length;
            order.disabled = busy || purpose !== 'existing_order' || !order.options.length;
            wizard.querySelectorAll('[data-create-purpose]').forEach(node => {
                node.disabled = busy || !payload || !!payload.has_pending_payment || !payload.items?.length
                    || (node.dataset.createPurpose === 'order' && !payload.can_order);
            });
            const choice = purpose === 'topup' ? Number(amount.value) >= 1 && Number(amount.value) <= 200 : purpose === 'new_order' ? !!product.value : !!order.value;
            submit.disabled = busy || !payload || !userId || !assetId || !choice || !!payload?.has_pending_payment;
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
            toggle?.setAttribute('aria-expanded', 'false');
        };
        const open = () => {
            if (busy) return;
            stopRead(); userId=0;purpose='';assetId='';payload=null;
            search.value=''; results.replaceChildren(); customer.replaceChildren(); amount.value='';
            result.hidden=true;toggle?.setAttribute('aria-expanded','true');
            wizard.hidden=false;list.hidden=true;panel.classList.add('is-creating');get('wallet').hidden=true;get('pending').hidden=true;showNotice();screen('user');search.focus();
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
            } catch (error) {
                if (token !== revision) return null;
                throw error.name === 'AbortError' ? new Error(copy.error) : error;
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
            get('wallet').hidden=true;get('pending').hidden=true;
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
            card.querySelectorAll('a:not([data-create-customer-profile])').forEach(link=>{link.removeAttribute('href');});
            customer.append(card);
            wizard.querySelectorAll('[data-create-purpose]').forEach(node=>{node.disabled=true;});
            try {
                const data=await read({action:'crypto_quick_create_options',customer_id:userId});if(!data)return;
                payload=data;
                const currency={EUR:'€',USD:'$',GBP:'£',PLN:'zł'}[data.currency?.code]||data.currency?.symbol||data.currency?.code||'';
                get('amount-label').textContent=copy.amount+' ('+amount.min+'–'+amount.max+(currency?' '+currency:'')+')';
                options(product,data.product_options||[],'product_id');options(order,data.orders||[],'id');
                assets.replaceChildren();
                (data.items||[]).forEach(item=>{
                    const label=document.createElement('label');label.className='admin-crypto-create__asset';
                    const input=document.createElement('input');input.type='radio';input.name='quick_create_asset_'+instance;input.value=String(item.id);
                    const text=document.createElement('span');text.textContent=[item.code,item.network_label||item.name].filter(Boolean).join(' · ')+(item.has_existing_assignment?' · '+copy.assigned:'');
                    label.append(input,text);assets.append(label);
                });
                wizard.querySelectorAll('[data-create-purpose]').forEach(node=>{node.disabled=!!data.has_pending_payment||!data.items?.length||(node.dataset.createPurpose==='order'&&!data.can_order);});
                get('pending').hidden=!data.has_pending_payment||!data.pending_payment_id;
                if(data.pending_payment_id) get('pending').href='/admin/?page=payments&payment_type=crypto&payment_id='+Number(data.pending_payment_id);
                get('wallet').hidden=!!data.items?.length||!data.can_wallet;
                showNotice(data.has_pending_payment?copy.pending:!data.items?.length?(data.wallet_shared?copy.no_wallet_shared:copy.no_wallet):'',true);
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
                notifyCreated(data.crypto_quick);
                feedback(copy.success,'success',Number(data.request_id)||0);
            }catch(error){
                if(uncertain){busy=false;hide();notifyCreated();feedback(copy.uncertain,'error');}
                else showNotice(error.message||copy.error,true);
            }finally {
                clearTimeout(timeout);busy=false;wizard.removeAttribute('aria-busy');
                wizard.querySelectorAll('button,input,select').forEach(node=>{node.disabled=false;});ready();
            }
        });
    };
    const init = () => document.querySelectorAll('[data-crypto-create]').forEach(initWizard);
    if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',init);else init();
})();
