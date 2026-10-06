<?php
/**
 * Pearl Framework — Sessions
 *
 * Dual-context session handling. Context is derived from the request
 * path (anything under /admin is 'admin' context, everything else is
 * 'user') — the same convention already used by the router's
 * wire/admin/* namespace, so routing and session context agree by
 * construction rather than needing separate configuration.
 *
 * AUTH_MODE controls cookie isolation:
 *   dual   (default) — separate cookies, separate session stores in
 *                       effect: PEARL_SESSION for user, PEARL_ADMIN_SESSION
 *                       for admin. A browser can be logged into both at once.
 *   single             — one shared cookie (PEARL_SESSION) for both
 *                       contexts. Role-based access is then enforced in
 *                       pearl/auth.php, not by session isolation.
 *
 * This is the fix for the original FluxPHP bug: two contexts sharing
 * one cookie name caused session bleed between user and admin logins.
 *
 * Depends on: bootstrap.php (env), paths.php (storage_path), request.php
 * (request_path).
 *
 * See pearl/FUNCTIONS.md for the ownership registry.
 */

declare(strict_types=1);

if (!function_exists('request_path')) {
    throw new RuntimeException(
        'pearl/request.php must be required before pearl/session.php.'
    );
}

if (!function_exists('storage_path')) {
    throw new RuntimeException(
        'pearl/paths.php must be required before pearl/session.php.'
    );
}

require_once __DIR__ . '/session_drivers.php';

/**
 * Load and cache config/session.php.
 */
if (!function_exists('session_config')) {
    function session_config(): array
    {
        static $config = null;
        if ($config === null) {
            $configPath = function_exists('config_path') ? config_path('session.php') : dirname(__DIR__) . '/config/session.php';
            if (is_file($configPath)) {
                $config = require $configPath;
            } else {
                $config = ['driver' => 'file', 'lifetime' => 120, 'table' => 'sessions'];
            }
        }
        return $config;
    }
}

/**
 * Detect which auth context the current request belongs to, based on
 * path prefix. 'admin' for anything under /admin, 'user' otherwise.
 */
if (!function_exists('session_detect_context')) {
    function session_detect_context(): string
    {
        $path = request_path();

        if ($path === '/admin' || str_starts_with($path, '/admin/')) {
            return 'admin';
        }

        return 'user';
    }
}

/**
 * Resolve the cookie/session name to use for a given context, based
 * on AUTH_MODE. Single owner of this mapping — do not hardcode cookie
 * names anywhere else.
 */
if (!function_exists('session_cookie_name')) {
    function session_cookie_name(string $context): string
    {
        $authMode = env('AUTH_MODE', 'dual');

        if ($authMode === 'single') {
            return 'PEARL_SESSION';
        }

        return $context === 'admin' ? 'PEARL_ADMIN_SESSION' : 'PEARL_SESSION';
    }
}

/**
 * Start the session for the current request's context, with secure
 * cookie defaults and swappable storage driver (file, database, redis).
 * Idempotent — safe to call more than once per request.
 */
if (!function_exists('session_start_pearl')) {
    function session_start_pearl(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE || headers_sent()) {
            return;
        }

        $context = session_detect_context();
        $cookieName = session_cookie_name($context);
        $config = session_config();
        $driver = $config['driver'] ?? 'file';

        if ($driver === 'database') {
            if (!function_exists('pdo')) {
                throw new RuntimeException('pearl/db.php must be required before using database sessions.');
            }
            $handler = new PearlDatabaseSessionHandler(pdo(), (string) ($config['table'] ?? 'sessions'), $context);
            session_set_save_handler($handler, true);
        } elseif ($driver === 'redis') {
            if (!class_exists('Redis')) {
                throw new RuntimeException("The ext-redis extension is required for SESSION_DRIVER=redis. Please enable it in php.ini.");
            }
            $redis = new Redis();
            $redisConfig = $config['redis'] ?? [];
            $redis->connect(
                (string) ($redisConfig['host'] ?? '127.0.0.1'),
                (int) ($redisConfig['port'] ?? 6379),
                (float) ($redisConfig['timeout'] ?? 2.0)
            );
            if (!empty($redisConfig['password'])) {
                $redis->auth($redisConfig['password']);
            }
            if (isset($redisConfig['database'])) {
                $redis->select((int) $redisConfig['database']);
            }
            $lifetime = (int) ($config['lifetime'] ?? 120) * 60;
            $handler = new PearlRedisSessionHandler($redis, $context, $lifetime);
            session_set_save_handler($handler, true);
        } else {
            // 'file' driver (default)
            $sessionDir = storage_path('sessions');
            if (!is_dir($sessionDir)) {
                mkdir($sessionDir, 0775, true);
            }
            session_save_path($sessionDir);
        }

        session_name($cookieName);

        $isSecure = env('APP_ENV', 'production') === 'production'
            || ($_SERVER['HTTPS'] ?? '') !== '';

        session_set_cookie_params([
            'lifetime' => 0,
            'path'     => '/',
            'domain'   => '',
            'secure'   => $isSecure,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);

        session_start();

        // Record which context this session was started under, so
        // later code (e.g. logout, auth checks) can confirm it matches
        // rather than trusting the path alone on every subsequent call.
        if (!isset($_SESSION['_pearl_context'])) {
            $_SESSION['_pearl_context'] = $context;
        }
    }
}

/**
 * The context the currently active session was started under. Differs
 * from session_detect_context() in that this reflects the *session's*
 * recorded context, not the current request's path — relevant mainly
 * for defensive checks in auth.php.
 */
if (!function_exists('session_current_context')) {
    function session_current_context(): ?string
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            return null;
        }

        return $_SESSION['_pearl_context'] ?? null;
    }
}

/**
 * Destroy the current session completely (data + cookie).
 */
if (!function_exists('session_destroy_pearl')) {
    function session_destroy_pearl(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            return;
        }

        $_SESSION = [];

        $cookieParams = session_get_cookie_params();
        if (!headers_sent()) {
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $cookieParams['path'],
                $cookieParams['domain'],
                $cookieParams['secure'],
                $cookieParams['httponly']
            );
        }

        session_destroy();
    }
}

/**
 * Returns the CSRF token for the current session, generating one if not set.
 */
if (!function_exists('csrf_token')) {
    function csrf_token(): string
    {
        session_start_pearl();

        if (empty($_SESSION['_csrf_token']) || !is_string($_SESSION['_csrf_token'])) {
            $_SESSION['_csrf_token'] = bin2hex(random_bytes(32));
        }

        return $_SESSION['_csrf_token'];
    }
}

/**
 * Verifies that the submitted CSRF token matches the session's token.
 * Checks $token if passed, otherwise reads from request input '_csrf' or
 * headers 'X-CSRF-TOKEN' / 'X-XSRF-TOKEN'.
 */
if (!function_exists('csrf_verify')) {
    function csrf_verify(?string $token = null): bool
    {
        session_start_pearl();

        $sessionToken = $_SESSION['_csrf_token'] ?? null;
        if (!is_string($sessionToken) || $sessionToken === '') {
            return false;
        }

        if ($token === null) {
            $input = function_exists('request_input') ? request_input() : [];
            $token = $input['_csrf'] ?? null;

            if ($token === null && function_exists('request_header')) {
                $token = request_header('X-CSRF-TOKEN') ?? request_header('X-XSRF-TOKEN');
            }
        }

        if (!is_string($token) || $token === '') {
            return false;
        }

        return hash_equals($sessionToken, $token);
    }
}
