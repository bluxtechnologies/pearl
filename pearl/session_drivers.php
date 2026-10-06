<?php
/**
 * Pearl Framework — Session Drivers
 *
 * Implements SessionHandlerInterface drivers for Database (PDO) and Redis
 * session storage, respecting strict dual-context isolation (§3 Invariant 7).
 *
 * User and admin sessions are isolated by context namespace across all drivers.
 */

declare(strict_types=1);

/**
 * Database session save handler using PDO.
 */
class PearlDatabaseSessionHandler implements SessionHandlerInterface
{
    protected PDO $pdo;
    protected string $table;
    protected string $context;

    public function __construct(PDO $pdo, string $table = 'sessions', string $context = 'user')
    {
        $this->pdo = $pdo;
        $this->table = $table;
        $this->context = $context;
    }

    public function open(string $path, string $name): bool
    {
        return true;
    }

    public function close(): bool
    {
        return true;
    }

    public function read(string $id): string|false
    {
        $stmt = $this->pdo->prepare(
            "SELECT payload FROM {$this->table} WHERE id = :id AND context = :context LIMIT 1"
        );
        $stmt->execute([
            'id' => $id,
            'context' => $this->context,
        ]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row !== false && isset($row['payload'])) {
            return (string) $row['payload'];
        }

        return '';
    }

    public function write(string $id, string $data): bool
    {
        $ip = function_exists('request_ip') ? request_ip() : ($_SERVER['REMOTE_ADDR'] ?? null);
        $ua = $_SERVER['HTTP_USER_AGENT'] ?? null;
        $now = time();

        $sql = "INSERT INTO {$this->table} (id, context, payload, last_activity, ip_address, user_agent)
                VALUES (:id, :context, :payload, :last_activity, :ip, :ua)
                ON DUPLICATE KEY UPDATE
                    payload = VALUES(payload),
                    last_activity = VALUES(last_activity),
                    ip_address = VALUES(ip_address),
                    user_agent = VALUES(user_agent)";

        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([
            'id' => $id,
            'context' => $this->context,
            'payload' => $data,
            'last_activity' => $now,
            'ip' => $ip,
            'ua' => $ua,
        ]);
    }

    public function destroy(string $id): bool
    {
        $stmt = $this->pdo->prepare(
            "DELETE FROM {$this->table} WHERE id = :id AND context = :context"
        );
        return $stmt->execute([
            'id' => $id,
            'context' => $this->context,
        ]);
    }

    public function gc(int $max_lifetime): int|false
    {
        $cutoff = time() - $max_lifetime;
        $stmt = $this->pdo->prepare("DELETE FROM {$this->table} WHERE last_activity < :cutoff");
        $stmt->execute(['cutoff' => $cutoff]);
        return $stmt->rowCount();
    }
}

/**
 * Redis session save handler with native key TTLs.
 */
class PearlRedisSessionHandler implements SessionHandlerInterface
{
    protected mixed $redis;
    protected string $context;
    protected int $lifetime;
    protected string $prefix;

    public function __construct(mixed $redis, string $context = 'user', int $lifetime = 7200)
    {
        $this->redis = $redis;
        $this->context = $context;
        $this->lifetime = max(60, $lifetime);
        $this->prefix = "pearl_session:{$context}:";
    }

    public function open(string $path, string $name): bool
    {
        return true;
    }

    public function close(): bool
    {
        return true;
    }

    public function read(string $id): string|false
    {
        $data = $this->redis->get($this->prefix . $id);
        if ($data !== false && $data !== null) {
            return (string) $data;
        }
        return '';
    }

    public function write(string $id, string $data): bool
    {
        return (bool) $this->redis->setex($this->prefix . $id, $this->lifetime, $data);
    }

    public function destroy(string $id): bool
    {
        $this->redis->del($this->prefix . $id);
        return true;
    }

    public function gc(int $max_lifetime): int|false
    {
        // Redis natively handles key expiration via SETEX TTL
        return 0;
    }
}
