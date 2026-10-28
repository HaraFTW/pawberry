<?php

declare(strict_types=1);

/**
 * Run pending database migrations.
 * Usage: php bin/migrate.php
 */

require dirname(__DIR__) . '/bootstrap.php';

use PawBerry\Core\Database;

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "This command must be run from the CLI.\n");
    exit(1);
}

$pdo = Database::connection();

$pdo->exec(
    'CREATE TABLE IF NOT EXISTS migrations (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        migration VARCHAR(255) NOT NULL,
        applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY migrations_migration_unique (migration)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
);

$dir = dirname(__DIR__) . '/database/migrations';
$files = glob($dir . '/*.php') ?: [];
sort($files, SORT_STRING);

$appliedStmt = $pdo->query('SELECT migration FROM migrations');
$applied = $appliedStmt->fetchAll(PDO::FETCH_COLUMN);
$appliedMap = array_fill_keys($applied, true);

$pending = 0;

foreach ($files as $file) {
    $name = basename($file);
    if (isset($appliedMap[$name])) {
        echo "[skip] {$name}\n";
        continue;
    }

    echo "[run]  {$name} ... ";
    /** @var object{up: callable} $migration */
    $migration = require $file;

    if (!is_object($migration) || !method_exists($migration, 'up')) {
        fwrite(STDERR, "invalid migration (missing up()).\n");
        exit(1);
    }

    try {
        // MySQL DDL implicitly commits; avoid wrapping schema migrations in transactions.
        $migration->up($pdo);
        $insert = $pdo->prepare('INSERT INTO migrations (migration, applied_at) VALUES (?, NOW())');
        $insert->execute([$name]);
        echo "ok\n";
        $pending++;
    } catch (Throwable $e) {
        fwrite(STDERR, "FAILED\n" . $e->getMessage() . "\n");
        exit(1);
    }
}

if ($pending === 0) {
    echo "No pending migrations.\n";
} else {
    echo "Applied {$pending} migration(s).\n";
}
