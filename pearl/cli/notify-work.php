<?php
/**
 * Pearl Framework — CLI: notify:work
 *
 * Handles: php bin/pearl notify:work [--driver=...] [--queue=...]
 *
 * Atomically reserves and processes queue jobs across the active driver
 * (file, database, redis). Handles retries with exponential backoff and
 * transfers exhausted jobs to dead-letter failed storage.
 *
 * Returns a callable: function(array $args): int
 */

declare(strict_types=1);

if (!function_exists('queue_reserve')) {
    throw new RuntimeException('pearl/queue.php must be required before notify-work.php.');
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

    echo "=== Pearl Queue Worker ===" . PHP_EOL;
    echo "Driver: {$driver}" . PHP_EOL;
    echo "Queue:  {$queue}" . PHP_EOL . PHP_EOL;

    $processed = 0;
    $retried = 0;
    $failed = 0;

    while (true) {
        $job = queue_reserve($queue, $driver);

        if ($job === null) {
            break; // No more available jobs in this pass
        }

        $id = (string) $job['id'];
        $type = (string) ($job['type'] ?? 'unknown');
        $attempt = (int) ($job['attempts'] ?? 1);

        try {
            queue_handle_job($job);
            queue_complete($id, $driver, $queue);
            echo "  [done]    {$type} (id: {$id}, attempt: {$attempt})" . PHP_EOL;
            $processed++;
        } catch (\Throwable $e) {
            $reason = $e->getMessage();
            $willRetry = queue_release_or_fail($job, $reason, $driver);

            if ($willRetry) {
                echo "  [retry]   {$type} (id: {$id}, attempt: {$attempt}): {$reason}" . PHP_EOL;
                $retried++;
            } else {
                echo "  [failed]  {$type} (id: {$id}, max attempts reached): {$reason}" . PHP_EOL;
                $failed++;
            }
        }
    }

    if ($processed === 0 && $retried === 0 && $failed === 0) {
        echo "No available jobs to process." . PHP_EOL;
    } else {
        echo PHP_EOL . "Summary: Processed: {$processed}, Retried: {$retried}, Failed: {$failed}" . PHP_EOL;
    }

    return $failed > 0 ? 1 : 0;
};
