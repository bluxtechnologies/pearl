<?php
/**
 * Pearl Framework — API Architecture & Token Authentication
 *
 * Implements Layer 2 API Authentication Primitives (§4, §15).
 * Features:
 *   - Bearer token authentication with high-entropy keys ('ptk_...')
 *   - SHA-256 hashed storage in database table api_tokens
 *   - Dual authentication context preservation (user vs. admin) (§3 Invariant 7)
 *   - Granular token abilities / scopes enforcement
 *   - Sliding activity tracking and expiration enforcement
 *   - Content-negotiated error responses
 *
 * Depends on: pearl/paths.php, pearl/db.php (pdo), pearl/request.php, pearl/response.php.
 *
 * See pearl/FUNCTIONS.md for the ownership registry.
 */

declare(strict_types=1);

if (!function_exists('pdo')) {
    throw new RuntimeException('pearl/db.php must be required before pearl/api.php.');
}

/**
 * Determine whether the current request is an API request.
 * Checks for /api/ URL prefix or Accept: application/json header.
 */
if (!function_exists('is_api_request')) {
    function is_api_request(): bool
    {
        $path = request_path();
        if (str_starts_with($path, '/api/') || $path === '/api') {
            return true;
        }

        $accept = (string) request_header('Accept');
        return str_contains($accept, 'application/json');
    }
}

/**
 * Generate a new API token for a user or admin entity.
 * Returns the plaintext bearer token. The database only stores the SHA-256 hash.
 */
if (!function_exists('api_token_create')) {
    function api_token_create(string $context, int $id, string $name, array $abilities = ['*'], ?int $ttl = null): string
    {
        if (!in_array($context, ['user', 'admin'], true)) {
            throw new InvalidArgumentException("Invalid tokenable context: '{$context}'. Must be 'user' or 'admin'.");
        }

        // Plain token format: ptk_<64 hex chars>
        $plainToken = 'ptk_' . bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $plainToken);
        $now = time();
        $expiresAt = ($ttl !== null) ? $now + $ttl : null;

        $stmt = pdo()->prepare(
            "INSERT INTO api_tokens (tokenable_type, tokenable_id, name, token_hash, abilities, last_used_at, expires_at, created_at) " .
            "VALUES (:type, :id, :name, :hash, :abilities, NULL, :expires, :created)"
        );

        $stmt->execute([
            ':type' => $context,
            ':id' => $id,
            ':name' => $name,
            ':hash' => $tokenHash,
            ':abilities' => json_encode($abilities),
            ':expires' => $expiresAt,
            ':created' => $now,
        ]);

        return $plainToken;
    }
}

/**
 * Authenticate incoming request via Bearer token.
 * Returns authentication payload array or null if invalid/expired.
 */
if (!function_exists('api_token_authenticate')) {
    function api_token_authenticate(?string $bearerToken = null): ?array
    {
        if ($bearerToken === null && isset($GLOBALS['pearl_api_auth'])) {
            return $GLOBALS['pearl_api_auth'];
        }

        if ($bearerToken === null) {
            $authHeader = (string) request_header('Authorization');
            if (str_starts_with($authHeader, 'Bearer ')) {
                $bearerToken = trim(substr($authHeader, 7));
            }
        }

        if (empty($bearerToken)) {
            return null;
        }

        $tokenHash = hash('sha256', $bearerToken);
        $stmt = pdo()->prepare("SELECT * FROM api_tokens WHERE token_hash = :hash");
        $stmt->execute([':hash' => $tokenHash]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$row) {
            return null;
        }

        $now = time();
        if ($row['expires_at'] !== null && ((int) $row['expires_at']) < $now) {
            return null; // Expired token
        }

        // Update last_used_at timestamp
        $update = pdo()->prepare("UPDATE api_tokens SET last_used_at = :now WHERE id = :id");
        $update->execute([':now' => $now, ':id' => $row['id']]);

        // Resolve entity respecting dual context isolation (§3 Invariant 7, 8)
        $context = (string) $row['tokenable_type'];
        $entityId = (int) $row['tokenable_id'];
        $table = ($context === 'admin') ? 'admins' : 'users';

        $entityStmt = pdo()->prepare("SELECT * FROM {$table} WHERE id = :id");
        $entityStmt->execute([':id' => $entityId]);
        $entity = $entityStmt->fetch(\PDO::FETCH_ASSOC);

        if (!$entity) {
            return null;
        }

        unset($entity['password_hash']);

        $authData = [
            'token_id' => (int) $row['id'],
            'token_name' => (string) $row['name'],
            'context' => $context,
            'entity_id' => $entityId,
            'abilities' => json_decode((string) ($row['abilities'] ?? '["*"]'), true) ?: ['*'],
            'user' => $entity,
        ];

        $GLOBALS['pearl_api_auth'] = $authData;
        return $authData;
    }
}

/**
 * Return currently authenticated API entity (user context).
 */
if (!function_exists('auth_api_user')) {
    function auth_api_user(): ?array
    {
        $auth = api_token_authenticate();
        if ($auth !== null && $auth['context'] === 'user') {
            return $auth['user'];
        }
        return null;
    }
}

/**
 * Return currently authenticated API entity (admin context).
 */
if (!function_exists('auth_api_admin')) {
    function auth_api_admin(): ?array
    {
        $auth = api_token_authenticate();
        if ($auth !== null && $auth['context'] === 'admin') {
            return $auth['user'];
        }
        return null;
    }
}

/**
 * Return active API token authentication payload.
 */
if (!function_exists('auth_api_token')) {
    function auth_api_token(): ?array
    {
        return api_token_authenticate();
    }
}

/**
 * Verify if the authenticated API token possesses a specified ability.
 */
if (!function_exists('auth_token_can')) {
    function auth_token_can(string $ability): bool
    {
        $auth = api_token_authenticate();
        if ($auth === null) {
            return false;
        }

        $abilities = (array) ($auth['abilities'] ?? []);
        return in_array('*', $abilities, true) || in_array($ability, $abilities, true);
    }
}

/**
 * Enforce API authentication guard. Aborts with JSON 401 or 403 on failure.
 */
if (!function_exists('auth_require_api')) {
    function auth_require_api(?string $ability = null, string $context = 'user'): array
    {
        $auth = api_token_authenticate();

        if ($auth === null || $auth['context'] !== $context) {
            response_api_error('Unauthenticated.', 401);
        }

        if ($ability !== null && !auth_token_can($ability)) {
            response_api_error("Forbidden: token lacks '{$ability}' ability.", 403);
        }

        return $auth['user'];
    }
}

/**
 * Revoke an API token by ID or by plaintext token.
 */
if (!function_exists('api_token_revoke')) {
    function api_token_revoke(int|string $tokenOrId): bool
    {
        if (is_numeric($tokenOrId)) {
            $stmt = pdo()->prepare("DELETE FROM api_tokens WHERE id = :id");
            return $stmt->execute([':id' => (int) $tokenOrId]);
        }

        $hash = hash('sha256', (string) $tokenOrId);
        $stmt = pdo()->prepare("DELETE FROM api_tokens WHERE token_hash = :hash");
        return $stmt->execute([':hash' => $hash]);
    }
}

/**
 * Revoke all API tokens for a specific user or admin entity.
 */
if (!function_exists('api_token_revoke_all')) {
    function api_token_revoke_all(string $context, int $entityId): bool
    {
        $stmt = pdo()->prepare("DELETE FROM api_tokens WHERE tokenable_type = :type AND tokenable_id = :id");
        return $stmt->execute([':type' => $context, ':id' => $entityId]);
    }
}
