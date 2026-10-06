# Pearl Function Ownership Registry

Pearl is procedural, not OOP — every global function lives in exactly
one file. This registry exists because of a recurring FluxPHP bug:
duplicate declarations of the same function name (`request_ip()`,
`csrf_token()`) across multiple core files, causing hard-to-diagnose
fatal errors depending on include order.

## Rule

**Before adding a new global function, check this file.** If the name
is taken, either reuse the existing function or pick a different name.
Every new global function gets a row added here in the same commit/
delivery that introduces it. No exceptions — this file must never
drift out of sync with the codebase.

All functions are wrapped in `if (!function_exists(...))` as a second
line of defense, but that only prevents a fatal crash — it does NOT
prevent silently picking up the wrong implementation if two files
define the same name. This registry is the source of truth; the
`function_exists` guards are a safety net, not a substitute.

## Naming convention

- Engine-level helpers: `pearl_*` (e.g. `pearl_path()`, `pearl_view()`)
- Path helpers: `{folder}_path()` (e.g. `wire_path()`, `view_path()`)
- Everything else: plain, descriptive, no prefix collisions with the
  above two patterns (e.g. `env()`, `csrf_token()`)

## Registry

| Function                        | Owning file              | Purpose                                                   |
|----------------------------------|---------------------------|------------------------------------------------------------|
| `pearl_load_env()`               | `pearl/bootstrap.php`     | Parses `.env` into `$_ENV` / `putenv()`                    |
| `env()`                          | `pearl/bootstrap.php`     | Reads an env value with type coercion + default            |
| `pearl_path()`                   | `pearl/paths.php`         | Path into `pearl/`                                         |
| `wire_path()`                    | `pearl/paths.php`         | Path into `wire/`                                          |
| `view_path()`                    | `pearl/paths.php`         | Path into `view/`                                          |
| `ink_path()`                     | `pearl/paths.php`         | Path into `ink/`                                           |
| `config_path()`                  | `pearl/paths.php`         | Path into `config/`                                        |
| `public_path()`                  | `pearl/paths.php`         | Path into `public/`                                        |
| `storage_path()`                 | `pearl/paths.php`         | Path into `storage/`                                       |
| `database_path()`                | `pearl/paths.php`         | Path into `database/`                                      |
| `pearl_clear_output_buffers()`   | `pearl/errors.php`        | Clears all open `ob_*` buffers before error rendering       |
| `pearl_log_error()`              | `pearl/errors.php`        | Appends a line to `storage/logs/error.log`                 |
| `pearl_render_error_page()`      | `pearl/errors.php`        | Renders `view/errors/{code}.php` or a minimal fallback      |
| `pearl_handle_exception()`       | `pearl/errors.php`        | `set_exception_handler` target                              |
| `pearl_handle_error()`           | `pearl/errors.php`        | `set_error_handler` target, converts to `ErrorException`    |
| `pearl_handle_shutdown()`        | `pearl/errors.php`        | `register_shutdown_function` target, catches fatal errors   |
| `pearl_register_error_handlers()`| `pearl/errors.php`        | Wires up the three handlers above                           |
| `request_method()`               | `pearl/request.php`       | Current HTTP method (respects `_method` override)           |
| `request_path()`                 | `pearl/request.php`       | Current URL path, normalized                                 |
| `request_input()`                | `pearl/request.php`       | Parsed body (JSON or form) as array                          |
| `request_query()`                | `pearl/request.php`       | Query string params as array                                 |
| `request_header()`               | `pearl/request.php`       | Read a single request header                                 |
| `request_ip()`                   | `pearl/request.php`       | Client IP (single owner — the old bug)                       |
| `response_status()`              | `pearl/response.php`      | Set the HTTP status code                                      |
| `response_json()`                | `pearl/response.php`      | Send a JSON response                                          |
| `response_redirect()`            | `pearl/response.php`      | Send a redirect response                                      |
| `response_text()`                | `pearl/response.php`      | Send a plain text response *(added beyond original reservation — flagged)* |
| `response_html()`                | `pearl/response.php`      | Send a raw HTML string response, e.g. HTMX partials *(added beyond original reservation — flagged)* |
| `pearl_render_file()`            | `pearl/view.php`          | Renders one template file in isolation, returns string *(internal helper, added beyond original reservation — flagged)* |
| `pearl_view()`                   | `pearl/view.php`          | Render a `view/` template with data, optional layout wrapping. Depends on `response_status()` from `pearl/response.php`. |
| `e()`                            | `pearl/view.php`          | HTML-escape a value for safe output in templates *(added beyond original reservation — flagged)* |
| `pearl_route_segments()`         | `pearl/router.php`        | Splits + validates a path into safe segments, blocks traversal *(internal helper, added beyond original reservation — flagged)* |
| `pearl_resolve_route()`          | `pearl/router.php`        | Resolves segments to a `wire/` file + remaining params *(internal helper, added beyond original reservation — flagged)* |
| `pearl_dispatch()`               | `pearl/router.php`        | Match request path to a `wire/` handler and hand off control        |
| `pdo()`                          | `pearl/db.php`            | Shared PDO connection singleton, pulled forward from Phase 3 because auth needs it |
| `session_detect_context()`       | `pearl/session.php`       | Derives 'user' vs 'admin' context from the request path (/admin prefix) |
| `session_cookie_name()`          | `pearl/session.php`       | Resolves cookie name for a context, respecting AUTH_MODE dual/single |
| `session_start_pearl()`          | `pearl/session.php`       | Starts the session with the correct name + secure cookie params, file-based storage under storage/sessions |
| `session_current_context()`      | `pearl/session.php`       | The context recorded on the active session (not re-derived from path) *(added beyond original reservation — flagged)* |
| `session_destroy_pearl()`        | `pearl/session.php`       | Fully destroys the current session (data + cookie) *(added beyond original reservation — flagged)* |
| `auth_table_for_context()`       | `pearl/auth.php`          | Resolves 'users' vs 'admins' table from context *(internal helper, added beyond original reservation — flagged)* |
| `auth_hash_password()`           | `pearl/auth.php`          | Hashes a plaintext password for storage *(added beyond original reservation — flagged)* |
| `auth_attempt()`                 | `pearl/auth.php`          | Verifies credentials against the context's table, returns row or null |
| `auth_login()`                   | `pearl/auth.php`          | Attempts login, persists auth id in session, regenerates session id |
| `auth_logout()`                  | `pearl/auth.php`          | Destroys the current context's session |
| `auth_check()`                   | `pearl/auth.php`          | Whether current context session is authenticated |
| `auth_user()`                    | `pearl/auth.php`          | Current authenticated user/admin row (password_hash stripped), cached per request |
| `auth_is_admin()`                | `pearl/rbac.php`          | True if current session is authenticated AND in admin context (minimal RBAC — no role column exists yet) |
| `auth_require_admin()`           | `pearl/rbac.php`          | Convenience: 403s and stops the request if not admin *(added beyond original reservation — flagged)* |
| `pearl_validate_identifier()`    | `pearl/query.php`         | Validates a table/column identifier (injection defense for interpolated SQL) *(internal helper)* |
| `query()`                        | `pearl/query.php`         | Entry point for the fluent query builder — the only procedural/public surface; returns a `PearlQuery` instance |

**Note:** `PearlQuery` (in `pearl/query.php`) is a class, not a global
function — the one deliberate exception to "procedural not OOP,"
needed for method chaining. It is never instantiated directly outside
`query()`. Its methods (`select`, `join`, `where`, `whereIn`, `order`,
`limit`, `offset`, `get`, `first`, `count`, `toSql`, `insert`,
`update`, `delete`) are class methods, not global functions, so they
don't need registry entries — but are documented here since they are
still part of the public API surface. `whereIn()`, `count()`, and
`toSql()` were added beyond the original file-order description, as
was a guard that throws if `update()`/`delete()` is called with no
`where()` clause — flagged as scope additions.

Phase 1 core files are now all reserved names implemented — no
outstanding reservations remain. Phase 2 (session/auth) will add its
own reservations here before implementation, same pattern.

## Phase 4 additions

| Function                | File               | Purpose                                                        |
|--------------------------|---------------------|------------------------------------------------------------------|
| `queue_ensure_dirs()`    | `pearl/queue.php`  | Ensures storage/queue/pending and /failed exist *(internal helper)* |
| `queue_push()`           | `pearl/queue.php`  | Pushes a new job onto the queue, returns its id                  |
| `queue_pending()`        | `pearl/queue.php`  | Returns all pending jobs, oldest first                            |
| `queue_complete()`       | `pearl/queue.php`  | Marks a job done — deletes its pending file                       |
| `queue_fail()`           | `pearl/queue.php`  | Marks a job failed — moves it to storage/queue/failed with the reason |
| `queue_counts()`         | `pearl/queue.php`  | Pending/failed job counts, for notify:status                      |
| `mail_config()`          | `pearl/mail.php`   | Loads + caches config/mail.php                                    |
| `mail_send()`            | `pearl/mail.php`   | Sends an email via the configured driver (log/smtp/phpmailer)     |
| `mail_send_via_log()`    | `pearl/mail.php`   | 'log' driver — writes to storage/mail/ *(internal helper)*        |
| `mail_send_via_phpmailer()` | `pearl/mail.php` | 'phpmailer' driver — optional, requires phpmailer/phpmailer installed separately *(internal helper)* |
| `pearl_scaffold_vite()`  | `pearl/cli/vite-install.php` | Generates package.json/vite.config.js/ink files — shared by `install` and `vite:install` so the logic exists in one place |
| `icon()`                 | `pearl/icons.php`  | Inlines a Lucide SVG icon by name from ink/icons/, merges caller's class attribute |
| `vite()`                 | `pearl/vite.php`   | Outputs script/link tags for a Vite entry — prod (manifest) or dev (dev server) |
| `vite_production_tags()` | `pearl/vite.php`   | Manifest-based tag resolution *(internal helper)* |
| `vite_dev_tags()`        | `pearl/vite.php`   | Dev-server tag output *(internal helper)* |

## Phase 7 additions

| Function            | File             | Purpose                                                              |
|---------------------|------------------|----------------------------------------------------------------------|
| `vite_asset_url()`  | `pearl/vite.php` | Resolves built asset URLs respecting ASSET_URL and subfolder paths   |

## Phase 8 additions

| Function                       | File                 | Purpose                                                              |
|--------------------------------|----------------------|----------------------------------------------------------------------|
| `csrf_token()`                 | `pearl/session.php`  | Returns/generates the active session's 64-char CSRF token             |
| `csrf_verify()`                | `pearl/session.php`  | Validates submitted token against session via hash_equals            |
| `pearl_sanitize_header_value()`| `pearl/response.php` | Strips CR/LF and null bytes to prevent header injection              |
| `response_redirect_external()` | `pearl/response.php` | Explicitly permits external redirects (response_redirect is same-origin) |
| `csrf_field()`                 | `pearl/view.php`     | Renders hidden input with session CSRF token for HTML forms          |
| `auth_owns()`                  | `pearl/auth.php`     | Verifies resource owner against auth_user ID (strict context isolation)|
| `auth_require_owner()`         | `pearl/auth.php`     | Enforces ownership, halting with 403 Forbidden if not the owner      |

## Phase 9 additions

| Function                       | File                   | Purpose                                                              |
|--------------------------------|------------------------|----------------------------------------------------------------------|
| `rate_limit_ensure_dir()`      | `pearl/rate_limit.php` | Ensures storage/rate_limits directory exists *(internal helper)*     |
| `rate_limit_file_path()`       | `pearl/rate_limit.php` | Resolves sha256 storage path for rate limit key *(internal helper)*  |
| `rate_limit_check()`           | `pearl/rate_limit.php` | Checks if key has attempts remaining within decay window             |
| `rate_limit_hit()`             | `pearl/rate_limit.php` | Increments attempt counter under atomic lock, returns new count     |
| `rate_limit_clear()`           | `pearl/rate_limit.php` | Resets rate limit counter for key upon successful action             |
| `rate_limit_available_in()`    | `pearl/rate_limit.php` | Seconds until lockout expires for Retry-After header                 |
| `rate_limit_remaining()`       | `pearl/rate_limit.php` | Remaining attempts available before limit is reached                 |
| `request_id()`                 | `pearl/request.php`    | Returns/generates unique request ID (UUID/hex) propagated to headers |
| `response_method_not_allowed()`| `pearl/response.php`   | Emits HTTP 405 with RFC-compliant Allow header                       |
| `response_too_many_requests()` | `pearl/response.php`   | Emits HTTP 429 with Retry-After and rate limit headers               |

## Phase 10 additions

| Function                       | File                   | Purpose                                                              |
|--------------------------------|------------------------|----------------------------------------------------------------------|
| `session_config()`             | `pearl/session.php`    | Loads and caches config/session.php for multi-driver support          |

**Classes (Deliberate Exceptions per §2.2 & Decision Log):**
- `PearlDatabaseSessionHandler` in `pearl/session_drivers.php` implements `SessionHandlerInterface` for database-backed sessions.
- `PearlRedisSessionHandler` in `pearl/session_drivers.php` implements `SessionHandlerInterface` for Redis-backed sessions with native TTLs.

## Phase 11 additions

| Function                       | File                   | Purpose                                                              |
|--------------------------------|------------------------|----------------------------------------------------------------------|
| `queue_config()`               | `pearl/queue.php`      | Loads and caches config/queue.php for multi-driver configuration     |
| `queue_register_handler()`     | `pearl/queue.php`      | Registers a procedural handler for a queue job type                  |
| `queue_handle_job()`           | `pearl/queue.php`      | Dispatches an in-flight job array to its registered handler          |
| `queue_reserve()`              | `pearl/queue.php`      | Atomically reserves the next available job across active driver      |
| `queue_release()`              | `pearl/queue.php`      | Releases a reserved job back to pending state with a backoff delay   |
| `queue_release_or_fail()`      | `pearl/queue.php`      | Retries with exponential backoff or marks failed once max_tries reached |
| `queue_failed_jobs()`          | `pearl/queue.php`      | Lists permanently failed jobs across the active driver               |

## Phase 12 additions

| Function                       | File                   | Purpose                                                              |
|--------------------------------|------------------------|----------------------------------------------------------------------|
| `mail_template()`              | `pearl/mail.php`       | Renders HTML email templates and layouts from ink/mail/              |
| `mail_send_via_smtp()`         | `pearl/mail.php`       | Zero-dependency raw socket SMTP client with STARTTLS & AUTH LOGIN    |

## Phase 13 additions

| Function                       | File                   | Purpose                                                              |
|--------------------------------|------------------------|----------------------------------------------------------------------|
| `cache_config()`               | `pearl/cache.php`      | Loads and caches config/cache.php for multi-driver caching           |
| `cache_ensure_dir()`           | `pearl/cache.php`      | Ensures storage/cache/ directory exists *(internal helper)*          |
| `cache_file_path()`            | `pearl/cache.php`      | Resolves 2-character sharded path for cached key *(internal helper)* |
| `cache_get()`                  | `pearl/cache.php`      | Retrieves cached item or returns default if missing or expired       |
| `cache_set()`                  | `pearl/cache.php`      | Stores item in cache with specified TTL in seconds                   |
| `cache_has()`                  | `pearl/cache.php`      | Checks whether item exists in cache and has not expired             |
| `cache_forget()`               | `pearl/cache.php`      | Removes specific item from active cache driver                       |
| `cache_remember()`             | `pearl/cache.php`      | Resolves item from cache or computes and stores closure result       |
| `cache_flush()`                | `pearl/cache.php`      | Flushes all cached items across the active driver                    |

## Phase 14 additions

| Function                       | File                   | Purpose                                                              |
|--------------------------------|------------------------|----------------------------------------------------------------------|
| `is_api_request()`             | `pearl/api.php`        | Determines if request targets /api/ or requests application/json     |
| `api_token_create()`           | `pearl/api.php`        | Generates high-entropy bearer token and stores SHA-256 hash in DB   |
| `api_token_authenticate()`     | `pearl/api.php`        | Authenticates bearer token, checks expiration, and updates activity |
| `auth_api_user()`              | `pearl/api.php`        | Resolves current authenticated API user                              |
| `auth_api_admin()`             | `pearl/api.php`        | Resolves current authenticated API administrator                     |
| `auth_api_token()`             | `pearl/api.php`        | Returns active API token metadata array                              |
| `auth_token_can()`             | `pearl/api.php`        | Checks if active token possesses specified ability/scope            |
| `auth_require_api()`           | `pearl/api.php`        | Enforces API token authentication and optional ability guard         |
| `api_token_revoke()`           | `pearl/api.php`        | Revokes token by ID or plaintext token                               |
| `api_token_revoke_all()`       | `pearl/api.php`        | Revokes all tokens for a given entity                                |
| `response_api_success()`       | `pearl/response.php`   | Emits standardized success JSON envelope with status and meta        |
| `response_api_error()`         | `pearl/response.php`   | Emits standardized error JSON envelope with status and error details |
| `columns_whitelist()`          | `pearl/response.php`   | Filters array to allowlisted keys for safe database serialization    |

## Phase 15 additions

| Function                       | File                   | Purpose                                                              |
|--------------------------------|------------------------|----------------------------------------------------------------------|
| `db_driver()`                  | `pearl/db.php`         | Returns configured or active PDO driver name (mysql, pgsql, sqlite)  |
| `db_quote_identifier()`        | `pearl/db.php`         | Safely quotes table/column identifiers across active database engine  |
| `db_disconnect()`              | `pearl/db.php`         | Resets shared PDO connection singleton for testing and migrations    |

**Note:** `ink/main.css` has explicit `@source` directives for `view/`
and `wire/` — without them, Tailwind's automatic content detection
(scoped to Vite's `root: 'ink'`) never scans those folders, so
Tailwind-generated utility classes (bg-canvas, text-muted, etc.)
silently vanish from the compiled CSS even though hand-written
`.pearl-*` component classes still work. Caught during Phase 5 live
testing — if you add another folder with class="..." usage outside
`ink/`, it needs its own `@source` line too.

