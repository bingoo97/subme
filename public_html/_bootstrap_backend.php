<?php
declare(strict_types=1);

$appRoot = realpath(__DIR__ . '/../dashboard-panel');
if ($appRoot === false) {
    http_response_code(500);
    exit('Application backend path is invalid.');
}

chdir($appRoot);

