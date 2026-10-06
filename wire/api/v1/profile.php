<?php
/**
 * Wire: /api/v1/profile
 *
 * Example API Endpoint adhering to Pearl API Architecture (§15).
 * Demonstrates:
 *   - Bearer token authentication via auth_require_api()
 *   - Ability checking ('read:profile' or '*')
 *   - Field allowlisting via columns_whitelist()
 *   - Standardized API envelope via response_api_success()
 *   - RFC-compliant HTTP method restriction
 */

declare(strict_types=1);

$user = auth_require_api('read:profile', 'user');

match (request_method()) {
    'GET' => response_api_success(
        columns_whitelist($user, ['id', 'email', 'created_at']),
        200,
        ['token' => auth_api_token()['token_name'] ?? '']
    ),
    default => response_method_not_allowed(['GET']),
};
