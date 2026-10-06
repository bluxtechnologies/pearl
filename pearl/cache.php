<?php
/**
 * Pearl Framework — Cache Subsystem
 *
 * Multi-driver caching conforming to Layer 2 Framework Contracts (§4, §14).
 * Supported drivers:
 *   - 'file'     (default) storage/cache/ with 2-char shard prefixes and atomic writes
 *   - 'database' cache table managed via PDO
 *   - 'redis'    Redis key-value store with native TTL expiration
 *
 * Invalidation and staleness:
 *   Expired entries are automatically pruned on read. Complete invalidation
 *   is supported via cache_forget() and cache_flush().
 *
 * Depends on: pearl/paths.php (config_path, storage_path), pearl/db.php (pdo).
 *
 * See pearl/FUNCTIONS.md for the ownership registry.
 */

declare(strict_types=1);

if (!function_exists('storage_path')) {
    throw new RuntimeException('pearl/paths.php must be required before pearl/cache.php.');
}

/**
 * Load and cache config/cache.php.
 */
if (!function_exists('cache_config')) {
    function cache_config(?string $key = null, mixed $default = null): mixed
    {
        static $config = null;

        if ($config === null) {
            $path = config_path('cache.php');
            $config = is_file($path) ? (require $path) : [];
        }

        if ($key === null) {
            return $config;
        }

        return $config[$key] ?? $default;
    }
}

/**
 * Ensure storage/cache/ directory exists.
 */
if (!function_exists('cache_ensure_dir')) {
    function cache_ensure_dir(): void
    {
        $dir = storage_path('cache');
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
    }
}

/**
 * Resolve filesystem path for a cached key with 2-character sharding.
 */
if (!function_exists('cache_file_path')) {
    function cache_file_path(string $key): string
    {
        $hash = sha1($key);
        $shard = substr($hash, 0, 2);
        $shardDir = storage_path("cache/{$shard}");

        if (!is_dir($shardDir)) {
            mkdir($shardDir, 0775, true);
        }

        return "{$shardDir}/{$hash}.cache";
    }
}

/**
 * Get an item from the cache. Returns $default if missing or expired.
 */
if (!function_exists('cache_get')) {
    function cache_get(string $key, mixed $default = null, ?string $driver = null): mixed
    {
        $driver ??= (string) cache_config('driver', 'file');

        if ($driver === 'file') {
            $path = cache_file_path($key);

            if (!is_file($path)) {
                return $default;
            }

            $raw = @file_get_contents($path);
            if ($raw === false) {
                return $default;
            }

            $envelope = @unserialize($raw);
            if (!is_array($envelope) || !array_key_exists('expiration', $envelope) || !array_key_exists('value', $envelope)) {
                @unlink($path);
                return $default;
            }

            $now = time();
            if ($envelope['expiration'] > 0 && $envelope['expiration'] < $now) {
                // Expired item — lazy prune
                @unlink($path);
                return $default;
            }

            return $envelope['value'];
        }

        if ($driver === 'database') {
            if (!function_exists('pdo')) {
                require_once pearl_path('db.php');
            }

            $table = (string) (cache_config('database')['table'] ?? 'cache');
            $stmt = pdo()->prepare("SELECT value, expiration FROM {$table} WHERE `key` = :key");
            $stmt->execute([':key' => $key]);
            $row = $stmt->fetch(\PDO::FETCH_ASSOC);

            if (!$row) {
                return $default;
            }

            $now = time();
            $expiration = (int) $row['expiration'];

            if ($expiration > 0 && $expiration < $now) {
                // Expired row — lazy prune
                $del = pdo()->prepare("DELETE FROM {$table} WHERE `key` = :key");
                $del->execute([':key' => $key]);
                return $default;
            }

            $val = @unserialize((string) $row['value']);
            return $val !== false || $row['value'] === serialize(false) ? $val : $default;
        }

        if ($driver === 'redis') {
            if (!extension_loaded('redis')) {
                throw new RuntimeException('PHP Redis extension (ext-redis) is required for CACHE_DRIVER=redis.');
            }

            $cfg = (array) cache_config('redis', []);
            $redis = new \Redis();
            $redis->connect((string) ($cfg['host'] ?? '127.0.0.1'), (int) ($cfg['port'] ?? 6379));
            if (!empty($cfg['password'])) {
                $redis->auth((string) $cfg['password']);
            }
            if (isset($cfg['database'])) {
                $redis->select((int) $cfg['database']);
            }

            $prefix = (string) ($cfg['prefix'] ?? 'pearl_cache:');
            $raw = $redis->get($prefix . $key);

            if ($raw === false) {
                return $default;
            }

            $val = @unserialize((string) $raw);
            return $val !== false || $raw === serialize(false) ? $val : $default;
        }

        throw new InvalidArgumentException("Unsupported cache driver: '{$driver}'");
    }
}

/**
 * Store an item in the cache with a specified TTL in seconds.
 */
if (!function_exists('cache_set')) {
    function cache_set(string $key, mixed $value, ?int $ttl = null, ?string $driver = null): bool
    {
        $driver ??= (string) cache_config('driver', 'file');
        $defaultTtl = (int) cache_config('ttl', 3600);
        $effectiveTtl = $ttl ?? $defaultTtl;

        // 0 means no expiration (forever); positive means relative future; negative means in the past (expired)
        $expiration = ($effectiveTtl === 0 ? 0 : time() + $effectiveTtl);

        if ($driver === 'file') {
            cache_ensure_dir();
            $path = cache_file_path($key);

            $envelope = [
                'expiration' => $expiration,
                'value' => $value,
            ];

            $payload = serialize($envelope);
            $tmpPath = $path . '.' . bin2hex(random_bytes(6)) . '.tmp';

            if (file_put_contents($tmpPath, $payload, LOCK_EX) === false) {
                return false;
            }

            return @rename($tmpPath, $path);
        }

        if ($driver === 'database') {
            if (!function_exists('pdo')) {
                require_once pearl_path('db.php');
            }

            $table = (string) (cache_config('database')['table'] ?? 'cache');
            $serialized = serialize($value);

            $stmt = pdo()->prepare(
                "INSERT INTO {$table} (`key`, `value`, `expiration`) " .
                "VALUES (:key, :value, :expiration) " .
                "ON DUPLICATE KEY UPDATE `value` = VALUES(`value`), `expiration` = VALUES(`expiration`)"
            );

            return $stmt->execute([
                ':key' => $key,
                ':value' => $serialized,
                ':expiration' => $expiration,
            ]);
        }

        if ($driver === 'redis') {
            if (!extension_loaded('redis')) {
                throw new RuntimeException('PHP Redis extension (ext-redis) is required for CACHE_DRIVER=redis.');
            }

            $cfg = (array) cache_config('redis', []);
            $redis = new \Redis();
            $redis->connect((string) ($cfg['host'] ?? '127.0.0.1'), (int) ($cfg['port'] ?? 6379));
            if (!empty($cfg['password'])) {
                $redis->auth((string) $cfg['password']);
            }
            if (isset($cfg['database'])) {
                $redis->select((int) $cfg['database']);
            }

            $prefix = (string) ($cfg['prefix'] ?? 'pearl_cache:');
            $serialized = serialize($value);

            if ($ttl > 0) {
                return (bool) $redis->setex($prefix . $key, $ttl, $serialized);
            }

            return (bool) $redis->set($prefix . $key, $serialized);
        }

        throw new InvalidArgumentException("Unsupported cache driver: '{$driver}'");
    }
}

/**
 * Determine if an item exists in the cache and has not expired.
 */
if (!function_exists('cache_has')) {
    function cache_has(string $key, ?string $driver = null): bool
    {
        $sentinel = new \stdClass();
        return cache_get($key, $sentinel, $driver) !== $sentinel;
    }
}

/**
 * Remove an item from the cache.
 */
if (!function_exists('cache_forget')) {
    function cache_forget(string $key, ?string $driver = null): bool
    {
        $driver ??= (string) cache_config('driver', 'file');

        if ($driver === 'file') {
            $path = cache_file_path($key);
            if (is_file($path)) {
                return @unlink($path);
            }
            return true;
        }

        if ($driver === 'database') {
            if (!function_exists('pdo')) {
                require_once pearl_path('db.php');
            }

            $table = (string) (cache_config('database')['table'] ?? 'cache');
            $stmt = pdo()->prepare("DELETE FROM {$table} WHERE `key` = :key");
            return $stmt->execute([':key' => $key]);
        }

        if ($driver === 'redis') {
            if (!extension_loaded('redis')) {
                throw new RuntimeException('PHP Redis extension (ext-redis) is required for CACHE_DRIVER=redis.');
            }

            $cfg = (array) cache_config('redis', []);
            $redis = new \Redis();
            $redis->connect((string) ($cfg['host'] ?? '127.0.0.1'), (int) ($cfg['port'] ?? 6379));
            if (!empty($cfg['password'])) {
                $redis->auth((string) $cfg['password']);
            }
            if (isset($cfg['database'])) {
                $redis->select((int) $cfg['database']);
            }

            $prefix = (string) ($cfg['prefix'] ?? 'pearl_cache:');
            return $redis->del($prefix . $key) > 0;
        }

        throw new InvalidArgumentException("Unsupported cache driver: '{$driver}'");
    }
}

/**
 * Get an item from the cache, or execute the given Closure and store the result.
 */
if (!function_exists('cache_remember')) {
    function cache_remember(string $key, int $ttl, callable $callback, ?string $driver = null): mixed
    {
        $sentinel = new \stdClass();
        $cached = cache_get($key, $sentinel, $driver);

        if ($cached !== $sentinel) {
            return $cached;
        }

        $value = $callback();
        cache_set($key, $value, $ttl, $driver);

        return $value;
    }
}

/**
 * Flush all items from the cache for the active driver.
 */
if (!function_exists('cache_flush')) {
    function cache_flush(?string $driver = null): bool
    {
        $driver ??= (string) cache_config('driver', 'file');

        if ($driver === 'file') {
            $dir = storage_path('cache');
            if (!is_dir($dir)) {
                return true;
            }

            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::CHILD_FIRST
            );

            foreach ($iterator as $item) {
                if ($item->isFile()) {
                    @unlink($item->getRealPath());
                } elseif ($item->isDir()) {
                    @rmdir($item->getRealPath());
                }
            }

            return true;
        }

        if ($driver === 'database') {
            if (!function_exists('pdo')) {
                require_once pearl_path('db.php');
            }

            $table = (string) (cache_config('database')['table'] ?? 'cache');
            pdo()->exec("TRUNCATE TABLE {$table}");
            return true;
        }

        if ($driver === 'redis') {
            if (!extension_loaded('redis')) {
                throw new RuntimeException('PHP Redis extension (ext-redis) is required for CACHE_DRIVER=redis.');
            }

            $cfg = (array) cache_config('redis', []);
            $redis = new \Redis();
            $redis->connect((string) ($cfg['host'] ?? '127.0.0.1'), (int) ($cfg['port'] ?? 6379));
            if (!empty($cfg['password'])) {
                $redis->auth((string) $cfg['password']);
            }
            if (isset($cfg['database'])) {
                $redis->select((int) $cfg['database']);
            }

            $prefix = (string) ($cfg['prefix'] ?? 'pearl_cache:');
            $keys = $redis->keys($prefix . '*');

            if (!empty($keys)) {
                $redis->del($keys);
            }

            return true;
        }

        throw new InvalidArgumentException("Unsupported cache driver: '{$driver}'");
    }
}
