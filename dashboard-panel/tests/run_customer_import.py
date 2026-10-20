# Run from the repository root with the local Docker stack. No live rates/chain calls.
import subprocess,time,sys
name='reseller_crypto_test_'+str(time.time_ns())
cache='/tmp/'+name
root=['docker','exec','-i','reseller_db_local','sh','-c','MYSQL_PWD="$MYSQL_ROOT_PASSWORD" exec mysql -uroot']
def sql(q):
 r=subprocess.run(root,input=q.encode(),capture_output=True,check=True);return r.stdout
try:
 sql(f"CREATE DATABASE `{name}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci; GRANT ALL ON `{name}`.* TO 'marbodz_reseller'@'%';")
 schema=subprocess.run(['docker','exec','reseller_db_local','sh','-c','MYSQL_PWD="$MYSQL_ROOT_PASSWORD" exec mysqldump -uroot --no-data --skip-triggers --no-tablespaces reseller_v2'],capture_output=True,check=True).stdout
 subprocess.run(root,input=('USE `'+name+'`;\n').encode()+schema,capture_output=True,check=True)
 sql(f"INSERT INTO `{name}`.currencies SELECT * FROM reseller_v2.currencies; INSERT INTO `{name}`.crypto_assets SELECT * FROM reseller_v2.crypto_assets; INSERT INTO `{name}`.admin_roles SELECT * FROM reseller_v2.admin_roles;")
 migration=open('dashboard-panel/migrations/041_panel_subscription_notifications.sql','rb').read()
 subprocess.run(root,input=('USE `'+name+'`;\n').encode()+migration+migration,capture_output=True,check=True)
 tests=['customer_import_regression.php','customer_import_bulk_regression.php','customer_import_status_security_regression.php']
 if len(sys.argv)>1:
  if sys.argv[1] not in tests:raise RuntimeError('Unknown import test')
  tests=['customer_import_regression.php',sys.argv[1]] if sys.argv[1]=='customer_import_bulk_regression.php' else [sys.argv[1]]
 for test in tests:
  r=subprocess.run(['docker','exec','-e','DB_NAME='+name,'-e','APP_CRYPTO_CACHE_DIR='+cache,'reseller_web_local','php','/var/www/html/dashboard-panel/tests/'+test],capture_output=True,text=True)
  print(test+': '+r.stdout+r.stderr,flush=True)
  if r.returncode or 'PASS ' not in r.stdout or 'Warning:' in r.stdout+r.stderr:raise RuntimeError('Regression failed')
finally:
 sql(f"DROP DATABASE IF EXISTS `{name}`; REVOKE ALL ON `{name}`.* FROM 'marbodz_reseller'@'%';")
 subprocess.run(['docker','exec','reseller_web_local','rm','-rf',cache,cache+'-smarty'],check=True)
 print('Isolated database and crypto cache removed.',flush=True)
