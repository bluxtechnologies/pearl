<?php
/**
 * Migration: create queue_jobs table
 *
 * One DDL statement per file by architectural invariant (§10, §11).
 */

declare(strict_types=1);

return [
    'up' => <<<'SQL'
        CREATE TABLE IF NOT EXISTS queue_jobs (
            id            BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            queue         VARCHAR(64) NOT NULL DEFAULT 'default',
            payload       LONGTEXT NOT NULL,
            attempts      TINYINT UNSIGNED NOT NULL DEFAULT 0,
            reserved_at   INT UNSIGNED NULL,
            available_at  INT UNSIGNED NOT NULL,
            created_at    INT UNSIGNED NOT NULL,

            KEY idx_queue_available (queue, reserved_at, available_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        SQL,

    'down' => 'DROP TABLE IF EXISTS queue_jobs',
];
