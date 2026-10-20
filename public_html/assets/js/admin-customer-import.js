(() => {
    'use strict';
    const form=document.querySelector('[data-customer-import]');if(!form)return;
    const get=name=>form.querySelector('[data-import-'+name+']');
    const notice=get('notice'),preview=get('preview'),start=get('start'),pause=get('pause'),resume=get('resume');
    const prepare=get('prepare'),progress=get('progress'),bar=get('progress-bar');
    const key='subme-customer-import:'+form.dataset.adminId;
    let job='',running=false,preparing=false,request=null,changed=false,reviewed=false,loopActive=false;
    const message=(text='',error=false)=>{notice.textContent=text;notice.hidden=!text;notice.classList.toggle('is-error',error);};
    const remember=()=>{try{if(job)sessionStorage.setItem(key,job);else sessionStorage.removeItem(key);}catch(_){}};
    const api=async(action,data=null)=>{
        const body=data||new FormData();body.set('action',action);body.set('_csrf',form.elements._csrf.value);if(job)body.set('job',job);
        const controller=new AbortController();request=controller;const timeout=setTimeout(()=>controller.abort(),30000);
        try{
            const response=await fetch('/admin/customer_import.php',{method:'POST',body,signal:controller.signal,credentials:'same-origin',headers:{Accept:'application/json'}});
            const result=await response.json();if(!response.ok||!result.ok){const error=new Error(result.message||'Nie udało się wykonać importu.');error.expected=true;throw error;}return result;
        }finally{clearTimeout(timeout);if(request===controller)request=null;}
    };
    const controls=()=>{
        form.querySelectorAll('input,select,textarea').forEach(node=>{node.disabled=running||preparing;});
        resume.disabled=loopActive;prepare.disabled=running||preparing;pause.hidden=!running;resume.hidden=running||!job||changed||preparing||reviewed;start.disabled=running||preparing||changed;
    };
    const add=(node,text,tag='span')=>{const child=document.createElement(tag);child.textContent=text;node.append(child);return child;};
    const summary=data=>{
        progress.hidden=false;bar.max=data.total;bar.value=data.processed;
        get('progress-title').textContent=(data.done?'Import zakończony':'Postęp importu')+' · '+data.processed+' / '+data.total;
        get('progress-text').textContent='Dodano: '+data.created+' · pominięto istniejące: '+data.skipped+' · błędy: '+data.failed+' · e-maile w kolejce: '+data.queued;
        if(data.errors?.length)message(data.errors.join('\n'),true);
        if(data.done){running=false;job='';remember();get('refresh').hidden=false;start.hidden=true;resume.hidden=true;}
    };
    const run=async()=>{
        if(running||loopActive||!job||preparing||changed)return;
        running=true;loopActive=true;reviewed=false;start.hidden=true;message();controls();
        while(running&&job){
            try{const data=await api('batch');summary(data);controls();if(data.done)break;}
            catch(error){running=false;message(error.expected?error.message:'Nie udało się potwierdzić postępu. Kliknij „Wznów import” — sprawdzimy zapisany stan i pominiemy istniejące konta. Nie wysyłamy partii ponownie automatycznie.',true);controls();break;}
            if(running)await new Promise(resolve=>setTimeout(resolve,250));
        }
        loopActive=false;controls();
    };
    form.addEventListener('submit',async event=>{
        event.preventDefault();if(running||preparing)return;
        const file=form.elements.import_source_file.files[0];if(file&&file.size>10*1024*1024){message('Plik jest za duży. Maksymalnie 10 MB.',true);return;}
        const source=new FormData(form);
        preparing=true;start.hidden=true;resume.hidden=true;message('Rozpoznawanie danych…');controls();
        try{
            const data=await api('prepare',source);job=data.job;remember();changed=false;reviewed=true;
            get('format').textContent=data.format.toUpperCase();const stats=get('stats');stats.replaceChildren();
            [['Poprawne adresy',data.total],['Istniejące konta',data.existing],['Duplikaty w źródle',data.duplicates],['Błędne adresy',data.invalid]].forEach(([label,value])=>{const node=add(stats,'','div');add(node,String(value),'strong');add(node,label);});
            form.querySelectorAll('[data-import-map]').forEach(select=>{
                const field=select.dataset.importMap;select.replaceChildren();select.add(new Option('Automatycznie','auto'));if(field!=='email')select.add(new Option('Brak / pomiń','-1'));
                data.headers.forEach((header,i)=>select.add(new Option(header,String(i))));select.value=String(data.mapping[field]);
            });
            const sample=get('sample');sample.replaceChildren();
            const statusLabels={active:'Aktywny',inactive:'Nieaktywny',blocked:'Zablokowany'};
            const passwordLabels={plain:'Zachowamy hasło',hash:'Zachowamy zgodny hash',generated:'Nowe hasło'};
            data.sample.forEach(row=>{
                const tr=document.createElement('tr');const identity=add(tr,'','td');add(identity,row.email,'strong');add(identity,'@'+row.handle,'small');
                const status=add(tr,'','td');add(status,statusLabels[row.status]||row.status).className='admin-customer-import__status is-'+row.status;
                add(tr,row.existing?'Konto istnieje — pominiemy':passwordLabels[row.password_mode],'td');sample.append(tr);
            });
            get('password-help').textContent='Hasła do zachowania: '+(data.passwords.plain+data.passwords.hash)+'. Nowe hasła: '+data.passwords.generated+'. Bcrypt / Argon2 są obsługiwane; nierozpoznane hashe zastępujemy nowym hasłem. '+(form.elements.import_send_password_email.checked?'Nowe hasła trafią do kolejki e-mail.':'Wysyłka jest wyłączona. Klient może skorzystać z przypomnienia hasła.');
            preview.hidden=false;progress.hidden=true;start.hidden=!data.can_start;
            if(!data.can_start)message('Brak konfiguracji SMTP lub aktywnego szablonu nowego hasła. Skonfiguruj wysyłkę w Settings albo świadomie wyłącz wysyłkę i sprawdź dane ponownie.',true);
            else if(data.unknown_status)message('Nie rozpoznano '+data.unknown_status+' statusów. Te konta będą nieaktywne; sprawdź podgląd i interpretację statusu.',true);
            else message('Sprawdź rozpoznane pola. Import nie zmienia istniejących kont ani danych finansowych.');
            resume.hidden=true;preview.scrollIntoView({behavior:matchMedia('(prefers-reduced-motion: reduce)').matches?'auto':'smooth',block:'start'});
        }catch(error){message(error.message||'Nie udało się odczytać danych.',true);}
        finally{preparing=false;controls();if(!start.hidden)resume.hidden=true;}
    });
    form.addEventListener('input',event=>{
        if(running)return;
        if(event.target.name==='import_source_text'&&event.target.value.trim())form.elements.import_source_file.value='';
        if(event.target.name==='import_source_file'&&event.target.files.length)form.elements.import_source_text.value='';
        if(event.target.matches('[name="import_source_text"],[name="import_source_file"]'))form.querySelectorAll('[data-import-map]').forEach(select=>{select.value='auto';});
        if(job){changed=true;start.disabled=true;resume.hidden=true;message('Dane zmieniono. Kliknij „Sprawdź dane”, aby odświeżyć podgląd.');}
    });
    form.addEventListener('change',event=>{if(event.target.type==='file')event.target.dispatchEvent(new Event('input',{bubbles:true}));});
    start.addEventListener('click',run);
    pause.addEventListener('click',()=>{running=false;controls();message('Import wstrzymany. Bieżąca partia może jeszcze zakończyć zapis. Możesz wznowić później.');});
    resume.addEventListener('click',async()=>{
        if(running||loopActive||preparing||!job)return;
        resume.disabled=true;
        try{const data=await api('status');summary(data);if(!data.done&&data.can_start)await run();else if(!data.can_start)message('Sprawdź konfigurację wysyłki i przygotuj podgląd ponownie.',true);}
        catch(error){message(error.message||'Nie udało się wznowić importu.',true);}finally{resume.disabled=false;controls();}
    });
    try{job=sessionStorage.getItem(key)||'';}catch(_){}
    if(job){resume.hidden=false;message('Masz zapisany import. Możesz sprawdzić postęp i wznowić go; źródło przechowujemy zaszyfrowane maksymalnie 2 godziny.');}
    window.addEventListener('beforeunload',()=>{running=false;request?.abort();});
})();
