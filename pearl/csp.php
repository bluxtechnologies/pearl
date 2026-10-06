<?php
/**
 * Pearl Framework — Security Headers
 *
 * Depends on: pearl/paths.php (config_path), bootstrap.php (env).
 *
 * See pearl/FUNCTIONS.md for the ownership registry.
 */

declare(strict_types=1);

if (!function_exists('config_path')) {
    throw new RuntimeException('pearl/paths.php must be required before pearl/csp.php.');
}

/**
 * Load and cache config/security.php.
 */
if (!function_exists('security_config')) {
    function security_config(): array
    {
        static $config = null;

        if ($config === null) {
            $config = require config_path('security.php');
        }

        return $config;
    }
}

/**
 * Build the Content-Security-Policy header value from config/security.php's
 * csp array — e.g. ['script-src' => ["'self'"]] becomes "script-src 'self'".
 */
if (!function_exists('csp_build_header')) {
    function csp_build_header(array $csp): string
    {
        $directives = [];

        foreach ($csp as $directive => $sources) {
            $directives[] = $directive . ' ' . implode(' ', $sources);
        }

        return implode('; ', $directives);
    }
}

/**
 * Send the CSP + other security headers, if csp_enabled is true.
 * Called once from public/index.php, before any output — headers
 * can't be sent after the response body has started.
 */
if (!function_exists('send_security_headers')) {
    function send_security_headers(): void
    {
        if (headers_sent()) {
            return;
        }

        $config = security_config();

        if (!$config['csp_enabled']) {
            return;
        }

        header('Content-Security-Policy: ' . csp_build_header($config['csp']));

        foreach ($config['headers'] as $name => $value) {
            $safeName = str_replace(["\r", "\n", "\0"], '', (string) $name);
            $safeValue = str_replace(["\r", "\n", "\0"], '', (string) $value);
            header("{$safeName}: {$safeValue}");
        }
    }
}
