<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit(1);
$site=$argv[1];if(!in_array($site,['demo','dashboard'],true))exit(1);
$app=getenv('HOME').'/.subme-apps/'.$site.'-subme-pro/dashboard-panel';
require $app.'/vendor/autoload.php';
$compile=getenv('HOME').'/.subme-deploy-backups/crypto-reminders-20261019-01/'.$site.'/template-compile-probe';
if(!is_dir($compile)&&!mkdir($compile,0700,true))throw new RuntimeException('Cannot create private compile directory');
$smarty=new \Smarty\Smarty();$smarty->setTemplateDir($app.'/templates');$smarty->setCompileDir($compile);
foreach(['profil/payments_crypto.tpl','profil/crypto_detected.tpl'] as $template)$smarty->createTemplate($template)->compileTemplateSource();
echo "CUSTOMER_PAYMENT_PURPOSE_TEMPLATES_COMPILE_OK site={$site}\n";
