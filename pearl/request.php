<?php
/**
 * Pearl Framework — Request
 *
 * Reads the incoming HTTP request. No dependency on router/response/view —
 * this file only ever reads from PHP superglobals, never writes output.
 *
 * See pearl/FUNCTIONS.md for the ownership registry these functions
 * are reserved under.
 */

declare(strict_types=1);

/**
 * Current HTTP method, uppercased. Respects the common
 * X-HTTP-Method-Override header / _method form field pattern for
 * clients that can't send real PUT/PATCH/DELETE (e.g. plain HTML forms).
 */
if (!function_exists('request_method')) {
    function request_method(): string
    {
        $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

        if ($method === 'POST') {
            $override = $_POST['_method'] ?? request_header('X-Http-Method-Override');

            if (is_string($override) && $override !== '') {
                return strtoupper($override);
            }
        }

        return $method;
    }
}

/**
 * Current URL path, normalized: no query string, no trailing slash
 * (except root "/"), always starts with "/".
 */
if (!function_exists('request_path')) {
    function request_path(): string
    {
        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        $path = parse_url($uri, PHP_URL_PATH);

        if (!is_string($path) || $path === '') {
            $path = '/';
        }

        if ($path !== '/' && str_ends_with($path, '/')) {
            $path = rtrim($path, '/');
        }

        if (!str_starts_with($path, '/')) {
            $path = '/' . $path;
        }

        return $path;
    }
}

/**
 * Parsed request body as an associative array.
 *
 * - application/json bodies are json_decode'd.
 * - application/x-www-form-urlencoded / multipart form bodies come
 *   from $_POST directly (PHP already parses these).
 * - Returns [] if the body is empty or unparseable, never throws —
 *   route handlers should treat a malformed body as "no input", not
 *   a fatal error.
 */
if (!function_exists('request_input')) {
    function request_input(): array
    {
        static $cached = null;

        if ($cached !== null) {
            return $cached;
        }

        $contentType = request_header('Content-Type') ?? '';

        if (str_contains($contentType, 'application/json')) {
            $raw = file_get_contents('php://input');
            $decoded = json_decode((string) $raw, true);
            $cached = is_array($decoded) ? $decoded : [];
            return $cached;
        }

        $cached = is_array($_POST) ? $_POST : [];
        return $cached;
    }
}

/**
 * Query string parameters as an associative array.
 */
if (!function_exists('request_query')) {
    function request_query(): array
    {
        return is_array($_GET) ? $_GET : [];
    }
}

/**
 * Read a single request header by name (case-insensitive).
 * Returns null if not present.
 */
if (!function_exists('request_header')) {
    function request_header(string $name): ?string
    {
        $serverKey = 'HTTP_' . strtoupper(str_replace('-', '_', $name));

        if (isset($_SERVER[$serverKey])) {
            return (string) $_SERVER[$serverKey];
        }

        // Content-Type / Content-Length don't get the HTTP_ prefix.
        $directKey = strtoupper(str_replace('-', '_', $name));

        if (isset($_SERVER[$directKey])) {
            return (string) $_SERVER[$directKey];
        }

        return null;
    }
}

/**
 * Client IP address. Single owner of this logic — the exact function
 * that caused duplicate-declaration bugs in FluxPHP. Do not redeclare
 * elsewhere; extend this one if new proxy headers need trusting.
 *
 * Trusts X-Forwarded-For only if TRUST_PROXY=true in .env, since
 * blindly trusting it lets clients spoof their IP.
 */
if (!function_exists('request_ip')) {
    function request_ip(): string
    {
        if (env('TRUST_PROXY', false) === true) {
            $forwarded = request_header('X-Forwarded-For');

            if ($forwarded !== null && $forwarded !== '') {
                $parts = explode(',', $forwarded);
                return trim($parts[0]);
            }
        }

        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }
}

/**
 * Unique request identifier. Reads incoming X-Request-ID header if valid
 * (safe alphanumeric string up to 64 chars), otherwise generates a fresh hex ID.
 * Cached for the life of the request.
 */
if (!function_exists('request_id')) {
    function request_id(): string
    {
        static $requestId = null;

        if ($requestId !== null) {
            return $requestId;
        }

        $incoming = request_header('X-Request-ID');
        if ($incoming !== null && preg_match('/^[A-Za-z0-9_-]{8,64}$/', $incoming)) {
            $requestId = $incoming;
        } else {
            $requestId = bin2hex(random_bytes(16));
        }

        return $requestId;
    }
}
