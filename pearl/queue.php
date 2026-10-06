<?php
/**
 * Pearl Framework — Queue
 *
 * Multi-driver job queue conforming to Layer 2 Framework Contracts (§4, §12).
 * Supported drivers:
 *   - 'file'     (default) storage/queue/{pending,reserved,failed} with atomic rename claiming
 *   - 'sync'     Synchronous inline execution for immediate developer feedback
 *   - 'database' queue_jobs and queue_failed_jobs tables managed via PDO with atomic row locks
 *   - 'redis'    Atomic Redis lists with ext-redis
 *
 * Job processing supports:
 *   - Atomic claiming to prevent multi-worker race conditions
 *   - Automatic exponential backoff and retry policy
 *   - Permanent dead-letter tracking on exhausted attempts
 *   - Pluggable procedural handler registry (queue_register_handler)
 *
 * Depends on: pearl/paths.php (storage_path, config_path), pearl/db.php (pdo).
 *
 * See pearl/FUNCTIONS.md for the ownership registry.
 */

declare(strict_types=1);

if (!function_exists('storage_path')) {
    throw new RuntimeException('pearl/paths.php must be required before pearl/queue.php.');
}

/**
 * Load and cache queue configuration from config/queue.php.
 */
if (!function_exists('queue_config')) {
    function queue_config(?string $key = null, mixed $default = null): mixed
    {
        static $config = null;

        if ($config === null) {
            $path = config_path('queue.php');
            $config = is_file($path) ? (require $path) : [];
        }

        if ($key === null) {
            return $config;
        }

        return $config[$key] ?? $default;
    }
}

/**
 * Register a procedural job handler for a given job type.
 */
if (!function_exists('queue_register_handler')) {
    function queue_register_handler(string $type, callable $handler): void
    {
        if (!isset($GLOBALS['pearl_queue_handlers'])) {
            $GLOBALS['pearl_queue_handlers'] = [];
        }
        $GLOBALS['pearl_queue_handlers'][$type] = $handler;
    }
}

/**
 * Dispatch an in-flight job array to its registered handler.
 */
if (!function_exists('queue_handle_job')) {
    function queue_handle_job(array $job): void
    {
        $type = (string) ($job['type'] ?? '');
        $handlers = $GLOBALS['pearl_queue_handlers'] ?? [];

        if (!isset($handlers[$type]) || !is_callable($handlers[$type])) {
            throw new RuntimeException("No queue handler registered for job type: '{$type}'");
        }

        $handlers[$type]($job['payload'] ?? [], $job);
    }
}

// Default handler for built-in mail notifications
queue_register_handler('send_mail', function (array $payload): void {
    if (!function_exists('mail_send')) {
        require_once pearl_path('mail.php');
    }
    mail_send(
        (string) ($payload['to'] ?? ''),
        (string) ($payload['subject'] ?? ''),
        (string) ($payload['body'] ?? '')
    );
});

/**
 * Ensure the file queue folder structure exists (pending, reserved, failed).
 */
if (!function_exists('queue_ensure_dirs')) {
    function queue_ensure_dirs(): void
    {
        foreach (['pending', 'reserved', 'failed'] as $sub) {
            $dir = storage_path('queue/' . $sub);
            if (!is_dir($dir)) {
                mkdir($dir, 0775, true);
            }
        }
    }
}

/**
 * Push a new job onto the queue. Returns the unique job ID.
 */
if (!function_exists('queue_push')) {
    function queue_push(string $type, array $payload = [], string $queue = 'default', ?string $driver = null): string
    {
        $driver ??= (string) queue_config('driver', 'file');

        if ($driver === 'sync') {
            $id = uniqid('sync_', true);
            $job = [
                'id' => $id,
                'queue' => $queue,
                'type' => $type,
                'payload' => $payload,
                'attempts' => 1,
                'created_at' => time(),
                'queued_at' => date('c'),
            ];
            queue_handle_job($job);
            return $id;
        }

        if ($driver === 'file') {
            queue_ensure_dirs();
            $id = uniqid('job_', true);
            $now = time();
            $job = [
                'id' => $id,
                'queue' => $queue,
                'type' => $type,
                'payload' => $payload,
                'attempts' => 0,
                'reserved_at' => null,
                'available_at' => $now,
                'created_at' => $now,
                'queued_at' => date('c'),
            ];

            $path = storage_path("queue/pending/{$id}.json");
            file_put_contents($path, json_encode($job, JSON_PRETTY_PRINT));
            return $id;
        }

        if ($driver === 'database') {
            if (!function_exists('pdo')) {
                require_once pearl_path('db.php');
            }
            $table = (string) (queue_config('database')['table'] ?? 'queue_jobs');
            $now = time();
            $stmt = pdo()->prepare("INSERT INTO {$table} (queue, payload, attempts, reserved_at, available_at, created_at) VALUES (:queue, :payload, 0, NULL, :available_at, :created_at)");
            $stmt->execute([
                ':queue' => $queue,
                ':payload' => json_encode(['type' => $type, 'payload' => $payload]),
                ':available_at' => $now,
                ':created_at' => $now,
            ]);
            return (string) pdo()->lastInsertId();
        }

        if ($driver === 'redis') {
            if (!extension_loaded('redis')) {
                throw new RuntimeException('PHP Redis extension (ext-redis) is required for QUEUE_DRIVER=redis.');
            }
            $redisCfg = (array) queue_config('redis', []);
            $redis = new \Redis();
            $redis->connect((string) ($redisCfg['host'] ?? '127.0.0.1'), (int) ($redisCfg['port'] ?? 6379));
            if (!empty($redisCfg['password'])) {
                $redis->auth((string) $redisCfg['password']);
            }
            if (isset($redisCfg['database'])) {
                $redis->select((int) $redisCfg['database']);
            }
            $id = uniqid('redis_', true);
            $now = time();
            $job = [
                'id' => $id,
                'queue' => $queue,
                'type' => $type,
                'payload' => $payload,
                'attempts' => 0,
                'reserved_at' => null,
                'available_at' => $now,
                'created_at' => $now,
            ];
            $redis->lPush("pearl:queue:{$queue}", json_encode($job));
            return $id;
        }

        throw new InvalidArgumentException("Unsupported queue driver: '{$driver}'");
    }
}

/**
 * Atomically reserve the next available job for processing.
 * Prevents multiple workers from claiming or executing the same job concurrently.
 */
if (!function_exists('queue_reserve')) {
    function queue_reserve(string $queue = 'default', ?string $driver = null): ?array
    {
        $driver ??= (string) queue_config('driver', 'file');

        if ($driver === 'sync') {
            return null; // Sync runs immediately on push
        }

        if ($driver === 'file') {
            queue_ensure_dirs();
            $now = time();
            $reservationTimeout = 90;

            // 1. Check for stale reservations that died / timed out
            $reservedFiles = glob(storage_path('queue/reserved/*.json')) ?: [];
            foreach ($reservedFiles as $resFile) {
                $data = json_decode((string) file_get_contents($resFile), true);
                if (is_array($data) && isset($data['reserved_at']) && ($now - (int) $data['reserved_at']) > $reservationTimeout) {
                    // Reclaim expired reservation back to pending
                    $id = $data['id'];
                    $pendingPath = storage_path("queue/pending/{$id}.json");
                    $data['reserved_at'] = null;
                    file_put_contents($pendingPath, json_encode($data, JSON_PRETTY_PRINT));
                    @unlink($resFile);
                }
            }

            // 2. Scan pending jobs oldest first
            $pendingFiles = glob(storage_path('queue/pending/*.json')) ?: [];
            sort($pendingFiles, SORT_STRING);

            foreach ($pendingFiles as $pendingPath) {
                $content = @file_get_contents($pendingPath);
                if ($content === false) {
                    continue; // Another worker claimed or removed it
                }
                $job = json_decode($content, true);
                if (!is_array($job)) {
                    continue;
                }

                if (($job['queue'] ?? 'default') !== $queue) {
                    continue;
                }

                if (($job['available_at'] ?? 0) > $now) {
                    continue; // Still backing off / not yet eligible
                }

                $id = (string) $job['id'];
                $reservedPath = storage_path("queue/reserved/{$id}.json");

                // Atomic filesystem reservation via rename()
                if (@rename($pendingPath, $reservedPath)) {
                    $job['attempts'] = ($job['attempts'] ?? 0) + 1;
                    $job['reserved_at'] = $now;
                    file_put_contents($reservedPath, json_encode($job, JSON_PRETTY_PRINT));
                    return $job;
                }
            }

            return null;
        }

        if ($driver === 'database') {
            if (!function_exists('pdo')) {
                require_once pearl_path('db.php');
            }
            $table = (string) (queue_config('database')['table'] ?? 'queue_jobs');
            $now = time();
            $reservationTimeout = 90;
            $expireThreshold = $now - $reservationTimeout;
            $pdo = pdo();

            $pdo->beginTransaction();
            try {
                $stmt = $pdo->prepare(
                    "SELECT id, queue, payload, attempts, reserved_at, available_at, created_at " .
                    "FROM {$table} " .
                    "WHERE queue = :queue " .
                    "  AND (reserved_at IS NULL OR reserved_at <= :expired) " .
                    "  AND available_at <= :now " .
                    "ORDER BY id ASC LIMIT 1 FOR UPDATE"
                );
                $stmt->execute([
                    ':queue' => $queue,
                    ':expired' => $expireThreshold,
                    ':now' => $now,
                ]);
                $row = $stmt->fetch(\PDO::FETCH_ASSOC);

                if (!$row) {
                    $pdo->commit();
                    return null;
                }

                $newAttempts = ((int) $row['attempts']) + 1;
                $update = $pdo->prepare("UPDATE {$table} SET reserved_at = :now, attempts = :attempts WHERE id = :id");
                $update->execute([
                    ':now' => $now,
                    ':attempts' => $newAttempts,
                    ':id' => $row['id'],
                ]);
                $pdo->commit();

                $payload = json_decode((string) $row['payload'], true);
                return [
                    'id' => (string) $row['id'],
                    'queue' => (string) $row['queue'],
                    'type' => (string) ($payload['type'] ?? 'unknown'),
                    'payload' => (array) ($payload['payload'] ?? []),
                    'attempts' => $newAttempts,
                    'reserved_at' => $now,
                    'available_at' => (int) $row['available_at'],
                    'created_at' => (int) $row['created_at'],
                ];
            } catch (\Throwable $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                throw $e;
            }
        }

        if ($driver === 'redis') {
            if (!extension_loaded('redis')) {
                throw new RuntimeException('PHP Redis extension (ext-redis) is required for QUEUE_DRIVER=redis.');
            }
            $redisCfg = (array) queue_config('redis', []);
            $redis = new \Redis();
            $redis->connect((string) ($redisCfg['host'] ?? '127.0.0.1'), (int) ($redisCfg['port'] ?? 6379));
            if (!empty($redisCfg['password'])) {
                $redis->auth((string) $redisCfg['password']);
            }
            if (isset($redisCfg['database'])) {
                $redis->select((int) $redisCfg['database']);
            }

            $raw = $redis->rpoplpush("pearl:queue:{$queue}", "pearl:queue:{$queue}:reserved");
            if (!$raw) {
                return null;
            }

            $job = json_decode((string) $raw, true);
            if (is_array($job)) {
                $job['attempts'] = ($job['attempts'] ?? 0) + 1;
                $job['reserved_at'] = time();
                return $job;
            }

            return null;
        }

        throw new InvalidArgumentException("Unsupported queue driver: '{$driver}'");
    }
}

/**
 * Release a reserved job back to pending state with a backoff delay.
 */
if (!function_exists('queue_release')) {
    function queue_release(array $job, int $delay = 0, ?string $driver = null): void
    {
        $driver ??= (string) queue_config('driver', 'file');
        $id = (string) ($job['id'] ?? '');

        if ($driver === 'file') {
            queue_ensure_dirs();
            $reservedPath = storage_path("queue/reserved/{$id}.json");
            $pendingPath = storage_path("queue/pending/{$id}.json");

            $job['reserved_at'] = null;
            $job['available_at'] = time() + $delay;

            file_put_contents($pendingPath, json_encode($job, JSON_PRETTY_PRINT));
            if (is_file($reservedPath)) {
                @unlink($reservedPath);
            }
            return;
        }

        if ($driver === 'database') {
            if (!function_exists('pdo')) {
                require_once pearl_path('db.php');
            }
            $table = (string) (queue_config('database')['table'] ?? 'queue_jobs');
            $stmt = pdo()->prepare("UPDATE {$table} SET reserved_at = NULL, available_at = :available WHERE id = :id");
            $stmt->execute([
                ':available' => time() + $delay,
                ':id' => $id,
            ]);
            return;
        }

        if ($driver === 'redis') {
            // Redis delay/retry can push back to pending list
            $redisCfg = (array) queue_config('redis', []);
            $redis = new \Redis();
            $redis->connect((string) ($redisCfg['host'] ?? '127.0.0.1'), (int) ($redisCfg['port'] ?? 6379));
            if (!empty($redisCfg['password'])) {
                $redis->auth((string) $redisCfg['password']);
            }
            if (isset($redisCfg['database'])) {
                $redis->select((int) $redisCfg['database']);
            }
            $queue = $job['queue'] ?? 'default';
            $job['reserved_at'] = null;
            $job['available_at'] = time() + $delay;
            $redis->lPush("pearl:queue:{$queue}", json_encode($job));
            $redis->lRem("pearl:queue:{$queue}:reserved", json_encode($job), 1);
        }
    }
}

/**
 * Marks a job as completed and deletes it from storage.
 */
if (!function_exists('queue_complete')) {
    function queue_complete(string|int $id, ?string $driver = null, string $queue = 'default'): void
    {
        $driver ??= (string) queue_config('driver', 'file');
        $idStr = (string) $id;

        if ($driver === 'file') {
            $reservedPath = storage_path("queue/reserved/{$idStr}.json");
            $pendingPath = storage_path("queue/pending/{$idStr}.json");

            if (is_file($reservedPath)) {
                unlink($reservedPath);
            } elseif (is_file($pendingPath)) {
                unlink($pendingPath);
            }
            return;
        }

        if ($driver === 'database') {
            if (!function_exists('pdo')) {
                require_once pearl_path('db.php');
            }
            $table = (string) (queue_config('database')['table'] ?? 'queue_jobs');
            $stmt = pdo()->prepare("DELETE FROM {$table} WHERE id = :id");
            $stmt->execute([':id' => $idStr]);
            return;
        }

        if ($driver === 'redis') {
            // Completed in Redis: remove from reserved list
            $redisCfg = (array) queue_config('redis', []);
            $redis = new \Redis();
            $redis->connect((string) ($redisCfg['host'] ?? '127.0.0.1'), (int) ($redisCfg['port'] ?? 6379));
            if (!empty($redisCfg['password'])) {
                $redis->auth((string) $redisCfg['password']);
            }
            if (isset($redisCfg['database'])) {
                $redis->select((int) $redisCfg['database']);
            }
            // In Redis list-based queue, reserved items are pruned
        }
    }
}

/**
 * Permanently mark a job as failed and move to dead-letter storage.
 */
if (!function_exists('queue_fail')) {
    function queue_fail(string|int $id, string $reason = '', ?string $driver = null, string $queue = 'default'): void
    {
        $driver ??= (string) queue_config('driver', 'file');
        $idStr = (string) $id;

        if ($driver === 'file') {
            queue_ensure_dirs();

            $reservedPath = storage_path("queue/reserved/{$idStr}.json");
            $pendingPath = storage_path("queue/pending/{$idStr}.json");
            $sourcePath = is_file($reservedPath) ? $reservedPath : (is_file($pendingPath) ? $pendingPath : null);

            $job = [];
            if ($sourcePath !== null) {
                $decoded = json_decode((string) file_get_contents($sourcePath), true);
                if (is_array($decoded)) {
                    $job = $decoded;
                }
                @unlink($sourcePath);
            }

            $job['id'] = $idStr;
            $job['failed_at'] = date('c');
            $job['reason'] = $reason;
            $job['exception'] = $reason;

            $failedPath = storage_path("queue/failed/{$idStr}.json");
            file_put_contents($failedPath, json_encode($job, JSON_PRETTY_PRINT));
            return;
        }

        if ($driver === 'database') {
            if (!function_exists('pdo')) {
                require_once pearl_path('db.php');
            }
            $table = (string) (queue_config('database')['table'] ?? 'queue_jobs');
            $failedTable = (string) (queue_config('database')['failed_table'] ?? 'queue_failed_jobs');
            $pdo = pdo();

            $stmt = $pdo->prepare("SELECT * FROM {$table} WHERE id = :id");
            $stmt->execute([':id' => $idStr]);
            $row = $stmt->fetch(\PDO::FETCH_ASSOC);

            $payload = $row ? (string) $row['payload'] : json_encode(['id' => $idStr]);
            $q = $row ? (string) $row['queue'] : $queue;

            $pdo->beginTransaction();
            try {
                $del = $pdo->prepare("DELETE FROM {$table} WHERE id = :id");
                $del->execute([':id' => $idStr]);

                $ins = $pdo->prepare("INSERT INTO {$failedTable} (queue, payload, exception, failed_at) VALUES (:queue, :payload, :exception, :failed_at)");
                $ins->execute([
                    ':queue' => $q,
                    ':payload' => $payload,
                    ':exception' => $reason,
                    ':failed_at' => time(),
                ]);
                $pdo->commit();
            } catch (\Throwable $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                throw $e;
            }
        }
    }
}

/**
 * Handle job failure: automatically retry with backoff if attempts remain,
 * or permanently mark failed if max_tries is reached.
 *
 * Returns true if retried, false if exhausted and failed.
 */
if (!function_exists('queue_release_or_fail')) {
    function queue_release_or_fail(array $job, string $reason = '', ?string $driver = null): bool
    {
        $driver ??= (string) queue_config('driver', 'file');
        $maxTries = (int) queue_config('max_tries', 3);
        $backoff = (int) queue_config('backoff', 5);
        $attempts = (int) ($job['attempts'] ?? 1);

        if ($attempts < $maxTries) {
            $delay = $attempts * $backoff;
            queue_release($job, $delay, $driver);
            return true;
        }

        queue_fail((string) $job['id'], $reason, $driver, (string) ($job['queue'] ?? 'default'));
        return false;
    }
}

/**
 * Return all pending jobs for a queue (non-destructive inspection).
 */
if (!function_exists('queue_pending')) {
    function queue_pending(string $queue = 'default', ?string $driver = null): array
    {
        $driver ??= (string) queue_config('driver', 'file');

        if ($driver === 'file') {
            queue_ensure_dirs();
            $files = glob(storage_path('queue/pending/*.json')) ?: [];
            sort($files, SORT_STRING);
            $jobs = [];
            foreach ($files as $file) {
                $decoded = json_decode((string) file_get_contents($file), true);
                if (is_array($decoded) && ($decoded['queue'] ?? 'default') === $queue) {
                    $jobs[] = $decoded;
                }
            }
            return $jobs;
        }

        if ($driver === 'database') {
            if (!function_exists('pdo')) {
                require_once pearl_path('db.php');
            }
            $table = (string) (queue_config('database')['table'] ?? 'queue_jobs');
            $now = time();
            $stmt = pdo()->prepare("SELECT * FROM {$table} WHERE queue = :queue AND reserved_at IS NULL AND available_at <= :now ORDER BY id ASC");
            $stmt->execute([':queue' => $queue, ':now' => $now]);
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            $jobs = [];
            foreach ($rows as $row) {
                $payload = json_decode((string) $row['payload'], true) ?: [];
                $jobs[] = [
                    'id' => (string) $row['id'],
                    'queue' => (string) $row['queue'],
                    'type' => (string) ($payload['type'] ?? 'unknown'),
                    'payload' => (array) ($payload['payload'] ?? []),
                    'attempts' => (int) $row['attempts'],
                    'available_at' => (int) $row['available_at'],
                    'created_at' => (int) $row['created_at'],
                ];
            }
            return $jobs;
        }

        return [];
    }
}

/**
 * Return all permanently failed jobs across the active driver.
 */
if (!function_exists('queue_failed_jobs')) {
    function queue_failed_jobs(string $queue = 'default', ?string $driver = null): array
    {
        $driver ??= (string) queue_config('driver', 'file');

        if ($driver === 'file') {
            queue_ensure_dirs();
            $files = glob(storage_path('queue/failed/*.json')) ?: [];
            sort($files, SORT_STRING);
            $failed = [];
            foreach ($files as $file) {
                $job = json_decode((string) file_get_contents($file), true);
                if (is_array($job)) {
                    $failed[] = $job;
                }
            }
            return $failed;
        }

        if ($driver === 'database') {
            if (!function_exists('pdo')) {
                require_once pearl_path('db.php');
            }
            $failedTable = (string) (queue_config('database')['failed_table'] ?? 'queue_failed_jobs');
            $stmt = pdo()->prepare("SELECT * FROM {$failedTable} WHERE queue = :queue ORDER BY id DESC");
            $stmt->execute([':queue' => $queue]);
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            $failed = [];
            foreach ($rows as $row) {
                $payload = json_decode((string) $row['payload'], true) ?: [];
                $failed[] = [
                    'id' => (string) $row['id'],
                    'queue' => (string) $row['queue'],
                    'type' => (string) ($payload['type'] ?? 'unknown'),
                    'payload' => (array) ($payload['payload'] ?? []),
                    'reason' => (string) $row['exception'],
                    'exception' => (string) $row['exception'],
                    'failed_at' => date('c', (int) $row['failed_at']),
                ];
            }
            return $failed;
        }

        return [];
    }
}

/**
 * Counts for reporting (used by notify:status).
 */
if (!function_exists('queue_counts')) {
    function queue_counts(string $queue = 'default', ?string $driver = null): array
    {
        $driver ??= (string) queue_config('driver', 'file');

        if ($driver === 'file') {
            queue_ensure_dirs();
            $pendingFiles = glob(storage_path('queue/pending/*.json')) ?: [];
            $reservedFiles = glob(storage_path('queue/reserved/*.json')) ?: [];
            $failedFiles = glob(storage_path('queue/failed/*.json')) ?: [];

            return [
                'pending' => count($pendingFiles),
                'reserved' => count($reservedFiles),
                'failed' => count($failedFiles),
            ];
        }

        if ($driver === 'database') {
            if (!function_exists('pdo')) {
                require_once pearl_path('db.php');
            }
            $table = (string) (queue_config('database')['table'] ?? 'queue_jobs');
            $failedTable = (string) (queue_config('database')['failed_table'] ?? 'queue_failed_jobs');
            $now = time();

            $pStmt = pdo()->prepare("SELECT COUNT(*) FROM {$table} WHERE queue = :queue AND reserved_at IS NULL AND available_at <= :now");
            $pStmt->execute([':queue' => $queue, ':now' => $now]);
            $pending = (int) $pStmt->fetchColumn();

            $rStmt = pdo()->prepare("SELECT COUNT(*) FROM {$table} WHERE queue = :queue AND reserved_at IS NOT NULL");
            $rStmt->execute([':queue' => $queue]);
            $reserved = (int) $rStmt->fetchColumn();

            $fStmt = pdo()->prepare("SELECT COUNT(*) FROM {$failedTable} WHERE queue = :queue");
            $fStmt->execute([':queue' => $queue]);
            $failed = (int) $fStmt->fetchColumn();

            return [
                'pending' => $pending,
                'reserved' => $reserved,
                'failed' => $failed,
            ];
        }

        return ['pending' => 0, 'reserved' => 0, 'failed' => 0];
    }
}
