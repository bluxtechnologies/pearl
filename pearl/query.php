<?php
/**
 * Pearl Framework — Query Builder
 *
 * Public interface is procedural (query('table')->where(...)->get()),
 * matching Pearl's convention. PearlQuery is an internal implementation
 * detail — wire/ modules never instantiate it directly, only through
 * the query() entry function below. Chaining requires an object; this
 * is the one deliberate, contained exception to "procedural not OOP,"
 * not a drift toward class-based architecture generally.
 *
 * Fully supports multi-engine SQL quotation via db_quote_identifier()
 * (MySQL, PostgreSQL, SQLite) and prevents keyword collisions (§10, §15).
 *
 * Depends on: pearl/db.php (pdo, db_quote_identifier, pearl_validate_identifier).
 *
 * See pearl/FUNCTIONS.md for the ownership registry.
 */

declare(strict_types=1);

if (!function_exists('pdo')) {
    throw new RuntimeException('pearl/db.php must be required before pearl/query.php.');
}

/**
 * Validate a table or column identifier. Allows an optional
 * "table.column" dotted form.
 */
if (!function_exists('pearl_validate_identifier')) {
    function pearl_validate_identifier(string $identifier): string
    {
        if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*(\.[A-Za-z_][A-Za-z0-9_]*)?$/', $identifier)) {
            throw new \InvalidArgumentException("Invalid identifier: {$identifier}");
        }

        return $identifier;
    }
}

/**
 * Internal query builder. Not part of the public API surface —
 * always obtain an instance via query(), never `new PearlQuery(...)`.
 */
final class PearlQuery
{
    private string $table;
    private array $selects = ['*'];
    private array $joins = [];
    private array $wheres = [];
    private array $bindings = [];
    private array $orders = [];
    private ?int $limitValue = null;
    private ?int $offsetValue = null;

    public function __construct(string $table)
    {
        $this->table = pearl_validate_identifier($table);
    }

    public function select(string ...$columns): self
    {
        $this->selects = array_map('pearl_validate_identifier', $columns);
        return $this;
    }

    public function join(string $table, string $first, string $operator, string $second): self
    {
        pearl_validate_identifier($table);
        pearl_validate_identifier($first);
        pearl_validate_identifier($second);

        $allowedOperators = ['=', '!=', '<', '<=', '>', '>='];
        if (!in_array($operator, $allowedOperators, true)) {
            throw new \InvalidArgumentException("Invalid join operator: {$operator}");
        }

        $quotedTable = function_exists('db_quote_identifier') ? db_quote_identifier($table) : $table;
        $quotedFirst = function_exists('db_quote_identifier') ? db_quote_identifier($first) : $first;
        $quotedSecond = function_exists('db_quote_identifier') ? db_quote_identifier($second) : $second;

        $this->joins[] = "JOIN {$quotedTable} ON {$quotedFirst} {$operator} {$quotedSecond}";
        return $this;
    }

    public function where(string $column, string $operator, mixed $value): self
    {
        pearl_validate_identifier($column);

        $allowedOperators = ['=', '!=', '<', '<=', '>', '>=', 'LIKE'];
        if (!in_array(strtoupper($operator), $allowedOperators, true)) {
            throw new \InvalidArgumentException("Invalid where operator: {$operator}");
        }

        $col = function_exists('db_quote_identifier') ? db_quote_identifier($column) : $column;
        $this->wheres[] = "{$col} {$operator} ?";
        $this->bindings[] = $value;
        return $this;
    }

    public function whereIn(string $column, array $values): self
    {
        pearl_validate_identifier($column);

        if ($values === []) {
            // Empty IN () is invalid SQL and semantically "match nothing".
            $this->wheres[] = '1 = 0';
            return $this;
        }

        $col = function_exists('db_quote_identifier') ? db_quote_identifier($column) : $column;
        $placeholders = implode(', ', array_fill(0, count($values), '?'));
        $this->wheres[] = "{$col} IN ({$placeholders})";
        array_push($this->bindings, ...array_values($values));
        return $this;
    }

    /**
     * Add an ORDER BY clause. Accumulates across multiple calls.
     */
    public function order(string $column, string $direction = 'asc'): self
    {
        pearl_validate_identifier($column);

        $col = function_exists('db_quote_identifier') ? db_quote_identifier($column) : $column;
        $direction = strtoupper($direction) === 'DESC' ? 'DESC' : 'ASC';
        $this->orders[] = "{$col} {$direction}";
        return $this;
    }

    public function limit(int $count): self
    {
        $this->limitValue = $count;
        return $this;
    }

    public function offset(int $count): self
    {
        $this->offsetValue = $count;
        return $this;
    }

    private function buildSelectSql(): string
    {
        $quotedSelects = array_map(
            static fn(string $c): string => function_exists('db_quote_identifier') ? db_quote_identifier($c) : $c,
            $this->selects
        );
        $quotedTable = function_exists('db_quote_identifier') ? db_quote_identifier($this->table) : $this->table;

        $sql = 'SELECT ' . implode(', ', $quotedSelects) . " FROM {$quotedTable}";

        foreach ($this->joins as $join) {
            $sql .= " {$join}";
        }

        if ($this->wheres !== []) {
            $sql .= ' WHERE ' . implode(' AND ', $this->wheres);
        }

        if ($this->orders !== []) {
            $sql .= ' ORDER BY ' . implode(', ', $this->orders);
        }

        if ($this->limitValue !== null) {
            $sql .= " LIMIT {$this->limitValue}";
        }

        if ($this->offsetValue !== null) {
            $sql .= " OFFSET {$this->offsetValue}";
        }

        return $sql;
    }

    /**
     * The SQL this builder would currently execute.
     */
    public function toSql(): string
    {
        return $this->buildSelectSql();
    }

    public function get(): array
    {
        $stmt = pdo()->prepare($this->buildSelectSql());
        $stmt->execute($this->bindings);
        return $stmt->fetchAll();
    }

    public function first(): ?array
    {
        $rows = $this->limit(1)->get();
        return $rows[0] ?? null;
    }

    public function count(): int
    {
        $originalSelects = $this->selects;
        $this->selects = ['COUNT(*) AS aggregate'];
        $sql = $this->buildSelectSql();
        $this->selects = $originalSelects;

        $stmt = pdo()->prepare($sql);
        $stmt->execute($this->bindings);
        $row = $stmt->fetch();

        return (int) ($row['aggregate'] ?? 0);
    }

    /**
     * Insert a row. Returns the new row's auto-increment id as a string.
     */
    public function insert(array $data): string
    {
        $columns = array_map('pearl_validate_identifier', array_keys($data));
        $quotedCols = array_map(
            static fn(string $c): string => function_exists('db_quote_identifier') ? db_quote_identifier($c) : $c,
            $columns
        );
        $placeholders = implode(', ', array_fill(0, count($columns), '?'));
        $quotedTable = function_exists('db_quote_identifier') ? db_quote_identifier($this->table) : $this->table;

        $sql = "INSERT INTO {$quotedTable} (" . implode(', ', $quotedCols) . ") VALUES ({$placeholders})";

        $stmt = pdo()->prepare($sql);
        $stmt->execute(array_values($data));

        return (string) pdo()->lastInsertId();
    }

    /**
     * Update rows matching the current where() conditions. Returns affected rows count.
     */
    public function update(array $data): int
    {
        if ($this->wheres === []) {
            throw new \RuntimeException(
                'update() requires at least one where() clause. ' .
                'Use a where() that matches all rows explicitly if that is really intended.'
            );
        }

        $setClauses = array_map(
            static function (string $column): string {
                pearl_validate_identifier($column);
                $col = function_exists('db_quote_identifier') ? db_quote_identifier($column) : $column;
                return "{$col} = ?";
            },
            array_keys($data)
        );

        $quotedTable = function_exists('db_quote_identifier') ? db_quote_identifier($this->table) : $this->table;
        $sql = "UPDATE {$quotedTable} SET " . implode(', ', $setClauses)
            . ' WHERE ' . implode(' AND ', $this->wheres);

        $stmt = pdo()->prepare($sql);
        $stmt->execute([...array_values($data), ...$this->bindings]);

        return $stmt->rowCount();
    }

    /**
     * Delete rows matching the current where() conditions.
     */
    public function delete(): int
    {
        if ($this->wheres === []) {
            throw new \RuntimeException(
                'delete() requires at least one where() clause. ' .
                'Use a where() that matches all rows explicitly if that is really intended.'
            );
        }

        $quotedTable = function_exists('db_quote_identifier') ? db_quote_identifier($this->table) : $this->table;
        $sql = "DELETE FROM {$quotedTable} WHERE " . implode(' AND ', $this->wheres);

        $stmt = pdo()->prepare($sql);
        $stmt->execute($this->bindings);

        return $stmt->rowCount();
    }
}

/**
 * Entry point for the query builder.
 */
if (!function_exists('query')) {
    function query(string $table): PearlQuery
    {
        return new PearlQuery($table);
    }
}
