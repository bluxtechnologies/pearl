<?php
/**
 * Pearl Framework — Queue Configuration
 *
 * Supported drivers:
 *   - 'file'     (default) storage/queue/{pending,reserved,failed}
 *   - 'sync'     Synchronous inline execution (fast feedback for dev)
 *   - 'database' queue_jobs and queue_failed_jobs tables managed via PDO
 *   - 'redis'    Redis lists with atomic BRPOPLPUSH / RPOPLPUSH
 */

declare(strict_types=1);

return [
    'driver' => env('QUEUE_DRIVER', 'file'),

    // Maximum attempts before a job is moved to failed storage
    'max_tries' => (int) env('QUEUE_MAX_TRIES', 3),

    // Delay in seconds before a failed attempt is eligible for retry (multiplied by attempt number)
    'backoff' => (int) env('QUEUE_BACKOFF', 5),

    // Database driver table names
    'database' => [
        'table' => 'queue_jobs',
        'failed_table' => 'queue_failed_jobs',
    ],

    // Redis driver connection settings
    'redis' => [
        'host' => env('REDIS_HOST', '127.0.0.1'),
        'port' => (int) env('REDIS_PORT', 6379),
        'password' => env('REDIS_PASSWORD', null),
        'database' => (int) env('REDIS_DB', 0),
        'queue' => 'default',
    ],
];
