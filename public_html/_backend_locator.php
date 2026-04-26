<?php
declare(strict_types=1);

function app_resolve_backend_root(string $publicRoot): string
{
    $publicRoot = rtrim($publicRoot, DIRECTORY_SEPARATOR);
    $candidates = [];

    $envOverride = trim((string)getenv('APP_BACKEND_ROOT'));
    if ($envOverride !== '') {
        $candidates[] = $envOverride;
    }

    $pointerFile = $publicRoot . '/.backend-path';
    if (is_file($pointerFile)) {
        $fileOverride = trim((string)file_get_contents($pointerFile));
        if ($fileOverride !== '') {
            $candidates[] = $fileOverride;
        }
    }

    $candidates[] = $publicRoot . '/../dashboard-panel';

    foreach ($candidates as $candidate) {
        $resolved = realpath($candidate);
        if ($resolved !== false && is_dir($resolved)) {
            return $resolved;
        }
    }

    http_response_code(500);
    exit('Application backend path is invalid.');
}
