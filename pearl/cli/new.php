<?php
/**
 * Pearl Framework — CLI: new
 *
 * Handles: php bin/pearl new <project-name> [--git]
 *
 * Scaffolds a fresh Pearl application into a new directory, ready for development.
 *
 * Returns a callable: function(array $args): int
 */

declare(strict_types=1);

if (!function_exists('pearl_path')) {
    throw new RuntimeException('pearl/paths.php must be required before new.php.');
}

return function (array $args): int {
    $projectName = null;
    $initGit = false;

    foreach ($args as $arg) {
        if ($arg === '--git') {
            $initGit = true;
        } elseif (!str_starts_with($arg, '--') && $projectName === null) {
            $projectName = $arg;
        }
    }

    if ($projectName === null || $projectName === '') {
        echo "Pearl Application Scaffolder" . PHP_EOL;
        echo "Usage: php bin/pearl new <project-name> [--git]" . PHP_EOL;
        return 1;
    }

    $targetDir = getcwd() . DIRECTORY_SEPARATOR . $projectName;

    if (file_exists($targetDir)) {
        fwrite(STDERR, "ERROR: Target directory '{$projectName}' already exists." . PHP_EOL);
        return 1;
    }

    echo "========================================================" . PHP_EOL;
    echo "       PEARL FRAMEWORK — NEW PROJECT SCAFFOLDER         " . PHP_EOL;
    echo "========================================================" . PHP_EOL . PHP_EOL;
    echo "Scaffolding fresh Pearl project in: {$targetDir}" . PHP_EOL . PHP_EOL;

    $baseDir = rtrim(str_replace('\\', '/', dirname(__DIR__, 2)), '/');

    // Patterns to exclude when copying scaffold template
    $excludePatterns = [
        '#^\.git(/|$)#',
        '#^\.idea(/|$)#',
        '#^\.vscode(/|$)#',
        '#^node_modules(/|$)#',
        '#^vendor(/|$)#',
        '#^dist(/|$)#',
        '#^scratch(/|$)#',
        '#^\.env$#',
        '#^\.env\.local$#',
        '#^\.env\.backup$#',
        '#^storage/cache/.*#',
        '#^storage/logs/.*#',
        '#^storage/mail/.*#',
        '#^storage/queue/pending/.*#',
        '#^storage/queue/reserved/.*#',
        '#^storage/queue/failed/.*#',
        '#^storage/rate_limits/.*#',
        '#^storage/sessions/.*#',
    ];

    mkdir($targetDir, 0775, true);

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($baseDir, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );

    $copiedCount = 0;

    foreach ($iterator as $item) {
        $path = str_replace('\\', '/', $item->getPathname());
        $relativePath = ltrim(substr($path, strlen($baseDir)), '/');

        // Do not copy the target directory into itself if inside baseDir
        if (str_starts_with($relativePath, $projectName . '/') || $relativePath === $projectName) {
            continue;
        }

        // Check if excluded
        $isExcluded = false;
        foreach ($excludePatterns as $pattern) {
            if (preg_match($pattern, $relativePath)) {
                if (basename($relativePath) === '.gitkeep') {
                    $isExcluded = false;
                    break;
                }
                $isExcluded = true;
                break;
            }
        }

        if ($isExcluded) {
            continue;
        }

        $dest = $targetDir . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);

        if ($item->isDir()) {
            if (!is_dir($dest)) {
                mkdir($dest, 0775, true);
            }
        } elseif ($item->isFile()) {
            $destDir = dirname($dest);
            if (!is_dir($destDir)) {
                mkdir($destDir, 0775, true);
            }
            copy($path, $dest);
            $copiedCount++;
        }
    }

    // Create .env from .env.example
    $envSource = $targetDir . DIRECTORY_SEPARATOR . '.env.example';
    $envDest = $targetDir . DIRECTORY_SEPARATOR . '.env';
    if (file_exists($envSource) && !file_exists($envDest)) {
        copy($envSource, $envDest);
    }

    // Optional git initialization
    if ($initGit && shell_exec('git --version 2>&1')) {
        @exec("git init \"{$targetDir}\"");
    }

    echo "SUCCESS: Pearl application '{$projectName}' successfully created! ({$copiedCount} files)" . PHP_EOL . PHP_EOL;
    echo "Next steps to run your application:" . PHP_EOL;
    echo "  1. cd {$projectName}" . PHP_EOL;
    echo "  2. php bin/pearl serve --port=7200" . PHP_EOL;
    echo "  3. Open http://localhost:7200 in your browser" . PHP_EOL . PHP_EOL;

    return 0;
};
