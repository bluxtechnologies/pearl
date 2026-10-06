<?php
/**
 * Pearl Framework — Cache Configuration
 *
 * Returned array, read by pearl/cache.php. Not auto-loaded on every
 * request — only required when cache_* functions are called.
 *
 * Supported drivers:
 *   - 'file'     (default) storage/cache/ serialized files with expiration envelopes
 *   - 'database' cache table managed via PDO
 *   - 'redis'    Redis key-value store with native TTL expiration
 */

declare(strict_types=1);

return [
    'driver' => env('CACHE_DRIVER', 'file'),

    // Default expiration time in seconds (1 hour default)
    'ttl' => (int) env('CACHE_TTL', 3600),

    // Database driver configuration
    'database' => [
        'table' => 'cache',
    ],

    // Redis driver configuration
    'redis' => [
        'host' => env('REDIS_HOST', '127.0.0.1'),
        'port' => (int) env('REDIS_PORT', 6379),
        'password' => env('REDIS_PASSWORD', null),
        'database' => (int) env('REDIS_CACHE_DB', 1),
        'prefix' => 'pearl_cache:',
    ],
];
