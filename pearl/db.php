<?php
/**
 * Pearl Framework — Database Connection & Multi-Engine Support
 *
 * Multi-engine database connection supporting MySQL, PostgreSQL, and SQLite.
 * Provides a shared PDO instance, driver detection, and procedural SQL
 * identifier quotation helpers (§4, §10, §28).
 *
 * Depends on: bootstrap.php (env()).
 *
 * See pearl/FUNCTIONS.md for the ownership registry.
 */

declare(strict_types=1);

if (!function_exists('env')) {
    throw new RuntimeException(
        'pearl/bootstrap.php must be required before pearl/db.php.'
    );
}

/**
 * Validate a table or column identifier. Allows an optional
 * "table.column" dotted form.
 */
if (!function_exists('pearl_validate_identifier')) {
    function pearl_validate_identifier(string $identifier): string
    {
        if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*(\.[A-Za-z_][A-Za-z0-9_]*)?$/', $identifier)) {
            throw new InvalidArgumentException("Invalid identifier: {$identifier}");
        }

        return $identifier;
    }
}

/**
 * Returns the configured or active PDO driver name ('mysql', 'pgsql', 'sqlite').
 */
if (!function_exists('db_driver')) {
    function db_driver(?\PDO $pdo = null): string
    {
        if ($pdo !== null) {
            return strtolower((string) $pdo->getAttribute(\PDO::ATTR_DRIVER_NAME));
        }

        if (isset($GLOBALS['pearl_pdo_instance']) && $GLOBALS['pearl_pdo_instance'] instanceof \PDO) {
            return strtolower((string) $GLOBALS['pearl_pdo_instance']->getAttribute(\PDO::ATTR_DRIVER_NAME));
        }

        return strtolower((string) env('DB_CONNECTION', 'mysql'));
    }
}

/**
 * Safely quote an SQL identifier according to the active database engine.
 * Supports table.column dotted notation.
 * Uses backticks (`) for MySQL and double quotes (") for PostgreSQL and SQLite.
 */
if (!function_exists('db_quote_identifier')) {
    function db_quote_identifier(string $identifier, ?string $driver = null): string
    {
        if ($identifier === '*' || str_contains($identifier, '(') || str_contains($identifier, ' ')) {
            return $identifier;
        }

        $driver ??= db_driver();
        $q = ($driver === 'mysql') ? '`' : '"';

        if (str_contains($identifier, '.')) {
            $parts = explode('.', $identifier);
            $quoted = array_map(static function (string $p) use ($q): string {
                return $p === '*' ? '*' : $q . pearl_validate_identifier($p) . $q;
            }, $parts);
            return implode('.', $quoted);
        }

        return $q . pearl_validate_identifier($identifier) . $q;
    }
}

/**
 * Disconnect the shared PDO connection instance.
 */
if (!function_exists('db_disconnect')) {
    function db_disconnect(): void
    {
        $GLOBALS['pearl_pdo_instance'] = null;
    }
}

/**
 * Returns a shared PDO connection, created on first call and reused
 * for the rest of the request. Throws on connection failure.
 */
if (!function_exists('pdo')) {
    function pdo(): \PDO
    {
        if (isset($GLOBALS['pearl_pdo_instance']) && $GLOBALS['pearl_pdo_instance'] instanceof \PDO) {
            return $GLOBALS['pearl_pdo_instance'];
        }

        $driver = db_driver();

        if ($driver === 'sqlite') {
            $database = env('DB_DATABASE', ':memory:');
            $dsn = "sqlite:{$database}";
            $username = null;
            $password = null;
        } elseif ($driver === 'pgsql') {
            $host = env('DB_HOST', '127.0.0.1');
            $port = env('DB_PORT', '5432');
            $database = env('DB_DATABASE', 'pearl');
            $username = env('DB_USERNAME', 'postgres');
            $password = env('DB_PASSWORD', '');
            $sslmode = env('DB_SSLMODE', 'prefer');
            $dsn = "pgsql:host={$host};port={$port};dbname={$database};sslmode={$sslmode}";
        } else {
            // Default: MySQL
            $host = env('DB_HOST', '127.0.0.1');
            $port = env('DB_PORT', '3306');
            $database = env('DB_DATABASE', 'pearl');
            $username = env('DB_USERNAME', 'root');
            $password = env('DB_PASSWORD', '');
            $dsn = "mysql:host={$host};port={$port};dbname={$database};charset=utf8mb4";
        }

        $connection = new \PDO($dsn, $username, $password, [
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
            \PDO::ATTR_EMULATE_PREPARES => false,
        ]);

        $GLOBALS['pearl_pdo_instance'] = $connection;
        return $connection;
    }
}
