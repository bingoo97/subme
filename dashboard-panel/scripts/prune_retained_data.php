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

$db = Mysql_ks::get_instance();
$result = app_prune_retained_data($db);

echo json_encode($result, JSON_UNESCAPED_SLASHES) . PHP_EOL;

exit(!empty($result['ok']) ? 0 : 1);
