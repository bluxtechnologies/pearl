<?php
/**
 * Pearl Framework — CLI: make:auth
 *
 * Handles: php bin/pearl make:auth
 *
 * Scaffolds the dual-context auth files, shaped exactly like the
 * hand-built versions already verified in Phase 2:
 *   wire/login.php          wire/admin/login.php
 *   wire/logout.php         wire/admin/logout.php
 *   view/login.php          view/admin/login.php
 *
 * Deliberately written AFTER the hand-built originals existed and were
 * proven to work end to end — scaffolding an unproven pattern would
 * just industrialize a guess. This command automates producing that
 * same proven shape, not a new one.
 *
 * Never overwrites an existing file — if you've customized login.php,
 * make:auth will skip it and tell you, not clobber your changes.
 *
 * Returns a callable: function(array $args): int
 */

declare(strict_types=1);

/**
 * Write a file only if it doesn't already exist. Returns true if
 * written, false if skipped (already present).
 */
$writeIfMissing = static function (string $path, string $content): bool {
    if (is_file($path)) {
        echo "  skip   {$path} (already exists)" . PHP_EOL;
        return false;
    }

    $dir = dirname($path);
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }

    file_put_contents($path, $content);
    echo "  create {$path}" . PHP_EOL;
    return true;
};

return function (array $args) use ($writeIfMissing): int {
    echo "=== Pearl make:auth ===" . PHP_EOL . PHP_EOL;

    // -------------------------------------------------------------
    // wire/login.php
    // -------------------------------------------------------------
    $wireLogin = <<<'PHP'
<?php
/**
 * wire/login.php — user-context login.
 *
 * Fat module: handles both GET (show form) and POST (attempt login)
 * for the /login route. $params is available here from
 * pearl_dispatch(), empty for this exact route.
 */

declare(strict_types=1);

match (request_method()) {
    'GET' => pearl_view('login', ['error' => null]),

    'POST' => (function () {
        $input = request_input();
        $email = $input['email'] ?? '';
        $password = $input['password'] ?? '';

        if (auth_login($email, $password)) {
            response_redirect('/dashboard');
            return;
        }

        pearl_view('login', ['error' => 'Invalid email or password.'], null, 401);
    })(),

    default => response_status(405),
};
PHP;

    // -------------------------------------------------------------
    // wire/admin/login.php
    // -------------------------------------------------------------
    $wireAdminLogin = <<<'PHP'
<?php
/**
 * wire/admin/login.php — admin-context login.
 *
 * Same fat-module pattern as wire/login.php, but sits under wire/admin/
 * so the router (via the /admin path prefix) and session context
 * detection both treat it as admin context automatically.
 */

declare(strict_types=1);

match (request_method()) {
    'GET' => pearl_view('admin/login', ['error' => null]),

    'POST' => (function () {
        $input = request_input();
        $email = $input['email'] ?? '';
        $password = $input['password'] ?? '';

        if (auth_login($email, $password)) {
            response_redirect('/admin/dashboard');
            return;
        }

        pearl_view('admin/login', ['error' => 'Invalid email or password.'], null, 401);
    })(),

    default => response_status(405),
};
PHP;

    // -------------------------------------------------------------
    // wire/logout.php (added beyond the hand-built set — auth_logout()
    // existed with no route wired to it yet; flagged here rather than
    // silently expanding scope)
    // -------------------------------------------------------------
    $wireLogout = <<<'PHP'
<?php
/**
 * wire/logout.php — user-context logout.
 */

declare(strict_types=1);

auth_logout();
response_redirect('/login');
PHP;

    $wireAdminLogout = <<<'PHP'
<?php
/**
 * wire/admin/logout.php — admin-context logout.
 */

declare(strict_types=1);

auth_logout();
response_redirect('/admin/login');
PHP;

    // -------------------------------------------------------------
    // view/login.php
    // -------------------------------------------------------------
    $viewLogin = <<<'HTML'
<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <title>Login</title>
</head>
<body>
    <h1>Login</h1>

    <?php if ($error): ?>
        <p style="color:red;"><?= e($error) ?></p>
    <?php endif; ?>

    <form method="post" action="/login">
        <label>
            Email
            <input type="email" name="email" required>
        </label>
        <label>
            Password
            <input type="password" name="password" required>
        </label>
        <button type="submit">Log in</button>
    </form>
</body>
</html>
HTML;

    // -------------------------------------------------------------
    // view/admin/login.php
    // -------------------------------------------------------------
    $viewAdminLogin = <<<'HTML'
<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <title>Admin Login</title>
</head>
<body>
    <h1>Admin Login</h1>

    <?php if ($error): ?>
        <p style="color:red;"><?= e($error) ?></p>
    <?php endif; ?>

    <form method="post" action="/admin/login">
        <label>
            Email
            <input type="email" name="email" required>
        </label>
        <label>
            Password
            <input type="password" name="password" required>
        </label>
        <button type="submit">Log in</button>
    </form>
</body>
</html>
HTML;

    $writeIfMissing(wire_path('login.php'), $wireLogin);
    $writeIfMissing(wire_path('admin/login.php'), $wireAdminLogin);
    $writeIfMissing(wire_path('logout.php'), $wireLogout);
    $writeIfMissing(wire_path('admin/logout.php'), $wireAdminLogout);
    $writeIfMissing(view_path('login.php'), $viewLogin);
    $writeIfMissing(view_path('admin/login.php'), $viewAdminLogin);

    echo PHP_EOL . "Reminder: these need the users/admins tables to exist." . PHP_EOL;
    echo "If you haven't already, run:" . PHP_EOL;
    echo "  php bin/pearl migrate" . PHP_EOL;

    return 0;
};
