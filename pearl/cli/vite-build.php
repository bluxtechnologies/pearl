<?php
/**
 * Pearl Framework — CLI: vite:build
 *
 * Handles: php bin/pearl vite:build
 *
 * Thin wrapper shelling out to `npm run build`. Streams output live
 * (passthru, not exec) so build errors/warnings are visible as they
 * happen, not buffered until the end.
 *
 * Returns a callable: function(array $args): int
 */

declare(strict_types=1);

return function (array $args): int {
    if (!is_file(PEARL_ROOT . '/package.json')) {
        fwrite(STDERR, "No package.json found — run 'php bin/pearl install' first." . PHP_EOL);
        return 1;
    }

    $cwd = getcwd();
    chdir(PEARL_ROOT);

    passthru('npm run build', $exitCode);

    chdir($cwd);

    return $exitCode;
};
