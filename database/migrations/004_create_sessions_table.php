<?php
/**
 * Migration: create sessions table
 *
 * Scoped by both id and context ('user' vs 'admin') to structurally enforce
 * dual authentication isolation at the database layer (§3 Invariant 7).
 *
 * One DDL statement in 'up' by convention (see §10, §11).
 */

declare(strict_types=1);

return [
    'up' => <<<'SQL'
        CREATE TABLE IF NOT EXISTS sessions (
            id              VARCHAR(128) NOT NULL,
            context         VARCHAR(16) NOT NULL DEFAULT 'user',
            payload         MEDIUMTEXT NOT NULL,
            last_activity   INT UNSIGNED NOT NULL,
            ip_address      VARCHAR(45) NULL,
            user_agent      TEXT NULL,

            PRIMARY KEY (id, context),
            KEY idx_sessions_last_activity (last_activity)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        SQL,

    'down' => 'DROP TABLE IF EXISTS sessions',
];
