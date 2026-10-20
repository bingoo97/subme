<?php
declare(strict_types=1);
require_once __DIR__.'/bootstrap.php';
require_once __DIR__.'/customer_import_lib.php';
admin_send_security_headers();admin_start_session();
header('Content-Type: application/json; charset=utf-8');header('Cache-Control: no-store');
$db=Mysql_ks::get_instance();$actor=admin_load_session_user($db);
if(!$actor||!admin_user_can_access_route($actor,'users')){http_response_code(403);echo json_encode(['ok'=>false,'message'=>'Brak dostępu.']);exit;}
try {
    if((int)($_SERVER['CONTENT_LENGTH']??0)>12*1024*1024)throw new RuntimeException('Maksymalnie 10 MB danych.');
    if(($_SERVER['REQUEST_METHOD']??'')!=='POST'||!is_string($_POST['_csrf']??null)||!admin_csrf_is_valid($_POST['_csrf'])){http_response_code(403);echo json_encode(['ok'=>false,'message'=>'Sesja wygasła. Odśwież stronę.']);exit;}
    foreach($_POST as $key=>$value)if($key!=='visible_provider_ids'&&!is_scalar($value))throw new RuntimeException('Nieprawidłowe dane formularza.');
    $action=(string)($_POST['action']??'prepare');
    if($action==='prepare') {
        $raw=(string)($_POST['import_source_text']??'');$file=$_FILES['import_source_file']??null;
        if($file&&($file['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_NO_FILE) {
            if(trim($raw)!=='')throw new RuntimeException('Wybierz plik albo wklej treść — nie oba źródła jednocześnie.');
            if(($file['error']??1)!==UPLOAD_ERR_OK||!is_uploaded_file($file['tmp_name']??'')||($file['size']??PHP_INT_MAX)>10*1024*1024)throw new RuntimeException('Nie udało się odczytać pliku. Maksymalnie 10 MB.');
            if(trim($raw)==='')$raw=(string)file_get_contents($file['tmp_name']);
        }
        $options=['locale_code'=>admin_normalize_locale((string)($_POST['import_locale_code']??'pl')),'status'=>(string)($_POST['import_status']??'active'),
            'customer_type'=>app_normalize_customer_type((string)($_POST['import_customer_type']??'client')),'send_password_email'=>!empty($_POST['import_send_password_email']),
            'visible_provider_ids'=>array_map('intval',array_filter((array)($_POST['visible_provider_ids']??[]),'is_scalar')),'numeric_status'=>(string)($_POST['numeric_status']??'auto')];
        foreach(['email','password','status'] as $field)if(isset($_POST[$field.'_column'])&&$_POST[$field.'_column']!=='auto')$options[$field.'_column']=(int)$_POST[$field.'_column'];
        $result=admin_customer_import_prepare($db,$raw,$options,(int)$actor['id']);
    } elseif(in_array($action,['batch','status'],true)) {
        $token=(string)($_POST['job']??'');$actorId=(int)$actor['id'];
        // Release the PHP session while hashing a batch; other admin tabs stay responsive.
        session_write_close();
        $result=admin_customer_import_batch($db,$token,$actorId,(string)($_SERVER['REMOTE_ADDR']??''),$action==='status');
    } else throw new RuntimeException('Nieznana operacja importu.');
    if(empty($result['ok']))http_response_code(!empty($result['busy'])?409:422);
    echo json_encode($result,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);
} catch(Throwable $e){http_response_code(422);echo json_encode(['ok'=>false,'message'=>$e instanceof RuntimeException?$e->getMessage():'Nie udało się wykonać importu. Sprawdź postęp przed ponowieniem.'],JSON_UNESCAPED_UNICODE);}
