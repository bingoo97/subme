<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/_backend_locator.php';

$appRoot = app_resolve_backend_root(dirname(__DIR__));
$adminRoot = realpath($appRoot . '/admin');
if ($adminRoot === false) {
    http_response_code(500);
    exit('Admin backend path is invalid.');
}

chdir($adminRoot);
