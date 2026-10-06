<?php
/**
 * Migration: create admins table
 */

declare(strict_types=1);

return [
    'up' => <<<'SQL'
        CREATE TABLE IF NOT EXISTS admins (
            id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            email           VARCHAR(255) NOT NULL,
            password_hash   VARCHAR(255) NOT NULL,
            name            VARCHAR(255) NOT NULL,
            status          ENUM('active', 'suspended') NOT NULL DEFAULT 'active',
            created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

            UNIQUE KEY uq_admins_email (email)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        SQL,

    'down' => 'DROP TABLE IF EXISTS admins',
];
