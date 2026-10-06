<?php
/**
 * Pearl Framework — RBAC (minimal, context-based)
 *
 * Deliberately thin for now: the users/admins schema has no `role`
 * column (removed as premature complexity — see database/schema/001_users.sql),
 * so there is no granular permission data to check yet. This file
 * only answers "is the current session an authenticated admin?" —
 * real permission granularity (e.g. distinguishing admin sub-roles)
 * is a feature to design deliberately later, once there's an actual
 * need for it, not a shape to guess at now.
 *
 * Depends on: pearl/auth.php (auth_check, session_current_context via
 * pearl/session.php).
 *
 * See pearl/FUNCTIONS.md for the ownership registry.
 */

declare(strict_types=1);

if (!function_exists('auth_check')) {
    throw new RuntimeException('pearl/auth.php must be required before pearl/rbac.php.');
}

/**
 * True if the current request is authenticated AND in admin context.
 * This is the only access check Pearl makes today — a wire/admin/*
 * module gates its whole file on this, rather than per-action roles.
 */
if (!function_exists('auth_is_admin')) {
    function auth_is_admin(): bool
    {
        return session_detect_context() === 'admin' && auth_check();
    }
}

/**
 * Require admin access or stop the request with a 403. Convenience
 * wrapper for the common case of gating an entire wire/admin/* module
 * in one line at the top of the file.
 */
if (!function_exists('auth_require_admin')) {
    function auth_require_admin(): void
    {
        if (!auth_is_admin()) {
            pearl_render_error_page(403);
            exit;
        }
    }
}
