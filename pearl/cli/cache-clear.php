<?php
/**
 * Pearl Framework — CLI: cache:clear
 *
 * Handles: php bin/pearl cache:clear [--driver=...]
 *
 * Flushes all cached items across the active cache driver.
 *
 * Returns a callable: function(array $args): int
 */

declare(strict_types=1);

if (!function_exists('cache_flush')) {
    throw new RuntimeException('pearl/cache.php must be required before cache-clear.php.');
}

return function (array $args): int {
    $driver = null;

    foreach ($args as $arg) {
        if (str_starts_with($arg, '--driver=')) {
            $driver = substr($arg, 9);
        }
    }

    $activeDriver = $driver ?? (string) cache_config('driver', 'file');

    echo "=== Pearl Cache Clear ===" . PHP_EOL . PHP_EOL;
    echo "Driver: {$activeDriver}" . PHP_EOL;

    try {
        cache_flush($driver);
        echo "SUCCESS: Cache successfully cleared." . PHP_EOL;
        return 0;
    } catch (\Throwable $e) {
        echo "ERROR: " . $e->getMessage() . PHP_EOL;
        return 1;
    }
};
