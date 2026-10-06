<?php
/**
 * Pearl Framework — Path Helpers
 *
 * Central place for resolving paths to each of Pearl's 8 top-level folders.
 * Requires PEARL_ROOT to already be defined (see pearl/bootstrap.php).
 * No other file should hardcode a path into these folders — always go
 * through these helpers so the folder layout can change in one place.
 */

declare(strict_types=1);

if (!defined('PEARL_ROOT')) {
    throw new RuntimeException(
        'PEARL_ROOT is not defined. pearl/bootstrap.php must be required before pearl/paths.php.'
    );
}

/**
 * Path into the pearl/ engine folder itself.
 */
if (!function_exists('pearl_path')) {
    function pearl_path(string $path = ''): string
    {
        return PEARL_ROOT . '/pearl' . ($path !== '' ? '/' . ltrim($path, '/') : '');
    }
}

/**
 * Path into wire/ — business logic / fat domain modules.
 */
if (!function_exists('wire_path')) {
    function wire_path(string $path = ''): string
    {
        return PEARL_ROOT . '/wire' . ($path !== '' ? '/' . ltrim($path, '/') : '');
    }
}

/**
 * Path into view/ — pages, layouts, components.
 */
if (!function_exists('view_path')) {
    function view_path(string $path = ''): string
    {
        return PEARL_ROOT . '/view' . ($path !== '' ? '/' . ltrim($path, '/') : '');
    }
}

/**
 * Path into ink/ — JS, CSS, email templates (Vite-built assets).
 */
if (!function_exists('ink_path')) {
    function ink_path(string $path = ''): string
    {
        return PEARL_ROOT . '/ink' . ($path !== '' ? '/' . ltrim($path, '/') : '');
    }
}

/**
 * Path into config/ — env-driven configuration files.
 */
if (!function_exists('config_path')) {
    function config_path(string $path = ''): string
    {
        return PEARL_ROOT . '/config' . ($path !== '' ? '/' . ltrim($path, '/') : '');
    }
}

/**
 * Path into public/ — web root.
 */
if (!function_exists('public_path')) {
    function public_path(string $path = ''): string
    {
        return PEARL_ROOT . '/public' . ($path !== '' ? '/' . ltrim($path, '/') : '');
    }
}

/**
 * Path into storage/ — cache, queue, mail, logs (file-based).
 */
if (!function_exists('storage_path')) {
    function storage_path(string $path = ''): string
    {
        return PEARL_ROOT . '/storage' . ($path !== '' ? '/' . ltrim($path, '/') : '');
    }
}

/**
 * Path into database/ — migrations and schema.
 */
if (!function_exists('database_path')) {
    function database_path(string $path = ''): string
    {
        return PEARL_ROOT . '/database' . ($path !== '' ? '/' . ltrim($path, '/') : '');
    }
}
