<?php
/**
 * Pearl Framework — Error Handling
 *
 * Registers PHP error/exception/shutdown handlers.
 * Requires pearl/bootstrap.php (for env()) and pearl/paths.php
 * (for view_path() / storage_path()) to already be loaded.
 *
 * Golden rule baked in from the old FluxPHP bug: ALWAYS clear any open
 * output buffers before rendering an error page, or the error page
 * gets corrupted by whatever partial output already escaped.
 */

declare(strict_types=1);

if (!defined('PEARL_ROOT')) {
    throw new RuntimeException(
        'PEARL_ROOT is not defined. pearl/bootstrap.php must be required before pearl/errors.php.'
    );
}

if (!function_exists('pearl_path')) {
    throw new RuntimeException(
        'Path helpers are not loaded. pearl/paths.php must be required before pearl/errors.php.'
    );
}

/**
 * Clear every open output buffer. Safe to call even if none are open.
 */
if (!function_exists('pearl_clear_output_buffers')) {
    function pearl_clear_output_buffers(): void
    {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
    }
}

/**
 * Write an error/exception to the storage log file.
 * Never throws — logging failures must not cascade into a second error.
 */
if (!function_exists('pearl_log_error')) {
    function pearl_log_error(string $message): void
    {
        try {
            $logDir = storage_path('logs');

            if (!is_dir($logDir)) {
                mkdir($logDir, 0775, true);
            }

            $line = '[' . date('Y-m-d H:i:s') . '] ' . $message . PHP_EOL;
            file_put_contents($logDir . '/error.log', $line, FILE_APPEND | LOCK_EX);
        } catch (\Throwable) {
            // Deliberately swallowed — logging must never throw.
        }
    }
}

/**
 * Render the error page for a given HTTP status code.
 *
 * Looks for a view at view/errors/{statusCode}.php, falling back to
 * view/errors/500.php, then to a minimal inline fallback if neither
 * exists yet (expected during early Phase 0/1 before views exist).
 */
if (!function_exists('pearl_render_error_page')) {
    function pearl_render_error_page(int $statusCode, ?\Throwable $e = null): void
    {
        // Clear any partial output BEFORE sending headers or rendering.
        pearl_clear_output_buffers();

        if (!headers_sent()) {
            http_response_code($statusCode);
        }

        $isDebug = in_array(env('APP_ENV', 'production'), ['local', 'development'], true);

        // Content-negotiated JSON error responses for API requests (§15)
        if (function_exists('is_api_request') && is_api_request()) {
            if (!headers_sent()) {
                header('Content-Type: application/json; charset=utf-8');
            }

            $errorTitle = match ($statusCode) {
                400 => 'Bad Request',
                401 => 'Unauthenticated',
                403 => 'Forbidden',
                404 => 'Resource Not Found',
                405 => 'Method Not Allowed',
                419 => 'Page Expired',
                422 => 'Unprocessable Content',
                429 => 'Too Many Requests',
                default => 'Internal Server Error',
            };

            $response = [
                'success' => false,
                'status' => $statusCode,
                'error' => $errorTitle,
            ];

            if ($isDebug && $e !== null) {
                $response['message'] = $e->getMessage();
                $response['file'] = $e->getFile() . ':' . $e->getLine();
                $response['trace'] = explode("\n", $e->getTraceAsString());
            } elseif ($e !== null && $statusCode < 500) {
                $response['message'] = $e->getMessage();
            }

            echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
            return;
        }

        $specificView = view_path("errors/{$statusCode}.php");
        $fallbackView = view_path('errors/500.php');

        if (is_file($specificView)) {
            require $specificView;
            return;
        }

        if (is_file($fallbackView)) {
            require $fallbackView;
            return;
        }

        // Minimal inline fallback — no view/ templates exist yet at this
        // stage of the build (Phase 5). Keeps the app from white-screening.
        echo '<!doctype html><html><head><meta charset="utf-8">';
        echo '<title>Error ' . $statusCode . '</title></head><body>';
        echo '<h1>Something went wrong (' . $statusCode . ')</h1>';

        if ($isDebug && $e !== null) {
            echo '<pre>' . htmlspecialchars(
                $e->getMessage() . "\n\n" . $e->getTraceAsString(),
                ENT_QUOTES
            ) . '</pre>';
        }

        echo '</body></html>';
    }
}

/**
 * Uncaught exception handler.
 */
if (!function_exists('pearl_handle_exception')) {
    function pearl_handle_exception(\Throwable $e): void
    {
        pearl_log_error(
            get_class($e) . ': ' . $e->getMessage() .
            ' in ' . $e->getFile() . ':' . $e->getLine()
        );

        pearl_render_error_page(500, $e);
    }
}

/**
 * Standard PHP error handler — converts warnings/notices into
 * ErrorException so they flow through the same exception handler,
 * unless the error was suppressed with @ (error_reporting() === 0).
 */
if (!function_exists('pearl_handle_error')) {
    function pearl_handle_error(int $severity, string $message, string $file, int $line): bool
    {
        if (!(error_reporting() & $severity)) {
            return false;
        }

        throw new \ErrorException($message, 0, $severity, $file, $line);
    }
}

/**
 * Fatal error catch-all (parse errors, memory exhaustion, etc. don't
 * flow through set_error_handler — this is the last line of defense).
 */
if (!function_exists('pearl_handle_shutdown')) {
    function pearl_handle_shutdown(): void
    {
        $error = error_get_last();

        if ($error === null) {
            return;
        }

        $fatalTypes = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR];

        if (!in_array($error['type'], $fatalTypes, true)) {
            return;
        }

        pearl_log_error(
            'FATAL: ' . $error['message'] .
            ' in ' . $error['file'] . ':' . $error['line']
        );

        pearl_render_error_page(500);
    }
}

/**
 * Wire up all handlers. Called once, typically from public/index.php
 * or bin/pearl right after bootstrap.php + paths.php are loaded.
 */
if (!function_exists('pearl_register_error_handlers')) {
    function pearl_register_error_handlers(): void
    {
        set_error_handler('pearl_handle_error');
        set_exception_handler('pearl_handle_exception');
        register_shutdown_function('pearl_handle_shutdown');
    }
}
