<?php
/**
 * Pearl Framework — CLI: install
 *
 * Handles: php bin/pearl install [--yes] [--db-host=] [--db-port=]
 *                                 [--db-database=] [--db-username=]
 *                                 [--db-password=]
 *
 * Responsibilities:
 *   1. Create .env from .env.example if missing.
 *   2. Prompt for (or accept flags for) DB credentials, write into .env.
 *   3. Generate package.json + Vite config with Tailwind v4 wired in —
 *      Tailwind is mandatory from install onward, not an opt-in step.
 *
 * Returns a callable: function(array $args): int
 */

declare(strict_types=1);

/**
 * Read a line from STDIN with a prompt + default value.
 * Falls back to the default immediately in non-interactive contexts
 * (no TTY) so the installer never hangs in CI or scripted runs.
 */
$promptFn = static function (string $question, string $default = ''): string {
    $suffix = $default !== '' ? " [{$default}]" : '';
    echo "{$question}{$suffix}: ";

    if (!stream_isatty(STDIN)) {
        echo $default . " (non-interactive, using default)" . PHP_EOL;
        return $default;
    }

    $line = fgets(STDIN);
    $line = $line === false ? '' : trim($line);

    return $line === '' ? $default : $line;
};

/**
 * Parse --key=value CLI flags into an assoc array.
 */
$parseFlags = static function (array $args): array {
    $flags = [];

    foreach ($args as $arg) {
        if (str_starts_with($arg, '--') && str_contains($arg, '=')) {
            [$key, $value] = explode('=', substr($arg, 2), 2);
            $flags[$key] = $value;
        } elseif (str_starts_with($arg, '--')) {
            $flags[substr($arg, 2)] = true;
        }
    }

    return $flags;
};

/**
 * Update or add a KEY=value line in the .env file content.
 */
$setEnvValue = static function (string $envContent, string $key, string $value): string {
    $pattern = '/^' . preg_quote($key, '/') . '=.*$/m';
    $line = "{$key}={$value}";

    if (preg_match($pattern, $envContent) === 1) {
        return preg_replace($pattern, $line, $envContent);
    }

    return rtrim($envContent) . PHP_EOL . $line . PHP_EOL;
};

return function (array $args) use ($promptFn, $parseFlags, $setEnvValue): int {
    $flags = $parseFlags($args);
    $nonInteractive = isset($flags['yes']);

    echo "=== Pearl Install ===" . PHP_EOL . PHP_EOL;

    // -------------------------------------------------------------
    // Step 1: .env
    // -------------------------------------------------------------
    $envPath = PEARL_ROOT . '/.env';
    $envExamplePath = PEARL_ROOT . '/.env.example';

    if (!is_file($envPath)) {
        if (!is_file($envExamplePath)) {
            fwrite(STDERR, ".env.example not found — cannot scaffold .env." . PHP_EOL);
            return 1;
        }

        copy($envExamplePath, $envPath);
        echo "Created .env from .env.example" . PHP_EOL;
    } else {
        echo ".env already exists — will update DB values only." . PHP_EOL;
    }

    // -------------------------------------------------------------
    // Step 2: DB credentials
    // -------------------------------------------------------------
    echo PHP_EOL . "-- Database configuration --" . PHP_EOL;

    $dbHost     = $flags['db-host']     ?? ($nonInteractive ? '127.0.0.1' : $promptFn('DB host', '127.0.0.1'));
    $dbPort     = $flags['db-port']     ?? ($nonInteractive ? '3306' : $promptFn('DB port', '3306'));
    $dbDatabase = $flags['db-database'] ?? ($nonInteractive ? 'pearl' : $promptFn('DB name', 'pearl'));
    $dbUsername = $flags['db-username'] ?? ($nonInteractive ? 'root' : $promptFn('DB username', 'root'));
    $dbPassword = $flags['db-password'] ?? ($nonInteractive ? '' : $promptFn('DB password', ''));

    $envContent = file_get_contents($envPath);
    $envContent = $setEnvValue($envContent, 'DB_HOST', (string) $dbHost);
    $envContent = $setEnvValue($envContent, 'DB_PORT', (string) $dbPort);
    $envContent = $setEnvValue($envContent, 'DB_DATABASE', (string) $dbDatabase);
    $envContent = $setEnvValue($envContent, 'DB_USERNAME', (string) $dbUsername);
    $envContent = $setEnvValue($envContent, 'DB_PASSWORD', (string) $dbPassword);
    file_put_contents($envPath, $envContent);

    echo "Updated .env with database configuration." . PHP_EOL;

    // -------------------------------------------------------------
    // Step 3: Vite + Tailwind v4 scaffolding
    // -------------------------------------------------------------
    // Delegates to vite-install.php's pearl_scaffold_vite() so this
    // logic exists in exactly one place, shared with the standalone
    // `vite:install` command.
    echo PHP_EOL . "-- Frontend (Vite + Tailwind v4) --" . PHP_EOL;

    require pearl_path('cli/vite-install.php');
    pearl_scaffold_vite();

    echo PHP_EOL . "=== Install complete ===" . PHP_EOL;
    echo "Next steps:" . PHP_EOL;
    echo "  npm install" . PHP_EOL;
    echo "  npm run dev" . PHP_EOL;

    return 0;
};
