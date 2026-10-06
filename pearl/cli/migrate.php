<?php
/**
 * Pearl Framework — CLI: migrate
 *
 * Handles: php bin/pearl migrate
 *
 * Runs pending migrations from database/migrations/, in filename order
 * (supporting sequential 001_... and timestamped YYYY_MM_DD_HHMMSS_... formats).
 *
 * Tracks executed migrations in a `migrations` table across MySQL, PostgreSQL,
 * and SQLite engines.
 *
 * Returns a callable: function(array $args): int
 */

declare(strict_types=1);

/**
 * Ensure the migrations tracking table exists across supported database engines.
 */
$ensureMigrationsTable = static function (): void {
    $driver = function_exists('db_driver') ? db_driver() : 'mysql';

    if ($driver === 'sqlite') {
        pdo()->exec(<<<'SQL'
            CREATE TABLE IF NOT EXISTS migrations (
                id         INTEGER PRIMARY KEY AUTOINCREMENT,
                migration  VARCHAR(255) NOT NULL UNIQUE,
                batch      INTEGER NOT NULL,
                run_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
            )
            SQL);
    } elseif ($driver === 'pgsql') {
        pdo()->exec(<<<'SQL'
            CREATE TABLE IF NOT EXISTS migrations (
                id         SERIAL PRIMARY KEY,
                migration  VARCHAR(255) NOT NULL UNIQUE,
                batch      INTEGER NOT NULL,
                run_at     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
            )
            SQL);
    } else {
        pdo()->exec(<<<'SQL'
            CREATE TABLE IF NOT EXISTS migrations (
                id         INT AUTO_INCREMENT PRIMARY KEY,
                migration  VARCHAR(255) NOT NULL,
                batch      INT NOT NULL,
                run_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

                UNIQUE KEY uq_migrations_migration (migration)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            SQL);
    }
};

/**
 * Load a migration file and validate its shape.
 */
$loadMigration = static function (string $file): array {
    $data = require $file;

    if (!is_array($data) || !isset($data['up']) || !is_string($data['up'])) {
        throw new \RuntimeException(
            basename($file) . " must return an array with at least a string 'up' key."
        );
    }

    return $data;
};

/**
 * Split SQL into individual statements on ';'.
 */
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

return function (array $args) use ($ensureMigrationsTable, $loadMigration, $splitStatements): int {
    echo "=== Pearl Migrate ===" . PHP_EOL . PHP_EOL;

    $ensureMigrationsTable();

    $migrationsDir = database_path('migrations');

    if (!is_dir($migrationsDir)) {
        echo "No database/migrations/ directory found — nothing to run." . PHP_EOL;
        return 0;
    }

    $files = glob($migrationsDir . '/*.php') ?: [];
    sort($files, SORT_STRING);

    if ($files === []) {
        echo "No migration files found." . PHP_EOL;
        return 0;
    }

    $alreadyRunStmt = pdo()->query('SELECT migration FROM migrations');
    $alreadyRun = array_flip($alreadyRunStmt->fetchAll(\PDO::FETCH_COLUMN));

    $pending = array_filter(
        $files,
        static fn (string $file): bool => !isset($alreadyRun[basename($file)])
    );

    if ($pending === []) {
        echo "Nothing to migrate — already up to date." . PHP_EOL;
        return 0;
    }

    $batchStmt = pdo()->query('SELECT COALESCE(MAX(batch), 0) AS max_batch FROM migrations');
    $batch = (int) $batchStmt->fetch()['max_batch'] + 1;

    foreach ($pending as $file) {
        $name = basename($file);

        try {
            $migration = $loadMigration($file);
        } catch (\Throwable $e) {
            fwrite(STDERR, "  FAILED {$name}: {$e->getMessage()}" . PHP_EOL);
            return 1;
        }

        $statements = $splitStatements($migration['up']);

        if ($statements === []) {
            echo "  skip   {$name} ('up' has no statements)" . PHP_EOL;
            continue;
        }

        pdo()->beginTransaction();

        try {
            foreach ($statements as $statement) {
                pdo()->exec($statement);
            }

            $insert = pdo()->prepare(
                'INSERT INTO migrations (migration, batch) VALUES (:migration, :batch)'
            );
            $insert->execute(['migration' => $name, 'batch' => $batch]);

            if (pdo()->inTransaction()) {
                pdo()->commit();
            }

            echo "  ran    {$name}" . PHP_EOL;
        } catch (\Throwable $e) {
            if (pdo()->inTransaction()) {
                pdo()->rollBack();
            }

            fwrite(STDERR, "  FAILED {$name}: {$e->getMessage()}" . PHP_EOL);
            fwrite(STDERR, "Stopping — later migrations were not attempted." . PHP_EOL);
            return 1;
        }
    }

    echo PHP_EOL . "Migration complete (batch {$batch})." . PHP_EOL;
    return 0;
};
