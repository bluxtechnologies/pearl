<?php
/**
 * Migration: create api_tokens table
 *
 * One DDL statement per file by architectural invariant (§10, §11).
 * Preserves dual-context authentication isolation via tokenable_type (§3 Invariant 7).
 */

declare(strict_types=1);

return [
    'up' => <<<'SQL'
        CREATE TABLE IF NOT EXISTS api_tokens (
            id             BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            tokenable_type VARCHAR(32) NOT NULL,
            tokenable_id   BIGINT UNSIGNED NOT NULL,
            name           VARCHAR(128) NOT NULL,
            token_hash     VARCHAR(64) NOT NULL UNIQUE,
            abilities      TEXT NULL,
            last_used_at   INT UNSIGNED NULL,
            expires_at     INT UNSIGNED NULL,
            created_at     INT UNSIGNED NOT NULL,

            KEY idx_tokenable (tokenable_type, tokenable_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        SQL,

    'down' => 'DROP TABLE IF EXISTS api_tokens',
];
