<?php
declare(strict_types=1);

/** Parse data only. Imported SQL is never executed. */
function admin_customer_import_sql_rows(string $raw): array
{
    $headers=[];$rows=[];$offset=0;$target='';
    while(preg_match('/\bINSERT\s+(?:IGNORE\s+)?INTO\s+(?<table>(?:`[^`]+`|"[^"]+"|[a-zA-Z0-9_]+)(?:\s*\.\s*(?:`[^`]+`|"[^"]+"|[a-zA-Z0-9_]+))?)\s*\((?<columns>[^)]+)\)\s*VALUES\s*/i',$raw,$m,PREG_OFFSET_CAPTURE,$offset)) {
        $current=strtolower(preg_replace('/[`"\s]/','',$m['table'][0]));
        if($target!==''&&$target!==$current)throw new RuntimeException('Importuj jedną tabelę klientów naraz.');$target=$current;
        $cols=array_map(static fn($v)=>trim($v," \t\n\r`\""),explode(',',$m['columns'][0]));
        if($headers&&$headers!==$cols)throw new RuntimeException('Importuj jedną tabelę klientów naraz. Kolumny w poleceniach INSERT muszą być takie same.');
        $headers=$cols;$i=$m[0][1]+strlen($m[0][0]);$length=strlen($raw);
        while($i<$length) {
            while($i<$length&&ctype_space($raw[$i]))$i++;
            if(($raw[$i]??'')!=='(')break;
            $i++;$row=[];
            while($i<$length) {
                while($i<$length&&ctype_space($raw[$i]))$i++;
                $value='';$quote=$raw[$i]??'';
                if($quote==="'"||$quote==='"') {
                    $i++;$closed=false;
                    while($i<$length) {
                        $c=$raw[$i++];
                        if($c==='\\'&&$i<$length){$next=$raw[$i++];$value.=match($next){'n'=>"\n",'r'=>"\r",'t'=>"\t",'0'=>"\0",default=>$next};continue;}
                        if($c===$quote){if(($raw[$i]??'')===$quote){$value.=$quote;$i++;continue;}$closed=true;break;}
                        $value.=$c;
                    }
                    if(!$closed)throw new RuntimeException('Niedomknięty tekst w eksporcie SQL.');
                } else {
                    $start=$i;while($i<$length&&!in_array($raw[$i],[',',')'],true))$i++;
                    $value=trim(substr($raw,$start,$i-$start));
                    if(strcasecmp($value,'NULL')===0)$value='';
                    elseif(!preg_match('/\A-?[0-9]+(?:\.[0-9]+)?\z/D',$value))throw new RuntimeException('Eksport SQL musi zawierać zwykłe wartości, bez funkcji i wyrażeń.');
                }
                $row[]=$value;while($i<$length&&ctype_space($raw[$i]))$i++;
                $end=$raw[$i++]??'';if($end===')')break;if($end!==',')throw new RuntimeException('Nieprawidłowy wiersz SQL.');
            }
            if(count($row)!==count($headers))throw new RuntimeException('Liczba wartości SQL nie zgadza się z kolumnami.');
            $rows[]=$row;if(count($rows)>10000)throw new RuntimeException('Maksymalnie 10 000 kont w jednym imporcie.');
            while($i<$length&&ctype_space($raw[$i]))$i++;
            if(($raw[$i]??'')!==',')break;$i++;
        }
        $offset=max($i,$m[0][1]+strlen($m[0][0]));
    }
    if(!$rows)throw new RuntimeException('Nie znaleziono INSERT z nazwami kolumn. Wyeksportuj tabelę jako CSV lub SQL z nazwami kolumn.');
    return [$headers,$rows];
}
function admin_customer_import_password(string $value): array
{
    if($value!==''&&strlen($value)<=255) {
        $probe=preg_replace('/\A\$2[ab]\$/','\$2y\$',$value)??$value;
        $info=password_get_info($probe);$name=$info['algoName']??'unknown';$opts=$info['options']??[];
        $validBcrypt=$name==='bcrypt'&&preg_match('/\A\$2[aby]\$[0-9]{2}\$[.\/A-Za-z0-9]{53}\z/D',$value)&&($opts['cost']??99)<=14;
        $validArgon=in_array($name,['argon2id','argon2i'],true)&&preg_match('/\A\$argon2(?:id|i)\$v=19\$m=\d+,t=\d+,p=\d+\$[A-Za-z0-9+\/]+\$[A-Za-z0-9+\/]+\z/D',$value)&&($opts['memory_cost']??PHP_INT_MAX)<=262144&&($opts['time_cost']??99)<=10&&($opts['threads']??99)<=8;
        if($validBcrypt||$validArgon)return ['mode'=>'hash','value'=>$value];
    }
    if($value===''||$value!==trim($value)||strlen($value)>72||preg_match('/[\x00-\x1f]/',$value)||preg_match('/\A[0-9a-f]{32,128}\z/iD',$value)||preg_match('/\A\$(?:2[abxy]|argon2(?:id|i)?|scrypt|[156y])\$/',$value)||preg_match('/\A(?:sha\d*|md5|pbkdf2[a-z0-9_]*|scrypt|argon2|bcrypt|\{[^}]+\})[:$]/i',$value))return ['mode'=>'generated','value'=>''];
    return ['mode'=>'plain','value'=>$value];
}
function admin_customer_import_status(string $value,string $header,string $fallback,string $numeric='auto'): array
{
    $v=strtolower(trim($value));$h=admin_import_normalize_header_cell($header);
    if($v==='')return [$fallback,false];
    if($v==='2'&&in_array($h,['status','state','account_status'],true))return ['blocked',false];
    if(in_array($v,['blocked','banned','ban','zablokowany','suspended'],true))return ['blocked',false];
    if(in_array($v,['inactive','disabled','deleted','expired','nieaktywny','nieaktywne'],true))return ['inactive',false];
    if(in_array($v,['active','enabled','aktywny','aktywne','approved'],true))return ['active',false];
    if(in_array($v,['0','1','true','false','yes','no','on','off','tak','nie'],true)) {
        $positive=in_array($v,['1','true','yes','on','tak'],true);
        $inverse=$numeric==='zero_active'||($numeric==='auto'&&in_array($h,['blocked','is_blocked','banned','is_banned','disabled','is_disabled'],true));
        if($inverse)$positive=!$positive;
        return [$positive?'active':(str_contains($h,'block')||str_contains($h,'bann')?'blocked':'inactive'),false];
    }
    return ['inactive',true];
}
function admin_customer_import_parse(string $raw,array $options=[]): array
{
    try {
        if(strlen($raw)>10*1024*1024)throw new RuntimeException('Plik jest za duży. Maksymalnie 10 MB.');
        if(str_starts_with($raw,"\xff\xfe")||str_starts_with($raw,"\xfe\xff"))$raw=mb_convert_encoding($raw,'UTF-8',str_starts_with($raw,"\xff\xfe")?'UTF-16LE':'UTF-16BE');
        $raw=preg_replace('/^\xEF\xBB\xBF/','',$raw)??$raw;
        if(!mb_check_encoding($raw,'UTF-8'))$raw=mb_convert_encoding($raw,'UTF-8','Windows-1250');
        $raw=trim($raw," \r\n");if($raw==='')throw new RuntimeException('Wklej dane lub wybierz plik.');
        $format='csv';$headers=[];$rows=[];$delimiter=',';$hasHeader=false;
        if(preg_match('/\A(?:INSERT\s+(?:IGNORE\s+)?INTO\b|--|\/\*|CREATE\s|DROP\s|SET\s|BEGIN\b|START\s+TRANSACTION\b)/i',$raw)) {
            [$headers,$rows]=admin_customer_import_sql_rows($raw);$hasHeader=true;$format='sql';
        } elseif(preg_match('/<table\b/i',$raw)) {
            if(preg_match_all('/<tr\b/i',$raw)>10001)throw new RuntimeException('Maksymalnie 10 000 kont w jednym imporcie.');
            $doc=new DOMDocument();$previous=libxml_use_internal_errors(true);$doc->loadHTML('<?xml encoding="UTF-8">'.$raw,LIBXML_NONET|LIBXML_NOERROR|LIBXML_NOWARNING);libxml_clear_errors();libxml_use_internal_errors($previous);
            foreach((new DOMXPath($doc))->query('(//table)[1]//tr') as $tr){$cells=[];foreach((new DOMXPath($doc))->query('./th|./td',$tr) as $cell)$cells[]=trim($cell->textContent);if($cells)$rows[]=$cells;}
            $format='table';
        } else {
            // SQL consoles copy tables as pipes or padded columns, not always CSV.
            $lines=explode("\n",str_replace(["\r\n","\r"],"\n",$raw));
            $tableLines=array_values(array_filter($lines,static fn($line)=>trim($line)!==''&&!preg_match('/^\s*\+[-+=]+\+\s*$/D',$line)));
            $tableFirst=trim((string)($tableLines[0]??''));
            $tableSeparator=str_starts_with($tableFirst,'|')&&str_ends_with($tableFirst,'|')?'pipe':(str_contains($tableFirst,"\t")?'':(preg_match('/\s{2,}/',$tableFirst)?'spaces':''));
            $tableHeader=$tableSeparator==='pipe'?array_map('trim',explode('|',trim($tableFirst,'| '))):($tableSeparator==='spaces'?preg_split('/\s{2,}/',$tableFirst):[]);
            $recognized=false;foreach($tableHeader as $cell)if(admin_import_header_is_email($cell))$recognized=true;
            if($recognized){
                if(count($tableLines)>10001)throw new RuntimeException('Maksymalnie 10 000 kont w jednym imporcie.');
                foreach($tableLines as $line){$line=trim($line,' ');$cells=$tableSeparator==='pipe'?array_map('trim',explode('|',trim($line,'| '))):preg_split('/[ ]{2,}/',$line);$rows[]=$cells;}
                $format='table';
            } else {
            $stream=fopen('php://temp','r+');fwrite($stream,str_replace(["\r\n","\r"],"\n",$raw));rewind($stream);
            $delimiter=admin_import_detect_delimiter(array_slice(explode("\n",$raw),0,5));
            while(($row=fgetcsv($stream,0,$delimiter,'"',''))!==false){if(count($row)>1||trim((string)($row[0]??''))!=='')$rows[]=$row;if(count($rows)>10001)break;}fclose($stream);
            }
        }
        if(!$rows)throw new RuntimeException('Nie znaleziono danych klientów.');
        $first=$rows[0];
        if(!$hasHeader)foreach($first as $cell)if(admin_import_header_is_email((string)$cell)){$headers=array_map('strval',array_shift($rows));$hasHeader=true;break;}
        // A plain email list may contain many addresses on the same line.
        if(!$hasHeader&&$format==='csv'&&!isset($options['email_column'])) {
            $tokens=preg_split('/[\s,;|]+/u',$raw,-1,PREG_SPLIT_NO_EMPTY);$onlyEmails=true;
            foreach($tokens as $token)if(!filter_var($token,FILTER_VALIDATE_EMAIL)){$onlyEmails=false;break;}
             $multi=false;foreach($rows as $row){$emailsInRow=0;foreach($row as $cell)$emailsInRow+=(int)(bool)filter_var(trim((string)$cell),FILTER_VALIDATE_EMAIL);if($emailsInRow>1){$multi=true;break;}}
            if($onlyEmails||$multi||(max(array_map('count',$rows))===1&&count($tokens)>count($rows))){$rows=array_map(static fn($e)=>[$e],$tokens);$format='emails';}
        }
        $max=max(array_map('count',$rows));if(!$headers)$headers=array_map(static fn($i)=>'Kolumna '.($i+1),range(0,$max-1));
        $map=['email'=>-1,'password'=>-1,'status'=>-1];
        foreach($headers as $i=>$header){$h=admin_import_normalize_header_cell($header);
            if(admin_import_header_is_email($header)&&$map['email']<0)$map['email']=$i;
            if(in_array($h,['password','passwd','pass','pwd','user_pass','user_password','password_hash','hashed_password','haslo','has_o'],true)&&$map['password']<0)$map['password']=$i;
            if(in_array($h,['status','state','active','is_active','enabled','is_enabled','blocked','is_blocked','banned','is_banned','disabled','is_disabled','account_status'],true)&&($map['status']<0||(in_array($h,['status','state','account_status'],true)&&!in_array(admin_import_normalize_header_cell($headers[$map['status']]),['status','state','account_status'],true))))$map['status']=$i;
        }
        if($map['email']<0){$scores=array_fill(0,count($headers),0);foreach(array_slice($rows,0,50) as $r)foreach($r as $i=>$v)if(filter_var(trim((string)$v),FILTER_VALIDATE_EMAIL))$scores[$i]=($scores[$i]??0)+1;$best=max($scores);if($best>0)$map['email']=(int)array_search($best,$scores,true);}
        foreach($map as $field=>$column)if(isset($options[$field.'_column'])){$override=(int)$options[$field.'_column'];if($override>=-1&&$override<count($headers))$map[$field]=$override;}
        if($map['email']<0)throw new RuntimeException('Nie rozpoznano kolumny e-mail. Dodaj nagłówek email albo wklej samą listę adresów.');
        if(count($rows)>10000)throw new RuntimeException('Maksymalnie 10 000 kont w jednym imporcie.');
        $items=[];$seen=[];$invalid=0;$duplicates=0;$unknown=0;$modes=['plain'=>0,'hash'=>0,'generated'=>0];
        $fallback=in_array($options['status']??'', ['active','inactive','blocked'],true)?$options['status']:'active';
        foreach($rows as $n=>$row){$email=strtolower(trim((string)($row[$map['email']]??'')));if(strlen($email)>191||!filter_var($email,FILTER_VALIDATE_EMAIL)){$invalid++;continue;}
            if($hasHeader&&count($row)!==count($headers))throw new RuntimeException('Wiersz '.($n+2).' ma inną liczbę kolumn niż nagłówek. Sprawdź separator i kompletność tabeli.');
            [$status,$unrecognized]=admin_customer_import_status($map['status']>=0?(string)($row[$map['status']]??''):'',$map['status']>=0?$headers[$map['status']]:'',$fallback,(string)($options['numeric_status']??'auto'));
            if(isset($seen[$email])){$duplicates++;if($status==='blocked')$items[$seen[$email]]['status']='blocked';continue;}$seen[$email]=count($items);
            $password=$map['password']>=0?(string)($row[$map['password']]??''):'';
            // Hash-labelled columns never treat an unsupported digest as a plain password.
            $credential=admin_customer_import_password($password);
            if($map['password']>=0&&str_contains(admin_import_normalize_header_cell($headers[$map['password']]),'hash')&&$credential['mode']==='plain')$credential=['mode'=>'generated','value'=>''];
            $unknown+=(int)$unrecognized;$modes[$credential['mode']]++;
            $items[]=['email'=>$email,'password'=>$credential['value'],'password_mode'=>$credential['mode'],'status'=>$status,'line_number'=>$n+($hasHeader?2:1)];
        }
        if(!$items)throw new RuntimeException('Nie znaleziono poprawnych adresów e-mail.');
        return ['ok'=>true,'items'=>$items,'headers'=>$headers,'mapping'=>$map,'format'=>$format,'has_header'=>$hasHeader,'delimiter'=>$delimiter,'invalid'=>$invalid,'duplicates'=>$duplicates,'unknown_status'=>$unknown,'passwords'=>$modes,'detected_email_column'=>$map['email'],'detected_email_column_label'=>$headers[$map['email']]];
    } catch(Throwable $e){return ['ok'=>false,'message'=>$e->getMessage()];}
}

function admin_customer_import_row(Mysql_ks $db,array $item,array $options,int $adminId,string $ip): array
{
    $email=(string)$item['email'];
    if(!empty($item['existing'])||app_find_customer_by_email($db,$email))return ['kind'=>'skipped'];
    $credential=admin_customer_import_password((string)($item['password']??''));
    if(($item['password_mode']??'')==='generated')$credential=['mode'=>'generated','value'=>''];
    $generated=$credential['mode']==='generated';
    $plain=$generated?admin_generate_customer_password(16):($credential['mode']==='plain'?$credential['value']:'');
    // Avoid quadratic suffix searches when thousands of addresses share e.g. "info".
    $handle=app_customer_handle_base_from_email($email);
    for($attempt=0;app_customer_public_handle_exists($db,$handle)&&$attempt<4;$attempt++)$handle=substr(app_customer_handle_base_from_email($email),0,30).'-'.substr(hash('sha256',$email.($attempt?(string)$attempt:'')),0,8);
    $db->start();
    try {
        $result=admin_create_customer_account($db,$email,$plain,[
            'locale_code'=>admin_normalize_locale((string)($options['locale_code']??'pl')),
            'status'=>$item['status'],'customer_type'=>$options['customer_type']??'client',
            'public_handle'=>$handle,'send_password_email'=>$generated&&!empty($options['send_password_email']),
            'defer_password_email'=>true,'imported_account'=>true,
            'import_password_hash'=>$credential['mode']==='hash'?$credential['value']:'',
            'provider_visibility_form_present'=>1,'visible_provider_ids'=>(array)($options['visible_provider_ids']??[]),
        ],$adminId,$ip);
        if(empty($result['ok']))throw new RuntimeException('Nie udało się utworzyć konta.');
        if($generated&&!empty($options['send_password_email'])) {
            $mail=$result['email_notification']??[];
            if(empty($mail['ok'])||empty($mail['queued'])||!empty($mail['failed']))throw new RuntimeException('Nie udało się dodać nowego hasła do kolejki e-mail. Konto nie zostało utworzone.');
        }
        if(!$db->query('COMMIT'))throw new RuntimeException('Nie udało się zatwierdzić konta.');
        return ['kind'=>'created','mode'=>$credential['mode'],'mail'=>$generated&&!empty($options['send_password_email'])?1:0];
    } catch(Throwable $e) {
        $db->query('ROLLBACK');
        if(app_find_customer_by_email($db,$email))return ['kind'=>'skipped'];
        return ['kind'=>'failed','message'=>$e->getMessage(),'retryable'=>str_contains($e->getMessage(),'kolejki e-mail')];
    }
}
function admin_customer_import_job_dir(): string
{
    $dir=app_crypto_runtime_dir().'/customer-imports';
    if(!is_dir($dir)&&!mkdir($dir,0700,true)&&!is_dir($dir))throw new RuntimeException('Nie można przygotować importu.');
    // Private encrypted jobs expire after two hours. No source/password enters session or logs.
    foreach(array_slice(glob($dir.'/*.enc')?:[],0,200) as $path)if(filemtime($path)<time()-7200){
        $lockPath=substr($path,0,-4).'.lock';$lock=fopen($lockPath,'c');
        if($lock&&flock($lock,LOCK_EX|LOCK_NB)){@unlink($path);@unlink($lockPath);flock($lock,LOCK_UN);}if($lock)fclose($lock);
    }
    return $dir;
}
function admin_customer_import_write(string $path,array $job): void
{
    $encrypted=admin_mixer_encrypt_secret(json_encode($job,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE));
    if($encrypted===''||admin_mixer_decrypt_secret($encrypted)==='')throw new RuntimeException('Nie można bezpiecznie zapisać importu.');
    $temp=$path.'.'.bin2hex(random_bytes(6)).'.tmp';
    if(file_put_contents($temp,$encrypted,LOCK_EX)===false)throw new RuntimeException('Nie można zapisać postępu importu.');
    chmod($temp,0600);if(!rename($temp,$path)){@unlink($temp);throw new RuntimeException('Nie można zapisać postępu importu.');}
}
function admin_customer_import_summary(array $job): array
{
    return ['ok'=>true,'job'=>$job['token'],'total'=>$job['total'],'processed'=>$job['cursor'],'can_start'=>$job['can_start'],'done'=>$job['cursor']>=$job['total'],
        'created'=>$job['created'],'skipped'=>$job['skipped'],'failed'=>$job['failed'],'queued'=>$job['queued'],'generated'=>$job['generated'],'preserved'=>$job['preserved'],'errors'=>$job['errors']];
}
function admin_customer_import_prepare(Mysql_ks $db,string $raw,array $options,int $adminId): array
{
    $parsed=admin_customer_import_parse($raw,$options);if(empty($parsed['ok']))return $parsed;
    $existing=[];
    foreach(array_chunk(array_column($parsed['items'],'email'),500) as $emails){
        $list=implode(',',array_map(static fn($email)=>"'".$db->escape($email)."'",$emails));
        foreach($db->select_full_user("SELECT email FROM customers WHERE email IN ({$list})")?:[] as $row)$existing[strtolower($row['email'])]=true;
    }
    $newGenerated=0;
    foreach($parsed['items'] as &$item){$item['existing']=isset($existing[$item['email']]);if(!$item['existing']&&$item['password_mode']==='generated')$newGenerated++;}unset($item);
    $token=bin2hex(random_bytes(24));
    $mailReady=app_email_smtp_is_configured(app_fetch_settings($db));
    $template=app_email_template_row($db,'password-reset');$mailReady=$mailReady&&!empty($template['is_active'])&&schema_object_exists($db,'outbound_email_queue');
    $willMail=$newGenerated>0&&!empty($options['send_password_email']);
    $job=['can_start'=>!$willMail||$mailReady,'owner'=>$adminId,'token'=>$token,'expires'=>time()+7200,'items'=>$parsed['items'],'options'=>$options,'total'=>count($parsed['items']),'cursor'=>0,'created'=>0,'skipped'=>0,'failed'=>0,'queued'=>0,'generated'=>0,'preserved'=>0,'errors'=>[]];
    admin_customer_import_write(admin_customer_import_job_dir().'/'.$token.'.enc',$job);
    $sample=[];
    foreach(array_slice($parsed['items'],0,8) as $item)$sample[]=['email'=>$item['email'],'handle'=>app_customer_handle_base_from_email($item['email']),'status'=>$item['status'],'password_mode'=>$item['password_mode'],'existing'=>!empty($item['existing'])];
    return ['ok'=>true,'job'=>$token,'total'=>count($parsed['items']),'existing'=>count($existing),'format'=>$parsed['format'],'headers'=>$parsed['headers'],'mapping'=>$parsed['mapping'],'invalid'=>$parsed['invalid'],'duplicates'=>$parsed['duplicates'],'unknown_status'=>$parsed['unknown_status'],'passwords'=>$parsed['passwords'],'sample'=>$sample,'can_start'=>!$willMail||$mailReady,'mail_ready'=>$mailReady];
}
function admin_customer_import_batch(Mysql_ks $db,string $token,int $adminId,string $ip,bool $statusOnly=false): array
{
    if(!preg_match('/\A[a-f0-9]{48}\z/D',$token))throw new RuntimeException('Nieprawidłowy identyfikator importu.');
    $base=admin_customer_import_job_dir().'/'.$token;
    if(!is_file($base.'.enc'))throw new RuntimeException('Import wygasł albo jest niedostępny. Przygotuj plik ponownie.');
    $lock=fopen($base.'.lock','c');if(!$lock)throw new RuntimeException('Nie można otworzyć importu.');chmod($base.'.lock',0600);
    if(!flock($lock,LOCK_EX|LOCK_NB)){fclose($lock);return ['ok'=>false,'busy'=>true,'message'=>'Import jest już przetwarzany. Odśwież postęp za chwilę.'];}
    try {
        $encrypted=is_file($base.'.enc')?file_get_contents($base.'.enc'):'';
        $job=json_decode(admin_mixer_decrypt_secret((string)$encrypted),true);
        if(!$job||($job['owner']??0)!==$adminId||($job['expires']??0)<time())throw new RuntimeException('Import wygasł albo jest niedostępny. Przygotuj plik ponownie.');
        if($statusOnly)return admin_customer_import_summary($job);
        if(empty($job['can_start']))throw new RuntimeException('Skonfiguruj SMTP i aktywny szablon nowego hasła lub wyłącz wysyłkę, a następnie przygotuj podgląd ponownie.');
        $start=microtime(true);$processed=0;
        while($job['cursor']<$job['total']&&$processed<25&&($processed===0||microtime(true)-$start<5)) {
            $item=$job['items'][$job['cursor']];
            $result=admin_customer_import_row($db,$item,$job['options'],$adminId,$ip);
            if(!empty($result['retryable'])){admin_customer_import_write($base.'.enc',$job);throw new RuntimeException($result['message'].' Popraw konfigurację wysyłki i wznów import.');}
            $job['cursor']++;$processed++;$job[$result['kind']]++;
            if($result['kind']==='created'){$job['queued']+=(int)$result['mail'];$job[$result['mode']==='generated'?'generated':'preserved']++;}
            if($result['kind']==='failed'&&count($job['errors'])<20)$job['errors'][]=$item['email'].': '.$result['message'];
        }
        if($job['cursor']>=$job['total']){$job['items']=[];$job['options']=[];}
        admin_customer_import_write($base.'.enc',$job);
        return admin_customer_import_summary($job);
    } finally {flock($lock,LOCK_UN);fclose($lock);}
}
