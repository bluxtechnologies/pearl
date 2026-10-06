<?php
/**
 * Pearl Framework — Session Configuration
 *
 * Configures session driver (file, database, redis), session lifetime,
 * and database/redis connection settings.
 *
 * Supported drivers:
 *   - 'file'     (default) Storage under storage/sessions/
 *   - 'database' Sessions table managed via PDO
 *   - 'redis'    Redis-backed sessions with native key TTLs
 */

declare(strict_types=1);

return [
    'driver' => env('SESSION_DRIVER', 'file'),

    // Session lifetime in minutes (default: 120 minutes / 2 hours)
    'lifetime' => (int) env('SESSION_LIFETIME', 120),

    // Database session driver settings
    'table' => 'sessions',

    // Redis session driver settings
    'redis' => [
        'host' => env('REDIS_HOST', '127.0.0.1'),
        'port' => (int) env('REDIS_PORT', 6379),
        'password' => env('REDIS_PASSWORD', null),
        'database' => (int) env('REDIS_DB', 0),
        'timeout' => 2.0,
    ],
];
