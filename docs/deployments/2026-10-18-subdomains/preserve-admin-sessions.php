<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') exit(1);
require $argv[1] . '/bootstrap/session.php';
$destination = dirname(app_session_storage_directory()) . '/admin';
if (!is_dir($destination) && !mkdir($destination, 0700, true)) throw new RuntimeException('Cannot create session storage');
$paths = [ini_get('session.save_path')];
foreach (glob('/opt/alt/php*/var/lib/php/session', GLOB_ONLYDIR) ?: [] as $path) $paths[] = $path;
$count = 0;
foreach (array_unique($paths) as $path) {
    foreach (glob(rtrim($path, '/') . '/sess_*') ?: [] as $file) {
        if (!is_file($file) || !is_readable($file) || fileowner($file) !== posix_geteuid() || filesize($file) > 1048576) continue;
        $handle = fopen($file, 'rb');
        if (!$handle || !flock($handle, LOCK_SH)) { if ($handle) fclose($handle); continue; }
        $data = stream_get_contents($handle);
        $target = $destination . '/' . basename($file);
        if (preg_match('/(?:^|;)admin_user_id\|i:[1-9][0-9]*;/', $data) && !file_exists($target)) {
            $new = fopen($target, 'x');
            if ($new) { chmod($target, 0600); fwrite($new, $data); fclose($new); touch($target, filemtime($file)); $count++; }
        }
        flock($handle, LOCK_UN); fclose($handle);
    }
}
echo 'ADMIN_SESSIONS_PRESERVED ' . $count . PHP_EOL;
