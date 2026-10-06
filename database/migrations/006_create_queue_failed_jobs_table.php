<?php
/**
 * Migration: create queue_failed_jobs table
 *
 * One DDL statement per file by architectural invariant (§10, §11).
 */

declare(strict_types=1);

return [
    'up' => <<<'SQL'
        CREATE TABLE IF NOT EXISTS queue_failed_jobs (
            id         BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            queue      VARCHAR(64) NOT NULL DEFAULT 'default',
            payload    LONGTEXT NOT NULL,
            exception  LONGTEXT NOT NULL,
            failed_at  INT UNSIGNED NOT NULL,

            KEY idx_failed_queue (queue, failed_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        SQL,

    'down' => 'DROP TABLE IF EXISTS queue_failed_jobs',
];
