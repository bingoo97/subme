<?php
declare(strict_types=1);
if (strpos((string)getenv('DB_NAME'), '_test_') === false) throw new RuntimeException('Isolated _test_ database required.');
require_once dirname(__DIR__).'/admin/bootstrap.php';
$db=Mysql_ks::get_instance();
if (($argv[1]??'')==='--save') { $p=json_decode(base64_decode($argv[2]),true);echo json_encode(admin_billing_save_document($db,$p,1,[],!empty($p['_issue'])));exit; }
if (($argv[1]??'')==='--action') { $p=json_decode(base64_decode($argv[2]),true);echo json_encode(admin_billing_apply_document_action($db,(int)$p['id'],$p['action'],1));exit; }
if (($argv[1]??'')==='--endpoint') {
    $p=json_decode(base64_decode($argv[2]),true);admin_start_session();
    $_SESSION=['admin_user_id'=>$p['admin']??3,'admin_csrf_token'=>'billing-audit','admin_locale'=>'pl'];
    $_SERVER['REQUEST_METHOD']=empty($p['post'])?'GET':'POST';$_SERVER['HTTP_HOST']='localhost';$_GET=$p['get'];$_POST=$p['post']??[];$_FILES=[];
    ob_start();register_shutdown_function(static function():void {$body=ob_get_clean();if(session_status()===PHP_SESSION_ACTIVE)session_destroy();echo json_encode(['status'=>http_response_code()?:200,'body'=>$body]);});
    require dirname(__DIR__).'/admin/index.php';exit;
}
$checks=0;
function ck(bool $condition,string $message):void {global $checks;if(!$condition)throw new RuntimeException($message);$checks++;}
function sql(string $query):void {global $db;if(!$db->query($query))throw new RuntimeException($db->error.': '.$query);}
function child(string $mode,array $payload):array { $p=proc_open([PHP_BINARY,__FILE__,$mode,base64_encode(json_encode($payload))],[1=>['pipe','w'],2=>['pipe','w']],$pipes);return [$p,$pipes]; }
function finish(array $process):array {[$p,$pipes]=$process;$out=stream_get_contents($pipes[1]);$err=stream_get_contents($pipes[2]);foreach($pipes as $pipe)fclose($pipe);if(proc_close($p)!==0 || !is_array($result=json_decode($out,true)))throw new RuntimeException($out.$err);return $result;}
function count_rows(string $table):int {global $db;return (int)$db->select_user("SELECT COUNT(*) n FROM {$table}")['n'];}
admin_billing_ensure_runtime_schema($db);admin_app_settings($db);app_ensure_payment_request_runtime_columns($db);
sql('SET FOREIGN_KEY_CHECKS=0');
foreach (['billing_document_deliveries','billing_document_items','billing_item_catalog','billing_automation_events','billing_documents','billing_customers','orders','products','customers','admin_users'] as $table)sql("DELETE FROM {$table}");
sql('SET FOREIGN_KEY_CHECKS=1');
sql("INSERT INTO admin_roles(id,name,slug,access_level) VALUES(3,'Billing denied','billing-denied',100) ON DUPLICATE KEY UPDATE access_level=100");
sql("INSERT INTO admin_users(id,role_id,login_name,email,password_hash) VALUES(1,1,'billing-audit','billing-owner@example.invalid','unused'),(3,3,'billing-denied','billing-denied@example.invalid','unused')");
sql("INSERT INTO customers(id,email,password_hash,status) VALUES(1,'billing-one@example.invalid','unused','active'),(2,'billing-two@example.invalid','unused','active')");
sql("UPDATE app_settings SET billing_enabled=1,billing_auto_issue_email_enabled=0,demo_messenger_showcase_enabled=0");
sql("UPDATE billing_company_profiles SET is_active=1,country_code='AT',company_name='Billing Audit Ltd',address_line1='Teststrasse 1',postal_code='1010',city='Vienna',country_name='Austria',vat_status='small_business_exempt',business_activity='general',number_prefix='AUDIT-{YYYY}-',next_number=1,default_currency_code='EUR' WHERE id=1");
$base=['company_profile_id'=>1,'source_type'=>'independent','buyer_name'=>'Audit Buyer','buyer_email'=>'buyer@example.invalid','buyer_address_line1'=>'Test 2','buyer_postal_code'=>'1010','buyer_city'=>'Vienna','destination_country_code'=>'AT','language_code'=>'pl','issue_date'=>date('Y-m-d'),'service_date'=>date('Y-m-d'),'due_date'=>date('Y-m-d',time()+86400),'currency_code'=>'EUR','supply_type'=>'digital_service','buyer_type'=>'consumer','tax_treatment'=>'small_business_exempt','tax_review_confirmed'=>1,'item_description'=>['Audit service'],'item_quantity'=>['1'],'item_unit'=>['szt.'],'item_unit_price'=>['12.34'],'item_tax_rate'=>['0']];
foreach(['abc','', '1e999', '-1'] as $bad)ck(empty(admin_billing_normalize_items(array_replace($base,['item_unit_price'=>[$bad]]))['ok']),'Invalid numeric price rejected');
ck(empty(admin_billing_normalize_items(array_replace($base,['item_quantity'=>['1e999']]))['ok']),'Infinite quantity rejected');
ck(empty(admin_billing_normalize_items(array_replace($base,['item_quantity'=>['999999999'],'item_unit_price'=>['99999999']]))['ok']),'Line overflow rejected');
ck(admin_billing_valid_date("2026-01-01\0")===null && admin_billing_valid_date('2025-02-29')===null && admin_billing_valid_date('2024-02-29')==='2024-02-29','Strict date/leap validation');
foreach(['net','gross'] as $mode)foreach([0,5,20,23] as $rate)foreach([.333,1,3] as $quantity) {
    $r=admin_billing_normalize_items(array_replace($base,['price_entry_mode'=>$mode,'item_quantity'=>[(string)$quantity],'item_unit_price'=>['12.3456'],'item_tax_rate'=>[(string)$rate]]));
    $item=$r['items'][0]??[];ck(!empty($r['ok']) && abs($item['line_gross']-$item['line_net']-$item['line_tax'])<.00001,'Net/gross rounding invariant');
}
$token=str_repeat('a',64);$input=$base+['billing_submission_token'=>$token,'_issue'=>true];
$children=[];for($i=0;$i<8;$i++)$children[]=child('--save',$input);$results=array_map('finish',$children);
$issued=admin_billing_save_document($db,$input,1,[],true);ck(!empty($issued['ok']), 'Issue is retryable with its original submission identity');$id=(int)$issued['document_id'];
ck(count_rows('billing_documents')===1,'Parallel submits issue one independent invoice');
ck((int)$db->select_user('SELECT next_number FROM billing_company_profiles WHERE id=1')['next_number']===2,'Parallel submit consumes one number');
$saved=admin_billing_document_row($db,$id);ck($saved['status']==='issued' && (float)$saved['total_gross']===12.34,'Issued document stores expected totals');
ck(empty(admin_billing_save_document($db,$base+['billing_document_id'=>$id],1,[],false)['ok']),'Issued document cannot revert to draft');
$draft=admin_billing_save_document($db,$base+['billing_submission_token'=>str_repeat('b',64)],1,[],false);ck(!empty($draft['ok']),'Draft is saved');
$draftInput=$base+['billing_document_id'=>$draft['document_id'],'_issue'=>true];$children=[child('--save',$draftInput),child('--save',$draftInput)];array_map('finish',$children);
ck(admin_billing_document_row($db,(int)$draft['document_id'])['status']==='issued' && (int)$db->select_user('SELECT next_number FROM billing_company_profiles WHERE id=1')['next_number']===3,'Draft issuance is serialized and consumes one number');
$children=[child('--action',['id'=>$id,'action'=>'mark_paid']),child('--action',['id'=>$id,'action'=>'cancel'])];$actions=array_map('finish',$children);
ck(count(array_filter($actions,fn($r)=>!empty($r['ok'])))===1,'Concurrent paid/cancel actions cannot both succeed');
$after=admin_billing_document_row($db,$id);ck(($after['status']==='paid' && !$after['cancelled_at']) || ($after['status']==='cancelled' && !$after['paid_at']),'Final document timestamps agree with final status');
// Fail a line write after optional customer persistence and number allocation.
$before=[count_rows('billing_documents'),count_rows('billing_customers'),$db->select_user('SELECT next_number FROM billing_company_profiles WHERE id=1')['next_number']];
sql("ALTER TABLE billing_document_items ADD CONSTRAINT billing_audit_failure CHECK (description <> 'FAIL')");
$failed=admin_billing_save_document($db,array_replace($base,['save_billing_customer'=>1,'item_description'=>['FAIL'],'billing_submission_token'=>str_repeat('c',64)]),1,[],true);
sql('ALTER TABLE billing_document_items DROP CHECK billing_audit_failure');
ck(empty($failed['ok']) && $before===[count_rows('billing_documents'),count_rows('billing_customers'),$db->select_user('SELECT next_number FROM billing_company_profiles WHERE id=1')['next_number']],'Failed item rolls back document/customer/number atomically');
$buyer=admin_billing_save_customer($db,$base+['linked_customer_id'=>1,'buyer_company_name'=>'Buyer Company','buyer_phone'=>'123'],1);
$buyerRow=$db->select_user('SELECT * FROM billing_customers WHERE id='.(int)$buyer['billing_customer_id']);
ck($buyerRow['company_name']==='Buyer Company' && $buyerRow['phone']==='123','Saving billing customer retains company and phone from document form');
ck(empty(admin_billing_save_document($db,$base+['linked_customer_id'=>2,'billing_customer_id'=>$buyer['billing_customer_id']],1,[],false)['ok']),'Independent document cannot link a different service user than its billing customer');
$beforeDefault=$db->select_user("SELECT id FROM billing_tax_rates WHERE country_code='AT' AND is_default=1")['id'];
$failed=admin_billing_save_tax_rate($db,['tax_rate_id'=>999999,'tax_country_code'=>'AT','tax_label'=>'Test','tax_rate_percent'=>'20','tax_is_default'=>1,'tax_is_active'=>1]);
ck(empty($failed['ok']) && $beforeDefault===$db->select_user("SELECT id FROM billing_tax_rates WHERE country_code='AT' AND is_default=1")['id'],'Invalid default-rate edit preserves the previous default');
ck(empty(admin_billing_save_tax_rate($db,['tax_country_code'=>'AT','tax_label'=>'Invalid','tax_rate_percent'=>'abc'])['ok']),'Malformed VAT rate rejected');
// Source lookup must find old paid orders beyond the previous 500-row cap.
sql("INSERT INTO products(id,name,slug,price_amount,currency_id,duration_hours,is_active) VALUES(1,'Audit product','billing-audit-product',12.34,2,720,1)");
for($i=1;$i<=502;$i++)sql("INSERT INTO orders(id,customer_id,product_id,total_amount,currency_id,status,payment_status,paid_at) VALUES({$i},1,1,12.34,2,'active','paid',NOW())");
ck((int)(admin_billing_source_record($db,'order',1)['id']??0)===1,'Old paid source is found directly by ID');
sql("UPDATE billing_company_profiles SET logo_url='/uploads/billing/current-logo.png' WHERE id=1");
$immutable=admin_billing_document_with_profile_logo($db,['status'=>'issued','company_profile_id'=>1,'seller_snapshot'=>'{}']);ck($immutable['seller_snapshot']==='{}','Issued issuer snapshot does not change after logo edits');
ck(admin_billing_pdf_logo_data_uri('/uploads/billing/../../img/crypto/btc.png')==='','Logo traversal outside billing uploads rejected');
$lock=admin_billing_operation_lock($db,'document:'.$id);$busy=finish(child('--action',['id'=>$id,'action'=>'mark_paid']));admin_billing_operation_unlock($db,$lock);
ck(empty($busy['ok']) && str_contains($busy['message'],'being processed'),'Busy document operation fails fast');
foreach([['page'=>'billing','billing_pdf'=>$id],['page'=>'billing','billing_search'=>1,'type'=>'billing_customer','q'=>'Audit']] as $get)ck(finish(child('--endpoint',['get'=>$get]))['status']===403,'Restricted role cannot retrieve billing PDF or lookup data');
$countBefore=count_rows('billing_documents');$csrf=finish(child('--endpoint',['admin'=>1,'get'=>['page'=>'billing'],'post'=>['admin_billing_save_document'=>1]+$base]));
ck(count_rows('billing_documents')===$countBefore,'Missing CSRF does not create a document');
$_GET=['billing_tab'=>'documents'];$ctx=admin_billing_context($db,['id'=>1],[],'en',[],[]);
ck($ctx['stats']===[] && $ctx['orders']===[] && $ctx['payments']===[] && $ctx['billing_customers']===[],'Document listing avoids unrelated statistics and large source/customer lists');
$_GET=['billing_tab'=>'create'];$ctx=admin_billing_context($db,['id'=>1],[],'en',[],[]);
ck($ctx['stats']===[] && $ctx['documents']===[],'Creation form avoids listing/statistics queries');
sql("ALTER TABLE billing_tax_rates ADD CONSTRAINT billing_tax_audit_failure CHECK (label <> 'FAIL')");
$rateFailure=admin_billing_save_tax_rate($db,['tax_country_code'=>'AT','tax_label'=>'FAIL','tax_rate_percent'=>'20','tax_is_default'=>1,'tax_is_active'=>1]);
sql('ALTER TABLE billing_tax_rates DROP CHECK billing_tax_audit_failure');
ck(empty($rateFailure['ok']) && $beforeDefault===$db->select_user("SELECT id FROM billing_tax_rates WHERE country_code='AT' AND is_default=1")['id'],'Tax insertion failure rolls back clearing the previous default');
$olderDraft=admin_billing_save_document($db,array_replace($base,['source_type'=>'order','source_record_id'=>1]),1,[],false);
ck(!empty($olderDraft['ok']),'Old paid order can back a draft');
$_GET=['billing_tab'=>'create','edit_document'=>$olderDraft['document_id']];$ctx=admin_billing_context($db,['id'=>1],[],'en',[],[]);
ck(in_array(1,array_map('intval',array_column($ctx['orders'],'id')),true),'Editing an old draft retains its source option outside the latest 300 orders');
$badTotal=admin_billing_save_document($db,array_replace($base,['source_type'=>'order','source_record_id'=>2,'item_unit_price'=>['99']]),1,[],true);
ck(empty($badTotal['ok']) && str_contains($badTotal['message'],'differs'),'Source amount mismatch needs deliberate confirmation');
$badDue=admin_billing_save_document($db,array_replace($base,['due_date'=>'2020-01-01']),1,[],true);
ck(empty($badDue['ok']) && str_contains($badDue['message'],'due date'),'Independent due date cannot precede issue date');
$restored=admin_billing_form_document_from_input(array_replace($base,['price_entry_mode'=>'gross','item_unit_price'=>['12.3456'],'billing_submission_token'=>$token]));
ck($restored['items'][0]['unit_price_net']==='12.3456' && $restored['billing_submission_token']===$token,'Validation errors preserve gross inputs and request identity');
$deliveries=count_rows('billing_document_deliveries');
ck(empty(admin_billing_send_document_email($db,admin_billing_document_row($db,(int)$draft['document_id']),1,false)['ok']) && count_rows('billing_document_deliveries')===$deliveries,'Unconfirmed email delivery neither sends nor creates a delivery record');
$beforeCustomers=count_rows('billing_customers');
$prg=finish(child('--endpoint',['admin'=>1,'get'=>['page'=>'billing','billing_tab'=>'customers'],'post'=>['admin_billing_save_customer'=>1,'_csrf'=>'billing-audit','billing_customer_display_name'=>'PRG test','billing_customer_email'=>'prg@example.invalid']]));
ck($prg['status']===302 && count_rows('billing_customers')===$beforeCustomers+1,'Successful customer POST redirects before browser refresh can repeat it');
$cancelInput=$base+['billing_submission_token'=>str_repeat('d',64)];
$toCancel=admin_billing_save_document($db,$cancelInput,1,[],true);
ck(!empty(admin_billing_apply_document_action($db,(int)$toCancel['document_id'],'cancel',1)['ok']),'Independent issued document can be cancelled');
$countBefore=count_rows('billing_documents');$replayed=admin_billing_save_document($db,$cancelInput,1,[],true);
ck((int)$replayed['document_id']===(int)$toCancel['document_id'] && $replayed['status']==='cancelled' && count_rows('billing_documents')===$countBefore,'Replayed cancelled form cannot silently issue another invoice');
$newCustomers=[];for($i=10;$i<=510;$i++)$newCustomers[]="({$i},'older-test-{$i}@example.invalid','unused','active')";
sql('INSERT INTO customers(id,email,password_hash,status) VALUES'.implode(',',$newCustomers));
$_GET=['billing_tab'=>'create','edit_document'=>$olderDraft['document_id']];$ctx=admin_billing_context($db,['id'=>1],[],'en',[],[]);
ck(in_array(1,array_map('intval',array_column($ctx['site_customers'],'id')),true),'Older linked service account remains selectable when editing a draft');
// Build a multi-page PDF entirely from synthetic data; never send it.
$pdfDoc=admin_billing_document_row($db,(int)$draft['document_id']);$pdfDoc['items']=[];
for($i=1;$i<=32;$i++)$pdfDoc['items'][]=['description'=>'Pozycja testowa '.$i.' - Ćwiczenia, płatności i dokumenty','quantity'=>1,'unit_label'=>'szt.','unit_price_net'=>12.34,'tax_rate_percent'=>0,'line_net'=>12.34,'line_tax'=>0,'line_gross'=>12.34];
$pdfDoc['subtotal_net']=$pdfDoc['total_gross']=32*12.34;$pdfDoc['tax_total']=0;
$pdf=admin_billing_document_pdf_bytes($pdfDoc);ck(str_starts_with($pdf,'%PDF-') && strlen($pdf)>10000,'Multi-page PDF generation succeeds');
$path=dirname(__DIR__,2).'/tmp/pdfs/billing-audit.pdf';file_put_contents($path,$pdf);
echo "PASS: {$checks} billing numeric/state/concurrency/permission/workflow assertions\n";
