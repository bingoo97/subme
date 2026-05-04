<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    echo "CLI only.\n";
    exit(1);
}

require_once __DIR__ . '/../config/mysql.php';
require_once __DIR__ . '/../bootstrap/schema.php';
require_once __DIR__ . '/../bootstrap/application.php';
require_once __DIR__ . '/../bootstrap/chat_groups.php';

$windowSeconds = 35;
foreach ($argv as $argument) {
    if (strpos((string)$argument, '--window-seconds=') === 0) {
        $windowSeconds = max(0, (int)substr((string)$argument, strlen('--window-seconds=')));
    }
}

$db = Mysql_ks::get_instance();
$startedAt = time();
$iteration = 0;
$lastResult = [];

do {
    $settings = app_fetch_settings($db);
    $lastResult = chat_demo_showcase_sync(
        $db,
        is_array($settings) ? $settings : [],
        [
            'emit_messages' => true,
            'source' => 'cli_worker',
        ]
    );

    $iteration++;
    if ($windowSeconds <= 0 || (time() - $startedAt) >= $windowSeconds || $iteration >= 2) {
        break;
    }

    $remaining = $windowSeconds - (time() - $startedAt);
    if ($remaining <= 0) {
        break;
    }

    sleep(min(30, $remaining));
} while (true);

echo json_encode([
    'ok' => !empty($lastResult['ok']),
    'iterations' => $iteration,
    'window_seconds' => $windowSeconds,
    'last_result' => $lastResult,
], JSON_UNESCAPED_SLASHES) . PHP_EOL;

exit(!empty($lastResult['ok']) ? 0 : 1);
