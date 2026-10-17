<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/bootstrap/crypto_runtime.php';
$dir = sys_get_temp_dir() . '/reseller_crypto_unit_test_' . getmypid();
putenv('APP_CRYPTO_CACHE_DIR=' . $dir);
if (($argv[1] ?? '') === '--stampede') {
    putenv('APP_CRYPTO_CACHE_DIR=' . $argv[2]);
    $reply=app_crypto_api_request('https://api.coingecko.com/audit-stampede',null,false,6,static function():array {
        file_put_contents(app_crypto_runtime_dir().'/calls.txt',"call\n",FILE_APPEND|LOCK_EX);
        usleep(200000);
        return ['status'=>200,'body'=>'{"ok":true}'];
    });
    echo json_encode($reply!==null);exit;
}
$checks = 0;
function crypto_check(bool $condition, string $message): void { global $checks; if (!$condition) throw new RuntimeException($message); $checks++; }
function reset_crypto_cache(): void { foreach (glob(app_crypto_runtime_dir() . '/*') ?: [] as $file) unlink($file); app_crypto_network_budget(60, 10000); }
$calls = 0;
$good = static function () use (&$calls): array { $calls++; return ['status' => 200, 'body' => '{"bitcoin":{"usd":100}}']; };
try {
    crypto_check(app_crypto_api_policy('http://api.coingecko.com/x') === null, 'Plain HTTP rejected');
    crypto_check(app_crypto_api_policy('https://api.coingecko.com.evil.invalid/x') === null, 'Lookalike host rejected');
    crypto_check(app_crypto_api_policy('https://api.coingecko.com:443/x') === null, 'Custom port rejected');
    reset_crypto_cache();
    $a='https://api.coingecko.com/api/v3/simple/price?ids=tether,bitcoin&vs_currencies=usd,eur';
    $b='https://api.coingecko.com/api/v3/simple/price?vs_currencies=eur,usd&ids=bitcoin,tether';
    crypto_check(app_crypto_api_request($a,null,false,6,$good,1000)!==null,'First price fetched');
    crypto_check(app_crypto_api_request($b,null,false,6,$good,1001)!==null && $calls===1,'Equivalent batches share cache');
    app_crypto_api_request($b,null,false,6,$good,1061);crypto_check($calls===2,'Price cache expires');
    reset_crypto_cache();$calls=0;
    $bad=static function () use (&$calls): array { $calls++; return ['status'=>429,'body'=>'','retry_after'=>300]; };
    app_crypto_api_request($a,null,false,6,$bad,2000);
    crypto_check(app_crypto_api_request('https://api.coingecko.com/api/v3/ping',null,false,6,$good,2100)===null && $calls===1,'Retry-After blocks other requests to the same provider');
    crypto_check(app_crypto_api_request($a,null,false,6,$good,2301)!==null && $calls===2,'Provider resumes after cooldown');
    reset_crypto_cache();$calls=0;
    $invalid=static function () use (&$calls): array { $calls++; return ['status'=>200,'body'=>'<html>unavailable</html>']; };
    crypto_check(app_crypto_api_request($a,null,false,6,$invalid,3000)===null,'Invalid JSON fails closed');
    app_crypto_api_request($a,null,false,6,$good,3020);crypto_check($calls===1,'Failed response has a negative cache');
    reset_crypto_cache();$calls=0;
    for($i=0;$i<3;$i++) app_crypto_api_request('https://api.ethplorer.io/getAddressInfo/test'.$i,null,false,6,$good,4000);
    crypto_check($calls===2,'Ethplorer second limit shared across different addresses');
    reset_crypto_cache();$calls=0;
    for($i=0;$i<21;$i++) app_crypto_api_request('https://api.ethplorer.io/getAddressInfo/test'.$i,null,false,6,$good,5000+$i*2);
    crypto_check($calls===20,'Ethplorer minute budget enforced');
    reset_crypto_cache();$calls=0;
    for($i=0;$i<101;$i++) app_crypto_api_request('https://api.ethplorer.io/getAddressInfo/test'.$i,null,false,6,$good,6000+$i*31);
    crypto_check($calls===100,'Ethplorer hourly budget enforced');
    reset_crypto_cache();$calls=0;
    app_crypto_api_request('https://api.mainnet-beta.solana.com',['method'=>'getSlot'],false,6,$good,10000);
    app_crypto_api_request('https://api.mainnet-beta.solana.com',['method'=>'getSlot'],false,6,$good,10001);
    app_crypto_api_request('https://api.mainnet-beta.solana.com',['method'=>'getTransaction'],false,6,$good,10002);
    crypto_check($calls===2,'Read-only RPC cache includes method and payload');
    reset_crypto_cache();$calls=0;app_crypto_network_budget(60,1);
    app_crypto_api_request($a,null,false,6,$good,11000);
    app_crypto_api_request('https://mempool.space/api/blocks/tip/height',null,true,6,$good,11001);
    crypto_check($calls===1,'Per-request network budget enforced');
    reset_crypto_cache();
    $oversize=static fn():array=>['status'=>200,'body'=>str_repeat('x',2097153)];
    crypto_check(app_crypto_api_request($a,null,false,6,$oversize,12000)===null,'Oversized responses rejected');
    $rate=['current_rate_fiat'=>42,'rate_currency_code'=>'EUR','rate_updated_at'=>gmdate('Y-m-d H:i:s')];
    crypto_check(app_crypto_rate_is_usable($rate,'EUR'),'Fresh rate accepted');
    crypto_check(!app_crypto_rate_is_usable($rate,'USD'),'Currency mismatch rejected');
    crypto_check(!app_crypto_rate_is_usable(array_merge($rate,['current_rate_fiat'=>INF]),'EUR'),'Infinite rate rejected');
    crypto_check(!app_crypto_rate_is_usable(array_merge($rate,['current_rate_fiat'=>-1]),'EUR'),'Negative rate rejected');
    crypto_check(!app_crypto_rate_is_usable(array_merge($rate,['rate_updated_at'=>gmdate('Y-m-d H:i:s',time()-4000)]),'EUR'),'Stale rate rejected for payment quotes');
    crypto_check(!app_crypto_rate_is_usable(array_merge($rate,['rate_updated_at'=>gmdate('Y-m-d H:i:s',time()+600)]),'EUR'),'Future rate timestamp rejected');
    reset_crypto_cache();
    $lock=fopen($dir.'/host-api.coingecko.com.lock','c');flock($lock,LOCK_EX);
    $started=microtime(true);$result=app_crypto_api_request($a,null,false,6,$good,13000);
    crypto_check($result===null && microtime(true)-$started<0.1,'Busy provider lock never blocks a page request');
    flock($lock,LOCK_UN);fclose($lock);
    reset_crypto_cache();
    $processes=[];
    for($i=0;$i<8;$i++) {
        $process=proc_open([PHP_BINARY,__FILE__,'--stampede',$dir],[1=>['pipe','w'],2=>['pipe','w']],$pipes);
        $processes[]=[$process,$pipes];
    }
    foreach($processes as [$process,$pipes]) {
        $out=stream_get_contents($pipes[1]);$err=stream_get_contents($pipes[2]);foreach($pipes as $pipe)fclose($pipe);
        crypto_check(proc_close($process)===0 && json_decode($out,true)!==null,'Parallel cache reader exits cleanly: '.$err);
    }
    crypto_check(count(file($dir.'/calls.txt'))===1,'Eight simultaneous requests perform one external fetch');
    // Long-window quotas remain enforced even when per-second/minute windows are clear.
    reset_crypto_cache();$calls=0;
    app_crypto_runtime_write($dir.'/host-api.ethplorer.io.json',['events'=>array_fill(0,1000,20000)]);
    crypto_check(app_crypto_api_request('https://api.ethplorer.io/daily',null,false,6,$good,25000)===null && $calls===0,'Daily provider budget enforced');
    reset_crypto_cache();
    app_crypto_runtime_write($dir.'/host-api.ethplorer.io.json',['events'=>array_fill(0,3000,20000)]);
    crypto_check(app_crypto_api_request('https://api.ethplorer.io/weekly',null,false,6,$good,150000)===null && $calls===0,'Weekly provider budget enforced');
    reset_crypto_cache();$calls=0;
    $batch=array_fill(0,12,['jsonrpc'=>'2.0','method'=>'getTransaction','id'=>1]);
    app_crypto_api_request('https://api.mainnet-beta.solana.com',$batch,false,6,$good,200000);
    $batch[0]['id']=2;
    app_crypto_api_request('https://api.mainnet-beta.solana.com',$batch,false,6,$good,200001);
    crypto_check($calls===1,'Solana batch consumes quota for each RPC operation');
    echo "PASS: {$checks} transport/rate assertions\n";
} finally { foreach(glob($dir.'/*')?:[] as $file) @unlink($file); @rmdir($dir); }
