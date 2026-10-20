<?php
declare(strict_types=1);
if(strpos((string)getenv('DB_NAME'),'_test_')===false)throw new RuntimeException('Disposable test database required');
require_once dirname(__DIR__).'/admin/bootstrap.php';
$db=Mysql_ks::get_instance();
if(($argv[1]??'')==='--seed') {app_ensure_system_content_pages_runtime($db);echo 'OK';exit;}
if(($argv[1]??'')==='--request'){
 $r=json_decode(base64_decode($argv[2]),true);admin_start_session();$_SESSION=['admin_user_id'=>$r['admin']??1,'admin_locale'=>'pl','admin_csrf_token'=>'fixture'];
 $_SERVER['HTTP_HOST']='localhost';$_SERVER['REMOTE_ADDR']='127.0.0.1';$_SERVER['REQUEST_METHOD']=isset($r['delete'])?'POST':'GET';$_GET=['page'=>'pages'];$_POST=isset($r['delete'])?['admin_delete_page'=>'1','page_id'=>$r['delete'],'_csrf'=>$r['csrf']??'fixture']:[];
 ob_start();register_shutdown_function(static function(){ $html=ob_get_clean();if(session_status()===PHP_SESSION_ACTIVE)session_destroy();echo json_encode(['status'=>http_response_code()?:200,'html'=>$html]);});require dirname(__DIR__).'/admin/index.php';exit;
}
$n=0;function ck($v,$m){global $n;if(!$v)throw new RuntimeException($m);$n++;}
function sql($q){global $db;if(!$db->query($q))throw new RuntimeException($db->error);}
function request($r){$p=proc_open([PHP_BINARY,__FILE__,'--request',base64_encode(json_encode($r))],[1=>['pipe','w'],2=>['pipe','w']],$pipes);$out=stream_get_contents($pipes[1]);$err=stream_get_contents($pipes[2]);foreach($pipes as $f)fclose($f);if(proc_close($p)!==0)throw new RuntimeException($err.$out);$v=json_decode($out,true);if(!$v)throw new RuntimeException($err.$out);return $v;}
app_fetch_settings($db);admin_ensure_user_runtime_columns($db);sql('SET FOREIGN_KEY_CHECKS=0');sql('INSERT IGNORE INTO app_settings(id) VALUES(1)');sql("UPDATE app_settings SET admin_panel_subscription_paid_until='2035-12-31',maintenance_mode=0,default_currency_id=2 WHERE id=1");
sql("INSERT INTO admin_roles(id,name,slug,access_level) VALUES(901,'Boss','pages-boss',1000),(902,'Limited','pages-limited',100) ON DUPLICATE KEY UPDATE access_level=VALUES(access_level)");
sql("INSERT INTO admin_users(id,role_id,login_name,email,password_hash) VALUES(1,901,'pageowner','owner@example.invalid','unused'),(2,902,'limited','limited@example.invalid','unused')");
app_ensure_system_content_pages_runtime($db);
$html=request([])['html'];ck(str_contains($html,'name="admin_delete_page"'),'System pages expose delete controls in the listing');

$custom=admin_create_page($db,['title'=>'Training news','slug'=>'training-news','locale_code'=>'pl','menu_section'=>'homepage','menu_title'=>'New materials','menu_sort_order'=>25,'body'=>'<p>New materials are available.</p>','is_active'=>'1']);
ck(!empty($custom['ok']),'A regular page can be created as a customer homepage tile');
$customId=(int)$custom['page_id'];$tiles=app_customer_homepage_pages($db,'pl');
ck(count($tiles)===1&&$tiles[0]['title']==='New materials'&&$tiles[0]['href']==='/training-news','Homepage tile uses its custom title and public URL');
ck(app_customer_homepage_pages($db,'en')===[],'Homepage tiles respect customer language');
sql("UPDATE static_pages SET is_active=0 WHERE id={$customId}");ck(app_customer_homepage_pages($db,'pl')===[],'Disabled page disappears from homepage');
sql("UPDATE static_pages SET is_active=1 WHERE id={$customId}");ck(!empty(admin_delete_page($db,$customId)['ok'])&&app_customer_homepage_pages($db,'pl')===[],'Deleting a regular page removes its homepage tile');
ck(!app_static_page_find_for_route($db,'training-news','pl'),'Deleted custom page cannot be opened through its old route');
$deleted=[];
foreach(['instruction-smart-iptv','instruction-trust-wallet','apps','instructions'] as $route){
 $slug=app_static_page_locale_slug($route,'pl');$row=$db->select_user("SELECT id FROM static_pages WHERE slug='".$db->escape($slug)."'");$id=(int)$row['id'];
 ck($id>0,'Seeded system page exists: '.$route);
 request(['delete'=>$id,'csrf'=>'wrong']);ck(admin_page_find($db,$id)!==null,'Invalid CSRF cannot delete a page');
 request(['delete'=>$id,'admin'=>2]);ck(admin_page_find($db,$id)!==null,'Limited role cannot delete pages');
 ck(request(['delete'=>$id])['status']===302,'Admin can delete system page: '.$route);
 ck(admin_page_find($db,$id)===null,'Deleted page is absent from the editor/list');
 ck(!app_static_page_route_is_publicly_visible($db,$route,'pl')&&app_static_page_find_for_route($db,$route,'pl')===null,'Deleted locale is hidden without falling back to another language');
 ck(app_static_page_route_is_publicly_visible($db,$route,'en'),'Deleting Polish page preserves English content');
 $t=$db->select_user("SELECT page_type,title,body,is_active FROM static_pages WHERE id={$id}");ck($t['page_type']==='deleted'&&$t['title']===''&&$t['body']===''&&(int)$t['is_active']===0,'Only a content-free deletion marker remains');
 $deleted[]=$id;
}
$p=proc_open([PHP_BINARY,__FILE__,'--seed'],[1=>['pipe','w'],2=>['pipe','w']],$pipes);$out=stream_get_contents($pipes[1]);$err=stream_get_contents($pipes[2]);foreach($pipes as $f)fclose($f);ck(proc_close($p)===0&&$out==='OK','Fresh runtime seeding completes after deletions');
foreach($deleted as $id)ck(admin_page_find($db,$id)===null,'Runtime seed never resurrects deleted content');
foreach(admin_page_all_rows($db) as $row)ck(!in_array((int)$row['id'],$deleted,true),'Admin list excludes deletion markers');
$remaining=admin_page_rows_by_locale($db,'pl',100);foreach($remaining as $row)ck(!empty(admin_delete_page($db,(int)$row['id'])['ok']),'Remaining pages can be deleted');
$pl=(require dirname(__DIR__).'/locales/pl.php')['messages'];ck(app_instruction_guides_from_static_pages($db,$pl,'pl')===[],'An empty guide section stays empty instead of restoring fallback cards');
ck(admin_page_rows_by_locale($db,'en',100)!==[],'Other locale is not affected by deleting all Polish pages');
echo "PASS {$n} page deletion, seed persistence, locale and authorization assertions\n";
