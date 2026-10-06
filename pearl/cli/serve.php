<?php
/**
 * Pearl Framework — CLI: serve
 *
 * Handles: php bin/pearl serve [--host=localhost] [--port=7200]
 *
 * Starts PHP's built-in development web server with public/ as DocumentRoot
 * and public/index.php as router.
 *
 * Returns a callable: function(array $args): int
 */

declare(strict_types=1);

if (!function_exists('public_path')) {
    throw new RuntimeException('pearl/paths.php must be required before serve.php.');
}

return function (array $args): int {
    $host = 'localhost';
    $port = 7200;

    foreach ($args as $arg) {
        if (str_starts_with($arg, '--host=')) {
            $host = substr($arg, 7);
        } elseif (str_starts_with($arg, '--port=')) {
            $port = (int) substr($arg, 7);
        }
    }

    $publicDir = public_path();
    $routerFile = public_path('index.php');

    echo "=== Pearl Development Server ===" . PHP_EOL . PHP_EOL;
    echo "Serving PEARL on: http://{$host}:{$port}" . PHP_EOL;
    echo "Document Root:    {$publicDir}" . PHP_EOL;
    echo "Press Ctrl+C to stop." . PHP_EOL . PHP_EOL;

    $cmd = sprintf(
        '"%s" -S %s:%d -t "%s" "%s"',
        PHP_BINARY,
        $host,
        $port,
        $publicDir,
        $routerFile
    );

    passthru($cmd, $exitCode);

    return (int) $exitCode;
};
