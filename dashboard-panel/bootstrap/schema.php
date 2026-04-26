<?php

function schema_object_cache(): array
{
    if (!isset($GLOBALS['schema_object_exists_cache']) || !is_array($GLOBALS['schema_object_exists_cache'])) {
        $GLOBALS['schema_object_exists_cache'] = [];
    }

    return $GLOBALS['schema_object_exists_cache'];
}

function schema_column_cache(): array
{
    if (!isset($GLOBALS['schema_column_exists_cache']) || !is_array($GLOBALS['schema_column_exists_cache'])) {
        $GLOBALS['schema_column_exists_cache'] = [];
    }

    return $GLOBALS['schema_column_exists_cache'];
}

function schema_forget_column_cache(string $tableName, string $columnName): void
{
    $cacheKey = $tableName . '.' . $columnName;
    if (isset($GLOBALS['schema_column_exists_cache'][$cacheKey])) {
        unset($GLOBALS['schema_column_exists_cache'][$cacheKey]);
    }
}

function schema_object_exists(Mysql_ks $db, string $objectName): bool
{
    $cache = schema_object_cache();

    if (array_key_exists($objectName, $cache)) {
        return $cache[$objectName];
    }

    $safeObjectName = $db->escape($objectName);
    $result = $db->select_user(
        "SELECT COUNT(*) AS total
         FROM information_schema.tables
         WHERE table_schema = DATABASE()
           AND table_name = '{$safeObjectName}'
         LIMIT 1"
    );

    $cache[$objectName] = isset($result['total']) && (int)$result['total'] > 0;
    $GLOBALS['schema_object_exists_cache'] = $cache;
    return $cache[$objectName];
}

function schema_column_exists(Mysql_ks $db, string $tableName, string $columnName): bool
{
    $cache = schema_column_cache();
    $cacheKey = $tableName . '.' . $columnName;

    if (array_key_exists($cacheKey, $cache)) {
        return $cache[$cacheKey];
    }

    $safeTableName = $db->escape($tableName);
    $safeColumnName = $db->escape($columnName);
    $result = $db->select_user(
        "SELECT COUNT(*) AS total
         FROM information_schema.columns
         WHERE table_schema = DATABASE()
           AND table_name = '{$safeTableName}'
           AND column_name = '{$safeColumnName}'
         LIMIT 1"
    );

    $cache[$cacheKey] = isset($result['total']) && (int)$result['total'] > 0;
    $GLOBALS['schema_column_exists_cache'] = $cache;
    return $cache[$cacheKey];
}

function schema_mapping(): array
{
    return [
        'messages' => [
            'read' => 'messages',
            'write' => 'komunikaty',
        ],
        'pages' => [
            'read' => 'pages',
            'write' => 'podstrony',
        ],
        'referrals' => [
            'read' => 'referrals',
            'write' => 'refy',
        ],
        'crypto_topups' => [
            'read' => 'crypto_topups',
            'write' => 'wplaty_crypto',
        ],
        'guest_visitors_online' => [
            'read' => 'guest_visitors_online',
            'write' => 'goscie_online',
        ],
        'tenant_news' => [
            'read' => 'tenant_news',
            'write' => 'resellers_news',
        ],
        'legacy_payments' => [
            'read' => 'legacy_payments',
            'write' => 'wplaty',
        ],
        'support_messages' => [
            'read' => 'support_messages',
            'write' => 'produkty_chat',
        ],
        'admin_support_messages' => [
            'read' => 'admin_support_messages',
            'write' => 'produkty_chat_admin',
        ],
        'tenant_profile' => [
            'read' => 'installation_profile',
            'write' => 'resellers',
        ],
    ];
}

function schema_read_target(Mysql_ks $db, string $logicalName): string
{
    $mapping = schema_mapping();
    if (!isset($mapping[$logicalName])) {
        return $logicalName;
    }

    $preferredReadObject = $mapping[$logicalName]['read'];
    if (schema_object_exists($db, $preferredReadObject)) {
        return $preferredReadObject;
    }

    return $mapping[$logicalName]['write'];
}

function schema_write_target(string $logicalName): string
{
    $mapping = schema_mapping();
    if (!isset($mapping[$logicalName])) {
        return $logicalName;
    }

    return $mapping[$logicalName]['write'];
}

function schema_read_column(Mysql_ks $db, string $logicalName, string $englishColumn, string $legacyColumn): string
{
    $tableName = schema_read_target($db, $logicalName);
    if (schema_column_exists($db, $tableName, $englishColumn)) {
        return $englishColumn;
    }

    return $legacyColumn;
}
