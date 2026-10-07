<?php
/**
 * Pearl Framework — Bootstrap
 *
 * The single entry point every other file requires first.
 * Responsible for: root path constant, env loading, error reporting baseline.
 * Must not depend on router, session, or db layers — those boot after this.
 */

declare(strict_types=1);

// ---------------------------------------------------------------------
// Root path
// ---------------------------------------------------------------------
// bootstrap.php lives in pearl/, so project root is one level up.
if (!defined('PEARL_ROOT')) {
    define('PEARL_ROOT', dirname(__DIR__));
}

if (!defined('PEARL_VERSION')) {
    define('PEARL_VERSION', '1.0.4');
}

// ---------------------------------------------------------------------
// Environment loading
// ---------------------------------------------------------------------
// Minimal .env parser — no external dependency. Supports KEY=VALUE,
// comments (#), quoted values, and skips blank lines.
if (!function_exists('pearl_load_env')) {
    function pearl_load_env(string $path): void
    {
        if (!is_file($path)) {
            return;
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        foreach ($lines as $line) {
            $line = trim($line);

            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            if (!str_contains($line, '=')) {
                continue;
            }

            [$key, $value] = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);

            // Strip matching surrounding quotes
            if (strlen($value) >= 2) {
                $first = $value[0];
                $last = $value[strlen($value) - 1];
                if (($first === '"' && $last === '"') || ($first === "'" && $last === "'")) {
                    $value = substr($value, 1, -1);
                }
            }

            if (!array_key_exists($key, $_ENV)) {
                putenv("{$key}={$value}");
                $_ENV[$key] = $value;
                $_SERVER[$key] = $value;
            }
        }
    }
}

if (!function_exists('env')) {
    function env(string $key, mixed $default = null): mixed
    {
        $value = $_ENV[$key] ?? getenv($key);

        if ($value === false || $value === null) {
            return $default;
        }

        return match (strtolower((string) $value)) {
            'true'  => true,
            'false' => false,
            'null'  => null,
            default => $value,
        };
    }
}

pearl_load_env(PEARL_ROOT . '/.env');

// ---------------------------------------------------------------------
// Error reporting baseline
// ---------------------------------------------------------------------
// APP_ENV controls display_errors. Actual error/exception HANDLING
// (output buffer clearing, error views) lives in pearl/errors.php,
// loaded separately in Phase 1 — this only sets the baseline.
$appEnv = env('APP_ENV', 'production');

if ($appEnv === 'local' || $appEnv === 'development') {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
} else {
    error_reporting(E_ALL);
    ini_set('display_errors', '0');
}

// ---------------------------------------------------------------------
// Timezone
// ---------------------------------------------------------------------
date_default_timezone_set(env('APP_TIMEZONE', 'UTC'));
