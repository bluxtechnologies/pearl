<?php
/**
 * Pearl Framework — Router
 *
 * File-based routing convention (fat domain modules, not one-file-per-route):
 *
 *   /                    -> wire/home.php            $params = []
 *   /users               -> wire/users.php           $params = []
 *   /users/42            -> wire/users.php           $params = ['42']
 *   /users/42/edit       -> wire/users.php           $params = ['42', 'edit']
 *   /admin/dashboard     -> wire/admin/dashboard.php  $params = []
 *   /admin/users/42      -> wire/admin/users.php      $params = ['42']
 *                          (longest matching file path wins, so nested
 *                           folders like wire/admin/ act as namespaces)
 *
 * Each wire/ module is expected to branch on request_method() and the
 * contents of $params itself (fat module pattern) — the router's only
 * job is finding the right file and handing off control.
 *
 * Depends on: request.php (request_path), errors.php
 * (pearl_render_error_page for 404s). response.php / view.php are used
 * by the wire/ modules themselves, not by the router directly.
 *
 * See pearl/FUNCTIONS.md for the ownership registry.
 */

declare(strict_types=1);

if (!function_exists('wire_path')) {
    throw new RuntimeException(
        'Path helpers are not loaded. pearl/paths.php must be required before pearl/router.php.'
    );
}

if (!function_exists('request_path')) {
    throw new RuntimeException(
        'pearl/request.php must be required before pearl/router.php.'
    );
}

if (!function_exists('pearl_render_error_page')) {
    throw new RuntimeException(
        'pearl/errors.php must be required before pearl/router.php.'
    );
}

/**
 * Split a normalized request path into segments, rejecting anything
 * that isn't a safe path-and-filename character. Prevents path
 * traversal (../, null bytes, etc.) from ever reaching the filesystem.
 */
if (!function_exists('pearl_route_segments')) {
    function pearl_route_segments(string $path): ?array
    {
        $trimmed = trim($path, '/');

        if ($trimmed === '') {
            return [];
        }

        $segments = explode('/', $trimmed);

        foreach ($segments as $segment) {
            if (!preg_match('/^[A-Za-z0-9_-]+$/', $segment)) {
                return null; // unsafe or malformed segment -> caller treats as 404
            }
        }

        return $segments;
    }
}

/**
 * Resolve a request path to a wire/ file, trying the longest possible
 * segment prefix first so nested folders act as namespaces.
 *
 * Returns [absoluteFilePath, remainingParams] or null if nothing matched.
 */
if (!function_exists('pearl_resolve_route')) {
    function pearl_resolve_route(array $segments): ?array
    {
        // Root path special case.
        if ($segments === []) {
            $homeFile = wire_path('home.php');
            return is_file($homeFile) ? [$homeFile, []] : null;
        }

        for ($n = count($segments); $n >= 1; $n--) {
            $candidate = implode('/', array_slice($segments, 0, $n));
            $file = wire_path($candidate . '.php');

            if (is_file($file)) {
                $params = array_slice($segments, $n);
                return [$file, $params];
            }
        }

        return null;
    }
}

/**
 * Main entry point: resolve the current request to a wire/ module and
 * hand off control. Renders a 404 page if nothing matches.
 *
 * $params is intentionally a plain local variable here (not passed via
 * function args) so that `require $file;` below shares this function's
 * scope and the wire/ module can read $params directly, per Pearl's
 * procedural convention.
 */
if (!function_exists('pearl_dispatch')) {
    function pearl_dispatch(): void
    {
        $path = request_path();
        $segments = pearl_route_segments($path);

        if ($segments === null) {
            pearl_render_error_page(404);
            return;
        }

        $resolved = pearl_resolve_route($segments);

        if ($resolved === null) {
            pearl_render_error_page(404);
            return;
        }

        [$file, $params] = $resolved;

        // Automated CSRF enforcement for state-changing requests (§3 Invariant 9)
        $method = function_exists('request_method') ? request_method() : ($_SERVER['REQUEST_METHOD'] ?? 'GET');
        if (in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            $securityConfig = function_exists('security_config') ? security_config() : [];
            $csrfConfig = $securityConfig['csrf'] ?? ['enabled' => true, 'exempt_routes' => []];

            if (!empty($csrfConfig['enabled'])) {
                $exemptRoutes = $csrfConfig['exempt_routes'] ?? [];
                $isExempt = in_array($path, $exemptRoutes, true) ||
                            str_starts_with($path, '/api/') ||
                            $path === '/api' ||
                            (function_exists('is_api_request') && is_api_request());

                if (!$isExempt) {
                    if (!function_exists('csrf_verify') || !csrf_verify()) {
                        if (function_exists('response_status')) {
                            response_status(419);
                        }
                        if (function_exists('response_text')) {
                            response_text('419 Page Expired (CSRF token mismatch)', 419);
                        } else {
                            echo '419 Page Expired (CSRF token mismatch)';
                        }
                        return;
                    }
                }
            }
        }

        require $file;
    }
}
