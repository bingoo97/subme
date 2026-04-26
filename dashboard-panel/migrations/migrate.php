<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "This migration runner is available only from CLI.\n");
    exit(1);
}

require_once __DIR__ . '/../config/mysql.php';

$db = Mysql_ks::get_instance();
$migrationDirectory = __DIR__;
$command = $argv[1] ?? 'up';

ensureMigrationTable($db);

$allMigrationFiles = discoverMigrationFiles($migrationDirectory);
$appliedMigrations = fetchAppliedMigrations($db);

if ($command === 'status') {
    printMigrationStatus($allMigrationFiles, $appliedMigrations);
    exit(0);
}

$dryRun = in_array('--dry-run', $argv, true);
$pendingMigrations = [];

foreach ($allMigrationFiles as $migrationFile) {
    $migrationName = basename($migrationFile);

    if (!isExecutableMigration($migrationName)) {
        continue;
    }

    if (isset($appliedMigrations[$migrationName])) {
        $currentChecksum = hash_file('sha256', $migrationFile) ?: '';
        if ($appliedMigrations[$migrationName]['checksum'] !== $currentChecksum) {
            fwrite(
                STDERR,
                "Checksum mismatch for already applied migration {$migrationName}. Review the file before re-running.\n"
            );
            exit(1);
        }

        continue;
    }

    $pendingMigrations[] = $migrationFile;
}

if ($dryRun) {
    foreach ($pendingMigrations as $pendingMigration) {
        fwrite(STDOUT, '[DRY RUN] Pending migration: ' . basename($pendingMigration) . PHP_EOL);
    }

    if (!$pendingMigrations) {
        fwrite(STDOUT, "No pending executable migrations.\n");
    }

    exit(0);
}

foreach ($pendingMigrations as $pendingMigration) {
    $migrationName = basename($pendingMigration);
    $migrationChecksum = hash_file('sha256', $pendingMigration) ?: '';

    fwrite(STDOUT, "Applying {$migrationName}..." . PHP_EOL);

    try {
        executeSqlFile($db, $pendingMigration);
        recordAppliedMigration($db, $migrationName, $migrationChecksum);
        fwrite(STDOUT, "Applied {$migrationName}" . PHP_EOL);
    } catch (Throwable $exception) {
        fwrite(STDERR, "Migration failed: {$migrationName}" . PHP_EOL);
        fwrite(STDERR, $exception->getMessage() . PHP_EOL);
        exit(1);
    }
}

if (!$pendingMigrations) {
    fwrite(STDOUT, "No pending executable migrations.\n");
}

function ensureMigrationTable(Mysql_ks $db): void
{
    $db->query(
        "CREATE TABLE IF NOT EXISTS `schema_migrations` (
            `id` INT NOT NULL AUTO_INCREMENT,
            `name` VARCHAR(255) NOT NULL,
            `checksum` CHAR(64) NOT NULL,
            `applied_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uniq_schema_migrations_name` (`name`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );
}

function discoverMigrationFiles(string $migrationDirectory): array
{
    $migrationFiles = glob($migrationDirectory . '/*.sql') ?: [];
    sort($migrationFiles, SORT_NATURAL);
    return $migrationFiles;
}

function isExecutableMigration(string $migrationName): bool
{
    if (!preg_match('/^\d{3}_.+\.sql$/', $migrationName)) {
        return false;
    }

    $nonExecutableMigrations = [
        '001_preflight_schema_audit.sql',
        '003_english_rename_plan.sql',
    ];

    return !in_array($migrationName, $nonExecutableMigrations, true);
}

function fetchAppliedMigrations(Mysql_ks $db): array
{
    $rows = $db->select_full('schema_migrations', '*', 'ORDER BY id ASC');
    $appliedMigrations = [];

    foreach ($rows as $row) {
        if (!isset($row['name'])) {
            continue;
        }

        $appliedMigrations[$row['name']] = $row;
    }

    return $appliedMigrations;
}

function printMigrationStatus(array $migrationFiles, array $appliedMigrations): void
{
    foreach ($migrationFiles as $migrationFile) {
        $migrationName = basename($migrationFile);
        $statusLabel = 'skipped';

        if (isExecutableMigration($migrationName)) {
            $statusLabel = isset($appliedMigrations[$migrationName]) ? 'applied' : 'pending';
        }

        fwrite(STDOUT, str_pad($statusLabel, 10) . ' ' . $migrationName . PHP_EOL);
    }
}

function executeSqlFile(Mysql_ks $db, string $migrationPath): void
{
    $sqlSource = file_get_contents($migrationPath);
    if ($sqlSource === false) {
        throw new RuntimeException("Unable to read migration file: {$migrationPath}");
    }

    $statements = parseSqlStatements($sqlSource);
    foreach ($statements as $statement) {
        if (trim($statement) === '') {
            continue;
        }

        $result = $db->query($statement);
        if ($result === false) {
            $errorMessage = $db->error ?? 'Unknown SQL error.';
            throw new RuntimeException("SQL execution failed.\n{$errorMessage}\n\nStatement:\n{$statement}");
        }
    }
}

function parseSqlStatements(string $sqlSource): array
{
    $lines = preg_split('/\R/', $sqlSource) ?: [];
    $delimiter = ';';
    $buffer = '';
    $statements = [];

    foreach ($lines as $line) {
        if (preg_match('/^\s*DELIMITER\s+(\S+)\s*$/i', $line, $matches)) {
            $delimiter = $matches[1];
            continue;
        }

        if ($buffer === '' && preg_match('/^\s*(--|#)/', $line)) {
            continue;
        }

        $buffer .= $line . "\n";

        if (statementEndsWithDelimiter($buffer, $delimiter)) {
            $statement = stripTrailingDelimiter($buffer, $delimiter);
            if (trim($statement) !== '') {
                $statements[] = trim($statement);
            }
            $buffer = '';
        }
    }

    if (trim($buffer) !== '') {
        $statements[] = trim($buffer);
    }

    return $statements;
}

function statementEndsWithDelimiter(string $buffer, string $delimiter): bool
{
    $trimmedBuffer = rtrim($buffer);
    return $delimiter !== '' && substr($trimmedBuffer, -strlen($delimiter)) === $delimiter;
}

function stripTrailingDelimiter(string $buffer, string $delimiter): string
{
    $trimmedBuffer = rtrim($buffer);
    return rtrim(substr($trimmedBuffer, 0, -strlen($delimiter)));
}

function recordAppliedMigration(Mysql_ks $db, string $migrationName, string $migrationChecksum): void
{
    $db->insert(
        ['name', 'checksum'],
        [$migrationName, $migrationChecksum],
        'schema_migrations'
    );
}
