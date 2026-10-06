# Pearl Framework

[![PHP Version](https://img.shields.io/badge/php-%3E%3D8.3-8892BF.svg)](https://php.net)
[![License](https://img.shields.io/badge/license-MIT-blue.svg)](LICENSE)
[![Architecture](https://img.shields.io/badge/architecture-procedural--first-emerald.svg)](PEARL_ARCHITECTURE.md)

**PEARL** is a modern, high-performance procedural PHP web framework built for clarity, speed, and strict security isolation.

Unlike typical PHP frameworks loaded with reflection magic, complex dependency injection containers, and bloated object hierarchies, Pearl embraces **pure procedural programming**, predictable data flow, and absolute separation of security boundaries.

---

## Key Highlights

- **Procedural-First Architecture**: Pure functions, plain arrays, shallow stack traces, zero autowiring, zero DI containers, zero hidden magic.
- **Strict Dual Authentication Isolation**: `users` and `admins` exist in completely isolated database tables, cookies, sessions, and token scopes. Privilege escalation via role column collision is structurally impossible.
- **Single-DDL Atomic Migrations**: Strictly one DDL operation per migration file to prevent broken database states under MySQL's implicit DDL commits.
- **File-Based Longest-Prefix Routing**: Fat domain modules (`wire/`) map intuitively to URL paths. Zero routing boilerplate.
- **Lightweight Fluent Query Builder**: `query()` / `PearlQuery` provides safe SQL construction with automatic parameter binding without ORM bloat.
- **Multi-Driver Subsystems**:
  - **Sessions**: File, Database (isolated composite key `(id, context)`), Redis.
  - **Queues**: File (atomic `rename` claim), Sync, Database (row locks), Redis.
  - **Cache**: File (2-character hex sharded folders), Database, Redis.
- **Zero-Dependency SMTP Client**: Raw socket communication (`fsockopen`/`stream_socket_client`) with TLS, AUTH LOGIN, RFC 5321 dot-stuffing, and responsive HTML email templates.
- **Bearer Token API Subsystem**: Prefix-hashed tokens (`ptk_...`), abilities scoping (`*`, `read`, `write`), column whitelisting, and content-negotiated JSON error handling.
- **Built-in Security Defaults**: Automatic CSRF tokens, strict same-origin redirect validation, CRLF header injection defenses, file-backed atomic rate limiting with `Retry-After`, and automatic production CSP.
- **Modern Asset Pipeline**: Vite 6, Tailwind CSS v4, Lucide SVG inline icons, and Alpine.js.

---

## Requirements

- **PHP**: 8.3 or higher (PHP 8.4+ fully supported)
- **Required Extensions**: `pdo`, `json`, `mbstring`
- **Recommended Extensions**: `openssl`, `curl`, `redis`, `pdo_mysql`, `pdo_pgsql`, `pdo_sqlite`

---

## Directory Structure

```text
pearlphp/
├── bin/
│   └── pearl              # Framework CLI dispatcher
├── config/                # Configuration files returning plain arrays
│   ├── app.php
│   ├── database.php
│   ├── mail.php
│   ├── queue.php
│   ├── security.php
│   └── session.php
├── database/              # Atomic migrations (1 DDL per file)
│   └── migrations/
├── ink/                   # Frontend design system & email templates
│   ├── main.css
│   ├── main.js
│   ├── icons/             # Lucide SVGs
│   └── mail/              # Responsive HTML mail layouts
├── pearl/                 # Framework engine (procedural functions)
│   ├── api.php
│   ├── auth.php
│   ├── bootstrap.php
│   ├── cache.php
│   ├── csp.php
│   ├── db.php
│   ├── errors.php
│   ├── icons.php
│   ├── mail.php
│   ├── paths.php
│   ├── query.php          # Fluent query builder (PearlQuery)
│   ├── queue.php
│   ├── rate_limit.php
│   ├── rbac.php
│   ├── request.php
│   ├── response.php
│   ├── router.php
│   ├── session.php
│   ├── session_drivers.php
│   ├── view.php
│   └── vite.php
├── public/                # Web entry point (DocumentRoot)
│   ├── index.php          # Front controller
│   ├── .htaccess          # Apache rewrite rules
│   └── build/             # Compiled Vite production assets
├── storage/               # Runtime files (cache, queues, logs, sessions)
├── view/                  # Server-rendered PHP templates & layouts
└── wire/                  # Domain modules (routes dispatch here)
    ├── home.php
    ├── login.php
    ├── admin/
    └── api/
```

---

## Getting Started

### 1. Installation

Clone or install via Composer:

```bash
composer create-project pearl/framework my-app
cd my-app
```

### 2. Environment Configuration

Copy `.env.example` to `.env` and set your database credentials:

```bash
cp .env.example .env
```

### 3. Run Migrations

```bash
php bin/pearl migrate
```

### 4. Build Frontend Assets

```bash
npm install
npm run build
```

### 5. Start Development Server

```bash
php bin/pearl serve --port=7200
```

Visit `http://localhost:7200` in your browser.

---

## CLI Commands

Pearl includes a zero-dependency CLI runner (`bin/pearl`):

| Command | Description |
|---|---|
| `php bin/pearl serve [--host=...] [--port=...]` | Starts PHP's built-in web server |
| `php bin/pearl migrate` | Runs pending database migrations |
| `php bin/pearl migrate:rollback` | Reverses the last applied migration |
| `php bin/pearl make:migration <name> [--timestamp]` | Generates a new single-DDL migration file |
| `php bin/pearl make:auth` | Scaffolds authentication tables and login wire modules |
| `php bin/pearl notify:work [--queue=...] [--driver=...]` | Starts background queue worker |
| `php bin/pearl notify:status` | Inspects pending and failed queue jobs |
| `php bin/pearl session:clear [--context=user\|admin]` | Prunes expired sessions |
| `php bin/pearl cache:clear [--driver=...]` | Flushes application cache |
| `php bin/pearl mail:test <recipient>` | Sends test email via configured SMTP driver |
| `php bin/pearl vite:install` | Installs Tailwind and Lucide icon assets |
| `php bin/pearl vite:build` | Compiles frontend assets for production |

---

## Routing & Wire Modules

Pearl routes requests by matching URL paths against files in `wire/` using longest-prefix match:

- `/` $\rightarrow$ `wire/home.php`
- `/login` $\rightarrow$ `wire/login.php`
- `/admin/dashboard` $\rightarrow$ `wire/admin/dashboard.php`
- `/users/42/edit` $\rightarrow$ `wire/users.php` (with `$params = ['42', 'edit']`)

### Writing a Wire Module

Domain modules are "fat" procedural controllers that branch on HTTP method:

```php
<?php
// wire/posts.php
declare(strict_types=1);

match (request_method()) {
    'GET' => (function () use ($params) {
        $postId = $params[0] ?? null;
        if ($postId) {
            $post = query('posts')->where('id', $postId)->first();
            return pearl_view('posts/show', ['post' => $post]);
        }
        $posts = query('posts')->orderBy('created_at', 'DESC')->get();
        return pearl_view('posts/index', ['posts' => $posts]);
    })(),

    'POST' => (function () {
        auth_require_login();
        $input = request_validate([
            'title' => 'required|string|max:255',
            'body'  => 'required|string',
        ]);

        $id = query('posts')->insert([
            'user_id' => auth_user_id(),
            'title'   => $input['title'],
            'body'    => $input['body'],
        ]);

        response_redirect("/posts/{$id}");
    })(),

    default => response_method_not_allowed(['GET', 'POST']),
};
```

---

## Dual-Context Authentication

Pearl strictly separates normal users from administrators:

```php
// User Context
if (auth_login($email, $password)) {
    response_redirect('/dashboard');
}

// Admin Context
if (auth_admin_login($email, $password)) {
    response_redirect('/admin/dashboard');
}

// Guarding routes
auth_require_login(); // redirects to /login if not authenticated as user
auth_require_admin(); // redirects to /admin/login if not authenticated as admin

// Checking ownership
auth_require_owner($post['user_id']); // throws 403 if user does not own record
```

---

## Database & Query Builder

Access the database using raw SQL or the fluent query builder:

```php
// Raw query
$user = db_fetch("SELECT * FROM users WHERE email = :email", ['email' => $email]);

// Fluent query builder
$users = query('users')
    ->where('active', 1)
    ->where('created_at', '>=', '2026-01-01')
    ->orderBy('name', 'ASC')
    ->limit(20)
    ->get();

// Insert & Update
$id = query('users')->insert(['email' => 'jane@example.com', 'password_hash' => $hash]);
query('users')->where('id', $id)->update(['active' => 1]);
```

---

## Background Queues

Dispatch jobs to be processed asynchronously:

```php
// Dispatch job
queue_push('send_welcome_email', ['user_id' => 42]);

// Run worker
// php bin/pearl notify:work
```

---

## Caching

Fast, multi-driver caching with atomic file operations:

```php
$stats = cache_remember('site_stats', 3600, function () {
    return [
        'users' => query('users')->count(),
        'posts' => query('posts')->count(),
    ];
});
```

---

## Stateless API Tokens

```php
// Create a token
$token = api_token_create('user', 1, 'Mobile App', ['posts:read', 'posts:write']);

// Authenticate incoming bearer token in wire/api/posts.php
$caller = api_authenticate('user');
if (!api_token_can($caller['token'], 'posts:write')) {
    response_api_error('Forbidden: insufficient token ability', 403);
}

response_api_success($data);
```

---

## Architectural Principles & Invariants

Pearl's design is permanently defined in [PEARL_ARCHITECTURE.md](PEARL_ARCHITECTURE.md) and [DECISIONS.md](DECISIONS.md).

Key Invariants:
1. Procedural-first: No classes except `PearlQuery` and `SessionHandlerInterface`.
2. Absolute isolation of `users` and `admins`.
3. Exactly one DDL statement per migration file.
4. Engine files in `pearl/` never reference domain tables or files in `wire/`.
5. Zero dependency injection containers or reflection routing.

---

## License

The Pearl Framework is open-sourced software licensed under the [MIT license](LICENSE).
