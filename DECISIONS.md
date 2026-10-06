# PEARL Architectural Decision Records (ADRs)

This document records the foundational architectural decisions underpinning the **PEARL Framework**. Every decision documented here is binding, deliberate, and aligned with `PEARL_ARCHITECTURE.md`.

---

## ADR 001: Procedural-First Architecture & The Single Exception Rule

### Status
Accepted & Enforced

### Context
Modern PHP frameworks rely heavily on deeply layered Object-Oriented Programming (OOP), Dependency Injection (DI) containers, autowiring, reflection-based routing, service providers, and dynamic facade proxies. While flexible, these abstractions introduce massive mental overhead, complex stack traces, performance latency, and hidden execution flows.

### Decision
PEARL is strictly **procedural-first**. Pure functions, plain PHP arrays, and explicit file includes are the non-negotiable default.
- **Classes are strictly forbidden** across the framework engine and application code, with exactly two justified exceptions:
  1. `PearlQuery`: A lightweight fluent query builder where method chaining provides undeniable SQL construction safety and developer ergonomics over nested array configuration.
  2. Native PHP engine interfaces: `SessionHandlerInterface` implementations (`PearlDatabaseSessionHandler`, `PearlRedisSessionHandler`), mandated by PHP's internal engine for custom session backends.
- Zero DI containers, zero autowiring, zero reflection, zero magic methods (`__call`, `__get`), and zero magic lifecycle hooks.

### Consequences
- **Positive:** Stack traces are shallow, direct, and completely readable. Every function call can be statically traced in an IDE without guessing container bindings. Zero instantiation overhead.
- **Negative:** Developers accustomed to class-based inheritance must structure domain logic in procedural files and modules.

---

## ADR 002: Dual Authentication Context Isolation (Users vs. Admins)

### Status
Accepted & Enforced

### Context
Standard web applications frequently merge user and administrative accounts into a single database table (e.g. `users`) distinguished by a `role`, `is_admin`, or `permission` column. A single missing condition (`WHERE role = 'admin'`) or faulty mass-assignment bug immediately creates a catastrophic privilege escalation vulnerability.

### Decision
PEARL enforces **absolute, permanent separation** between authentication contexts (`users` and `admins`):
- Dedicated, independent database tables: `users` and `admins`.
- Dedicated session cookie names: `pearl_user_session` vs. `pearl_admin_session`.
- Dedicated session data keys: `$_SESSION['auth_user_id']` vs. `$_SESSION['auth_admin_id']`.
- Dedicated API token scopes: `api_tokens.tokenable_type` set to `'user'` or `'admin'`.
- Dedicated middleware/guards: `auth_require_login()` vs. `auth_require_admin()`.

Under no circumstances may `users` and `admins` be collapsed into a single table with a role column.

### Consequences
- **Positive:** Privilege escalation via column overwrite or missing WHERE clauses is structurally impossible. Admin and user sessions can exist concurrently on the same browser without collision or bleed.
- **Negative:** Authentication logic must support explicit context parameters rather than assuming a single global user entity.

---

## ADR 003: Single DDL Statement Per Migration File

### Status
Accepted & Enforced

### Context
In MySQL (and MariaDB), Data Definition Language (DDL) statements (`CREATE TABLE`, `ALTER TABLE`, `DROP TABLE`) trigger an immediate, implicit transaction commit. If a migration file contains multiple DDL statements and the second one fails, the first statement has already been committed and cannot be rolled back inside a database transaction, leaving the database in an inconsistent, half-migrated state.

### Decision
Every PEARL migration file must contain **strictly one DDL statement per migration file** in its `up()` function, and strictly one reverse statement in its `down()` function.
- Multi-DDL migrations are explicitly prohibited.
- Migration runner tracks execution per file in the `migrations` table.
- Supports both 3-digit numbered files (`001_create_users_table.php`) and timestamped filenames (`make:migration --timestamp`).
- Rollback execution validates file presence and schema reversibility, defending against orphaned records.

### Consequences
- **Positive:** Database migrations are atomic. If a migration fails, the database is never left in a partially mutated, unrecoverable state.
- **Negative:** Schema changes requiring multiple tables or alterations must be split across multiple migration files.

---

## ADR 004: File-Based Longest-Prefix Routing with Fat Domain Modules

### Status
Accepted & Enforced

### Context
Large frameworks rely on centralized route files mapping regexes or attributes to controller actions. This requires maintaining thousands of route definitions and micro-controllers.

### Decision
PEARL uses **file-based, longest-prefix matching** into fat domain modules inside `wire/`:
- `GET /` $\rightarrow$ `wire/home.php`
- `GET /login` $\rightarrow$ `wire/login.php`
- `GET /admin/dashboard` $\rightarrow$ `wire/admin/dashboard.php`
- Dynamic arguments pass naturally: `/users/42/edit` maps to `wire/users.php` with `$params = ['42', 'edit']`.
- Fat domain modules handle both GET (presentation) and POST/PUT/DELETE (mutation) using PHP's native `match(request_method())`.

### Consequences
- **Positive:** Zero route registration boilerplate. Adding a new route is as simple as creating a file. Route discovery is instant and follows the filesystem.
- **Negative:** Complex regex routing patterns must be parsed inside the domain file via `$params`.

---

## ADR 005: Multi-Engine Database Abstraction via Portable PDO Helpers

### Status
Accepted & Enforced

### Context
Many applications need flexibility to run on MySQL, PostgreSQL, or SQLite. However, heavy Object-Relational Mappers (ORMs) incur massive CPU and memory penalties and hide raw SQL execution.

### Decision
PEARL provides lightweight, multi-engine database portability over raw PDO:
- Standardized driver configuration in `config/database.php` supporting `'mysql'`, `'pgsql'`, and `'sqlite'`.
- Portable identifier quoting via `db_quote_identifier()` (backticks for MySQL, double-quotes for PostgreSQL and SQLite).
- Engine-agnostic schema inspection via `db_table_exists()` utilizing SQLite `sqlite_master`, PostgreSQL `information_schema.tables`, and MySQL `INFORMATION_SCHEMA.TABLES`.
- Explicit SQL binding guards ensuring SQL injection immunity across all engines.

### Consequences
- **Positive:** Zero ORM overhead. Developers write real SQL with safe query parameters across MySQL, PostgreSQL, and SQLite.
- **Negative:** Advanced engine-specific proprietary features (e.g., MySQL `ON DUPLICATE KEY UPDATE` vs. PostgreSQL `ON CONFLICT DO UPDATE`) must be written conditionally or handled cleanly in application code.

---

## ADR 006: Multi-Driver Session Architecture with Isolation Guard

### Status
Accepted & Enforced

### Context
Different deployment environments require different session storage backends: local development fits filesystem sessions, clustered servers require centralized MySQL/PostgreSQL databases, and high-throughput environments require Redis. In dual-context systems, session storage must prevent cross-context token reuse.

### Decision
PEARL provides multi-driver session support (`file`, `database`, `redis`):
- Custom session drivers implement PHP's native `SessionHandlerInterface`.
- In the `database` driver, sessions are keyed by a composite primary key `(id, context)` in the `sessions` table, preventing user and admin session collisions.
- Session garbage collection (`gc()`) and CLI maintenance (`session:clear`) prune expired entries based on UTC epoch timestamps.

### Consequences
- **Positive:** Seamless horizontal scaling by changing `SESSION_DRIVER` in `.env`. Strict prevention of cross-context session collision.
- **Negative:** When switching to `database` driver, the `sessions` table migration must be executed.

---

## ADR 007: Atomic Queue Claiming, Exponential Backoff, and Dead-Letter Isolation

### Status
Accepted & Enforced

### Context
Asynchronous job processing often suffers from worker race conditions (two workers grabbing the same job simultaneously) and catastrophic job loss upon repeated failure.

### Decision
PEARL implements robust, race-condition-free background queues across multiple drivers (`file`, `sync`, `database`, `redis`):
- **File Driver:** Atomic claiming using POSIX/filesystem `rename()` from `storage/queue/pending/` to `storage/queue/reserved/`.
- **Database Driver:** Atomic row-level locking via `SELECT ... FOR UPDATE` with immediate transition to `status = 'reserved'`.
- **Exponential Backoff:** Failed jobs record failure count and calculate delay before releasing back to pending status.
- **Dead-Letter Storage:** Jobs exceeding `max_attempts` are permanently preserved in `storage/queue/failed/` or `queue_failed_jobs` table for forensic analysis.

### Consequences
- **Positive:** Zero job duplication under high concurrency. Resilient failure recovery without lost tasks.
- **Negative:** Persistent workers (`notify:work`) must be monitored via system daemons (systemd, Supervisor).

---

## ADR 008: Zero-Dependency Raw Socket SMTP Client with RFC Compliance

### Status
Accepted & Enforced

### Context
Third-party mail libraries (e.g. PHPMailer, Symfony Mailer) pull in dozens of nested vendor dependencies and class hierarchies for simple transactional SMTP delivery.

### Decision
PEARL includes an autonomous, zero-dependency SMTP client (`mail_send_via_smtp()`):
- Communicates directly over raw network sockets (`fsockopen` / `stream_socket_client`).
- Implements full RFC 5321 conversation flow: `EHLO`, `STARTTLS` cryptographic upgrade, `AUTH LOGIN` (Base64), `MAIL FROM`, `RCPT TO`, and `DATA`.
- Implements mandatory RFC 5321 dot-stuffing (replacing leading dots with double dots to prevent premature mail termination).
- Supports templated, responsive HTML layout rendering (`ink/mail/layout.php`) alongside plain text fallback.

### Consequences
- **Positive:** Zero external dependencies. Rapid execution with minimal memory footprint.
- **Negative:** Advanced complex MIME structures (e.g., S/MIME encryption, multipart alternatives with custom boundaries) require manual header formatting.

---

## ADR 009: High-Performance Multi-Driver Cache with Sharded Storage

### Status
Accepted & Enforced

### Context
Application caching requires diverse drivers (`file`, `database`, `redis`) without polluting global application state or suffering from lock contention and filesystem degradation.

### Decision
PEARL implements a lightweight, multi-driver caching subsystem (`cache.php`):
- **File Driver:** Keys are hashed via SHA-256 and stored in a 2-character hex sharded folder structure (`storage/cache/ab/cd...`) to eliminate directory inode saturation.
- File writes use atomic temporary file replacement with exclusive file locking (`LOCK_EX`).
- Lazy expiration pruning removes stale files on read access, preventing filesystem bloat.
- Provides unified `cache_get()`, `cache_set()`, `cache_has()`, `cache_delete()`, and `cache_remember()` helpers.

### Consequences
- **Positive:** Lightning fast caching without memory leaks or OS file limits.
- **Negative:** File cache is local to the server node; multi-node architectures must use Redis or Database drivers.

---

## ADR 010: Bearer Token API Layer with Abilities Scoping and Dual-Context Separation

### Status
Accepted & Enforced

### Context
Modern web applications must serve both server-rendered web clients and mobile/SPA clients via APIs without duplicating domain authorization or leaking session state.

### Decision
PEARL includes a stateless API token authentication engine:
- Token strings use a human-readable prefix and secure entropy: `ptk_<random_bytes>`.
- Only SHA-256 hashes of tokens are persisted in `api_tokens` table; plain text tokens are only displayed once upon generation.
- Dual context separation: `tokenable_type` binds tokens explicitly to `'user'` or `'admin'`.
- Fine-grained ability validation: `api_token_can($token, 'posts:write')` with wildcard `'*'` support.
- Automated API content-negotiation: all `/api/*` endpoints and JSON requests receive RFC-compliant JSON responses (`response_api_success()`, `response_api_error()`) even on unhandled exceptions or 404s.

### Consequences
- **Positive:** Secure, fast stateless authentication with absolute token protection against database leakage.
- **Negative:** Stateful browser sessions and API tokens operate via separate authentication headers/cookies.
