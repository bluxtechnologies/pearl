<?php
/**
 * wire/home.php — Root Route / Developer Onboarding Hub
 *
 * Dispatched by pearl_dispatch() for GET /.
 * Procedural fat module rendering the welcome view.
 */

declare(strict_types=1);

pearl_view('welcome', [
    'title' => 'Pearl — Procedural PHP Framework',
    'phpVersion' => PHP_VERSION,
    'pearlVersion' => defined('PEARL_VERSION') ? PEARL_VERSION : '1.0.4',
], 'layouts/welcome');
