<?php
/**
 * Pearl Framework — CLI: notify:status
 *
 * Handles: php bin/pearl notify:status [--driver=...] [--queue=...]
 *
 * Reports pending, reserved, and failed queue job counts across the active
 * driver, and lists failed jobs with diagnostic exception traces.
 *
 * Returns a callable: function(array $args): int
 */

declare(strict_types=1);

if (!function_exists('queue_counts')) {
    throw new RuntimeException('pearl/queue.php must be required before notify-status.php.');
}

return function (array $args): int {
    $driver = null;
    $queue = 'default';

    foreach ($args as $arg) {
        if (str_starts_with($arg, '--driver=')) {
            $driver = substr($arg, 9);
        } elseif (str_starts_with($arg, '--queue=')) {
            $queue = substr($arg, 8);
        }
    }

    $driver ??= (string) queue_config('driver', 'file');
    $counts = queue_counts($queue, $driver);

    echo "=== Pearl Queue Status ===" . PHP_EOL . PHP_EOL;
    echo "Driver:   {$driver}" . PHP_EOL;
    echo "Queue:    {$queue}" . PHP_EOL;
    echo "Pending:  {$counts['pending']}" . PHP_EOL;
    echo "Reserved: {$counts['reserved']}" . PHP_EOL;
    echo "Failed:   {$counts['failed']}" . PHP_EOL;

    if ($counts['failed'] > 0) {
        echo PHP_EOL . "-- Failed jobs --" . PHP_EOL;
        $failedJobs = queue_failed_jobs($queue, $driver);

        foreach ($failedJobs as $job) {
            $id = $job['id'] ?? '?';
            $type = $job['type'] ?? 'unknown';
            $attempts = $job['attempts'] ?? '?';
            $reason = $job['reason'] ?? ($job['exception'] ?? '(no reason recorded)');
            $failedAt = $job['failed_at'] ?? 'unknown';

            echo "  [id: {$id}] type: {$type} | failed_at: {$failedAt}" . PHP_EOL;
            echo "    reason: {$reason}" . PHP_EOL;
        }
    }

    return 0;
};
