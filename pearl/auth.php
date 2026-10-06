<?php
/**
 * Pearl Framework — Auth
 *
 * Authenticates against the `users` or `admins` table, chosen by the
 * same context detection session.php already uses (path-based:
 * /admin/* -> admins table, everything else -> users table). This
 * keeps routing, session cookie naming, and auth table selection all
 * driven by one single source of truth instead of three.
 *
 * Depends on: pearl/db.php (pdo), pearl/session.php (session_start_pearl,
 * session_detect_context, session_current_context, session_destroy_pearl).
 *
 * See pearl/FUNCTIONS.md for the ownership registry.
 */

declare(strict_types=1);

if (!function_exists('pdo')) {
    throw new RuntimeException('pearl/db.php must be required before pearl/auth.php.');
}

if (!function_exists('session_start_pearl')) {
    throw new RuntimeException('pearl/session.php must be required before pearl/auth.php.');
}

/**
 * Resolve which table a given context authenticates against. Single
 * owner of this mapping — do not hardcode 'users'/'admins' table
 * names anywhere else based on context.
 */
if (!function_exists('auth_table_for_context')) {
    function auth_table_for_context(string $context): string
    {
        return $context === 'admin' ? 'admins' : 'users';
    }
}

/**
 * Hash a plaintext password for storage. Single owner of the hashing
 * algorithm choice — used at registration time (make:auth scaffolds
 * this) and here for reference, so the algorithm only needs to change
 * in one place if ever needed.
 */
if (!function_exists('auth_hash_password')) {
    function auth_hash_password(string $plain): string
    {
        return password_hash($plain, PASSWORD_DEFAULT);
    }
}

/**
 * Verify credentials against the table for the CURRENT request's
 * context (via session_detect_context() — path-based, not session
 * state, since this runs before any session may exist yet).
 *
 * Returns the user/admin row (password_hash stripped) on success,
 * or null on any failure (unknown email, wrong password, inactive
 * status) — deliberately the same null for all failure reasons so
 * callers can't be used to enumerate valid emails via response timing
 * or content differences.
 */
if (!function_exists('auth_attempt')) {
    function auth_attempt(string $email, string $password): ?array
    {
        $context = session_detect_context();
        $table = auth_table_for_context($context);

        $stmt = pdo()->prepare("SELECT * FROM {$table} WHERE email = :email LIMIT 1");
        $stmt->execute(['email' => $email]);
        $row = $stmt->fetch();

        if ($row === false) {
            // Still run password_verify against a dummy hash so a
            // nonexistent email doesn't respond measurably faster
            // than a wrong password (basic timing-attack mitigation).
            password_verify($password, '$2y$10$invalidsaltinvalidsaltinvalidsaltuO');
            return null;
        }

        if ($row['status'] !== 'active') {
            return null;
        }

        if (!password_verify($password, $row['password_hash'])) {
            return null;
        }

        // Automatic password rehashing when algorithm or cost parameters update
        if (password_needs_rehash($row['password_hash'], PASSWORD_DEFAULT)) {
            $newHash = auth_hash_password($password);
            $updateStmt = pdo()->prepare("UPDATE {$table} SET password_hash = :hash WHERE id = :id");
            $updateStmt->execute(['hash' => $newHash, 'id' => $row['id']]);
        }

        unset($row['password_hash']);
        return $row;
    }
}

/**
 * Attempt login and, on success, persist the authenticated id in the
 * session for the current context. Regenerates the session id on
 * successful login to prevent session fixation.
 */
if (!function_exists('auth_login')) {
    function auth_login(string $email, string $password): bool
    {
        session_start_pearl();

        $record = auth_attempt($email, $password);

        if ($record === null) {
            return false;
        }

        session_regenerate_id(true);
        $_SESSION['_pearl_auth_id'] = $record['id'];

        return true;
    }
}

/**
 * Log out of the current context's session entirely.
 *
 * Explicitly starts the session first (reading whatever cookie the
 * request already sent) before destroying it — logout must work
 * regardless of whether the calling wire/ module already called
 * session_start_pearl(). Skipping this was a real bug caught during
 * testing: destroy silently no-ops if no session is active yet.
 */
if (!function_exists('auth_logout')) {
    function auth_logout(): void
    {
        session_start_pearl();
        session_destroy_pearl();
    }
}

/**
 * Whether the current context's session has an authenticated user/admin.
 */
if (!function_exists('auth_check')) {
    function auth_check(): bool
    {
        session_start_pearl();
        return isset($_SESSION['_pearl_auth_id']);
    }
}

/**
 * The currently authenticated user/admin row for this context
 * (password_hash stripped), or null if not logged in. Cached per
 * request — safe since auth doesn't change mid-request in normal flow.
 */
if (!function_exists('auth_user')) {
    function auth_user(): ?array
    {
        static $cache = [];

        session_start_pearl();

        if (!isset($_SESSION['_pearl_auth_id'])) {
            return null;
        }

        $context = session_current_context() ?? session_detect_context();
        $table = auth_table_for_context($context);
        $id = $_SESSION['_pearl_auth_id'];
        $cacheKey = $table . ':' . $id;

        if (array_key_exists($cacheKey, $cache)) {
            return $cache[$cacheKey];
        }

        $stmt = pdo()->prepare("SELECT * FROM {$table} WHERE id = :id LIMIT 1");
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        if ($row === false) {
            $cache[$cacheKey] = null;
            return null;
        }

        unset($row['password_hash']);
        $cache[$cacheKey] = $row;
        return $row;
    }
}

/**
 * Check if the currently authenticated user owns the given resource identifier.
 * Strictly checks user identity equality (§3 Invariant 7) — admin context does
 * not automatically satisfy user ownership.
 */
if (!function_exists('auth_owns')) {
    function auth_owns(int|string|null $ownerId): bool
    {
        if ($ownerId === null || $ownerId === '') {
            return false;
        }

        $user = auth_user();
        if ($user === null || !isset($user['id'])) {
            return false;
        }

        return (string) $user['id'] === (string) $ownerId;
    }
}

/**
 * Enforce resource ownership. Aborts request with 403 Forbidden if the
 * current user is not the owner.
 */
if (!function_exists('auth_require_owner')) {
    function auth_require_owner(int|string|null $ownerId): void
    {
        if (!auth_owns($ownerId)) {
            if (function_exists('response_status')) {
                response_status(403);
            }
            if (function_exists('pearl_render_error_page')) {
                pearl_render_error_page(403);
            } else {
                echo '403 Forbidden';
            }
            exit;
        }
    }
}
