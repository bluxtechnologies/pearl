<?php
/**
 * Migration: create users table
 *
 * Separate table from admins (not a role column) by design — see
 * pearl/auth.php for the reasoning.
 *
 * One DDL statement in 'up' by convention: MySQL DDL causes an
 * implicit commit, so multi-statement DDL migrations can't be rolled
 * back atomically by the runner (see pearl/cli/migrate.php header).
 */

declare(strict_types=1);

return [
    'up' => <<<'SQL'
        CREATE TABLE IF NOT EXISTS users (
            id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            email           VARCHAR(255) NOT NULL,
            password_hash   VARCHAR(255) NOT NULL,
            name            VARCHAR(255) NOT NULL,
            status          ENUM('active', 'suspended', 'pending') NOT NULL DEFAULT 'pending',
            created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

            UNIQUE KEY uq_users_email (email)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        SQL,

    'down' => 'DROP TABLE IF EXISTS users',
];
