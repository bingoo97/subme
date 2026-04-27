<?php
declare(strict_types=1);

require_once __DIR__ . '/_backend_locator.php';

$appRoot = app_resolve_backend_root(__DIR__);

chdir($appRoot);

