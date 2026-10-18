<?php

/** Bound optional cleanup on read requests across users and PHP workers. */
function app_run_read_maintenance_once(Mysql_ks $db, string $task, int $intervalSeconds, callable $work): bool
{
    static $scopes = [];
    $objectId = spl_object_id($db);
    if (!isset($scopes[$objectId])) {
        $database = $db->select_user('SELECT DATABASE() AS db_name');
        $scopes[$objectId] = hash('sha256', __DIR__ . ':' . (string)($database['db_name'] ?? ''));
    }
    $key = app_crypto_runtime_dir() . '/read-maintenance-' . hash('sha256', $scopes[$objectId] . ':' . $task);
    $intervalSeconds = max(1, $intervalSeconds);
    $isRecent = static function () use ($key, $intervalSeconds): bool {
        clearstatcache(true, $key . '.json');
        $state = app_crypto_runtime_read($key . '.json');
        $checkedAt = (int)($state['checked_at'] ?? 0);
        return $checkedAt <= time() && $checkedAt > time() - $intervalSeconds;
    };
    if ($isRecent()) return false;
    $lock = app_crypto_runtime_lock($key . '.lock');
    // Scheduled maintenance remains the fallback when private cache is unavailable.
    if (!$lock) return false;
    if (!flock($lock, LOCK_EX | LOCK_NB)) { fclose($lock); return false; }
    try {
        if ($isRecent()) return false;
        $work();
        app_crypto_runtime_write($key . '.json', ['checked_at' => time()]);
        return true;
    } finally {
        flock($lock, LOCK_UN);
        fclose($lock);
    }
}
