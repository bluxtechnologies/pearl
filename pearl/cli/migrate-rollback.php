<?php
/**
 * Pearl Framework — CLI: migrate:rollback
 *
 * Handles: php bin/pearl migrate:rollback [--steps=N]
 *
 * Rolls back the most recent batch of migrations in reverse order.
 * Defensive against malformed rollback files, missing down operations,
 * and database execution failures (§11, §28).
 *
 * Returns a callable: function(array $args): int
 */

declare(strict_types=1);

$loadMigration = static function (string $file): ?array {
    try {
        $data = require $file;
        if (!is_array($data)) {
            return null;
        }
        return $data;
    } catch (\Throwable) {
        return null;
    }
};

$splitStatements = static function (string $sql): array {
    $parts = explode(';', $sql);
    $statements = [];

    foreach ($parts as $part) {
        $trimmed = trim($part);
        if ($trimmed !== '') {
            $statements[] = $trimmed;
        }
    }

    return $statements;
};

return function (array $args) use ($loadMigration, $splitStatements): int {
    echo "=== Pearl Migrate Rollback ===" . PHP_EOL . PHP_EOL;

    // Portable cross-engine table existence check
    try {
        $check = pdo()->query("SELECT 1 FROM migrations LIMIT 1");
    } catch (\Throwable) {
        echo "No migrations table found — nothing has ever been migrated." . PHP_EOL;
        return 0;
    }

    $batchStmt = pdo()->query('SELECT MAX(batch) AS last_batch FROM migrations');
    $lastBatch = $batchStmt->fetch()['last_batch'];

    if ($lastBatch === null) {
        echo "Nothing to roll back — no migrations have run." . PHP_EOL;
        return 0;
    }

    $rowsStmt = pdo()->prepare(
        'SELECT migration FROM migrations WHERE batch = :batch ORDER BY id DESC, migration DESC'
    );
    $rowsStmt->execute(['batch' => $lastBatch]);
    $migrationNames = $rowsStmt->fetchAll(\PDO::FETCH_COLUMN);

    $migrationsDir = database_path('migrations');

    foreach ($migrationNames as $name) {
        $file = $migrationsDir . '/' . $name;

        if (!is_file($file)) {
            fwrite(STDERR, "  SKIP   {$name} (file no longer exists on disk — removing tracking row)" . PHP_EOL);
            $delete = pdo()->prepare('DELETE FROM migrations WHERE migration = :migration');
            $delete->execute(['migration' => $name]);
            continue;
        }

        $migration = $loadMigration($file);

        if ($migration === null) {
            fwrite(STDERR, "  FAILED {$name}: file is malformed or threw syntax error on load" . PHP_EOL);
            fwrite(STDERR, "Stopping — remaining migrations in this batch were not attempted." . PHP_EOL);
            return 1;
        }

        if (!isset($migration['down']) || !is_string($migration['down']) || trim($migration['down']) === '') {
            fwrite(STDERR, "  SKIP   {$name} (no valid 'down' statement defined — cannot roll back automatically)" . PHP_EOL);
            continue;
        }

        $statements = $splitStatements($migration['down']);

        pdo()->beginTransaction();

        try {
            foreach ($statements as $statement) {
                pdo()->exec($statement);
            }

            $delete = pdo()->prepare('DELETE FROM migrations WHERE migration = :migration');
            $delete->execute(['migration' => $name]);

            if (pdo()->inTransaction()) {
                pdo()->commit();
            }

            echo "  rolled back   {$name}" . PHP_EOL;
        } catch (\Throwable $e) {
            if (pdo()->inTransaction()) {
                pdo()->rollBack();
            }

            fwrite(STDERR, "  FAILED {$name}: {$e->getMessage()}" . PHP_EOL);
            fwrite(STDERR, "Stopping — remaining migrations in this batch were not attempted." . PHP_EOL);
            return 1;
        }
    }

    echo PHP_EOL . "Rollback complete (batch {$lastBatch})." . PHP_EOL;
    return 0;
};
