<?php
/**
 * Pearl Framework — CLI: deploy
 *
 * Handles: php bin/pearl deploy [--check-only]
 *
 * Verifies production environment prerequisites, tests database connectivity,
 * runs pending migrations, flushes stale cache, and checks Vite build assets.
 *
 * Returns a callable: function(array $args): int
 */

declare(strict_types=1);

if (!function_exists('storage_path')) {
    throw new RuntimeException('pearl/paths.php must be required before deploy.php.');
}

return function (array $args): int {
    $checkOnly = in_array('--check-only', $args, true);

    echo "========================================================" . PHP_EOL;
    echo "       PEARL FRAMEWORK — PRODUCTION DEPLOYMENT RUNNER   " . PHP_EOL;
    echo "========================================================" . PHP_EOL . PHP_EOL;

    $errors = 0;
    $warnings = 0;

    // 1. PHP Version & Extensions Check
    echo "[1/5] Checking PHP Version & Extensions..." . PHP_EOL;
    $phpVersion = PHP_VERSION;
    if (version_compare($phpVersion, '8.3.0', '>=')) {
        echo "  [OK] PHP Version: {$phpVersion}" . PHP_EOL;
    } else {
        echo "  [FAIL] PHP Version {$phpVersion} is below required 8.3.0" . PHP_EOL;
        $errors++;
    }

    $requiredExtensions = ['pdo', 'json', 'mbstring', 'openssl'];
    foreach ($requiredExtensions as $ext) {
        if (extension_loaded($ext)) {
            echo "  [OK] Extension '{$ext}' loaded" . PHP_EOL;
        } else {
            echo "  [FAIL] Required extension '{$ext}' is MISSING" . PHP_EOL;
            $errors++;
        }
    }

    // 2. Storage & File Permissions Check
    echo PHP_EOL . "[2/5] Checking Storage & Permissions..." . PHP_EOL;
    $storageDirs = [
        'storage',
        'storage/cache',
        'storage/logs',
        'storage/mail',
        'storage/queue',
        'storage/queue/pending',
        'storage/queue/reserved',
        'storage/queue/failed',
        'storage/rate_limits',
        'storage/sessions'
    ];

    foreach ($storageDirs as $dir) {
        $abs = storage_path(str_replace('storage/', '', $dir === 'storage' ? '' : $dir));
        if (!is_dir($abs)) {
            @mkdir($abs, 0775, true);
        }
        if (is_writable($abs)) {
            echo "  [OK] Directory writable: {$dir}" . PHP_EOL;
        } else {
            echo "  [FAIL] Directory not writable: {$dir}" . PHP_EOL;
            $errors++;
        }
    }

    // 3. Database Connectivity & Migrations
    echo PHP_EOL . "[3/5] Checking Database & Migrations..." . PHP_EOL;
    try {
        if (!function_exists('pdo')) {
            require_once pearl_path('db.php');
        }
        $pdo = pdo();
        echo "  [OK] Database connection established (Engine: " . (function_exists('db_engine') ? db_engine() : 'pdo') . ")" . PHP_EOL;

        if (!$checkOnly) {
            echo "  Running pending migrations..." . PHP_EOL;
            $migrateHandler = require pearl_path('cli/migrate.php');
            $migrateExit = $migrateHandler([]);
            if ($migrateExit === 0) {
                echo "  [OK] Migrations successfully applied" . PHP_EOL;
            } else {
                echo "  [FAIL] Migration execution failed with exit code {$migrateExit}" . PHP_EOL;
                $errors++;
            }
        } else {
            echo "  [INFO] Skipping migration execution (--check-only flag provided)" . PHP_EOL;
        }
    } catch (\Throwable $e) {
        echo "  [FAIL] Database check failed: " . $e->getMessage() . PHP_EOL;
        $errors++;
    }

    // 4. Frontend Assets Check
    echo PHP_EOL . "[4/5] Checking Compiled Vite Assets..." . PHP_EOL;
    $manifestPath = public_path('build/.vite/manifest.json');
    if (is_file($manifestPath)) {
        echo "  [OK] Vite production manifest verified at public/build/.vite/manifest.json" . PHP_EOL;
    } else {
        echo "  [WARN] Vite manifest missing. Run 'npm run build' before serving traffic." . PHP_EOL;
        $warnings++;
    }

    // 5. Cache & Session Maintenance
    echo PHP_EOL . "[5/5] Flushing Cache & Cleaning Maintenance Records..." . PHP_EOL;
    if (!$checkOnly) {
        try {
            if (function_exists('cache_flush')) {
                cache_flush();
                echo "  [OK] Application cache flushed" . PHP_EOL;
            }
        } catch (\Throwable $e) {
            echo "  [WARN] Could not flush cache: " . $e->getMessage() . PHP_EOL;
            $warnings++;
        }
    } else {
        echo "  [INFO] Skipping cache flush (--check-only flag provided)" . PHP_EOL;
    }

    // Deployment Summary
    echo PHP_EOL . "========================================================" . PHP_EOL;
    if ($errors === 0) {
        echo "DEPLOYMENT STATUS: SUCCESS (0 errors, {$warnings} warning(s))" . PHP_EOL;
        echo "========================================================" . PHP_EOL;
        return 0;
    } else {
        echo "DEPLOYMENT STATUS: FAILED ({$errors} error(s), {$warnings} warning(s))" . PHP_EOL;
        echo "========================================================" . PHP_EOL;
        return 1;
    }
};
