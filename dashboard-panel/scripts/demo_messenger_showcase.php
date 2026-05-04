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

$db = Mysql_ks::get_instance();
$settings = app_fetch_settings($db);
$emitMessages = !in_array('--no-messages', $argv, true);

$result = chat_demo_showcase_sync(
    $db,
    is_array($settings) ? $settings : [],
    [
        'emit_messages' => $emitMessages,
        'source' => 'cli',
    ]
);

echo json_encode($result, JSON_UNESCAPED_SLASHES) . PHP_EOL;

exit(!empty($result['ok']) ? 0 : 1);
