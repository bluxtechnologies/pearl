<?php
/**
 * Migration: create cache table
 *
 * One DDL statement per file by architectural invariant (§10, §11).
 */

declare(strict_types=1);

return [
    'up' => <<<'SQL'
        CREATE TABLE IF NOT EXISTS cache (
            `key`        VARCHAR(255) NOT NULL PRIMARY KEY,
            `value`      LONGTEXT NOT NULL,
            `expiration` INT UNSIGNED NOT NULL,

            KEY idx_cache_expiration (`expiration`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        SQL,

    'down' => 'DROP TABLE IF EXISTS cache',
];
