<?php
/**
 * Pearl Framework — CLI: session:clear
 *
 * Handles: php bin/pearl session:clear
 *
 * Deletes all session files under storage/sessions/. Useful after
 * changing AUTH_MODE (dual/single) during development, since existing
 * session cookies won't match the new session name/structure, or
 * simply to force everyone logged out.
 *
 * Returns a callable: function(array $args): int
 */

declare(strict_types=1);

if (!function_exists('storage_path')) {
    throw new RuntimeException('pearl/paths.php must be required before session-clear.php.');
}

return function (array $args): int {
    $config = function_exists('session_config') ? session_config() : ['driver' => 'file', 'table' => 'sessions'];
    $driver = $config['driver'] ?? 'file';

    $cleared = 0;

    // Clear file sessions if directory exists
    $dir = storage_path('sessions');
    if (is_dir($dir)) {
        $files = glob($dir . '/*') ?: [];
        foreach ($files as $file) {
            if (is_file($file)) {
                unlink($file);
                $cleared++;
            }
        }
        if ($driver === 'file') {
            echo "Cleared {$cleared} session file(s) from storage/sessions/." . PHP_EOL;
        }
    }

    // Clear database sessions if configured or table exists
    if ($driver === 'database' || in_array('--all', $args, true)) {
        if (function_exists('pdo')) {
            try {
                $table = $config['table'] ?? 'sessions';
                $stmt = pdo()->prepare("DELETE FROM {$table}");
                $stmt->execute();
                $dbCount = $stmt->rowCount();
                echo "Cleared {$dbCount} session record(s) from database table '{$table}'." . PHP_EOL;
            } catch (Throwable $e) {
                echo "Database session clear skipped: " . $e->getMessage() . PHP_EOL;
            }
        }
    }

    // Clear Redis sessions if configured
    if ($driver === 'redis' || in_array('--all', $args, true)) {
        if (class_exists('Redis')) {
            try {
                $redis = new Redis();
                $rConf = $config['redis'] ?? [];
                $redis->connect(
                    (string) ($rConf['host'] ?? '127.0.0.1'),
                    (int) ($rConf['port'] ?? 6379),
                    (float) ($rConf['timeout'] ?? 2.0)
                );
                if (!empty($rConf['password'])) {
                    $redis->auth($rConf['password']);
                }
                if (isset($rConf['database'])) {
                    $redis->select((int) $rConf['database']);
                }
                $keys = $redis->keys('pearl_session:*');
                $rCount = 0;
                if (!empty($keys)) {
                    $rCount = $redis->del($keys);
                }
                echo "Cleared {$rCount} session key(s) from Redis." . PHP_EOL;
            } catch (Throwable $e) {
                echo "Redis session clear skipped: " . $e->getMessage() . PHP_EOL;
            }
        }
    }

    return 0;
};
