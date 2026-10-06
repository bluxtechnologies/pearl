<?php
/**
 * Pearl Framework — Response
 *
 * Helpers for sending output back to the client. No dependency on
 * request/router/view — this file only ever writes headers/output,
 * never reads request state.
 *
 * Convention: each response_* function is terminal for that request —
 * a handler should call exactly one of them (or pearl_view(), from
 * view.php) and then return. Calling more than one will produce
 * "headers already sent" warnings, same as any PHP app.
 *
 * See pearl/FUNCTIONS.md for the ownership registry.
 */

declare(strict_types=1);

/**
 * Set the HTTP status code. Safe to call even if headers were already
 * sent (silently no-ops instead of throwing a warning into output).
 */
if (!function_exists('response_status')) {
    function response_status(int $code): void
    {
        if (!headers_sent()) {
            http_response_code($code);
        }
    }
}

/**
 * Send a JSON response with the correct Content-Type and status code.
 */
if (!function_exists('response_json')) {
    function response_json(mixed $data, int $status = 200): void
    {
        response_status($status);

        if (!headers_sent()) {
            header('Content-Type: application/json; charset=utf-8');
        }

        echo json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }
}

/**
 * Strip CR, LF, and null bytes to prevent HTTP header injection.
 */
if (!function_exists('pearl_sanitize_header_value')) {
    function pearl_sanitize_header_value(string $value): string
    {
        return str_replace(["\r", "\n", "\0"], '', $value);
    }
}

/**
 * Send a redirect response and stop further output.
 * Strictly same-origin: rejects protocol-relative URLs (//...) and external schemes.
 * Use response_redirect_external() for deliberate off-site redirects.
 *
 * $status defaults to 302 (temporary). Use 301 for permanent redirects.
 */
if (!function_exists('response_redirect')) {
    function response_redirect(string $url, int $status = 302): void
    {
        $cleanUrl = pearl_sanitize_header_value(trim($url));

        // Block protocol-relative (//example.com), backslashes (\example.com), and explicit schemes (https://, javascript:)
        if (
            str_starts_with($cleanUrl, '//') ||
            str_starts_with($cleanUrl, '/\\') ||
            str_starts_with($cleanUrl, '\\') ||
            preg_match('#^[a-zA-Z][a-zA-Z0-9+.-]*:#', $cleanUrl)
        ) {
            throw new InvalidArgumentException(
                "Invalid redirect target: '{$url}' is not a same-origin path. Use response_redirect_external() for off-site redirects."
            );
        }

        response_status($status);

        if (!headers_sent()) {
            header('Location: ' . $cleanUrl);
        }
    }
}

/**
 * Send an explicit external redirect response.
 */
if (!function_exists('response_redirect_external')) {
    function response_redirect_external(string $url, int $status = 302): void
    {
        $cleanUrl = pearl_sanitize_header_value(trim($url));

        response_status($status);

        if (!headers_sent()) {
            header('Location: ' . $cleanUrl);
        }
    }
}

/**
 * Send a plain text response.
 */
if (!function_exists('response_text')) {
    function response_text(string $text, int $status = 200): void
    {
        response_status($status);

        if (!headers_sent()) {
            header('Content-Type: text/plain; charset=utf-8');
        }

        echo $text;
    }
}

/**
 * Send a raw HTML response (as opposed to pearl_view(), which
 * renders a view/ template file — this is for handlers that already
 * have a finished HTML string, e.g. from an HTMX partial helper).
 */
if (!function_exists('response_html')) {
    function response_html(string $html, int $status = 200): void
    {
        response_status($status);

        if (!headers_sent()) {
            header('Content-Type: text/html; charset=utf-8');
        }

        echo $html;
    }
}

/**
 * Send an RFC-compliant HTTP 405 Method Not Allowed response with the Allow header.
 */
if (!function_exists('response_method_not_allowed')) {
    function response_method_not_allowed(array $allowedMethods = ['GET']): void
    {
        response_status(405);

        $cleanMethods = array_map(static fn($m) => strtoupper(trim((string) $m)), $allowedMethods);
        $allowHeader = implode(', ', array_unique($cleanMethods));

        if (!headers_sent()) {
            header('Allow: ' . $allowHeader);
        }

        if (function_exists('pearl_render_error_page')) {
            pearl_render_error_page(405);
        } else {
            response_text("405 Method Not Allowed. Allowed methods: {$allowHeader}", 405);
        }
    }
}

/**
 * Send an HTTP 429 Too Many Requests response with Retry-After and rate limit headers.
 */
if (!function_exists('response_too_many_requests')) {
    function response_too_many_requests(int $retryAfter = 60, ?string $message = null): void
    {
        response_status(429);

        if (!headers_sent()) {
            header('Retry-After: ' . max(1, $retryAfter));
            header('X-RateLimit-Remaining: 0');
        }

        $msg = $message ?? "Too many requests. Please try again in {$retryAfter} seconds.";

        if (function_exists('request_input') && isset($_SERVER['HTTP_ACCEPT']) && str_contains($_SERVER['HTTP_ACCEPT'], 'application/json')) {
            response_json(['error' => $msg, 'retry_after' => $retryAfter], 429);
        } else {
            response_text($msg, 429);
        }
    }
}

/**
 * Send a standardized API success response.
 */
if (!function_exists('response_api_success')) {
    function response_api_success(mixed $data = null, int $status = 200, array $meta = []): void
    {
        $payload = [
            'success' => true,
            'status' => $status,
            'data' => $data,
        ];

        if (!empty($meta)) {
            $payload['meta'] = $meta;
        }

        response_json($payload, $status);
    }
}

/**
 * Send a standardized API error response.
 */
if (!function_exists('response_api_error')) {
    function response_api_error(string $message, int $status = 400, array $errors = []): void
    {
        $payload = [
            'success' => false,
            'status' => $status,
            'error' => $message,
        ];

        if (!empty($errors)) {
            $payload['errors'] = $errors;
        }

        response_json($payload, $status);
        exit;
    }
}

/**
 * Filter an associative array to only include allowlisted keys (§15 Field Whitelisting).
 */
if (!function_exists('columns_whitelist')) {
    function columns_whitelist(array $row, array $allowedColumns): array
    {
        return array_intersect_key($row, array_flip($allowedColumns));
    }
}

