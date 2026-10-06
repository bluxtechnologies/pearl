<?php
/**
 * Migration: create login_history table
 *
 * A generic login audit trail (user_id -> users.id, one-to-many).
 * Added specifically to give wire/admin/users.php something real to
 * JOIN against for the Phase 3 CRUD demo — `users` alone is a
 * standalone table with nothing to join to, and Phase 3's "done"
 * condition requires demonstrating a join + table-qualified order().
 *
 * ON DELETE CASCADE: deleting a user also removes their login history,
 * so the demo's delete() call doesn't leave orphaned rows behind.
 */

declare(strict_types=1);

return [
    'up' => <<<'SQL'
        CREATE TABLE IF NOT EXISTS login_history (
            id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id         BIGINT UNSIGNED NOT NULL,
            logged_in_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

            CONSTRAINT fk_login_history_user
                FOREIGN KEY (user_id) REFERENCES users(id)
                ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        SQL,

    'down' => 'DROP TABLE IF EXISTS login_history',
];
