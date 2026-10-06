<?php
/**
 * Pearl Framework — CLI: package
 *
 * Handles: php bin/pearl package [--output=dist/pearl-v1.0.0.zip]
 *
 * Generates a clean, production/distribution-ready standalone ZIP archive
 * of the Pearl Framework, excluding development artifacts, cache, and secrets
 * (per PEARL_ARCHITECTURE.md §26).
 *
 * Returns a callable: function(array $args): int
 */

declare(strict_types=1);

if (!function_exists('pearl_path')) {
    throw new RuntimeException('pearl/paths.php must be required before package.php.');
}

return function (array $args): int {
    if (!class_exists('ZipArchive')) {
        echo "ERROR: PHP ZipArchive extension is required to generate packages." . PHP_EOL;
        return 1;
    }

    $outputFile = 'dist/pearl-v1.0.0.zip';
    foreach ($args as $arg) {
        if (str_starts_with($arg, '--output=')) {
            $outputFile = substr($arg, 9);
        }
    }

    $baseDir = rtrim(str_replace('\\', '/', dirname(__DIR__, 2)), '/');
    $zipPath = str_starts_with($outputFile, '/') || preg_match('/^[A-Za-z]:[\\\\\/]/', $outputFile)
        ? $outputFile
        : $baseDir . '/' . ltrim($outputFile, '/');

    $zipDir = dirname($zipPath);
    if (!is_dir($zipDir)) {
        mkdir($zipDir, 0775, true);
    }

    if (file_exists($zipPath)) {
        unlink($zipPath);
    }

    echo "=== Pearl Framework — Distribution Package Generator ===" . PHP_EOL . PHP_EOL;
    echo "Source: {$baseDir}" . PHP_EOL;
    echo "Target: {$zipPath}" . PHP_EOL . PHP_EOL;

    $zip = new ZipArchive();
    if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        echo "ERROR: Failed to create ZIP file at {$zipPath}" . PHP_EOL;
        return 1;
    }

    // Exclusion patterns
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

    $includedCount = 0;
    $totalBytes = 0;

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($baseDir, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );

    foreach ($iterator as $item) {
        $path = str_replace('\\', '/', $item->getPathname());
        $relativePath = ltrim(substr($path, strlen($baseDir)), '/');

        // Check if excluded
        $isExcluded = false;
        foreach ($excludePatterns as $pattern) {
            if (preg_match($pattern, $relativePath)) {
                // If it's a .gitkeep file inside storage, keep it to preserve directory structure
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

        if ($item->isDir()) {
            $zip->addEmptyDir($relativePath);
        } elseif ($item->isFile()) {
            $zip->addFile($path, $relativePath);
            $includedCount++;
            $totalBytes += $item->getSize();
        }
    }

    $zip->close();

    $zipSize = filesize($zipPath);
    $sha256 = hash_file('sha256', $zipPath);

    echo "PACKAGE SUMMARY:" . PHP_EOL;
    echo "  Total Files:      {$includedCount}" . PHP_EOL;
    echo "  Uncompressed:     " . number_format($totalBytes / 1024, 2) . " KB" . PHP_EOL;
    echo "  Compressed Size:  " . number_format($zipSize / 1024, 2) . " KB" . PHP_EOL;
    echo "  SHA-256 Checksum: {$sha256}" . PHP_EOL . PHP_EOL;
    echo "SUCCESS: Distribution archive generated successfully." . PHP_EOL;

    return 0;
};
