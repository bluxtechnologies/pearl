<?php
/**
 * Pearl Framework — Rate Limiting
 *
 * File-backed procedural rate limiter. Stores attempt counts and expiry
 * timestamps in storage/rate_limits/ using atomic file locks (flock).
 *
 * Zero external infrastructure required out of the box — matches
 * Pearl's Layer 3 file-storage default philosophy (§9, §12).
 *
 * Depends on: paths.php (storage_path).
 *
 * See pearl/FUNCTIONS.md for the ownership registry.
 */

declare(strict_types=1);

if (!function_exists('storage_path')) {
    throw new RuntimeException('pearl/paths.php must be required before pearl/rate_limit.php.');
}

/**
 * Ensure storage/rate_limits directory exists.
 */
if (!function_exists('rate_limit_ensure_dir')) {
    function rate_limit_ensure_dir(): string
    {
        $dir = storage_path('rate_limits');
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        return $dir;
    }
}

/**
 * Returns the absolute storage path for a given rate limit key.
 */
if (!function_exists('rate_limit_file_path')) {
    function rate_limit_file_path(string $key): string
    {
        $hash = hash('sha256', $key);
        return rate_limit_ensure_dir() . '/' . $hash . '.json';
    }
}

/**
 * Check whether a given key has exceeded $maxAttempts within the decay window.
 * Returns true if the action is allowed, false if rate limited.
 */
if (!function_exists('rate_limit_check')) {
    function rate_limit_check(string $key, int $maxAttempts, int $decaySeconds = 60): bool
    {
        $file = rate_limit_file_path($key);

        if (!is_file($file)) {
            return true;
        }

        $fp = fopen($file, 'c+');
        if ($fp === false) {
            return true;
        }

        flock($fp, LOCK_SH);
        $content = stream_get_contents($fp);
        flock($fp, LOCK_UN);
        fclose($fp);

        if ($content === false || $content === '') {
            return true;
        }

        $data = json_decode($content, true);
        if (!is_array($data) || !isset($data['hits'], $data['expires_at'])) {
            return true;
        }

        $now = time();
        if ($now >= (int) $data['expires_at']) {
            @unlink($file);
            return true;
        }

        return (int) $data['hits'] < $maxAttempts;
    }
}

/**
 * Increment the hit counter for a given key.
 * Returns the new hit count.
 */
if (!function_exists('rate_limit_hit')) {
    function rate_limit_hit(string $key, int $decaySeconds = 60): int
    {
        $file = rate_limit_file_path($key);
        $fp = fopen($file, 'c+');

        if ($fp === false) {
            return 1;
        }

        flock($fp, LOCK_EX);

        $content = stream_get_contents($fp);
        $data = ($content !== false && $content !== '') ? json_decode($content, true) : null;
        $now = time();

        if (!is_array($data) || !isset($data['hits'], $data['expires_at']) || $now >= (int) $data['expires_at']) {
            $data = [
                'hits' => 1,
                'expires_at' => $now + $decaySeconds,
            ];
        } else {
            $data['hits'] = (int) $data['hits'] + 1;
        }

        ftruncate($fp, 0);
        rewind($fp);
        fwrite($fp, json_encode($data, JSON_UNESCAPED_SLASHES));
        fflush($fp);

        flock($fp, LOCK_UN);
        fclose($fp);

        return (int) $data['hits'];
    }
}

/**
 * Clear/reset the rate limiter state for a given key (e.g. after successful login).
 */
if (!function_exists('rate_limit_clear')) {
    function rate_limit_clear(string $key): void
    {
        $file = rate_limit_file_path($key);
        if (is_file($file)) {
            @unlink($file);
        }
    }
}

/**
 * Returns the number of seconds until a locked key becomes available again.
 * Returns 0 if not currently rate limited or expired.
 */
if (!function_exists('rate_limit_available_in')) {
    function rate_limit_available_in(string $key): int
    {
        $file = rate_limit_file_path($key);
        if (!is_file($file)) {
            return 0;
        }

        $content = @file_get_contents($file);
        if ($content === false || $content === '') {
            return 0;
        }

        $data = json_decode($content, true);
        if (!is_array($data) || !isset($data['expires_at'])) {
            return 0;
        }

        $now = time();
        $remaining = (int) $data['expires_at'] - $now;
        return max(0, $remaining);
    }
}

/**
 * Returns how many attempts remain for a given key.
 */
if (!function_exists('rate_limit_remaining')) {
    function rate_limit_remaining(string $key, int $maxAttempts): int
    {
        $file = rate_limit_file_path($key);
        if (!is_file($file)) {
            return $maxAttempts;
        }

        $content = @file_get_contents($file);
        if ($content === false || $content === '') {
            return $maxAttempts;
        }

        $data = json_decode($content, true);
        if (!is_array($data) || !isset($data['hits'], $data['expires_at'])) {
            return $maxAttempts;
        }

        if (time() >= (int) $data['expires_at']) {
            return $maxAttempts;
        }

        return max(0, $maxAttempts - (int) $data['hits']);
    }
}
