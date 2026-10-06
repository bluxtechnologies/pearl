<?php
/**
 * Pearl Framework — CLI: make:migration
 *
 * Handles: php bin/pearl make:migration <name> [--timestamp|--sequential]
 *   e.g.   php bin/pearl make:migration create_orders_table
 *          php bin/pearl make:migration create_tags_table --timestamp
 *
 * Scaffolds an empty migration file in database/migrations/.
 * Supports both sequential zero-padded numbering (001_..., 002_...)
 * and timestamped prefixing (2026_10_02_120000_...) to prevent branching collisions.
 *
 * Returns a callable: function(array $args): int
 */

declare(strict_types=1);

/**
 * Turn arbitrary input into a safe migration name fragment.
 */
$sanitizeName = static function (string $raw): string {
    $transliterated = @iconv('UTF-8', 'ASCII//TRANSLIT', $raw);
    if ($transliterated === false) {
        $transliterated = $raw;
    }

    $lower = strtolower($transliterated);
    $safe = preg_replace('/[^a-z0-9]+/', '_', $lower);
    return trim($safe, '_');
};

/**
 * Find the next sequential migration number.
 */
$nextMigrationNumber = static function (string $migrationsDir): string {
    $files = glob($migrationsDir . '/*.php') ?: [];
    $highest = 0;

    foreach ($files as $file) {
        $basename = basename($file);
        // Only consider sequential numeric prefixes (e.g. 001_, 012_) not timestamps (> 9999)
        if (preg_match('/^(\d{1,4})_/', $basename, $matches) === 1) {
            $highest = max($highest, (int) $matches[1]);
        }
    }

    $next = $highest + 1;
    return str_pad((string) $next, 3, '0', STR_PAD_LEFT);
};

return function (array $args) use ($sanitizeName, $nextMigrationNumber): int {
    $rawName = '';
    $useTimestamp = (strtolower((string) env('MIGRATION_FORMAT', 'sequential')) === 'timestamp');

    foreach ($args as $arg) {
        if ($arg === '--timestamp' || $arg === '-t') {
            $useTimestamp = true;
        } elseif ($arg === '--sequential' || $arg === '-s') {
            $useTimestamp = false;
        } elseif (!str_starts_with($arg, '--') && $rawName === '') {
            $rawName = $arg;
        }
    }

    if (trim($rawName) === '') {
        fwrite(STDERR, "Usage: php bin/pearl make:migration <name> [--timestamp]" . PHP_EOL);
        fwrite(STDERR, "Example: php bin/pearl make:migration create_orders_table" . PHP_EOL);
        return 1;
    }

    $name = $sanitizeName($rawName);

    if ($name === '') {
        fwrite(STDERR, "Migration name resolved to empty after sanitizing — use letters/numbers." . PHP_EOL);
        return 1;
    }

    $migrationsDir = database_path('migrations');

    if (!is_dir($migrationsDir)) {
        mkdir($migrationsDir, 0775, true);
    }

    $prefix = $useTimestamp ? date('Y_m_d_His') : $nextMigrationNumber($migrationsDir);
    $filename = "{$prefix}_{$name}.php";
    $path = $migrationsDir . '/' . $filename;

    if (is_file($path)) {
        fwrite(STDERR, "Migration already exists: {$filename}" . PHP_EOL);
        return 1;
    }

    $template = <<<PHP
        <?php
        /**
         * Migration: {$name}
         *
         * One DDL statement in 'up' by convention (see
         * pearl/cli/migrate.php header for why: MySQL DDL causes an
         * implicit commit, so this is the only way a failed migration
         * can't leave a file half-applied).
         */

        declare(strict_types=1);

        return [
            'up' => <<<'SQL'
                -- CREATE TABLE ... ;
                SQL,

            'down' => '-- DROP TABLE ... ;',
        ];

        PHP;

    file_put_contents($path, $template);

    echo "Created database/migrations/{$filename}" . PHP_EOL;

    return 0;
};
