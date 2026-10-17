<?php
declare(strict_types=1);
if (($argv[1] ?? '') === '--child') {
    require $argv[2] . '/bootstrap/session.php';
    $source = file_get_contents(dirname(__DIR__) . '/admin/bootstrap.php');
    $start = strpos($source, 'function admin_start_session(): void');
    $end = strpos($source, 'function admin_send_security_headers()', $start);
    eval(substr($source, $start, $end - $start));
    ini_set('session.gc_probability', '1');
    ini_set('session.gc_divisor', '1');
    $_SERVER['HTTPS'] = 'on';
    if (($argv[4] ?? '') !== '') session_id($argv[4]);
    $argv[3] === 'admin' ? admin_start_session() : app_start_session();
    $existing = $_SESSION['session_test_marker'] ?? null;
    $_SESSION['session_test_marker'] = 'preserved';
    $result = ['id' => session_id(), 'marker' => $existing, 'params' => session_get_cookie_params(),
        'gc' => (int)ini_get('session.gc_maxlifetime'), 'path' => session_save_path(),
        'refreshed' => (int)($_SESSION['_cookie_refreshed_at'] ?? 0)];
    $_SESSION['_cookie_refreshed_at'] = time() - 172800;
    session_write_close();
    echo json_encode($result);
    exit;
}
$root = sys_get_temp_dir() . '/reseller-session-test-' . bin2hex(random_bytes(8));
mkdir($root . '/backend/bootstrap', 0700, true);
copy(dirname(__DIR__) . '/bootstrap/session.php', $root . '/backend/bootstrap/session.php');
function request_session(string $root, string $type, string $id = ''): array {
    $p = proc_open([PHP_BINARY, __FILE__, '--child', $root . '/backend', $type, $id], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
    $out = stream_get_contents($pipes[1]); $err = stream_get_contents($pipes[2]);
    foreach ($pipes as $pipe) fclose($pipe);
    if (proc_close($p) !== 0 || !is_array($value = json_decode($out, true))) throw new RuntimeException('Session child failed: ' . $err);
    return $value;
}
$checks = 0;
function check_session(bool $condition, string $label): void {
    global $checks;
    if (!$condition) throw new RuntimeException($label);
    $checks++;
}
try {
    foreach (['customer', 'admin'] as $type) {
        $first = request_session($root, $type);
        check_session($first['params']['lifetime'] === 2592000 && $first['gc'] === 2592000, "$type: 30-day cookie and storage");
        check_session($first['params']['secure'] && $first['params']['httponly'] && $first['params']['samesite'] === 'Lax' && $first['params']['domain'] === '', "$type: secure host-only cookie");
        check_session(str_ends_with($first['path'], '/sessions/' . $type), "$type: separate storage");
        touch($first['path'] . '/sess_' . $first['id'], time() - 172800);
        $second = request_session($root, $type, $first['id']);
        check_session($second['id'] === $first['id'] && $second['marker'] === 'preserved', "$type: same session after 2 idle days");
        check_session($second['refreshed'] >= time() - 10, "$type: renew expiry during use");
    }
    echo "PASS: {$checks} session lifetime/isolation/continuity assertions\n";
} finally {
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($it as $file) $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
    rmdir($root);
}
