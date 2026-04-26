<?php

declare(strict_types=1);

$adminRoot = realpath(__DIR__ . '/../../dashboard-panel/admin');
if ($adminRoot === false) {
    http_response_code(500);
    exit('Admin backend path is invalid.');
}

chdir($adminRoot);

